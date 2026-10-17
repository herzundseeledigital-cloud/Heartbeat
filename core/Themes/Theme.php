<?php
declare(strict_types=1);

namespace Heartbeat\Themes;

use Heartbeat\Security\Security;

/**
 * Theme Model
 * Encapsulates theme metadata, design tokens, templates and configuration.
 */
final class Theme
{
    private string $slug;
    private string $name;
    private string $version;
    private string $author;
    private string $description;
    /** @var array<string, string> */
    private array $colors;
    /** @var array<string, string> */
    private array $typography;
    /** @var array<int, array<string, mixed>> */
    private array $cptBlueprints;
    /** @var array<int, string> */
    private array $templates;
    /** @var array<string, mixed> */
    private array $settings;
    private string $path;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $slug, array $data, string $path)
    {
        $this->slug = $slug;
        $this->name = (string)($data['name'] ?? ucfirst($slug));
        $this->version = (string)($data['version'] ?? '1.0.0');
        $this->author = (string)($data['author'] ?? 'Heartbeat Agency');
        $this->description = (string)($data['description'] ?? '');
        $this->colors = (array)($data['colors'] ?? [
            'primary'       => '#f97316',
            'primary_hover' => '#ea580c',
            'background'    => '#090d16',
            'card'          => '#0f172a',
            'border'        => '#1e293b',
            'text'          => '#f8fafc',
            'accent'        => '#38bdf8',
        ]);
        $this->typography = (array)($data['typography'] ?? [
            'font_family'  => 'Inter',
            'heading_font' => 'Inter',
        ]);
        $this->cptBlueprints = (array)($data['cpt_blueprints'] ?? []);
        $this->templates = (array)($data['templates'] ?? ['home', 'page', 'single', '404']);
        $this->settings = (array)($data['settings'] ?? [
            'container_width' => 'max-w-6xl',
            'border_radius'   => 'rounded-2xl',
        ]);
        $this->path = rtrim($path, '/');
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    public function getColor(string $key, string $default = ''): string
    {
        return $this->colors[$key] ?? $default;
    }

    public function setColor(string $key, string $value): self
    {
        $this->colors[$key] = $value;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTypography(): array
    {
        return $this->typography;
    }

    public function setTypography(string $key, string $value): self
    {
        $this->typography[$key] = $value;
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCptBlueprints(): array
    {
        return $this->cptBlueprints;
    }

    /**
     * @return array<int, string>
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSetting(string $key, mixed $value): self
    {
        $this->settings[$key] = $value;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Generate CSS variables from design tokens
     */
    public function getCssVariables(): string
    {
        $vars = [
            '--hb-primary: ' . ($this->colors['primary'] ?? '#f97316'),
            '--hb-primary-hover: ' . ($this->colors['primary_hover'] ?? '#ea580c'),
            '--hb-bg: ' . ($this->colors['background'] ?? '#090d16'),
            '--hb-card: ' . ($this->colors['card'] ?? '#0f172a'),
            '--hb-border: ' . ($this->colors['border'] ?? '#1e293b'),
            '--hb-text: ' . ($this->colors['text'] ?? '#f8fafc'),
            '--hb-accent: ' . ($this->colors['accent'] ?? '#38bdf8'),
            '--hb-font-family: ' . ($this->typography['font_family'] ?? 'Inter') . ', sans-serif',
            '--hb-heading-font: ' . ($this->typography['heading_font'] ?? 'Inter') . ', sans-serif',
        ];

        return ":root {\n    " . implode(";\n    ", $vars) . ";\n}";
    }

    /**
     * Save theme configuration back to theme.json
     */
    public function save(): bool
    {
        $manifestPath = $this->path . '/theme.json';
        $data = $this->toArray();

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return (bool)@file_put_contents($manifestPath, $json . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'slug'           => $this->slug,
            'version'        => $this->version,
            'author'         => $this->author,
            'description'    => $this->description,
            'colors'         => $this->colors,
            'typography'     => $this->typography,
            'cpt_blueprints' => $this->cptBlueprints,
            'templates'      => $this->templates,
            'settings'       => $this->settings,
        ];
    }
}

