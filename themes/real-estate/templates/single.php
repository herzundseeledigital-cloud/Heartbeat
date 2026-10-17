<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

include __DIR__ . '/header.php';

$authorName = !empty($post['author_name']) ? Security::e($post['author_name']) : 'Makler-Team';
$dateFormatted = !empty($post['published_at']) ? date('d.m.Y', strtotime((string)$post['published_at'])) : date('d.m.Y');
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full max-w-4xl">
    <div class="mb-10 pb-6 border-b border-[#134e3a]">
        <div class="flex items-center space-x-3 text-xs text-emerald-400 font-semibold mb-3">
            <span>Veröffentlicht am <?= $dateFormatted ?></span>
            <span>&bull;</span>
            <span>Von <?= $authorName ?></span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight"><?= $safePageTitle ?></h1>
    </div>

    <article class="prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm sm:text-base">
        <?= $contentHtml ?? '' ?>
    </article>

    <div class="mt-12 pt-8 border-t border-[#134e3a] flex items-center justify-between">
        <a href="/" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition">&larr; Zurück zur Startseite</a>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

