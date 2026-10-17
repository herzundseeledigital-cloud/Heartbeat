<?php
declare(strict_types=1);

namespace Heartbeat\Media;

use Heartbeat\Content\MetaService;
use Heartbeat\Database\Database;
use Heartbeat\Security\Security;
use RuntimeException;
use InvalidArgumentException;

/**
 * Media Manager & Asset Pipeline
 * Handles secure file uploads, strict MIME checks, native GD/Imagick WebP/AVIF conversions,
 * responsive thumbnail sizes, srcset generation, and database attachment records.
 */
final class MediaService
{
    private static ?MediaService $instance = null;
    private Database $db;
    private MetaService $meta;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
    ];

    public function __construct(?Database $db = null, ?MetaService $meta = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->meta = $meta ?? MetaService::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get root uploads directory
     */
    public function getUploadsDir(): string
    {
        $dir = defined('HEARTBEAT_ROOT') ? HEARTBEAT_ROOT . '/uploads' : dirname(__DIR__, 2) . '/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $this->ensureUploadsHtaccess($dir);
        return $dir;
    }

    /**
     * Ensure security .htaccess exists in uploads folder to prevent script execution
     */
    private function ensureUploadsHtaccess(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            $rules = <<<HTACCESS
# Block execution of PHP and executable scripts in uploads directory
<FilesMatch "\.(php|phtml|php3|php4|php5|phps|phar|pl|py|cgi|sh)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
Options -ExecCGI
php_flag engine off
HTACCESS;
            @file_put_contents($htaccess, $rules);
        }
    }

    /**
     * Upload and process a file from $_FILES array
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @param int $authorId
     * @return array<string, mixed> Attachment record
     */
    public function upload(array $file, int $authorId = 1): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload fehlgeschlagen. Fehlercode: ' . ($file['error'] ?? 'unbekannt'));
        }

        $origName = basename($file['name']);
        $tmpPath = $file['tmp_name'];

        // 1. Strict filename check: Reject double extensions like file.php.jpg
        $parts = explode('.', strtolower($origName));
        if (count($parts) > 2) {
            foreach (array_slice($parts, 0, -1) as $part) {
                if (in_array($part, ['php', 'phtml', 'php5', 'phar', 'exe', 'sh'], true)) {
                    throw new InvalidArgumentException('Verdächtiger Dateiname abgewiesen.');
                }
            }
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException("Dateityp .{$ext} ist nicht erlaubt.");
        }

        // 2. Strict MIME verification via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $expectedMime = self::ALLOWED_MIME_TYPES[$ext];
        if ($detectedMime !== $expectedMime) {
            // Special tolerance for JPEG / SVG
            if (!($ext === 'svg' && str_contains((string)$detectedMime, 'xml')) &&
                !($ext === 'jpg' && $detectedMime === 'image/jpeg') &&
                !($ext === 'jpeg' && $detectedMime === 'image/jpeg')) {
                throw new InvalidArgumentException("MIME-Typ {$detectedMime} stimmt nicht mit der Dateiendung überein.");
            }
        }

        // 3. Prepare target directory: uploads/YYYY/MM
        $yearMonth = date('Y/m');
        $targetDir = $this->getUploadsDir() . '/' . $yearMonth;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        // 4. Generate unique, clean filename
        $nameWithoutExt = pathinfo($origName, PATHINFO_FILENAME);
        $cleanBase = Security::slugify($nameWithoutExt) ?: 'datei';
        $finalName = $cleanBase . '.' . $ext;
        $counter = 2;

        while (is_file($targetDir . '/' . $finalName)) {
            $finalName = $cleanBase . '-' . $counter . '.' . $ext;
            $counter++;
        }

        $finalPath = $targetDir . '/' . $finalName;
        $relativePath = 'uploads/' . $yearMonth . '/' . $finalName;
        $publicUrl = '/' . $relativePath;

        // Move uploaded file
        if (!move_uploaded_file($tmpPath, $finalPath) && !copy($tmpPath, $finalPath)) {
            throw new RuntimeException('Die Datei konnte nicht im Upload-Verzeichnis gespeichert werden.');
        }

        $fileSize = filesize($finalPath) ?: $file['size'];
        $width = 0;
        $height = 0;
        $webpPath = null;
        $webpUrl = null;
        $sizes = [];

        // 5. Image conversions & sizes (JPEG, PNG)
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $imageInfo = @getimagesize($finalPath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];

                // Convert to WebP if not already webp
                if ($ext !== 'webp' && function_exists('imagewebp')) {
                    $webpFilename = pathinfo($finalName, PATHINFO_FILENAME) . '.webp';
                    $targetWebpPath = $targetDir . '/' . $webpFilename;
                    if ($this->convertToWebP($finalPath, $targetWebpPath, $ext)) {
                        $webpPath = 'uploads/' . $yearMonth . '/' . $webpFilename;
                        $webpUrl = '/' . $webpPath;
                    }
                } elseif ($ext === 'webp') {
                    $webpPath = $relativePath;
                    $webpUrl = $publicUrl;
                }

                // Generate responsive intermediate sizes
                $sizes = $this->generateSizes($finalPath, $targetDir, $cleanBase, $ext, $yearMonth, $width, $height);
            }
        }

        // 6. Insert attachment into {prefix}posts
        $title = ucwords(str_replace(['-', '_'], ' ', $cleanBase));
        $now = date('Y-m-d H:i:s');

        $attachmentId = (int)$this->db->insert('{prefix}posts', [
            'title'          => $title,
            'slug'           => $cleanBase,
            'content_blocks' => '[]',
            'content_html'   => '',
            'post_type'      => 'attachment',
            'status'         => 'publish',
            'author_id'      => $authorId,
            'created_at'     => $now,
            'updated_at'     => $now,
            'published_at'   => $now,
        ]);

        // 7. Store metadata in {prefix}meta_data
        $metaValues = [
            '_file_path' => $relativePath,
            '_url'       => $publicUrl,
            '_mime_type' => $detectedMime ?: $expectedMime,
            '_file_size' => $fileSize,
            '_width'     => $width,
            '_height'    => $height,
            '_alt_text'  => $title,
            '_webp_path' => $webpPath,
            '_webp_url'  => $webpUrl,
            '_sizes'     => $sizes,
        ];

        $this->meta->setMultiple('post', $attachmentId, $metaValues);

        return $this->get($attachmentId) ?? [];
    }

    /**
     * Convert an image file to WebP using native GD
     */
    private function convertToWebP(string $sourcePath, string $targetPath, string $ext): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $image = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            if (function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($sourcePath);
            }
        } elseif ($ext === 'png') {
            if (function_exists('imagecreatefrompng')) {
                $image = @imagecreatefrompng($sourcePath);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
            }
        }

        if (!$image) {
            return false;
        }

        $success = @imagewebp($image, $targetPath, 85);
        imagedestroy($image);
        return $success;
    }

    /**
     * Generate responsive thumbnail and intermediate image sizes
     * @return array<string, array<string, mixed>>
     */
    private function generateSizes(string $sourcePath, string $targetDir, string $baseName, string $ext, string $yearMonth, int $origW, int $origH): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return [];
        }

        $targets = [
            'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
            'medium'    => ['width' => 600, 'height' => 400, 'crop' => false],
            'large'     => ['width' => 1200, 'height' => 800, 'crop' => false],
        ];

        $results = [];

        foreach ($targets as $sizeName => $conf) {
            $targetW = $conf['width'];
            $targetH = $conf['height'];

            // Don't upscale
            if ($origW <= $targetW && $origH <= $targetH) {
                continue;
            }

            if (!$conf['crop']) {
                $ratio = min($targetW / $origW, $targetH / $origH);
                $newW = (int)round($origW * $ratio);
                $newH = (int)round($origH * $ratio);
            } else {
                $newW = $targetW;
                $newH = $targetH;
            }

            $sizeFilename = "{$baseName}-{$newW}x{$newH}.{$ext}";
            $sizeTarget = $targetDir . '/' . $sizeFilename;

            if ($this->resizeImage($sourcePath, $sizeTarget, $ext, $newW, $newH, $origW, $origH, $conf['crop'])) {
                $sizeRelative = "uploads/{$yearMonth}/{$sizeFilename}";
                $sizeUrl = '/' . $sizeRelative;

                // Also generate WebP variant for this size
                $sizeWebpUrl = null;
                if (function_exists('imagewebp')) {
                    $sizeWebpFilename = "{$baseName}-{$newW}x{$newH}.webp";
                    $sizeWebpTarget = $targetDir . '/' . $sizeWebpFilename;
                    if ($this->convertToWebP($sizeTarget, $sizeWebpTarget, $ext)) {
                        $sizeWebpUrl = "/uploads/{$yearMonth}/{$sizeWebpFilename}";
                    }
                }

                $results[$sizeName] = [
                    'file'     => $sizeFilename,
                    'path'     => $sizeRelative,
                    'url'      => $sizeUrl,
                    'webp_url' => $sizeWebpUrl,
                    'width'    => $newW,
                    'height'   => $newH,
                ];
            }
        }

        return $results;
    }

    private function resizeImage(string $src, string $dst, string $ext, int $dstW, int $dstH, int $srcW, int $srcH, bool $crop): bool
    {
        $srcImg = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $srcImg = @imagecreatefromjpeg($src);
        } elseif ($ext === 'png') {
            $srcImg = @imagecreatefrompng($src);
        } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $srcImg = @imagecreatefromwebp($src);
        }

        if (!$srcImg) {
            return false;
        }

        $dstImg = imagecreatetruecolor($dstW, $dstH);
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
        }

        if ($crop) {
            $srcRatio = $srcW / $srcH;
            $dstRatio = $dstW / $dstH;

            if ($srcRatio > $dstRatio) {
                $cropW = (int)round($srcH * $dstRatio);
                $cropH = $srcH;
                $srcX = (int)round(($srcW - $cropW) / 2);
                $srcY = 0;
            } else {
                $cropW = $srcW;
                $cropH = (int)round($srcW / $dstRatio);
                $srcX = 0;
                $srcY = (int)round(($srcH - $cropH) / 2);
            }

            imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $dstW, $dstH, $cropW, $cropH);
        } else {
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        }

        $saved = false;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $saved = @imagejpeg($dstImg, $dst, 85);
        } elseif ($ext === 'png') {
            $saved = @imagepng($dstImg, $dst, 8);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            $saved = @imagewebp($dstImg, $dst, 85);
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);
        return $saved;
    }

    /**
     * Get an attachment by ID
     * @return array<string, mixed>|null
     */
    public function get(int $id): ?array
    {
        $post = $this->db->fetchOne("SELECT * FROM {prefix}posts WHERE id = :id AND post_type = 'attachment' LIMIT 1", ['id' => $id]);
        if (!$post) {
            return null;
        }

        $meta = $this->meta->getAllPostMeta($id);
        return array_merge($post, $meta);
    }

    /**
     * Query all attachments
     * @param array<string, mixed> $args
     * @return array<array<string, mixed>>
     */
    public function getAll(array $args = []): array
    {
        $search = trim((string)($args['search'] ?? ''));
        $limit = max(1, min(100, (int)($args['limit'] ?? 50)));
        $offset = max(0, (int)($args['offset'] ?? 0));

        $where = ["post_type = 'attachment'"];
        $params = [];

        if ($search !== '') {
            $where[] = '(title LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);
        $rows = $this->db->fetchAll(
            "SELECT * FROM {prefix}posts WHERE {$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $results = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $meta = $this->meta->getAllPostMeta($id);
            $results[] = array_merge($row, $meta);
        }

        return $results;
    }

    public function count(array $args = []): int
    {
        $search = trim((string)($args['search'] ?? ''));
        $where = ["post_type = 'attachment'"];
        $params = [];

        if ($search !== '') {
            $where[] = '(title LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM {prefix}posts WHERE {$whereSql}", $params);
    }

    /**
     * Delete an attachment and all its files from disk
     */
    public function delete(int $id): bool
    {
        $att = $this->get($id);
        if (!$att) {
            return false;
        }

        $root = defined('HEARTBEAT_ROOT') ? HEARTBEAT_ROOT : dirname(__DIR__, 2);

        // 1. Delete original file
        if (!empty($att['_file_path'])) {
            $filePath = $root . '/' . ltrim($att['_file_path'], '/');
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        // 2. Delete WebP conversion
        if (!empty($att['_webp_path'])) {
            $webpPath = $root . '/' . ltrim($att['_webp_path'], '/');
            if (is_file($webpPath)) {
                @unlink($webpPath);
            }
        }

        // 3. Delete generated sizes
        if (!empty($att['_sizes']) && is_array($att['_sizes'])) {
            foreach ($att['_sizes'] as $sizeInfo) {
                if (!empty($sizeInfo['path'])) {
                    $sizeFile = $root . '/' . ltrim($sizeInfo['path'], '/');
                    if (is_file($sizeFile)) {
                        @unlink($sizeFile);
                    }
                }
                if (!empty($sizeInfo['webp_url'])) {
                    $sizeWebp = $root . '/' . ltrim($sizeInfo['webp_url'], '/');
                    if (is_file($sizeWebp)) {
                        @unlink($sizeWebp);
                    }
                }
            }
        }

        // 4. Delete database records
        $this->meta->deletePostMeta($id);
        return $this->db->delete('{prefix}posts', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Generate responsive srcset attribute string
     *
     * @param int|array<string, mixed> $attachment
     */
    public function getSrcset(int|array $attachment, bool $preferWebp = true): string
    {
        $att = is_int($attachment) ? $this->get($attachment) : $attachment;
        if (!$att) {
            return '';
        }

        $srcset = [];
        $sizes = $att['_sizes'] ?? [];

        if (is_array($sizes)) {
            foreach ($sizes as $s) {
                $url = $preferWebp && !empty($s['webp_url']) ? $s['webp_url'] : ($s['url'] ?? '');
                $w = $s['width'] ?? 0;
                if ($url && $w > 0) {
                    $srcset[] = "{$url} {$w}w";
                }
            }
        }

        // Add original / full image
        $origUrl = $preferWebp && !empty($att['_webp_url']) ? $att['_webp_url'] : ($att['_url'] ?? '');
        $origW = (int)($att['_width'] ?? 0);
        if ($origUrl && $origW > 0) {
            $srcset[] = "{$origUrl} {$origW}w";
        }

        return implode(', ', $srcset);
    }

    /**
     * Render an HTML5 picture tag with WebP source and fallback
     * @param int|array<string, mixed> $attachment
     */
    public function renderPictureTag(int|array $attachment, string $alt = '', string $class = '', string $sizesAttr = '100vw'): string
    {
        $att = is_int($attachment) ? $this->get($attachment) : $attachment;
        if (!$att) {
            return '';
        }

        $safeAlt = Security::e($alt !== '' ? $alt : (string)($att['_alt_text'] ?? $att['title'] ?? ''));
        $safeClass = Security::e($class);
        $safeSizes = Security::e($sizesAttr);

        $fallbackUrl = Security::e((string)($att['_url'] ?? ''));
        $webpSrcset = $this->getSrcset($att, true);
        $standardSrcset = $this->getSrcset($att, false);

        $sources = '';
        if ($webpSrcset !== '') {
            $sources .= "<source type=\"image/webp\" srcset=\"{$webpSrcset}\" sizes=\"{$safeSizes}\">\n";
        }
        if ($standardSrcset !== '') {
            $sources .= "<source srcset=\"{$standardSrcset}\" sizes=\"{$safeSizes}\">\n";
        }

        return <<<HTML
<picture class="{$safeClass}">
    {$sources}
    <img src="{$fallbackUrl}" alt="{$safeAlt}" class="{$safeClass}" loading="lazy">
</picture>
HTML;
    }
}

