<?php
declare(strict_types=1);

namespace Heartbeat\Themes;

use Heartbeat\Database\Database;
use Heartbeat\Cache\Cache;

/**
 * Central Theme Service
 * Manages theme discovery, active theme resolution, token updating and CSS compilation.
 */
final class ThemeService
{
    private static ?self $instance = null;
    private string $themesDir;
    private CssCompiler $compiler;
    /** @var array<string, Theme>|null */
    private ?array $themes = null;

    public function __construct(?string $themesDir = null, ?CssCompiler $compiler = null)
    {
        $base = $themesDir ?? (defined('HEARTBEAT_ROOT') ? HEARTBEAT_ROOT . '/themes' : dirname(__DIR__, 2) . '/themes');
        $this->themesDir = rtrim($base, '/');
        $this->compiler = $compiler ?? CssCompiler::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Discover and load all themes in the themes/ directory
     * @return array<string, Theme>
     */
    public function getAll(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $this->themes = [];

        if (!is_dir($this->themesDir)) {
            return $this->themes;
        }

        $dirs = glob($this->themesDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $manifestFile = $dir . '/theme.json';
            if (is_file($manifestFile)) {
                $raw = @file_get_contents($manifestFile);
                if ($raw) {
                    $json = json_decode($raw, true);
                    if (is_array($json)) {
                        $dirSlug = basename($dir);
                        $slug = (string)($json['slug'] ?? $dirSlug);
                        $theme = new Theme($slug, $json, $dir);
                        $this->themes[$slug] = $theme;
                        // Also index by dirSlug if different
                        if ($dirSlug !== $slug) {
                            $this->themes[$dirSlug] = $theme;
                        }
                    }
                }
            }
        }

        return $this->themes;
    }

    /**
     * Get single theme by slug
     */
    public function get(string $slug): ?Theme
    {
        $all = $this->getAll();
        return $all[$slug] ?? null;
    }

    /**
     * Get active theme from database or fallback to first available theme
     */
    public function getActive(): Theme
    {
        $activeSlug = 'default';
        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'active_theme'");
            if (!empty($val)) {
                $activeSlug = (string)$val;
            }
        } catch (\Throwable) {
            // DB not ready, fallback
        }

        $theme = $this->get($activeSlug);
        if ($theme !== null) {
            return $theme;
        }

        // Fallback checks: 'one-pager', 'default', or first discovered theme
        if (isset($this->themes['one-pager'])) {
            return $this->themes['one-pager'];
        }
        if (isset($this->themes['default'])) {
            return $this->themes['default'];
        }

        $all = $this->getAll();
        if (!empty($all)) {
            return reset($all);
        }

        // Emergency fallback virtual theme
        return new Theme('default', [
            'name'        => 'Default Theme',
            'slug'        => 'default',
            'version'     => '1.0.0',
            'description' => 'Fallback system theme',
            'colors'      => [
                'primary'    => '#f97316',
                'background' => '#090d16',
                'text'       => '#f8fafc',
            ],
        ], $this->themesDir . '/default');
    }

    /**
     * Set active theme
     */
    public function setActive(string $slug): bool
    {
        $theme = $this->get($slug);
        if (!$theme) {
            return false;
        }

        try {
            $db = Database::getInstance();
            $existing = $db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = 'active_theme'");
            if ($existing) {
                $db->update('{prefix}options', ['option_value' => $slug], 'option_name = :name', ['name' => 'active_theme']);
            } else {
                $db->insert('{prefix}options', [
                    'option_name'  => 'active_theme',
                    'option_value' => $slug,
                    'autoload'     => 1,
                ]);
            }

            // Invalidate cache and compile CSS
            $this->compiler->compileForTheme($theme);
            if (class_exists(Cache::class)) {
                Cache::page()->flush();
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Update theme settings and compile CSS
     * @param array<string, mixed> $settings
     */
    public function updateThemeSettings(string $slug, array $settings): bool
    {
        $theme = $this->get($slug);
        if (!$theme) {
            return false;
        }

        // Update colors
        if (!empty($settings['colors']) && is_array($settings['colors'])) {
            foreach ($settings['colors'] as $key => $val) {
                $theme->setColor((string)$key, (string)$val);
            }
        }

        // Update typography
        if (!empty($settings['typography']) && is_array($settings['typography'])) {
            foreach ($settings['typography'] as $key => $val) {
                $theme->setTypography((string)$key, (string)$val);
            }
        }

        // Update other settings
        if (!empty($settings['settings']) && is_array($settings['settings'])) {
            foreach ($settings['settings'] as $key => $val) {
                $theme->setSetting((string)$key, $val);
            }
        }

        $saved = $theme->save();

        // Always recompile CSS and purge page cache
        $customCss = (string)($settings['custom_css'] ?? '');
        $this->compiler->compileForTheme($theme, null, $customCss);

        if (class_exists(Cache::class)) {
            Cache::page()->flush();
        }

        return $saved;
    }

    /**
     * Compile CSS bundle for active theme
     */
    public function compileActiveThemeCss(?array $additionalClasses = null, string $customCss = ''): string
    {
        $theme = $this->getActive();
        return $this->compiler->compileForTheme($theme, $additionalClasses, $customCss);
    }

    /**
     * Render template file from active theme with fallback
     * @param array<string, mixed> $context
     */
    public function renderTemplate(string $template, array $context = []): ?string
    {
        $theme = $this->getActive();
        $candidates = [
            $theme->getPath() . '/templates/' . $template . '.php',
            $theme->getPath() . '/' . $template . '.php',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                extract($context, EXTR_SKIP);
                ob_start();
                include $file;
                return (string)ob_get_clean();
            }
        }

        return null;
    }
}

