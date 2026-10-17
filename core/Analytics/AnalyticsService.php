<?php
declare(strict_types=1);

namespace Heartbeat\Analytics;

use Heartbeat\Database\Database;
use Heartbeat\Http\Request;

/**
 * 100% Cookieless & DSGVO/TDDDG Compliant Native Analytics Engine
 * Zero cookies, zero localStorage, zero third-party calls.
 * Uses daily cryptographic rotating salt + IP subnet masking.
 */
final class AnalyticsService
{
    private static ?AnalyticsService $instance = null;
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
     * Record a page visit if request is eligible
     */
    public function track(Request $request): void
    {
        $path = $request->getPath();

        // 1. Skip admin, install, static assets, or api routes
        if ($this->shouldSkipPath($path)) {
            return;
        }

        $userAgent = $request->getHeader('user-agent');
        if ($this->isBot($userAgent)) {
            return;
        }

        $ip = $request->getIp();
        $truncatedIp = $this->truncateIp($ip);
        $dailySalt = $this->getDailySalt();
        $visitorHash = hash('sha256', $truncatedIp . '|' . $userAgent . '|' . $dailySalt);

        $today = date('Y-m-d');
        $device = $this->detectDevice($userAgent);
        $browser = $this->detectBrowser($userAgent);
        $rawReferrer = $request->getHeader('referer');
        $cleanReferrer = $this->cleanReferrer($rawReferrer, $request->getHost());

        // Check if visitor has already visited this specific path today
        $existing = $this->db->fetchOne(
            "SELECT id, views FROM {prefix}analytics_daily WHERE date = :d AND path = :p AND visitor_hash = :vh LIMIT 1",
            [
                'd'  => $today,
                'p'  => $path,
                'vh' => $visitorHash,
            ]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE {prefix}analytics_daily SET views = views + 1 WHERE id = :id",
                ['id' => $existing['id']]
            );
        } else {
            $this->db->insert('{prefix}analytics_daily', [
                'date'         => $today,
                'path'         => $path,
                'visitor_hash' => $visitorHash,
                'views'        => 1,
                'device'       => $device,
                'browser'      => $browser,
                'referrer'     => $cleanReferrer,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Check if path should be omitted from tracking
     */
    public function shouldSkipPath(string $path): bool
    {
        if ($path === '') return true;

        $ignoredPrefixes = ['/admin', '/install', '/assets', '/uploads', '/api/'];
        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $ignoredExtensions = ['.css', '.js', '.map', '.woff', '.woff2', '.ttf', '.png', '.jpg', '.jpeg', '.webp', '.svg', '.gif', '.ico'];
        foreach ($ignoredExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect known bots / web crawlers
     */
    public function isBot(string $userAgent): bool
    {
        if ($userAgent === '') return false;
        $ua = strtolower($userAgent);
        $botKeywords = ['bot', 'crawler', 'spider', 'slurp', 'crawling', 'curl', 'wget', 'mediapartners', 'lighthouse', 'headless'];
        foreach ($botKeywords as $kw) {
            if (str_contains($ua, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask IP address (IPv4 /24, IPv6 /48) for strict GDPR compliance
     */
    public function truncateIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 3) {
                return $parts[0] . ':' . $parts[1] . ':' . $parts[2] . '::';
            }
        }
        return '0.0.0.0';
    }

    /**
     * Detect client device category
     */
    public function detectDevice(string $ua): string
    {
        $lower = strtolower($ua);
        if (str_contains($lower, 'tablet') || str_contains($lower, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($lower, 'mobile') || str_contains($lower, 'android') || str_contains($lower, 'iphone')) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Detect client browser
     */
    public function detectBrowser(string $ua): string
    {
        $lower = strtolower($ua);
        if (str_contains($lower, 'edg/')) return 'edge';
        if (str_contains($lower, 'opr/') || str_contains($lower, 'opera')) return 'opera';
        if (str_contains($lower, 'chrome/')) return 'chrome';
        if (str_contains($lower, 'firefox/')) return 'firefox';
        if (str_contains($lower, 'safari/') && !str_contains($lower, 'chrome/')) return 'safari';
        return 'other';
    }

    /**
     * Normalize referrer
     */
    public function cleanReferrer(string $referrer, string $currentHost): string
    {
        if ($referrer === '') return 'Direkt / Bookmark';
        $parsed = parse_url($referrer);
        $refHost = $parsed['host'] ?? '';
        if ($refHost === '' || str_contains($refHost, $currentHost)) {
            return 'Direkt / Bookmark';
        }
        return $refHost;
    }

    /**
     * Retrieve aggregated metrics for the admin analytics dashboard
     * @return array<string, mixed>
     */
    public function getDashboardSummary(int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $today = date('Y-m-d');

        // Total views and uniques in period
        $totals = $this->db->fetchOne(
            "SELECT SUM(views) as total_views, COUNT(DISTINCT visitor_hash) as unique_visitors 
             FROM {prefix}analytics_daily WHERE date >= :start",
            ['start' => $startDate]
        );

        $totalViews = (int)($totals['total_views'] ?? 0);
        $totalUniques = (int)($totals['unique_visitors'] ?? 0);

        // Today's metrics
        $todayMetrics = $this->db->fetchOne(
            "SELECT SUM(views) as today_views, COUNT(DISTINCT visitor_hash) as today_uniques 
             FROM {prefix}analytics_daily WHERE date = :today",
            ['today' => $today]
        );
        $todayViews = (int)($todayMetrics['today_views'] ?? 0);
        $todayUniques = (int)($todayMetrics['today_uniques'] ?? 0);

        // Daily trend data (last 14 or 30 days)
        $dailyRows = $this->db->fetchAll(
            "SELECT date, SUM(views) as views, COUNT(DISTINCT visitor_hash) as uniques 
             FROM {prefix}analytics_daily 
             WHERE date >= :start 
             GROUP BY date 
             ORDER BY date ASC",
            ['start' => $startDate]
        );

        // Top 10 pages
        $topPages = $this->db->fetchAll(
            "SELECT path, SUM(views) as views, COUNT(DISTINCT visitor_hash) as uniques 
             FROM {prefix}analytics_daily 
             WHERE date >= :start 
             GROUP BY path 
             ORDER BY views DESC 
             LIMIT 10",
            ['start' => $startDate]
        );

        // Top referrers
        $topReferrers = $this->db->fetchAll(
            "SELECT referrer, SUM(views) as views 
             FROM {prefix}analytics_daily 
             WHERE date >= :start AND referrer != '' 
             GROUP BY referrer 
             ORDER BY views DESC 
             LIMIT 8",
            ['start' => $startDate]
        );

        // Device breakdown
        $deviceRows = $this->db->fetchAll(
            "SELECT device, SUM(views) as views 
             FROM {prefix}analytics_daily 
             WHERE date >= :start 
             GROUP BY device",
            ['start' => $startDate]
        );

        $devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
        foreach ($deviceRows as $dr) {
            $dev = strtolower((string)$dr['device']);
            if (isset($devices[$dev])) {
                $devices[$dev] = (int)$dr['views'];
            }
        }

        // Browser breakdown
        $browserRows = $this->db->fetchAll(
            "SELECT browser, SUM(views) as views 
             FROM {prefix}analytics_daily 
             WHERE date >= :start 
             GROUP BY browser 
             ORDER BY views DESC 
             LIMIT 5",
            ['start' => $startDate]
        );

        return [
            'total_views'     => $totalViews,
            'unique_visitors' => $totalUniques,
            'today_views'     => $todayViews,
            'today_uniques'   => $todayUniques,
            'daily_trend'     => $dailyRows,
            'top_pages'       => $topPages,
            'top_referrers'   => $topReferrers,
            'devices'         => $devices,
            'browsers'        => $browserRows,
            'period_days'     => $days,
        ];
    }

    /**
     * Daily cryptographic rotating salt
     */
    private function getDailySalt(): string
    {
        $siteSecret = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_secret'");
        if ($siteSecret === '') {
            $siteSecret = bin2hex(random_bytes(32));
            $this->db->insert('{prefix}options', [
                'option_name'  => 'site_secret',
                'option_value' => $siteSecret,
                'autoload'     => 1,
            ]);
        }

        return hash('sha256', date('Y-m-d') . '|' . $siteSecret);
    }
}

