<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

include __DIR__ . '/header.php';

$authorName = !empty($post['author_name']) ? Security::e($post['author_name']) : 'Redaktion';
$dateFormatted = !empty($post['published_at']) ? date('d.m.Y', strtotime((string)$post['published_at'])) : date('d.m.Y');
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full max-w-4xl">
    <div class="mb-10 pb-6 border-b border-[#1e293b]">
        <div class="flex items-center space-x-3 text-xs text-orange-400 font-semibold mb-3">
            <span>Veröffentlicht am <?= $dateFormatted ?></span>
            <span>&bull;</span>
            <span>Von <?= $authorName ?></span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight"><?= $safePageTitle ?></h1>
    </div>

    <article class="prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm sm:text-base">
        <?= $contentHtml ?? '' ?>
    </article>

    <div class="mt-12 pt-8 border-t border-[#1e293b] flex items-center justify-between">
        <a href="/" class="text-xs font-semibold text-orange-400 hover:text-orange-300 transition">&larr; Zurück zur Übersicht</a>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

