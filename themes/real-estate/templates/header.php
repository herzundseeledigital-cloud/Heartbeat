<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
/** @var string $title */
/** @var string $seoTags */
/** @var array<int, array<string, mixed>> $navPages */

$safeSiteTitle = Security::e($siteTitle ?? 'Immobilien &amp; Residenzen');
$safePageTitle = Security::e($title ?? $safeSiteTitle);
$cssVars = isset($theme) ? $theme->getCssVariables() : '';
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-7xl'));
$navPages = $navPages ?? [];
?>
<!DOCTYPE html>
<html lang="de" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($seoTags)): ?>
        <?= $seoTags ?>
    <?php else: ?>
        <title><?= $safePageTitle ?> &bull; <?= $safeSiteTitle ?></title>
    <?php endif; ?>
    <style>
        <?= $cssVars ?>
        [x-cloak] { display: none !important; }
    </style>
    <link rel="stylesheet" href="/assets/css/installer.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#061a14] text-slate-100 font-sans antialiased selection:bg-[#059669] selection:text-white min-h-screen flex flex-col justify-between"
      x-data="{ mobileNav: false }">

    <!-- Broker Hotline & Topbar -->
    <div class="bg-[#04120e] border-b border-[#134e3a] text-slate-400 text-[11px] py-1.5 px-6 hidden sm:block">
        <div class="<?= $containerWidth ?> mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span>Exklusive Wohn- &amp; Gewerbeimmobilien</span>
                <span>&bull;</span>
                <span>IVD Verbandsmakler zertifiziert</span>
            </div>
            <div class="flex items-center space-x-4">
                <span>📞 Beratung: +49 (0) 89 1234 5678</span>
                <span>&bull;</span>
                <a href="/admin" class="hover:text-emerald-400 transition">Makler-Portal &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="border-b border-[#134e3a] bg-[#061a14]/90 backdrop-blur sticky top-0 z-50">
        <div class="<?= $containerWidth ?> mx-auto px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 flex items-center justify-center text-white font-black text-base shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition transform">
                    RE
                </div>
                <div>
                    <span class="font-extrabold text-white text-lg tracking-tight block leading-tight"><?= $safeSiteTitle ?></span>
                    <span class="text-[10px] text-emerald-400 uppercase tracking-widest font-semibold">Exklusive Immobilien</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-slate-300">
                <a href="/" class="hover:text-emerald-400 transition">Startseite</a>
                <a href="#properties" class="hover:text-emerald-400 transition">Immobilienangebote</a>
                <a href="#about" class="hover:text-emerald-400 transition">Über uns</a>
                <a href="#contact" class="hover:text-emerald-400 transition">Kontakt &amp; Suchauftrag</a>

                <?php foreach ($navPages as $np): ?>
                    <a href="/<?= Security::e((string)$np['slug']) ?>" class="hover:text-emerald-400 transition"><?= Security::e((string)$np['title']) ?></a>
                <?php endforeach; ?>
            </nav>

            <!-- Actions -->
            <div class="hidden sm:flex items-center space-x-3">
                <a href="#contact" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-lg shadow-emerald-500/20 transition transform hover:scale-105">
                    Suchauftrag aufgeben &rarr;
                </a>
            </div>

            <!-- Mobile Hamburger -->
            <button @click="mobileNav = !mobileNav" class="md:hidden p-2 rounded-lg bg-[#0a2e23] text-slate-300 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileNav" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path x-show="mobileNav" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileNav" x-cloak x-transition class="md:hidden bg-[#0a2e23] border-b border-[#134e3a] px-6 py-4 space-y-2">
            <a @click="mobileNav = false" href="/" class="block py-2 text-sm text-slate-200">Startseite</a>
            <a @click="mobileNav = false" href="#properties" class="block py-2 text-sm text-slate-200">Immobilienangebote</a>
            <a @click="mobileNav = false" href="#about" class="block py-2 text-sm text-slate-200">Über uns</a>
            <a @click="mobileNav = false" href="#contact" class="block py-2 text-sm text-emerald-400 font-semibold">Suchauftrag &rarr;</a>
        </div>
    </header>

