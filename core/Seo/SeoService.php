<?php
declare(strict_types=1);

namespace Heartbeat\Seo;

use Heartbeat\Database\Database;
use Heartbeat\Security\Security;

/**
 * Built-in SEO Engine & XML Sitemap Generator
 * Generates OpenGraph, Twitter Cards, Canonical links, JSON-LD Schema, and dynamic XML sitemaps.
 */
final class SeoService
{
    private static ?SeoService $instance = null;
    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate HTML meta tags for a page/post
     *
     * @param array<string, mixed> $post The post/page record including meta
     * @param string $siteTitle The global site title
     * @param string $siteUrl Base website URL
     * @return string Rendered HTML meta tags
     */
    public function renderMetaTags(array $post, string $siteTitle, string $siteUrl): string
    {
        $postTitle = (string)($post['title'] ?? 'Home');
        $meta = (array)($post['meta'] ?? []);

        // Custom SEO title fallback to page title
        $seoTitle = (string)($meta['_seo_title'] ?? '');
        $finalTitle = $seoTitle !== '' ? $seoTitle : "{$postTitle} - {$siteTitle}";

        // Description fallback
        $seoDesc = (string)($meta['_seo_description'] ?? '');
        if ($seoDesc === '') {
            $excerpt = (string)($meta['_excerpt'] ?? '');
            if ($excerpt !== '') {
                $seoDesc = $excerpt;
            } else {
                $contentHtml = (string)($post['content_html'] ?? '');
                $plain = trim(strip_tags($contentHtml));
                $seoDesc = mb_substr($plain, 0, 160);
            }
        }

        // Canonical URL
        $slug = (string)($post['slug'] ?? '');
        $postType = (string)($post['post_type'] ?? 'page');
        $customCanonical = (string)($meta['_seo_canonical'] ?? '');
        if ($customCanonical !== '') {
            $canonicalUrl = $customCanonical;
        } else {
            $canonicalUrl = $slug === 'home' 
                ? rtrim($siteUrl, '/') . '/' 
                : rtrim($siteUrl, '/') . '/' . ($postType === 'page' ? $slug : "{$postType}/{$slug}");
        }

        // Robots
        $globalIndexing = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_indexing'");
        $isGlobalNoindex = ($globalIndexing === 'noindex');
        $noindex = $isGlobalNoindex || !empty($meta['_seo_noindex']);
        $nofollow = $isGlobalNoindex || !empty($meta['_seo_nofollow']);
        $robots = ($noindex || $nofollow) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large';

        // Image for OpenGraph
        $ogImage = (string)($meta['_seo_og_image'] ?? '');
        if ($ogImage === '') {
            $ogImage = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_default_og_image'");
        }
        if ($ogImage !== '' && !str_starts_with($ogImage, 'http')) {
            $ogImage = rtrim($siteUrl, '/') . '/' . ltrim($ogImage, '/');
        }

        $eTitle = Security::e($finalTitle);
        $eDesc = Security::e($seoDesc);
        $eCanonical = Security::e($canonicalUrl);
        $eSiteTitle = Security::e($siteTitle);
        $eRobots = Security::e($robots);
        $eOgImage = Security::e($ogImage);
        $ogType = ($postType === 'post') ? 'article' : 'website';

        $tags = [
            "<title>{$eTitle}</title>",
            "<meta name=\"description\" content=\"{$eDesc}\">",
            "<meta name=\"robots\" content=\"{$eRobots}\">",
            "<link rel=\"canonical\" href=\"{$eCanonical}\">",
            // OpenGraph
            "<meta property=\"og:title\" content=\"{$eTitle}\">",
            "<meta property=\"og:description\" content=\"{$eDesc}\">",
            "<meta property=\"og:url\" content=\"{$eCanonical}\">",
            "<meta property=\"og:site_name\" content=\"{$eSiteTitle}\">",
            "<meta property=\"og:type\" content=\"{$ogType}\">",
            // Twitter Cards
            "<meta name=\"twitter:card\" content=\"summary_large_image\">",
            "<meta name=\"twitter:title\" content=\"{$eTitle}\">",
            "<meta name=\"twitter:description\" content=\"{$eDesc}\">",
        ];

        if ($ogImage !== '') {
            $tags[] = "<meta property=\"og:image\" content=\"{$eOgImage}\">";
            $tags[] = "<meta name=\"twitter:image\" content=\"{$eOgImage}\">";
        }

        // Webmaster Verification Tags (Google Search Console & Bing Webmaster)
        $googleVerification = trim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_google_verification'"));
        if ($googleVerification !== '') {
            if (str_starts_with($googleVerification, '<meta')) {
                $tags[] = $googleVerification;
            } else {
                $eGv = Security::e($googleVerification);
                $tags[] = "<meta name=\"google-site-verification\" content=\"{$eGv}\">";
            }
        }

        $bingVerification = trim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_bing_verification'"));
        if ($bingVerification !== '') {
            if (str_starts_with($bingVerification, '<meta')) {
                $tags[] = $bingVerification;
            } else {
                $eBv = Security::e($bingVerification);
                $tags[] = "<meta name=\"msvalidate.01\" content=\"{$eBv}\">";
            }
        }

        // Automated JSON-LD Schema
        $schema = $this->generateJsonLdSchema($post, $siteTitle, $siteUrl, $canonicalUrl, $seoDesc);
        if ($schema !== '') {
            $tags[] = "<script type=\"application/ld+json\">{$schema}</script>";
        }

        return implode("\n    ", $tags);
    }

    /**
     * Generate dynamic robots.txt content honoring global indexing setting
     */
    public function generateRobotsTxt(): string
    {
        $siteUrl = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000';
        $siteUrl = rtrim($siteUrl, '/');
        $indexing = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_indexing'");

        $txt = "User-agent: *\n";
        if ($indexing === 'noindex') {
            $txt .= "Disallow: /\n";
        } else {
            $txt .= "Allow: /\n";
            $txt .= "Disallow: /admin/\n";
            $txt .= "Disallow: /install/\n";
            $txt .= "Sitemap: {$siteUrl}/sitemap.xml\n";
        }

        return $txt;
    }

    /**
     * Generate Schema.org structured JSON-LD
     */
    public function generateJsonLdSchema(array $post, string $siteTitle, string $siteUrl, string $canonicalUrl, string $description): string
    {
        $postType = (string)($post['post_type'] ?? 'page');

        if ($postType === 'post') {
            $data = [
                '@context'         => 'https://schema.org',
                '@type'            => 'BlogPosting',
                'headline'         => (string)($post['title'] ?? ''),
                'description'      => $description,
                'url'              => $canonicalUrl,
                'datePublished'    => (string)($post['published_at'] ?? $post['created_at'] ?? ''),
                'dateModified'     => (string)($post['updated_at'] ?? ''),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $canonicalUrl,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name'  => $siteTitle,
                    'url'   => $siteUrl,
                ],
            ];
        } else {
            $data = [
                '@context'    => 'https://schema.org',
                '@type'       => 'WebPage',
                'name'        => (string)($post['title'] ?? ''),
                'description' => $description,
                'url'         => $canonicalUrl,
                'isPartOf'    => [
                    '@type' => 'WebSite',
                    'name'  => $siteTitle,
                    'url'   => $siteUrl,
                ],
            ];
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json ?: '';
    }

    /**
     * Generate dynamic XML sitemap
     */
    public function generateSitemap(): string
    {
        $siteUrl = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000';
        $siteUrl = rtrim($siteUrl, '/');

        // Fetch all published posts across all types
        $posts = $this->db->fetchAll(
            "SELECT slug, post_type, updated_at, published_at FROM {prefix}posts WHERE status = 'publish' ORDER BY id DESC"
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($posts as $p) {
            $slug = (string)$p['slug'];
            $type = (string)$p['post_type'];

            if ($slug === 'home') {
                $loc = $siteUrl . '/';
                $priority = '1.0';
                $changefreq = 'daily';
            } elseif ($type === 'page') {
                $loc = $siteUrl . '/' . $slug;
                $priority = '0.8';
                $changefreq = 'weekly';
            } else {
                $loc = $siteUrl . '/' . $type . '/' . $slug;
                $priority = '0.7';
                $changefreq = 'monthly';
            }

            $date = date('c', strtotime((string)($p['updated_at'] ?: $p['published_at'] ?: date('Y-m-d H:i:s'))));

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>{$date}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }
}

