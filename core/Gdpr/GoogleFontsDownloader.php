<?php
declare(strict_types=1);

namespace Heartbeat\Gdpr;

/**
 * Local Google Fonts Downloader & GDPR Compliance Engine
 * Downloads Google Fonts .woff2 binaries to the local server and generates
 * zero-external-dependency self-hosted @font-face stylesheets.
 */
final class GoogleFontsDownloader
{
    private static ?GoogleFontsDownloader $instance = null;
    private string $fontsDir;
    private string $lastError = '';

    private function __construct()
    {
        $this->fontsDir = HEARTBEAT_PUBLIC . '/assets/fonts';
        if (!is_dir($this->fontsDir)) {
            @mkdir($this->fontsDir, 0755, true);
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
     * Download Google Font and generate local CSS
     *
     * @param string $urlOrFamily Either full Google Fonts CSS URL or font family name (e.g. 'Plus Jakarta Sans', 'Inter')
     * @return array{success: bool, font_slug: string, css_file: string, css_url: string, files_count: int, message: string}
     */
    public function downloadFont(string $urlOrFamily): array
    {
        $this->lastError = '';
        $input = trim($urlOrFamily);

        if ($input === '') {
            return [
                'success'     => false,
                'font_slug'   => '',
                'css_file'    => '',
                'css_url'     => '',
                'files_count' => 0,
                'message'     => 'Keine Schriftart oder Google Fonts URL angegeben.',
            ];
        }

        // Construct Google Fonts CSS URL if family name provided
        $cssUrl = $input;
        if (!str_starts_with($input, 'http')) {
            $familyEncoded = urlencode($input);
            $cssUrl = "https://fonts.googleapis.com/css2?family={$familyEncoded}:wght@300;400;500;600;700;800&display=swap";
            $fontSlug = $this->slugify($input);
        } else {
            // Extract font family name from URL
            $matches = [];
            if (preg_match('/family=([^:&]+)/', $input, $matches)) {
                $fontSlug = $this->slugify(urldecode($matches[1]));
            } else {
                $fontSlug = 'font_' . substr(md5($input), 0, 8);
            }
        }

        // Fetch CSS with modern browser User-Agent so Google returns .woff2 formats
        $modernUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $ch = curl_init($cssUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => $modernUserAgent,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/css,*/*;q=0.1',
            ],
        ]);

        $rawCss = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $httpCode !== 200 || !is_string($rawCss) || trim($rawCss) === '') {
            return [
                'success'     => false,
                'font_slug'   => $fontSlug,
                'css_file'    => '',
                'css_url'     => '',
                'files_count' => 0,
                'message'     => "Download der Font-Definition von Google fehlgeschlagen (HTTP {$httpCode}): " . ($curlError ?: 'Leere Antwort'),
            ];
        }

        // Parse out all .woff2 URLs
        // Pattern: src: url(https://fonts.gstatic.com/s/...) format('woff2');
        $fontUrls = [];
        preg_match_all('/url\((https:\/\/[^)]+\.woff2)\)/', $rawCss, $fontUrls);

        if (empty($fontUrls[1])) {
            return [
                'success'     => false,
                'font_slug'   => $fontSlug,
                'css_file'    => '',
                'css_url'     => '',
                'files_count' => 0,
                'message'     => 'Keine .woff2-Dateien in der Google Fonts Antwort gefunden.',
            ];
        }

        $uniqueUrls = array_unique($fontUrls[1]);
        $downloadedCount = 0;
        $localCss = $rawCss;

        foreach ($uniqueUrls as $remoteUrl) {
            $hash = substr(md5($remoteUrl), 0, 10);
            $localFilename = "{$fontSlug}_{$hash}.woff2";
            $localFilePath = $this->fontsDir . '/' . $localFilename;
            $localWebUrl = "/assets/fonts/{$localFilename}";

            // Download file if not already cached
            if (!is_file($localFilePath)) {
                $fCh = curl_init($remoteUrl);
                $fp = fopen($localFilePath, 'wb');
                curl_setopt_array($fCh, [
                    CURLOPT_FILE           => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_USERAGENT      => $modernUserAgent,
                ]);
                $fSuccess = curl_exec($fCh);
                curl_close($fCh);
                fclose($fp);

                if ($fSuccess && filesize($localFilePath) > 0) {
                    $downloadedCount++;
                }
            } else {
                $downloadedCount++;
            }

            // Replace remote url with local url in CSS
            $localCss = str_replace($remoteUrl, $localWebUrl, $localCss);
        }

        // Save local CSS bundle
        $cssFilename = "{$fontSlug}.css";
        $cssFilePath = $this->fontsDir . '/' . $cssFilename;
        $cssWebUrl = "/assets/fonts/{$cssFilename}";

        file_put_contents($cssFilePath, "/* Self-hosted Google Font: {$fontSlug} (DSGVO-konform) */\n" . $localCss);

        return [
            'success'     => true,
            'font_slug'   => $fontSlug,
            'css_file'    => $cssFilePath,
            'css_url'     => $cssWebUrl,
            'files_count' => $downloadedCount,
            'message'     => "Schriftart '{$fontSlug}' erfolgreich heruntergeladen ({$downloadedCount} WOFF2-Dateien lokal gehostet).",
        ];
    }

    /**
     * List all locally installed self-hosted fonts
     * @return array<int, array{slug: string, css_file: string, css_url: string, modified: string}>
     */
    public function listLocalFonts(): array
    {
        $files = glob($this->fontsDir . '/*.css');
        if ($files === false || empty($files)) {
            return [];
        }

        $fonts = [];
        foreach ($files as $f) {
            $slug = basename($f, '.css');
            $fonts[] = [
                'slug'     => $slug,
                'css_file' => $f,
                'css_url'  => "/assets/fonts/{$slug}.css",
                'modified' => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }

        return $fonts;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? $text;
        return trim($text, '-');
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

