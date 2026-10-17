<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;

include __DIR__ . '/header.php';

$postsService = PostService::getInstance();
$metaService = MetaService::getInstance();

$dbCaseStudies = $postsService->query([
    'post_type' => 'case_study',
    'status'    => 'publish',
    'limit'     => 50,
]);

$caseStudies = [];
if (!empty($dbCaseStudies)) {
    foreach ($dbCaseStudies as $cs) {
        $meta = $metaService->getAllPostMeta((int)$cs['id']);
        $caseStudies[] = [
            'id'          => $cs['id'],
            'title'       => $cs['title'],
            'slug'        => $cs['slug'],
            'client_name' => $meta['client_name'] ?? 'Enterprise Kunde',
            'project_year'=> $meta['project_year'] ?? '2026',
            'services'    => $meta['services_provided'] ?? 'Full-Stack Replatforming',
            'project_url' => $meta['project_url'] ?? '',
            'metrics'     => '+340% Performance & Skalierung',
        ];
    }
} else {
    $caseStudies = [
        ['id' => 1, 'title' => 'Fintech Banking Plattform', 'slug' => 'fintech-banking', 'client_name' => 'NextGen Capital Group', 'project_year' => '2026', 'services' => 'Cloud Migration & Headless Portal', 'project_url' => 'https://example.com', 'metrics' => '+340% Performance & ISO 27001'],
        ['id' => 2, 'title' => 'Globales Logistikportal', 'slug' => 'global-logistics', 'client_name' => 'EuroTrans Logistics SE', 'project_year' => '2025', 'services' => 'Echtzeit-Tracking & API Integration', 'project_url' => 'https://example.com', 'metrics' => '99.99% Uptime & 10M+ Requests/Tag'],
        ['id' => 3, 'title' => 'HealthTech Telemedizin Suite', 'slug' => 'healthtech-suite', 'client_name' => 'MediConnect Deutschland', 'project_year' => '2026', 'services' => 'DSGVO-konforme Patientenakte', 'project_url' => 'https://example.com', 'metrics' => '100% DSGVO & 0 Third-Party Calls'],
        ['id' => 4, 'title' => 'Automotive B2B Ersatzteil-Portal', 'slug' => 'automotive-parts', 'client_name' => 'AutoParts International', 'project_year' => '2025', 'services' => 'ERP Schnittstellen & Multilingual', 'project_url' => 'https://example.com', 'metrics' => '400k+ SKUs & &lt;20ms Suche'],
    ];
}
?>

<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full">
    <div class="mb-12 pb-6 border-b border-[#1e293b]">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 text-blue-400 text-xs font-semibold mb-3">
            <span>Portfolio</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">Fallstudien &amp; Referenzen</h1>
        <p class="text-sm text-slate-400 mt-2 max-w-2xl">
            Entdecken Sie, wie wir führenden Unternehmen dabei geholfen haben, ihre digitale Infrastruktur auf das nächste Level zu heben.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($caseStudies as $cs): ?>
            <div class="p-8 rounded-2xl bg-[#0f172a] border border-[#1e293b] flex flex-col justify-between hover:border-blue-500/40 transition">
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-500 mb-4 font-mono">
                        <span class="text-blue-400 font-bold"><?= Security::e((string)$cs['client_name']) ?></span>
                        <span>Projektjahr: <?= Security::e((string)$cs['project_year']) ?></span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3"><?= Security::e((string)$cs['title']) ?></h3>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-6">
                        <?= Security::e((string)$cs['services']) ?>
                    </p>
                </div>

                <div class="pt-6 border-t border-[#1e293b] flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-400 font-mono"><?= Security::e((string)$cs['metrics']) ?></span>
                    <?php if (!empty($cs['project_url'])): ?>
                        <a href="<?= Security::e((string)$cs['project_url']) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-blue-400 hover:text-blue-300">Projekt ansehen &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

