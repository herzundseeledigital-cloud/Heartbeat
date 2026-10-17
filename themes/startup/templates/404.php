<?php
declare(strict_types=1);

include __DIR__ . '/header.php';
?>
<main class="<?= $containerWidth ?> mx-auto px-6 py-24 flex-grow w-full flex items-center justify-center text-center">
    <div class="max-w-md">
        <div class="w-16 h-16 rounded-2xl bg-purple-500/10 text-purple-400 mx-auto mb-4 flex items-center justify-center text-2xl font-black">
            404
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">Resource Not Found</h1>
        <p class="text-xs sm:text-sm text-slate-400 mb-8 leading-relaxed">
            The endpoint or product page you were looking for does not exist or has been deprecated.
        </p>
        <a href="/" class="px-6 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-semibold text-xs transition shadow-lg shadow-purple-500/20 inline-block">
            &larr; Return to Dashboard / Home
        </a>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>

