<?php
declare(strict_types=1);

namespace Heartbeat\Blocks;

/**
 * Block Registry
 * Defines core blocks, categories, default attributes, and rendering metadata.
 */
final class BlockRegistry
{
    private static ?BlockRegistry $instance = null;

    /** @var array<string, array<string, mixed>> */
    private array $blocks = [];

    public function __construct()
    {
        $this->registerCoreBlocks();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function registerCoreBlocks(): void
    {
        // 1. Hero Block
        $this->register('core/hero', [
            'name'        => 'Hero Banner',
            'category'    => 'Layout & Header',
            'icon'        => 'sparkles',
            'description' => 'Großer, wirkungsvoller Einstiegsbereich mit Überschrift, CTA-Buttons und Hintergrund.',
            'default_attrs' => [
                'headline'           => 'Gestalten Sie Ihre digitale Zukunft',
                'subheadline'        => 'Modern, blitzschnell und maßgeschneidert auf Höchstleistung.',
                'badge'              => 'Neu: Version 2.0 verfügbar',
                'primary_btn_text'   => 'Jetzt starten',
                'primary_btn_url'    => '#contact',
                'secondary_btn_text' => 'Mehr erfahren',
                'secondary_btn_url'  => '#features',
                'alignment'          => 'center',
                'bg_type'            => 'gradient',
                'bg_image'           => '',
            ],
        ]);

        // 2. Heading Block
        $this->register('core/heading', [
            'name'        => 'Überschrift',
            'category'    => 'Typografie',
            'icon'        => 'hashtag',
            'description' => 'Strukturierende Überschriften (H1 bis H6) mit optionalem Untertitel.',
            'default_attrs' => [
                'level'     => 'h2',
                'content'   => 'Abschnittsüberschrift',
                'subtitle'  => 'Prägnanter Untertitel oder Kicker',
                'alignment' => 'left',
            ],
        ]);

        // 3. Paragraph / Text Block
        $this->register('core/paragraph', [
            'name'        => 'Textabsatz',
            'category'    => 'Typografie',
            'icon'        => 'align-left',
            'description' => 'Fließtext mit Lead-Option und Textausrichtung.',
            'default_attrs' => [
                'content'   => 'Geben Sie hier Ihren Textabsatz ein. Heartbeat CMS sorgt für saubere Typografie und Lesbarkeit.',
                'lead'      => false,
                'alignment' => 'left',
            ],
        ]);

        // 4. Image Block
        $this->register('core/image', [
            'name'        => 'Bild',
            'category'    => 'Medien',
            'icon'        => 'photograph',
            'description' => 'Responsives Bild mit WebP-Unterstützung, Bildunterschrift und Eckenabrundung.',
            'default_attrs' => [
                'url'      => '/assets/img/placeholder.webp',
                'alt'      => 'Bildbeschreibung',
                'caption'  => '',
                'aspect'   => 'video',
                'rounded'  => '2xl',
                'shadow'   => true,
                'link_url' => '',
            ],
        ]);

        // 5. Button Block
        $this->register('core/button', [
            'name'        => 'Call-to-Action Button',
            'category'    => 'Interaktion',
            'icon'        => 'cursor-click',
            'description' => 'Auffälliger Button mit Farbvarianten und Verlinkung.',
            'default_attrs' => [
                'text'      => 'Aktion ausführen',
                'url'       => '#',
                'variant'   => 'primary',
                'size'      => 'md',
                'target'    => '_self',
                'alignment' => 'left',
            ],
        ]);

        // 6. Columns / Grid Container
        $this->register('core/columns', [
            'name'        => 'Spalten-Raster (Grid)',
            'category'    => 'Layout & Header',
            'icon'        => 'view-grid',
            'description' => 'Responsives Raster (2, 3 oder 4 Spalten) für verschachtelte Blöcke.',
            'default_attrs' => [
                'columns' => 3,
                'gap'     => 'md',
            ],
            'supports_inner_blocks' => true,
        ]);

        // 7. Card Block
        $this->register('core/card', [
            'name'        => 'Inhaltskarte',
            'category'    => 'Komponenten',
            'icon'        => 'template',
            'description' => 'Hervorgehobene Karte mit Icon/Emoji, Titel, Text und optionalem Link.',
            'default_attrs' => [
                'icon'         => '⚡',
                'title'        => 'Leistungsstark',
                'description'  => 'Optimiert für minimale Ladezeiten und maximale Konversion.',
                'link_url'     => '',
                'badge'        => '',
                'hover_effect' => true,
            ],
        ]);

        // 8. Features Grid Block
        $this->register('core/features', [
            'name'        => 'Vorteile & Features',
            'category'    => 'Komponenten',
            'icon'        => 'badge-check',
            'description' => 'Kompakte Liste oder Raster von Merkmalen und Vorteilen.',
            'default_attrs' => [
                'columns' => 3,
                'items'   => [
                    [
                        'icon'        => '🚀',
                        'title'       => 'Blitzschnell',
                        'description' => 'Unter 20ms TTFB dank Multi-Tier Static Cache.',
                    ],
                    [
                        'icon'        => '🔒',
                        'title'       => 'Sicher by Design',
                        'description' => '100% Prepared Statements und gehärtete Sessions.',
                    ],
                    [
                        'icon'        => '🎨',
                        'title'       => 'Live Tailwind',
                        'description' => 'Echtzeit-Styling ohne Node.js auf dem Server.',
                    ],
                ],
            ],
        ]);

        // 9. FAQ Accordion Block
        $this->register('core/faq', [
            'name'        => 'Häufige Fragen (FAQ)',
            'category'    => 'Komponenten',
            'icon'        => 'question-mark-circle',
            'description' => 'Interaktives Akkordeon mit Fragen und aufklappbaren Antworten (Alpine.js).',
            'default_attrs' => [
                'items' => [
                    [
                        'question' => 'Wie schnell ist Heartbeat CMS?',
                        'answer'   => 'Dank des integrierten Static Caches liefert Heartbeat HTML in unter 20 Millisekunden an Besucher aus.',
                    ],
                    [
                        'question' => 'Funktioniert Heartbeat auf Standard Webhosting?',
                        'answer'   => 'Ja! Heartbeat benötigt weder Node.js noch SSH und läuft auf jedem Webgo, IONOS oder Mittwald Paket.',
                    ],
                    [
                        'question' => 'Sind meine Daten DSGVO-konform geschützt?',
                        'answer'   => 'Absolut. Heartbeat nutzt 100% cookielose Analysen und bindet Schriften sowie Skripte rein lokal ein.',
                    ],
                ],
            ],
        ]);

        // 10. Quote / Testimonial Block
        $this->register('core/quote', [
            'name'        => 'Kundenstimme & Zitat',
            'category'    => 'Komponenten',
            'icon'        => 'chat-alt-2',
            'description' => 'Kundenbewertung oder Zitat mit Name, Funktion und Bewertungssternen.',
            'default_attrs' => [
                'quote'      => 'Heartbeat CMS hat unsere Webprojekte revolutioniert. Keine Plugin-Hölle mehr, extreme Performance!',
                'author'     => 'Anna Schmidt',
                'role'       => 'Head of Marketing, FutureBrand GmbH',
                'avatar_url' => '',
                'rating'     => 5,
            ],
        ]);

        // 11. Alert / Notice Box Block
        $this->register('core/alert', [
            'name'        => 'Hinweisbox',
            'category'    => 'Komponenten',
            'icon'        => 'information-circle',
            'description' => 'Farblich akzentuierte Box für wichtige Mitteilungen oder Warnungen.',
            'default_attrs' => [
                'type'    => 'info',
                'title'   => 'Wichtiger Hinweis',
                'message' => 'Diese Information sollten Ihre Besucher auf keinen Fall verpassen.',
            ],
        ]);

        // 12. Spacer & Divider Block
        $this->register('core/spacer', [
            'name'        => 'Abstand & Trennlinie',
            'category'    => 'Layout & Header',
            'icon'        => 'arrows-expand',
            'description' => 'Vertikaler Weißraum mit optionaler eleganter Trennlinie.',
            'default_attrs' => [
                'height'       => 'md',
                'show_divider' => true,
            ],
        ]);

        // 13. Popup, Notification & Paywall Toolkit Block
        $this->register('core/popup', [
            'name'        => 'Popup & Paywall Toolkit',
            'category'    => 'Marketing & Conversion',
            'icon'        => 'speakerphone',
            'description' => 'Modales Fenster, Banner oder Paywall-Gate mit Trigger-Bedingungen (Zeit, Scroll, Exit, Rolle).',
            'default_attrs' => [
                'title'          => 'Exklusives Angebot sichern',
                'message'        => 'Abonnieren Sie unseren Newsletter oder loggen Sie sich ein, um alle Premium-Inhalte freizuschalten.',
                'trigger'        => 'time_delay', // 'time_delay' | 'scroll_depth' | 'exit_intent' | 'role_gate'
                'trigger_value'  => 5,
                'cta_text'       => 'Jetzt freischalten',
                'cta_url'        => '#unlock',
                'required_role'  => 'member',
                'variant'        => 'modal', // 'modal' | 'banner_bottom'
            ],
        ]);
    }

    /**
     * Register a new block type
     * @param array<string, mixed> $meta
     */
    public function register(string $type, array $meta): void
    {
        $this->blocks[$type] = array_merge([
            'type'                  => $type,
            'name'                  => $type,
            'category'              => 'Allgemein',
            'icon'                  => 'cube',
            'description'           => '',
            'default_attrs'         => [],
            'supports_inner_blocks' => false,
        ], $meta);
    }

    public function get(string $type): ?array
    {
        return $this->blocks[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->blocks;
    }

    /**
     * Get blocks grouped by category for the Inserter UI
     * @return array<string, array<string, mixed>>
     */
    public function getGroupedByCategory(): array
    {
        $grouped = [];
        foreach ($this->blocks as $type => $block) {
            $cat = $block['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][$type] = $block;
        }
        return $grouped;
    }
}

