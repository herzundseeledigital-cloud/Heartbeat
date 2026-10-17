<?php
declare(strict_types=1);

namespace Heartbeat\Installer;

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
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

        $existingHome = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'home' AND post_type = 'page'");
        if (!$existingHome) {
            $db->insert('{prefix}posts', [
                'title'          => 'Startseite',
                'slug'           => 'home',
                'content_blocks' => json_encode($starterBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'content_html'   => '<!-- Initial Rendered Content Cache -->',
                'post_type'      => 'page',
                'status'         => 'publish',
                'author_id'      => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
                'published_at'   => $now,
            ]);
        }

        // Theme-specific starter seeds
        if ($theme === 'corporate') {
            // Seed a starter Team Member
            $existingTeam = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'team_member' LIMIT 1");
            if (!$existingTeam) {
                $tmId = $db->insert('{prefix}posts', [
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
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'position', 'meta_value' => 'Managing Director & CEO']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'department', 'meta_value' => 'Geschäftsführung']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'email', 'meta_value' => 'becker@agentur.de']);
            }

            // Seed a starter Case Study
            $existingCs = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'case_study' LIMIT 1");
            if (!$existingCs) {
                $csId = $db->insert('{prefix}posts', [
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
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'client_name', 'meta_value' => 'NextGen Capital Group']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'project_year', 'meta_value' => '2026']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $csId, 'meta_key' => 'services_provided', 'meta_value' => 'Headless Architektur & Cloud Migration']);
            }
        } elseif ($theme === 'real-estate') {
            // Seed a starter Property
            $existingProp = $db->fetchOne("SELECT id FROM {prefix}posts WHERE post_type = 'property' LIMIT 1");
            if (!$existingProp) {
                $propId = $db->insert('{prefix}posts', [
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
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'price', 'meta_value' => '1250000']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'rooms', 'meta_value' => '4.5']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'living_space', 'meta_value' => '185']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'location', 'meta_value' => 'Berlin-Mitte']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'property_type', 'meta_value' => 'Penthouse']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'status', 'meta_value' => 'Verfügbar']);
                $db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'energy_class', 'meta_value' => 'A+']);
            }
        } elseif ($theme === 'startup') {
            // Seed a starter Pricing Page
            $existingPricing = $db->fetchOne("SELECT id FROM {prefix}posts WHERE slug = 'pricing' AND post_type = 'page'");
            if (!$existingPricing) {
                $db->insert('{prefix}posts', [
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
