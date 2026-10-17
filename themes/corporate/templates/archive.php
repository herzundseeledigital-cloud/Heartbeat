<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

include __DIR__ . '/header.php';

$items = $items ?? [];
$postType = $postType ?? 'Beiträge';
$safePostType = Security::e(ucfirst((string)$postType));
?>

<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full">
    <div class="mb-12 pb-6 border-b border-[#1e293b]">
        <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Archiv: <?= $safePostType ?></h1>
    </div>

    <?php if (empty($items)): ?>
        <p class="text-slate-400 text-sm">Aktuell sind in dieser Kategorie keine Einträge vorhanden.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($items as $item): ?>
                <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition">
                    <h3 class="text-base font-bold text-white mb-2"><?= Security::e((string)$item['title']) ?></h3>
                    <p class="text-xs text-slate-400 line-clamp-3 mb-4"><?= Security::e((string)($item['excerpt'] ?? '')) ?></p>
                    <a href="/<?= Security::e((string)$item['slug']) ?>" class="text-xs text-blue-400 font-semibold hover:underline">Mehr lesen &rarr;</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>

