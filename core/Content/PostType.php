<?php
declare(strict_types=1);

namespace Heartbeat\Content;

/**
 * PostType Definition Model
 * Represents a Content Type Blueprint (Page, Post, or Custom Post Type).
 */
final class PostType
{
    private string $name;
    private string $singular;
    private string $plural;
    private string $icon;
    private string $description;
    private bool $public;
    private bool $hasArchive;
    private string $slugPrefix;
    /** @var array<string> */
    private array $supports;
    /** @var array<array<string, mixed>> */
    private array $metaFields;
    /** @var array<string> */
    private array $taxonomies;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(string $name, array $config = [])
    {
        $this->name = strtolower(trim($name));
        $this->singular = (string)($config['singular'] ?? ucfirst($this->name));
        $this->plural = (string)($config['plural'] ?? $this->singular . 's');
        $this->icon = (string)($config['icon'] ?? 'document');
        $this->description = (string)($config['description'] ?? '');
        $this->public = (bool)($config['public'] ?? true);
        $this->hasArchive = (bool)($config['has_archive'] ?? ($this->name !== 'page'));
        $this->slugPrefix = (string)($config['slug_prefix'] ?? ($this->name === 'page' || $this->name === 'post' ? '' : $this->name));
        $this->supports = (array)($config['supports'] ?? ['title', 'editor', 'thumbnail', 'excerpt', 'meta']);
        $this->metaFields = (array)($config['meta_fields'] ?? []);
        $this->taxonomies = (array)($config['taxonomies'] ?? []);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSingular(): string
    {
        return $this->singular;
    }

    public function getPlural(): string
    {
        return $this->plural;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function hasArchive(): bool
    {
        return $this->hasArchive;
    }

    public function getSlugPrefix(): string
    {
        return $this->slugPrefix;
    }

    /**
     * @return array<string>
     */
    public function getSupports(): array
    {
        return $this->supports;
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports, true);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getMetaFields(): array
    {
        return $this->metaFields;
    }

    /**
     * @return array<string>
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Convert to array representation
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'singular'    => $this->singular,
            'plural'      => $this->plural,
            'icon'        => $this->icon,
            'description' => $this->description,
            'public'      => $this->public,
            'has_archive' => $this->hasArchive,
            'slug_prefix' => $this->slugPrefix,
            'supports'    => $this->supports,
            'meta_fields' => $this->metaFields,
            'taxonomies'  => $this->taxonomies,
        ];
    }
}

