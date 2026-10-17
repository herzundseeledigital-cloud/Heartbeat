<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
/** @var string $siteUrl */
/** @var string $title */
/** @var string $seoTags */
/** @var array<int, array<string, mixed>> $navPages */

$safeSiteTitle = Security::e($siteTitle ?? 'Heartbeat CMS');
$safePageTitle = Security::e($title ?? $safeSiteTitle);
$cssVars = isset($theme) ? $theme->getCssVariables() : '';
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-6xl'));
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
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[#f97316] selection:text-white min-h-screen flex flex-col justify-between" x-data="{ mobileMenuOpen: false }">

    <!-- Sticky Navigation Header -->
    <header class="border-b border-[#1e293b] bg-[#090d16]/80 backdrop-blur sticky top-0 z-50 transition-all">
        <div class="<?= $containerWidth ?> mx-auto px-6 h-16 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-orange-500/20 group-hover:scale-105 transition transform">
                    HB
                </div>
                <span class="font-extrabold text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
            </a>

            <!-- Desktop Navigation Links (Smooth-Scroll Anchors) -->
            <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-slate-300">
                <a href="#hero" class="hover:text-orange-400 transition">Start</a>
                <a href="#features" class="hover:text-orange-400 transition">Vorteile</a>
                <a href="#services" class="hover:text-orange-400 transition">Leistungen</a>
                <a href="#testimonials" class="hover:text-orange-400 transition">Referenzen</a>
                <a href="#faq" class="hover:text-orange-400 transition">FAQ</a>
                <a href="#contact" class="hover:text-orange-400 transition">Kontakt</a>

                <?php foreach ($navPages as $np): ?>
                    <a href="/<?= Security::e((string)$np['slug']) ?>" class="hover:text-orange-400 transition"><?= Security::e((string)$np['title']) ?></a>
                <?php endforeach; ?>
            </nav>

            <!-- Actions -->
            <div class="hidden sm:flex items-center space-x-3">
                <a href="#contact" class="px-4 py-2 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20 transition transform hover:scale-105">
                    Projekt anfragen &rarr;
                </a>
                <a href="/admin/login" class="px-3 py-2 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-300 hover:text-white text-xs font-medium transition" title="CMS Admin Login">
                    Admin
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-lg bg-[#1e293b] text-slate-300 hover:text-white focus:outline-none" aria-label="Menü öffnen">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden bg-[#0f172a] border-b border-[#1e293b] px-6 py-4 space-y-3">
            <a @click="mobileMenuOpen = false" href="#hero" class="block py-2 text-sm text-slate-200 hover:text-orange-400">Start</a>
            <a @click="mobileMenuOpen = false" href="#features" class="block py-2 text-sm text-slate-200 hover:text-orange-400">Vorteile</a>
            <a @click="mobileMenuOpen = false" href="#services" class="block py-2 text-sm text-slate-200 hover:text-orange-400">Leistungen</a>
            <a @click="mobileMenuOpen = false" href="#testimonials" class="block py-2 text-sm text-slate-200 hover:text-orange-400">Referenzen</a>
            <a @click="mobileMenuOpen = false" href="#faq" class="block py-2 text-sm text-slate-200 hover:text-orange-400">FAQ</a>
            <a @click="mobileMenuOpen = false" href="#contact" class="block py-2 text-sm text-orange-400 font-semibold">Jetzt anfragen &rarr;</a>
            <?php foreach ($navPages as $np): ?>
                <a @click="mobileMenuOpen = false" href="/<?= Security::e((string)$np['slug']) ?>" class="block py-2 text-sm text-slate-400"><?= Security::e((string)$np['title']) ?></a>
            <?php endforeach; ?>
        </div>
    </header>

