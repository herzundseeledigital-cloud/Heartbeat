<?php
declare(strict_types=1);

namespace Heartbeat\Installer;

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Security\Security;
use RuntimeException;
use Throwable;

/**
 * Core Service performing installer actions
 */
final class InstallerService
{
    /**
     * Check if CMS is already installed and locked
     */
    public static function isLocked(): bool
    {
        return is_file(HEARTBEAT_CONFIG . '/installed.lock');
    }

    /**
     * Test database configuration
     */
    public static function testDatabase(array $dbConfig): array
    {
        return Database::testConnection($dbConfig);
    }

    /**
     * Execute full installation pipeline
     */
    public static function runInstallation(array $payload): array
    {
        if (self::isLocked()) {
            throw new RuntimeException("Installation ist gesperrt. Heartbeat CMS ist bereits installiert.");
        }

        // 1. Prepare and test Database connection
        $dbConfig = [
            'driver'   => $payload['db_driver'] ?? 'mysql',
            'host'     => $payload['db_host'] ?? '127.0.0.1',
            'port'     => (int)($payload['db_port'] ?? 3306),
            'database' => $payload['db_name'] ?? 'heartbeat',
            'username' => $payload['db_user'] ?? 'root',
            'password' => $payload['db_pass'] ?? '',
            'prefix'   => $payload['db_prefix'] ?? 'hb_',
            'charset'  => 'utf8mb4',
        ];

        if ($dbConfig['driver'] === 'sqlite') {
            $dbConfig['database'] = HEARTBEAT_STORAGE . '/database.sqlite';
        }

        $test = Database::testConnection($dbConfig);
        if (!$test['success']) {
            throw new RuntimeException("Datenbankfehler: " . $test['message']);
        }

        // 2. Initialize Database and run migrations
        $db = new Database($dbConfig);
        $migrationRunner = new MigrationRunner($db);
        $appliedMigrations = $migrationRunner->runPending();

        // 3. Seed Initial Data (Options, Admin User, Starter Homepage with JSON blocks)
        self::seedData($db, $payload);

        // 4. Generate app key and write config/env.php
        $appKey = bin2hex(random_bytes(32));
        self::writeEnvFile($dbConfig, $payload, $appKey);

        // 5. Lock installer
        self::writeLockFile($appKey);

        return [
            'success'    => true,
            'migrations' => $appliedMigrations,
            'admin_url'  => '/admin/login',
        ];
    }

    /**
     * Seed initial options, super admin user and starter page
     */
    private static function seedData(Database $db, array $payload): void
    {
        $siteTitle = trim($payload['site_title'] ?? 'Heartbeat CMS');
        $siteUrl = rtrim(trim($payload['site_url'] ?? 'http://localhost:8000'), '/');
        $theme = trim($payload['theme'] ?? 'one-pager');
        $agencyMode = !empty($payload['agency_mode']) ? 1 : 0;
        $agencyName = trim($payload['agency_name'] ?? 'Herz & Seele Digital');
        $agencySupport = trim($payload['agency_support'] ?? 'hallo@herzundseele-digital.de');
        $clientGuard = !empty($payload['client_guard']) ? 1 : 0;

        $adminUser = trim($payload['admin_user'] ?? 'admin');
        $adminEmail = trim($payload['admin_email'] ?? 'admin@example.com');
        $adminPass = $payload['admin_pass'] ?? 'Admin123!';

        // Insert options
        $options = [
            'site_title'           => $siteTitle,
            'site_url'             => $siteUrl,
            'active_theme'         => $theme,
            'agency_mode'          => (string)$agencyMode,
            'agency_name'          => $agencyName,
            'agency_support_email' => $agencySupport,
            'client_guard'         => (string)$clientGuard,
            'admin_email'          => $adminEmail,
            'heartbeat_version'    => '1.0.0',
        ];

        foreach ($options as $name => $value) {
            $existing = $db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :name", ['name' => $name]);
            if ($existing) {
                $db->update('{prefix}options', ['option_value' => $value], 'option_name = :name', ['name' => $name]);
            } else {
                $db->insert('{prefix}options', [
                    'option_name'  => $name,
                    'option_value' => $value,
                    'autoload'     => 1,
                ]);
            }
        }

        // Insert Super-Admin user
        $passwordHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $existingAdmin = $db->fetchOne("SELECT id FROM {prefix}users WHERE username = :u OR email = :e", [
            'u' => $adminUser,
            'e' => $adminEmail
        ]);

        $now = date('Y-m-d H:i:s');
        if (!$existingAdmin) {
            $adminId = $db->insert('{prefix}users', [
                'username'      => $adminUser,
                'email'         => $adminEmail,
                'password_hash' => $passwordHash,
                'role'          => 'super_admin',
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } else {
            $adminId = $existingAdmin['id'];
        }

        // Seed initial Home Page with clean JSON Block Tree
        $starterBlocks = [
            [
                'id'       => 'hero-block-1',
                'type'     => 'core/hero',
                'attrs'    => [
                    'headline'    => 'Willkommen bei Heartbeat CMS',
                    'subheadline' => 'Das moderne, blitzschnelle und kompromisslos sichere Site-Factory CMS für anspruchsvolle Agenturen.',
                    'cta_text'    => 'Jetzt Adminbereich öffnen',
                    'cta_link'    => '/admin',
                    'secondary_text' => 'Dokumentation lesen',
                    'secondary_link' => '#features',
                ],
                'styles'   => [
                    'bg_color'   => '#090d16',
                    'text_color' => '#ffffff',
                ],
            ],
            [
                'id'       => 'features-block-1',
                'type'     => 'core/features-grid',
                'attrs'    => [
                    'title'    => 'Leistungsstarke Agentur-Features',
                    'features' => [
                        [
                            'icon'        => 'lightning',
                            'title'       => 'Unter 20ms TTFB',
                            'description' => 'Multi-Tier HTML- und Object-Caching liefert statische Seiten blitzschnell aus.',
                        ],
                        [
                            'icon'        => 'shield',
                            'title'       => 'Senior AppSec Standard',
                            'description' => '100% Prepared Statements, CSRF-Schutz, strikte Header und vollständige Isolierung.',
                        ],
                        [
                            'icon'        => 'palette',
                            'title'       => 'Live-Server Theme JIT',
                            'description' => 'Direkte Anpassung im Browser ohne Node.js-Abhängigkeit auf dem Kundenserver.',
                        ],
                    ],
                ],
            ],
            [
                'id'       => 'cta-block-1',
                'type'     => 'core/cta-banner',
                'attrs'    => [
                    'headline' => 'Bereit für deine nächste Kunden-Website?',
                    'button'   => 'Admin Dashboard aufrufen',
                    'link'     => '/admin/login',
                ],
            ],
        ];

        $renderer = class_exists(\Heartbeat\Blocks\BlockRenderer::class) ? \Heartbeat\Blocks\BlockRenderer::getInstance() : null;

        // 1. Startseite (Home)
        $existingHome = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'home' AND post_type = 'page'");
        if (!$existingHome) {
            $homeBlocksJson = json_encode($starterBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $homeHtml = $renderer ? $renderer->render($starterBlocks) : '<!-- Initial Rendered Content Cache -->';
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Startseite',
                'slug'           => 'home',
                'content_blocks' => $homeBlocksJson,
                'content_html'   => $homeHtml,
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // 2. Über uns (Parent Page)
        $aboutPage = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'ueber-uns' AND post_type = 'page'");
        if (!$aboutPage) {
            $aboutBlocks = [
                [
                    'id'    => 'hero-about',
                    'type'  => 'core/hero',
                    'attrs' => [
                        'headline'         => 'Über uns – Herz & Seele unserer Agentur',
                        'subheadline'      => 'Seit über einem Jahrzehnt gestalten wir digitale Exzellenz mit Leidenschaft, Präzision und Weitsicht.',
                        'primary_btn_text' => 'Unser Team kennenlernen',
                        'primary_btn_url'  => '/ueber-uns/team',
                    ],
                ],
                [
                    'id'    => 'heading-mission',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h2',
                        'subtitle' => 'ÜBER UNS & MISSION',
                        'content'  => 'Digitale Werte schaffen, die Bestand haben',
                    ],
                ],
                [
                    'id'    => 'p-mission',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Wir glauben, dass großartige digitale Produkte an der Schnittstelle von visionärer Gestaltung, moderner Software-Architektur und echter Partnerschaft entstehen. Unser Anspruch ist nicht das Durchschnittliche, sondern das Herausragende.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-val-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'verified',
                        'title'       => '100% Inhabergeführt',
                        'description' => 'Keine Zwischenhändler, keine bürokratischen Hürden – direkte Kommunikation auf Augenhöhe mit den Machern.',
                    ],
                ],
                [
                    'id'    => 'card-val-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'workspace_premium',
                        'title'       => 'Höchster Qualitätsstandard',
                        'description' => 'Senior Code Reviews, ISO-zertifizierte Sicherheit und barrierefreie, blitzschnelle Oberflächen.',
                    ],
                ],
            ];
            $aboutBlocksJson = json_encode($aboutBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $aboutHtml = $renderer ? $renderer->render($aboutBlocks) : '';
            $aboutId = (int)$db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Über uns',
                'slug'           => 'ueber-uns',
                'content_blocks' => $aboutBlocksJson,
                'content_html'   => $aboutHtml,
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        } else {
            $aboutId = (int)$aboutPage['id'];
        }

        // Sub-page 2a: Team
        $existingTeamPage = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug IN ('ueber-uns/team', 'team') AND post_type = 'page'");
        if (!$existingTeamPage) {
            $teamBlocks = [
                [
                    'id'    => 'heading-team',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'MENSCHEN BEI HERZ & SEELE',
                        'content'  => 'Unser Team',
                    ],
                ],
                [
                    'id'    => 'p-team',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Hinter jedem erfolgreichen Projekt stehen Menschen mit Begeisterung, Spezialwissen und Liebe zum Detail. Lernen Sie unsere Spezialisten kennen.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-tm-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'badge',
                        'title'       => 'Dr. Alexander Becker',
                        'description' => 'Managing Director & Chief Architect – Spezialist für skalierbare Systeme und Enterprise-Infrastruktur.',
                    ],
                ],
                [
                    'id'    => 'card-tm-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'design_services',
                        'title'       => 'Sophie Lindner',
                        'description' => 'Head of Design & UX – Gestaltet intuitive Nutzererlebnisse und markenprägende Visuals.',
                    ],
                ],
                [
                    'id'    => 'card-tm-3',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'terminal',
                        'title'       => 'Maximilian Weber',
                        'description' => 'Lead Fullstack Engineer – Experte für Hochleistungs-Backends und moderne Web-Architekturen.',
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => $aboutId,
                'title'          => 'Unser Team',
                'slug'           => 'ueber-uns/team',
                'content_blocks' => json_encode($teamBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($teamBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // Sub-page 2b: Philosophie
        $existingPhilPage = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug IN ('ueber-uns/philosophie', 'philosophie') AND post_type = 'page'");
        if (!$existingPhilPage) {
            $philBlocks = [
                [
                    'id'    => 'heading-phil',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'WERTE & PRINZIPIEN',
                        'content'  => 'Unsere Philosophie',
                    ],
                ],
                [
                    'id'    => 'p-phil',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Technologie ist für uns kein Selbstzweck, sondern das Werkzeug, um menschliche Interaktionen einfacher, schöner und wirkungsvoller zu machen.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-phil-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'speed',
                        'title'       => 'Geschwindigkeit ist Wertschätzung',
                        'description' => 'Jede Millisekunde Ladezeit entscheidet über Kundenzufriedenheit, Vertrauen und geschäftlichen Erfolg.',
                    ],
                ],
                [
                    'id'    => 'card-phil-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'lock',
                        'title'       => 'Kompromisslose Sicherheit',
                        'description' => 'Datenschutz nach DSGVO und Senior AppSec Standards werden bei uns von Tag eins an mitgedacht.',
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => $aboutId,
                'title'          => 'Unsere Philosophie',
                'slug'           => 'ueber-uns/philosophie',
                'content_blocks' => json_encode($philBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($philBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // Sub-page 2c: Historie
        $existingHistPage = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug IN ('ueber-uns/historie', 'historie') AND post_type = 'page'");
        if (!$existingHistPage) {
            $histBlocks = [
                [
                    'id'    => 'heading-hist',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'MEILENSTEINE UNSERES WACHSTUMS',
                        'content'  => 'Unsere Historie',
                    ],
                ],
                [
                    'id'    => 'p-hist',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Von der Gründung als spezialisiertes Entwicklungsstudio im Jahr 2014 bis zur modernen Full-Service Digitalagentur im Jahr 2026.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-hist-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'flag',
                        'title'       => '2014 – Die Gründung',
                        'description' => 'Start mit drei Entwicklern und der klaren Vision, Websites kompromisslos schnell zu bauen.',
                    ],
                ],
                [
                    'id'    => 'card-hist-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'rocket_launch',
                        'title'       => '2019 – Internationalisierung',
                        'description' => 'Erweiterung des Kundenstamms auf die gesamte DACH-Region und erste internationale Enterprise-Projekte.',
                    ],
                ],
                [
                    'id'    => 'card-hist-3',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'diamond',
                        'title'       => '2026 – Heartbeat CMS Launch',
                        'description' => 'Einführung des hauseigenen, blitzschnellen Site-Factory-Systems für über 150 aktive Kunden.',
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => $aboutId,
                'title'          => 'Unsere Historie',
                'slug'           => 'ueber-uns/historie',
                'content_blocks' => json_encode($histBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($histBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // 3. Portfolio
        $existingPort = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'portfolio' AND post_type = 'page'");
        if (!$existingPort) {
            $portBlocks = [
                [
                    'id'    => 'heading-port',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'PORTFOLIO & CASE STUDIES',
                        'content'  => 'Ausgewählte Arbeiten',
                    ],
                ],
                [
                    'id'    => 'p-port',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Ein Auszug aus Projekten, die wir in den letzten Monaten konzipiert, gestaltet und technisch umgesetzt haben.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-p-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'storefront',
                        'title'       => 'NextGen B2B Plattform',
                        'description' => 'Headless E-Commerce Plattform mit 340% Performance-Steigerung und nahtloser ERP-Anbindung.',
                    ],
                ],
                [
                    'id'    => 'card-p-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'apartment',
                        'title'       => 'Immobilienportal Berlin-Mitte',
                        'description' => 'Exklusives Vermarktungsportal mit interaktiver Filtersuche und automatisiertem Exposé-Generator.',
                    ],
                ],
                [
                    'id'    => 'card-p-3',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'account_balance',
                        'title'       => 'FinTech Kundenportal',
                        'description' => 'Hochsicheres Banking-Dashboard mit biometrischer Authentifizierung und Realtime-Datenverarbeitung.',
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Portfolio',
                'slug'           => 'portfolio',
                'content_blocks' => json_encode($portBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($portBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // 4. Kontakt
        $existingKontakt = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'kontakt' AND post_type = 'page'");
        if (!$existingKontakt) {
            $kontaktBlocks = [
                [
                    'id'    => 'heading-kont',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'LASSEN SIE UNS SPRECHEN',
                        'content'  => 'Kontakt aufnehmen',
                    ],
                ],
                [
                    'id'    => 'p-kont',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => 'Haben Sie ein Projekt im Sinn oder möchten Sie mehr über unsere Arbeitsweise erfahren? Schreiben Sie uns oder rufen Sie uns direkt an.',
                        'lead'    => true,
                    ],
                ],
                [
                    'id'    => 'card-k-1',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'mail',
                        'title'       => 'E-Mail schreiben',
                        'description' => 'hallo@herzundseele-digital.de – Wir antworten an Werktagen verlässlich innerhalb von 24 Stunden.',
                    ],
                ],
                [
                    'id'    => 'card-k-2',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'call',
                        'title'       => 'Telefonisch erreichen',
                        'description' => '+49 (0) 30 1234 5678 – Montag bis Freitag von 09:00 bis 18:00 Uhr.',
                    ],
                ],
                [
                    'id'    => 'card-k-3',
                    'type'  => 'core/card',
                    'attrs' => [
                        'icon'        => 'location_on',
                        'title'       => 'Agenturstandort',
                        'description' => 'Friedrichstraße 120, 10117 Berlin – Mitten im Herzen der Hauptstadt.',
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Kontakt',
                'slug'           => 'kontakt',
                'content_blocks' => json_encode($kontaktBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($kontaktBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // 5. Impressum
        $existingImp = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'impressum' AND post_type = 'page'");
        if (!$existingImp) {
            $impBlocks = [
                [
                    'id'    => 'heading-imp',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'RECHTLICHE ANGABEN GEMÄSS § 5 DDG',
                        'content'  => 'Impressum',
                    ],
                ],
                [
                    'id'    => 'p-imp',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => "Herz & Seele Digital GmbH\nFriedrichstraße 120\n10117 Berlin\n\nVertreten durch die Geschäftsführung:\nDr. Alexander Becker\n\nKontakt:\nTelefon: +49 (0) 30 1234 5678\nE-Mail: impressum@herzundseele-digital.de\n\nRegistereintrag:\nAmtsgericht Charlottenburg, HRB 123456 B\nUmsatzsteuer-Identifikationsnummer: DE 123456789",
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Impressum',
                'slug'           => 'impressum',
                'content_blocks' => json_encode($impBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($impBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // 6. Datenschutz
        $existingDs = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'datenschutz' AND post_type = 'page'");
        if (!$existingDs) {
            $dsBlocks = [
                [
                    'id'    => 'heading-ds',
                    'type'  => 'core/heading',
                    'attrs' => [
                        'level'    => 'h1',
                        'subtitle' => 'INFORMATIONEN ZUR DATENVERARBEITUNG',
                        'content'  => 'Datenschutzerklärung',
                    ],
                ],
                [
                    'id'    => 'p-ds',
                    'type'  => 'core/paragraph',
                    'attrs' => [
                        'content' => "Wir nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen Datenschutzvorschriften (DSGVO) sowie dieser Datenschutzerklärung.\n\nVerantwortliche Stelle:\nHerz & Seele Digital GmbH, Friedrichstraße 120, 10117 Berlin.\n\nDiese Website verzichtet bewusst auf Tracking-Cookies von Drittanbietern und setzt ausschließlich technisch notwendige Cookies ein. Server-Logdateien werden nach 7 Tagen automatisch gelöscht.",
                    ],
                ],
            ];
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Datenschutz',
                'slug'           => 'datenschutz',
                'content_blocks' => json_encode($dsBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => $renderer ? $renderer->render($dsBlocks) : '',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // Seed starter content for theme CPTs so switching themes works seamlessly
        // 1. Team Member (Corporate Theme)
        $existingTeam = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'team_member' LIMIT 1");
        if (!$existingTeam) {
            $tmId = $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Dr. Alexander Becker',
                'slug'           => 'alexander-becker',
                'content_blocks' => '[]',
                'content_html'   => '<p>Managing Director mit über 15 Jahren Erfahrung in der Skalierung von B2B Softwarelösungen.</p>',
                'post_type'      => 'team_member',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'position', 'meta_value' => 'Managing Director & CEO']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'department', 'meta_value' => 'Geschäftsführung']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'email', 'meta_value' => 'becker@agentur.de']);
        }

        // 2. Case Study (Corporate Theme)
        $existingCs = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'case_study' LIMIT 1");
        if (!$existingCs) {
            $csId = $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Enterprise Portal Replatforming',
                'slug'           => 'enterprise-portal-replatforming',
                'content_blocks' => '[]',
                'content_html'   => '<p>Vollständige Modernisierung der Kernplattform mit 340% Performance-Steigerung.</p>',
                'post_type'      => 'case_study',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'client_name', 'meta_value' => 'NextGen Capital Group']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'project_year', 'meta_value' => '2026']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'services_provided', 'meta_value' => 'Headless Architektur & Cloud Migration']);
        }

        // 3. Luxury Listing (Real Estate Theme)
        $existingProp = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'property' LIMIT 1");
        if (!$existingProp) {
            $propId = $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Moderne Penthouse-Wohnung am Spreeufer',
                'slug'           => 'penthouse-spreeufer',
                'content_blocks' => '[]',
                'content_html'   => '<p>Exklusives Penthouse mit 185m² Wohnfläche, 4.5 Zimmern und traumhaftem Wasserblick.</p>',
                'post_type'      => 'property',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'price', 'meta_value' => '1250000']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'rooms', 'meta_value' => '4.5']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'living_space', 'meta_value' => '185']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'location', 'meta_value' => 'Berlin-Mitte']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'property_type', 'meta_value' => 'Penthouse']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'status', 'meta_value' => 'Verfügbar']);
            $db->insert('{prefix}meta_data', ['uuid' => Security::uuid(), 'object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'energy_class', 'meta_value' => 'A+']);
        }

        // 4. Pricing Page (Startup Theme)
        $existingPricing = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'pricing' AND post_type = 'page'");
        if (!$existingPricing) {
            $db->insert('{prefix}posts', [
                'uuid'           => Security::uuid(),
                'parent_id'      => 0,
                'title'          => 'Tarife & Preise',
                'slug'           => 'pricing',
                'content_blocks' => '[]',
                'content_html'   => '<p>Wählen Sie den passenden Plan für Ihr nächstes Projekt.</p>',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }
    }

    /**
     * Write config/env.php
     */
    private static function writeEnvFile(array $dbConfig, array $payload, string $appKey): void
    {
        $env = $payload['app_env'] ?? 'development';
        $debug = $env === 'development';
        $debugStr = $debug ? 'true' : 'false';
        $siteUrl = rtrim(trim($payload['site_url'] ?? 'http://localhost:8000'), '/');

        $driver = $dbConfig['driver'];
        $host = addcslashes($dbConfig['host'], "'\\");
        $port = (int)$dbConfig['port'];
        $dbName = addcslashes($dbConfig['database'], "'\\");
        $user = addcslashes($dbConfig['username'], "'\\");
        $pass = addcslashes($dbConfig['password'], "'\\");
        $prefix = addcslashes($dbConfig['prefix'], "'\\");
        $dateStr = date('Y-m-d H:i:s');

        $content = <<<PHP
<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Production Environment Configuration
 * Auto-generated by Web Installer on {$dateStr}
 */

return [
    'app' => [
        'name'      => 'Heartbeat CMS',
        'key'       => '{$appKey}',
        'env'       => '{$env}',
        'debug'     => {$debugStr},
        'url'       => '{$siteUrl}',
        'timezone'  => 'Europe/Berlin',
        'locale'    => 'de',
    ],

    'database' => [
        'driver'    => '{$driver}',
        'host'      => '{$host}',
        'port'      => {$port},
        'database'  => '{$dbName}',
        'username'  => '{$user}',
        'password'  => '{$pass}',
        'prefix'    => '{$prefix}',
        'charset'   => 'utf8mb4',
    ],

    'agency' => [
        'white_label'   => true,
        'name'          => '{$payload['agency_name']}',
        'support_email' => '{$payload['agency_support']}',
        'client_guard'  => true,
    ],

    'mail' => [
        'driver'        => 'resend', // 'resend' or 'smtp'
        'resend_api_key'=> '',
        'from_address'  => 'info@domain.de',
        'from_name'     => 'Heartbeat Mailer',
        'smtp' => [
            'host'      => 'smtp.example.com',
            'port'      => 587,
            'encryption'=> 'tls',
            'username'  => '',
            'password'  => '',
        ],
    ],

    'cache' => [
        'page_cache'    => true,
        'ttl_seconds'   => 3600,
        'driver'        => 'file', // 'file', 'apcu', 'redis'
    ],
];
PHP;

        if (!is_dir(HEARTBEAT_CONFIG)) {
            @mkdir(HEARTBEAT_CONFIG, 0775, true);
        }

        $written = file_put_contents(HEARTBEAT_CONFIG . '/env.php', $content);
        if ($written === false) {
            throw new RuntimeException("Konnte config/env.php nicht schreiben. Bitte Ordnerberechtigungen prüfen.");
        }
    }

    /**
     * Create lock file preventing installer execution
     */
    private static function writeLockFile(string $appKey): void
    {
        $lockData = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version'      => '1.0.0',
            'app_key_hash' => hash('sha256', $appKey),
        ];

        file_put_contents(
            HEARTBEAT_CONFIG . '/installed.lock',
            json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
