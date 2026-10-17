<?php
declare(strict_types=1);

include __DIR__ . '/header.php';
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full">
    <div class="mb-8 pb-6 border-b border-[#1e293b]">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight"><?= $safePageTitle ?></h1>
    </div>

    <div class="prose prose-invert max-w-none text-slate-300">
        <?= $contentHtml ?? '<p class="text-slate-500">Keine Inhalte vorhanden.</p>' ?>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

