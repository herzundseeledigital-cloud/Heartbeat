<?php
declare(strict_types=1);

namespace Heartbeat\Content;

use Heartbeat\Database\Database;

/**
 * Custom Post Type Registry
 * Manages built-in and dynamically registered content types and blueprints.
 */
final class PostTypeRegistry
{
    private static ?PostTypeRegistry $instance = null;

    /** @var array<string, PostType> */
    private array $postTypes = [];

    private bool $discovered = false;

    public function __construct()
    {
        $this->registerDefaults();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register built-in agency content types
     */
    private function registerDefaults(): void
    {
        // 1. Pages
        $this->register(new PostType('page', [
            'singular'    => 'Seite',
            'plural'      => 'Seiten',
            'icon'        => 'document',
            'description' => 'Statische Inhaltsseiten und flexible Landingpages.',
            'has_archive' => false,
            'slug_prefix' => '',
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'meta'],
        ]));

        // 2. Posts (Blog / News)
        $this->register(new PostType('post', [
            'singular'    => 'Beitrag',
            'plural'      => 'Beiträge',
            'icon'        => 'newspaper',
            'description' => 'Aktuelle Blogartikel, Neuigkeiten und Veröffentlichungen.',
            'has_archive' => true,
            'slug_prefix' => 'blog',
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'meta'],
            'taxonomies'  => ['category', 'tag'],
        ]));

        // 3. Property CPT (Real Estate Theme)
        $this->register(new PostType('property', [
            'singular'    => 'Immobilie',
            'plural'      => 'Immobilien',
            'icon'        => 'home',
            'description' => 'Immobilienangebote, Wohnungen, Häuser und Gewerbeobjekte.',
            'has_archive' => true,
            'slug_prefix' => 'immobilien',
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'meta'],
            'meta_fields' => [
                [
                    'key'         => 'price',
                    'label'       => 'Kaufpreis (€)',
                    'type'        => 'number',
                    'placeholder' => 'z.B. 450000',
                    'required'    => true,
                    'step'        => 500,
                ],
                [
                    'key'         => 'rooms',
                    'label'       => 'Zimmeranzahl',
                    'type'        => 'number',
                    'placeholder' => 'z.B. 3.5',
                    'step'        => 0.5,
                ],
                [
                    'key'         => 'living_space',
                    'label'       => 'Wohnfläche (m²)',
                    'type'        => 'number',
                    'placeholder' => 'z.B. 95',
                    'step'        => 1,
                ],
                [
                    'key'         => 'location',
                    'label'       => 'Standort / Ort',
                    'type'        => 'text',
                    'placeholder' => 'z.B. Berlin-Mitte',
                ],
                [
                    'key'     => 'property_type',
                    'label'   => 'Objektart',
                    'type'    => 'select',
                    'options' => ['Wohnung', 'Einfamilienhaus', 'Villa', 'Gewerbe', 'Grundstück', 'Penthouse'],
                ],
                [
                    'key'     => 'status',
                    'label'   => 'Vermarktungsstatus',
                    'type'    => 'select',
                    'options' => ['Verfügbar', 'Reserviert', 'Verkauft', 'In Vorbereitung'],
                ],
                [
                    'key'     => 'energy_class',
                    'label'   => 'Energieeffizienzklasse',
                    'type'    => 'select',
                    'options' => ['A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
                ],
            ],
            'taxonomies' => ['property_type', 'location'],
        ]));

        // 4. Team Member CPT (Corporate Theme)
        $this->register(new PostType('team_member', [
            'singular'    => 'Team-Mitglied',
            'plural'      => 'Team',
            'icon'        => 'user-group',
            'description' => 'Mitarbeiterprofile, Ansprechpartner und Experten.',
            'has_archive' => true,
            'slug_prefix' => 'team',
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'meta'],
            'meta_fields' => [
                [
                    'key'         => 'position',
                    'label'       => 'Position / Berufsbezeichnung',
                    'type'        => 'text',
                    'placeholder' => 'z.B. Lead Software Architect',
                    'required'    => true,
                ],
                [
                    'key'         => 'department',
                    'label'       => 'Abteilung',
                    'type'        => 'select',
                    'options'     => ['Geschäftsführung', 'Software Engineering', 'UI/UX Design', 'Marketing & Sales', 'Kundenberatung'],
                ],
                [
                    'key'         => 'email',
                    'label'       => 'E-Mail-Adresse',
                    'type'        => 'email',
                    'placeholder' => 'z.B. name@agentur.de',
                ],
                [
                    'key'         => 'phone',
                    'label'       => 'Telefonnummer',
                    'type'        => 'text',
                    'placeholder' => 'z.B. +49 30 1234567',
                ],
                [
                    'key'         => 'linkedin',
                    'label'       => 'LinkedIn Profil URL',
                    'type'        => 'url',
                    'placeholder' => 'https://linkedin.com/in/...',
                ],
            ],
            'taxonomies' => ['department'],
        ]));

        // 5. Case Study CPT (Agency Portfolio)
        $this->register(new PostType('case_study', [
            'singular'    => 'Fallstudie',
            'plural'      => 'Fallstudien',
            'icon'        => 'briefcase',
            'description' => 'Kundenprojekte, Referenzen und Erfolgsgeschichten.',
            'has_archive' => true,
            'slug_prefix' => 'referenzen',
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'meta'],
            'meta_fields' => [
                [
                    'key'         => 'client_name',
                    'label'       => 'Kunde / Auftraggeber',
                    'type'        => 'text',
                    'placeholder' => 'z.B. Acme Corp GmbH',
                ],
                [
                    'key'         => 'project_year',
                    'label'       => 'Projektjahr',
                    'type'        => 'number',
                    'placeholder' => '2026',
                ],
                [
                    'key'         => 'services_provided',
                    'label'       => 'Erbringte Leistungen',
                    'type'        => 'text',
                    'placeholder' => 'z.B. Webentwicklung, SEO & Branding',
                ],
                [
                    'key'         => 'project_url',
                    'label'       => 'Live Website URL',
                    'type'        => 'url',
                    'placeholder' => 'https://...',
                ],
            ],
            'taxonomies' => ['service_category'],
        ]));
    }

    /**
     * Register a post type instance
     */
    public function register(PostType $postType): void
    {
        $this->postTypes[$postType->getName()] = $postType;
    }

    /**
     * Register from array blueprint
     * @param array<string, mixed> $blueprint
     */
    public function registerFromBlueprint(array $blueprint): PostType
    {
        $name = (string)($blueprint['post_type'] ?? $blueprint['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Post type blueprint must specify post_type or name.');
        }

        $postType = new PostType($name, $blueprint);
        $this->register($postType);
        return $postType;
    }

    /**
     * Discover blueprints from config and themes
     */
    public function discoverBlueprints(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        // 1. Check config/cpt/*.json
        $configDir = defined('HEARTBEAT_CONFIG') ? HEARTBEAT_CONFIG . '/cpt' : dirname(__DIR__, 2) . '/config/cpt';
        $this->loadBlueprintsFromDir($configDir);

        // 2. Check active theme directory
        try {
            $db = Database::getInstance();
            $activeTheme = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'active_theme'");
            if ($activeTheme) {
                $themeDir = (defined('HEARTBEAT_THEMES') ? HEARTBEAT_THEMES : dirname(__DIR__, 2) . '/themes') . '/' . $activeTheme . '/cpt';
                $this->loadBlueprintsFromDir($themeDir);
            }
        } catch (\Throwable) {
            // Database might not be installed yet during installer flow
        }
    }

    private function loadBlueprintsFromDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.json');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $blueprint = json_decode($json, true);
            if (is_array($blueprint)) {
                $this->registerFromBlueprint($blueprint);
            }
        }
    }

    public function get(string $name): ?PostType
    {
        $this->discoverBlueprints();
        return $this->postTypes[strtolower($name)] ?? null;
    }

    public function has(string $name): bool
    {
        $this->discoverBlueprints();
        return isset($this->postTypes[strtolower($name)]);
    }

    /**
     * @return array<string, PostType>
     */
    public function getAll(): array
    {
        $this->discoverBlueprints();
        return $this->postTypes;
    }

    /**
     * @return array<string, PostType> All non-standard post types
     */
    public function getCustomPostTypes(): array
    {
        $this->discoverBlueprints();
        return array_filter($this->postTypes, fn(PostType $pt) => !in_array($pt->getName(), ['page', 'post', 'attachment'], true));
    }
}

