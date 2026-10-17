<?php
declare(strict_types=1);

include __DIR__ . '/header.php';
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full">
    <div class="mb-10 pb-6 border-b border-[#134e3a]">
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight"><?= $safePageTitle ?></h1>
    </div>

    <div class="prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm sm:text-base">
        <?= $contentHtml ?? '<p class="text-slate-500">Keine Inhalte vorhanden.</p>' ?>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

