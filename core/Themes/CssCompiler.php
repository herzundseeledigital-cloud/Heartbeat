<?php
declare(strict_types=1);

namespace Heartbeat\Themes;

use Heartbeat\Database\Database;

/**
 * High-Performance Tailwind CSS Utility Compiler & Extractor for Shared Hosting.
 * Compiles used Tailwind classes, design tokens, and custom rules into static CSS without Node.js or exec().
 */
final class CssCompiler
{
    private static ?self $instance = null;
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $base = $storageDir ?? (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE : dirname(__DIR__, 2) . '/storage');
        $this->storageDir = rtrim($base, '/') . '/cache/css';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get file path for a theme's compiled CSS bundle
     */
    public function getCssFilePath(string $themeSlug): string
    {
        return $this->storageDir . '/theme_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $themeSlug) . '.css';
    }

    /**
     * Get compiled CSS content for a theme
     */
    public function getCss(string $themeSlug): string
    {
        $path = $this->getCssFilePath($themeSlug);
        if (is_file($path)) {
            return (string)file_get_contents($path);
        }
        return '';
    }

    /**
     * Save compiled CSS bundle to static storage
     */
    public function saveCss(string $themeSlug, string $css): string
    {
        $path = $this->getCssFilePath($themeSlug);
        @file_put_contents($path, $css);
        return $path;
    }

    /**
     * Extract class names from raw HTML or JSON content
     * @return array<int, string>
     */
    public function extractClasses(string $content): array
    {
        $classes = [];

        // Match HTML class="..." or class='...'
        if (preg_match_all('/\bclass=["\']([^"\']+)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $tokens = preg_split('/\s+/', trim($match));
                if (is_array($tokens)) {
                    foreach ($tokens as $t) {
                        if ($t !== '') {
                            $classes[$t] = true;
                        }
                    }
                }
            }
        }

        // Match JSON class definitions: "custom_classes":"...", "classes":"...", "className":"..."
        if (preg_match_all('/"(?:custom_classes|classes|className|align|style)":\s*"([^"]+)"/i', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $tokens = preg_split('/\s+/', trim($match));
                if (is_array($tokens)) {
                    foreach ($tokens as $t) {
                        if ($t !== '') {
                            $classes[$t] = true;
                        }
                    }
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * Extract all classes used across all published and draft posts in the database
     * @return array<int, string>
     */
    public function extractAllDatabaseClasses(): array
    {
        $classes = [];
        try {
            $db = Database::getInstance();
            $posts = $db->fetchAll("SELECT content_blocks, content_html FROM {prefix}posts LIMIT 500");
            foreach ($posts as $post) {
                $blockClasses = $this->extractClasses((string)($post['content_blocks'] ?? ''));
                $htmlClasses = $this->extractClasses((string)($post['content_html'] ?? ''));
                foreach ($blockClasses as $c) {
                    $classes[$c] = true;
                }
                foreach ($htmlClasses as $c) {
                    $classes[$c] = true;
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully if database not ready
        }

        return array_keys($classes);
    }

    /**
     * Extract classes from all template, HTML and JSON files in a theme's directory
     * @return array<int, string>
     */
    public function extractThemeClasses(Theme $theme): array
    {
        $classes = [];
        $dir = $theme->getPath();
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'html', 'json'], true)) {
                    $content = @file_get_contents($file->getPathname());
                    if ($content) {
                        foreach ($this->extractClasses($content) as $c) {
                            $classes[$c] = true;
                        }
                    }
                }
            }
        }
        return array_keys($classes);
    }

    /**
     * Compile CSS for a theme combining tokens, base styles, and scanned utilities
     * @param array<int, string>|null $additionalClasses
     */
    public function compileForTheme(Theme $theme, ?array $additionalClasses = null, string $customCss = ''): string
    {
        $tokensCss = $theme->getCssVariables();

        // Collect all classes from database, theme templates and custom inputs
        $dbClasses = $this->extractAllDatabaseClasses();
        $themeClasses = $this->extractThemeClasses($theme);
        $allClasses = array_unique(array_merge($dbClasses, $themeClasses, $additionalClasses ?? []));

        // Generate base styles and CSS variables
        $baseCss = <<<CSS
/* Heartbeat CMS JIT Compiled Theme Bundle */
/* Theme: {$theme->getName()} ({$theme->getSlug()}) v{$theme->getVersion()} */

{$tokensCss}

:root {
    --tw-border-spacing-x: 0;
    --tw-border-spacing-y: 0;
    --tw-translate-x: 0;
    --tw-translate-y: 0;
    --tw-rotate: 0;
    --tw-skew-x: 0;
    --tw-skew-y: 0;
    --tw-scale-x: 1;
    --tw-scale-y: 1;
}

body {
    background-color: var(--hb-bg);
    color: var(--hb-text);
    font-family: var(--hb-font-family);
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--hb-heading-font);
}

.bg-hb-primary { background-color: var(--hb-primary); }
.bg-hb-primary-hover:hover { background-color: var(--hb-primary-hover); }
.text-hb-primary { color: var(--hb-primary); }
.border-hb-primary { border-color: var(--hb-primary); }
.bg-hb-card { background-color: var(--hb-card); }
.border-hb-border { border-color: var(--hb-border); }
.text-hb-accent { color: var(--hb-accent); }
CSS;

        // Compile scanned utilities
        $utilityCss = $this->generateUtilities($allClasses);

        $compiled = $baseCss . "\n\n" . $utilityCss;

        if (trim($customCss) !== '') {
            $compiled .= "\n\n/* Custom User Snippets */\n" . trim($customCss) . "\n";
        }

        // Minify output
        $minified = $this->minifyCss($compiled);
        $this->saveCss($theme->getSlug(), $minified);

        return $minified;
    }

    /**
     * Generate CSS rules for matched Tailwind utility classes
     * @param array<int, string> $classes
     */
    public function generateUtilities(array $classes): string
    {
        $rules = [];
        $mediaRules = [
            'sm' => [],
            'md' => [],
            'lg' => [],
            'xl' => [],
        ];

        foreach ($classes as $rawClass) {
            $class = trim($rawClass);
            if ($class === '') continue;

            // Handle responsive variants like md:grid-cols-2
            if (preg_match('/^(sm|md|lg|xl):(.+)$/', $class, $m)) {
                $bp = $m[1];
                $innerClass = $m[2];
                $rule = $this->parseUtilityRule($innerClass, $class);
                if ($rule) {
                    $mediaRules[$bp][] = $rule;
                }
                continue;
            }

            // Normal or pseudo rules (hover:..., focus:...)
            $rule = $this->parseUtilityRule($class);
            if ($rule) {
                $rules[] = $rule;
            }
        }

        $out = implode("\n", array_unique($rules));

        if (!empty($mediaRules['sm'])) {
            $out .= "\n@media (min-width: 640px) {\n  " . implode("\n  ", array_unique($mediaRules['sm'])) . "\n}";
        }
        if (!empty($mediaRules['md'])) {
            $out .= "\n@media (min-width: 768px) {\n  " . implode("\n  ", array_unique($mediaRules['md'])) . "\n}";
        }
        if (!empty($mediaRules['lg'])) {
            $out .= "\n@media (min-width: 1024px) {\n  " . implode("\n  ", array_unique($mediaRules['lg'])) . "\n}";
        }
        if (!empty($mediaRules['xl'])) {
            $out .= "\n@media (min-width: 1280px) {\n  " . implode("\n  ", array_unique($mediaRules['xl'])) . "\n}";
        }

        return $out;
    }

    /**
     * Parse single class to CSS declaration
     */
    private function parseUtilityRule(string $class, ?string $fullSelector = null): ?string
    {
        $sel = $fullSelector ?? $class;
        $escapedSelector = '.' . $this->escapeCssSelector($sel);

        // Hover variant
        if (str_starts_with($class, 'hover:')) {
            $base = substr($class, 6);
            $subRule = $this->parseProperty($base);
            return $subRule ? "{$escapedSelector}:hover { {$subRule} }" : null;
        }

        // Focus variant
        if (str_starts_with($class, 'focus:')) {
            $base = substr($class, 6);
            $subRule = $this->parseProperty($base);
            return $subRule ? "{$escapedSelector}:focus { {$subRule} }" : null;
        }

        $property = $this->parseProperty($class);
        return $property ? "{$escapedSelector} { {$property} }" : null;
    }

    /**
     * Map utility class to CSS property: value
     */
    private function parseProperty(string $c): ?string
    {
        // Display & Layout
        if ($c === 'flex') return 'display: flex;';
        if ($c === 'inline-flex') return 'display: inline-flex;';
        if ($c === 'grid') return 'display: grid;';
        if ($c === 'block') return 'display: block;';
        if ($c === 'inline-block') return 'display: inline-block;';
        if ($c === 'hidden') return 'display: none;';
        if ($c === 'relative') return 'position: relative;';
        if ($c === 'absolute') return 'position: absolute;';
        if ($c === 'fixed') return 'position: fixed;';
        if ($c === 'sticky') return 'position: sticky;';
        if ($c === 'top-0') return 'top: 0px;';
        if ($c === 'bottom-0') return 'bottom: 0px;';
        if ($c === 'left-0') return 'left: 0px;';
        if ($c === 'right-0') return 'right: 0px;';
        if ($c === 'inset-0') return 'top: 0px; right: 0px; bottom: 0px; left: 0px;';
        if ($c === 'z-10') return 'z-index: 10;';
        if ($c === 'z-20') return 'z-index: 20;';
        if ($c === 'z-30') return 'z-index: 30;';
        if ($c === 'z-40') return 'z-index: 40;';
        if ($c === 'z-50') return 'z-index: 50;';
        if ($c === 'overflow-hidden') return 'overflow: hidden;';
        if ($c === 'overflow-x-auto') return 'overflow-x: auto;';
        if ($c === 'cursor-pointer') return 'cursor: pointer;';
        if ($c === 'select-none') return 'user-select: none;';
        if ($c === 'sr-only') return 'position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;';

        // Sizing helpers
        if (preg_match('/^w-(\d+)$/', $c, $m)) return "width: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^h-(\d+)$/', $c, $m)) return "height: " . (((int)$m[1]) * 0.25) . "rem;";
        if ($c === 'w-auto') return 'width: auto;';
        if ($c === 'h-auto') return 'height: auto;';
        if ($c === 'max-w-none') return 'max-width: none;';

        // Transforms & Transitions
        if ($c === 'transform') return 'transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));';
        if ($c === 'scale-105') return '--tw-scale-x: 1.05; --tw-scale-y: 1.05; transform: scale(1.05);';
        if ($c === 'scale-95') return '--tw-scale-x: 0.95; --tw-scale-y: 0.95; transform: scale(0.95);';
        if ($c === 'duration-150') return 'transition-duration: 150ms;';
        if ($c === 'duration-200') return 'transition-duration: 200ms;';
        if ($c === 'duration-300') return 'transition-duration: 300ms;';
        if ($c === 'duration-500') return 'transition-duration: 500ms;';

        // Flexbox & Grid
        if ($c === 'flex-col') return 'flex-direction: column;';
        if ($c === 'flex-row') return 'flex-direction: row;';
        if ($c === 'flex-wrap') return 'flex-wrap: wrap;';
        if ($c === 'flex-1') return 'flex: 1 1 0%;';
        if ($c === 'flex-grow') return 'flex-grow: 1;';
        if ($c === 'flex-shrink-0') return 'flex-shrink: 0;';
        if ($c === 'items-center') return 'align-items: center;';
        if ($c === 'items-start') return 'align-items: flex-start;';
        if ($c === 'items-end') return 'align-items: flex-end;';
        if ($c === 'justify-center') return 'justify-content: center;';
        if ($c === 'justify-between') return 'justify-content: space-between;';
        if ($c === 'justify-start') return 'justify-content: flex-start;';
        if ($c === 'justify-end') return 'justify-content: flex-end;';

        // Grid columns
        if (preg_match('/^grid-cols-(\d+)$/', $c, $m)) {
            return "grid-template-columns: repeat({$m[1]}, minmax(0, 1fr));";
        }
        if (preg_match('/^col-span-(\d+)$/', $c, $m)) {
            return "grid-column: span {$m[1]} / span {$m[1]};";
        }

        // Gap
        if (preg_match('/^gap-(\d+)$/', $c, $m)) {
            $rem = ((int)$m[1]) * 0.25;
            return "gap: {$rem}rem;";
        }

        // Max Width & Width / Height
        if ($c === 'max-w-6xl') return 'max-width: 72rem;';
        if ($c === 'max-w-7xl') return 'max-width: 80rem;';
        if ($c === 'max-w-5xl') return 'max-width: 64rem;';
        if ($c === 'max-w-4xl') return 'max-width: 56rem;';
        if ($c === 'max-w-3xl') return 'max-width: 48rem;';
        if ($c === 'max-w-2xl') return 'max-width: 42rem;';
        if ($c === 'max-w-xl') return 'max-width: 36rem;';
        if ($c === 'max-w-lg') return 'max-width: 32rem;';
        if ($c === 'max-w-md') return 'max-width: 28rem;';
        if ($c === 'mx-auto') return 'margin-left: auto; margin-right: auto;';
        if ($c === 'w-full') return 'width: 100%;';
        if ($c === 'h-full') return 'height: 100%;';
        if ($c === 'min-h-screen') return 'min-height: 100vh;';

        // Arbitrary hex color backgrounds e.g. bg-[#090d16]
        if (preg_match('/^bg-\[#([a-fA-F0-9]{3,8})\]$/', $c, $m)) {
            return "background-color: #{$m[1]};";
        }
        if (preg_match('/^text-\[#([a-fA-F0-9]{3,8})\]$/', $c, $m)) {
            return "color: #{$m[1]};";
        }
        if (preg_match('/^border-\[#([a-fA-F0-9]{3,8})\]$/', $c, $m)) {
            return "border-color: #{$m[1]};";
        }

        // Standard palette colors
        $palette = [
            'white'         => '#ffffff',
            'black'         => '#000000',
            'slate-950'     => '#020617',
            'slate-900'     => '#0f172a',
            'slate-800'     => '#1e293b',
            'slate-700'     => '#334155',
            'slate-600'     => '#475569',
            'slate-500'     => '#64748b',
            'slate-400'     => '#94a3b8',
            'slate-300'     => '#cbd5e1',
            'slate-200'     => '#e2e8f0',
            'slate-100'     => '#f1f5f9',
            'slate-50'      => '#f8fafc',
            'orange-500'    => '#f97316',
            'orange-600'    => '#ea580c',
            'amber-400'     => '#fbbf24',
            'amber-500'     => '#f59e0b',
            'amber-600'     => '#d97706',
            'emerald-400'   => '#34d399',
            'emerald-500'   => '#10b981',
            'emerald-600'   => '#059669',
            'emerald-700'   => '#047857',
            'emerald-800'   => '#065f46',
            'emerald-900'   => '#064e3b',
            'emerald-950'   => '#022c22',
            'blue-500'      => '#3b82f6',
            'blue-600'      => '#2563eb',
            'blue-700'      => '#1d4ed8',
            'violet-400'    => '#a78bfa',
            'violet-500'    => '#8b5cf6',
            'violet-600'    => '#7c3aed',
            'violet-700'    => '#6d28d9',
            'violet-900'    => '#4c1d95',
            'violet-950'    => '#2e1065',
            'purple-500'    => '#a855f7',
            'purple-600'    => '#9333ea',
            'rose-400'      => '#fb7185',
            'rose-500'      => '#f43f5e',
            'rose-600'      => '#e11d48',
            'sky-400'       => '#38bdf8',
            'sky-500'       => '#0ea5e9',
        ];

        foreach ($palette as $name => $hex) {
            if ($c === "bg-{$name}") return "background-color: {$hex};";
            if ($c === "text-{$name}") return "color: {$hex};";
            if ($c === "border-{$name}") return "border-color: {$hex};";
        }

        // Padding & Margin
        if (preg_match('/^p-(\d+)$/', $c, $m)) {
            $rem = ((int)$m[1]) * 0.25;
            return "padding: {$rem}rem;";
        }
        if (preg_match('/^px-(\d+)$/', $c, $m)) {
            $rem = ((int)$m[1]) * 0.25;
            return "padding-left: {$rem}rem; padding-right: {$rem}rem;";
        }
        if (preg_match('/^py-(\d+)$/', $c, $m)) {
            $rem = ((int)$m[1]) * 0.25;
            return "padding-top: {$rem}rem; padding-bottom: {$rem}rem;";
        }
        if (preg_match('/^pt-(\d+)$/', $c, $m)) return "padding-top: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^pb-(\d+)$/', $c, $m)) return "padding-bottom: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^pl-(\d+)$/', $c, $m)) return "padding-left: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^pr-(\d+)$/', $c, $m)) return "padding-right: " . (((int)$m[1]) * 0.25) . "rem;";

        if (preg_match('/^m-(\d+)$/', $c, $m)) return "margin: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^mt-(\d+)$/', $c, $m)) return "margin-top: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^mb-(\d+)$/', $c, $m)) return "margin-bottom: " . (((int)$m[1]) * 0.25) . "rem;";
        if (preg_match('/^my-(\d+)$/', $c, $m)) return "margin-top: " . (((int)$m[1]) * 0.25) . "rem; margin-bottom: " . (((int)$m[1]) * 0.25) . "rem;";

        // Typography
        if ($c === 'text-xs') return 'font-size: 0.75rem; line-height: 1rem;';
        if ($c === 'text-sm') return 'font-size: 0.875rem; line-height: 1.25rem;';
        if ($c === 'text-base') return 'font-size: 1rem; line-height: 1.5rem;';
        if ($c === 'text-lg') return 'font-size: 1.125rem; line-height: 1.75rem;';
        if ($c === 'text-xl') return 'font-size: 1.25rem; line-height: 1.75rem;';
        if ($c === 'text-2xl') return 'font-size: 1.5rem; line-height: 2rem;';
        if ($c === 'text-3xl') return 'font-size: 1.875rem; line-height: 2.25rem;';
        if ($c === 'text-4xl') return 'font-size: 2.25rem; line-height: 2.5rem;';
        if ($c === 'text-5xl') return 'font-size: 3rem; line-height: 1;';
        if ($c === 'text-6xl') return 'font-size: 3.75rem; line-height: 1;';
        if ($c === 'font-bold') return 'font-weight: 700;';
        if ($c === 'font-black') return 'font-weight: 900;';
        if ($c === 'font-semibold') return 'font-weight: 600;';
        if ($c === 'font-medium') return 'font-weight: 500;';
        if ($c === 'font-mono') return 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;';
        if ($c === 'text-center') return 'text-align: center;';
        if ($c === 'text-left') return 'text-align: left;';
        if ($c === 'text-right') return 'text-align: right;';
        if ($c === 'tracking-tight') return 'letter-spacing: -0.025em;';
        if ($c === 'leading-tight') return 'line-height: 1.25;';
        if ($c === 'leading-relaxed') return 'line-height: 1.625;';

        // Borders & Radius
        if ($c === 'border') return 'border-width: 1px; border-style: solid;';
        if ($c === 'border-t') return 'border-top-width: 1px; border-top-style: solid;';
        if ($c === 'border-b') return 'border-bottom-width: 1px; border-bottom-style: solid;';
        if ($c === 'rounded-sm') return 'border-radius: 0.125rem;';
        if ($c === 'rounded') return 'border-radius: 0.25rem;';
        if ($c === 'rounded-md') return 'border-radius: 0.375rem;';
        if ($c === 'rounded-lg') return 'border-radius: 0.5rem;';
        if ($c === 'rounded-xl') return 'border-radius: 0.75rem;';
        if ($c === 'rounded-2xl') return 'border-radius: 1rem;';
        if ($c === 'rounded-full') return 'border-radius: 9999px;';

        // Shadows & Effects
        if ($c === 'shadow-sm') return 'box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);';
        if ($c === 'shadow-md') return 'box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);';
        if ($c === 'shadow-lg') return 'box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);';
        if ($c === 'shadow-xl') return 'box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);';
        if ($c === 'shadow-2xl') return 'box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);';
        if ($c === 'backdrop-blur') return 'backdrop-filter: blur(8px);';
        if ($c === 'backdrop-blur-md') return 'backdrop-filter: blur(12px);';
        if ($c === 'transition') return 'transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;';
        if ($c === 'transition-all') return 'transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;';

        return null;
    }

    /**
     * Escape special characters in CSS class selector
     */
    private function escapeCssSelector(string $class): string
    {
        return str_replace(
            [':', '[', ']', '#', '/', '%', '.'],
            ['\\:', '\\[', '\\]', '\\#', '\\/', '\\%', '\\.'],
            $class
        );
    }

    /**
     * Simple fast CSS minifier
     */
    private function minifyCss(string $css): string
    {
        // Strip comments
        $css = (string)preg_replace('!/\*.*?\*/!s', '', $css);
        // Replace multiple whitespace with single space
        $css = (string)preg_replace('/\s+/', ' ', $css);
        // Remove spaces around brackets and colons
        $css = (string)preg_replace('/\s*([\{\};:,])\s*/', '$1', $css);
        // Remove trailing semicolons before closing brackets
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }
}

