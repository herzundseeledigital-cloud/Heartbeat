<?php
declare(strict_types=1);

include __DIR__ . '/header.php';
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-24 flex-grow w-full flex items-center justify-center text-center">
    <div class="max-w-md">
        <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-400 mx-auto mb-4 flex items-center justify-center text-2xl font-black">
            404
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">Immobilie nicht gefunden</h1>
        <p class="text-xs sm:text-sm text-slate-400 mb-8 leading-relaxed">
            Dieses Immobilienangebot existiert leider nicht mehr oder wurde bereits erfolgreich vermittelt.
        </p>
        <a href="/" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition shadow-lg shadow-emerald-500/20 inline-block">
            &larr; Alle aktuellen Angebote ansehen
        </a>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

