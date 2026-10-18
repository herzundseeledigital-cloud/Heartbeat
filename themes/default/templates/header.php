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
$navTree = $navTree ?? [];
if (empty($navTree)) {
    $navTree = array_map(fn($p) => ['title' => $p['title'] ?? '', 'slug' => $p['slug'] ?? '', 'children' => []], $navPages);
}
if (empty($customCss)) {
    $themeSlug = isset($theme) && is_object($theme) ? $theme->getSlug() : \Heartbeat\Themes\ThemeService::getInstance()->getActive()->getSlug();
    $customCss = \Heartbeat\Themes\ThemeService::getInstance()->getCustomCss($themeSlug);
}
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
    <?php if (!empty($customCss)): ?>
    <style id="theme-custom-css"><?= $customCss ?></style>
    <?php endif; ?>
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[#f97316] selection:text-white min-h-screen flex flex-col justify-between" x-data="{ mobileMenuOpen: false }">

    <!-- Sticky Navigation Header -->
    <header class="border-b border-[#1e293b] bg-[#090d16]/80 backdrop-blur sticky top-0 z-50 transition-all">
        <div class="<?= $containerWidth ?> mx-auto px-6 h-16 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#f97316] to-[#ea580c] flex items-center justify-center text-white font-black text-sm shadow-lg shadow-[#f97316]/20 group-hover:scale-105 transition transform">
                    HB
                </div>
                <span class="font-extrabold text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center space-x-6 text-xs font-semibold text-slate-300">
                <?php foreach ($navTree as $item): ?>
                    <?php 
                    $slug = (string)($item['slug'] ?? '');
                    $itemUrl = $slug === 'home' ? '/' : '/' . Security::e($slug);
                    $hasChildren = !empty($item['children']);
                    ?>
                    <?php if ($hasChildren): ?>
                        <div x-data="{ open: false }" @mouseleave="open = false" class="relative">
                            <button @click="open = !open" @mouseenter="open = true" class="flex items-center space-x-1 hover:text-[#f97316] transition py-2 focus:outline-none">
                                <span><?= Security::e((string)$item['title']) ?></span>
                                <svg class="w-3.5 h-3.5 opacity-60 transition-transform duration-150" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute left-0 mt-1 w-52 rounded-xl bg-[#0f172a] border border-[#1e293b] shadow-2xl py-2 z-50">
                                <a href="<?= $itemUrl ?>" class="block px-4 py-2 text-xs font-bold text-slate-200 hover:text-white hover:bg-slate-800/60 transition border-b border-[#1e293b]/60">
                                    <?= Security::e((string)$item['title']) ?> (Übersicht)
                                </a>
                                <?php foreach ($item['children'] as $child): ?>
                                    <a href="/<?= Security::e((string)$child['slug']) ?>" class="block px-4 py-2 text-xs text-slate-300 hover:text-[#f97316] hover:bg-slate-800/60 transition">
                                        <?= Security::e((string)$child['title']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= $itemUrl ?>" class="hover:text-[#f97316] transition py-2">
                            <?= Security::e((string)$item['title']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <!-- Actions -->
            <div class="hidden sm:flex items-center space-x-3">
                <a href="/kontakt" class="px-4 py-2 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-[#f97316]/20 transition transform hover:scale-105">
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
        <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden bg-[#0f172a] border-b border-[#1e293b] px-6 py-4 space-y-2">
            <?php foreach ($navTree as $item): ?>
                <?php 
                $slug = (string)($item['slug'] ?? '');
                $itemUrl = $slug === 'home' ? '/' : '/' . Security::e($slug);
                $hasChildren = !empty($item['children']);
                ?>
                <?php if ($hasChildren): ?>
                    <div x-data="{ subOpen: false }" class="py-1">
                        <button @click="subOpen = !subOpen" class="w-full flex items-center justify-between text-sm text-slate-200 hover:text-[#f97316] py-1.5 focus:outline-none">
                            <span class="font-semibold"><?= Security::e((string)$item['title']) ?></span>
                            <svg class="w-4 h-4 transition-transform duration-150" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="subOpen" class="pl-4 space-y-1 pt-1 border-l border-[#1e293b] ml-1">
                            <a @click="mobileMenuOpen = false" href="<?= $itemUrl ?>" class="block text-xs text-slate-400 hover:text-white py-1">
                                <?= Security::e((string)$item['title']) ?> (Übersicht)
                            </a>
                            <?php foreach ($item['children'] as $child): ?>
                                <a @click="mobileMenuOpen = false" href="/<?= Security::e((string)$child['slug']) ?>" class="block text-xs text-slate-400 hover:text-[#f97316] py-1">
                                    <?= Security::e((string)$child['title']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a @click="mobileMenuOpen = false" href="<?= $itemUrl ?>" class="block py-2 text-sm text-slate-200 hover:text-[#f97316]">
                        <?= Security::e((string)$item['title']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="pt-3 border-t border-[#1e293b]">
                <a @click="mobileMenuOpen = false" href="/kontakt" class="block w-full py-2.5 px-4 rounded-xl bg-[#f97316] text-white text-center text-xs font-semibold shadow-lg shadow-[#f97316]/20">
                    Jetzt anfragen &rarr;
                </a>
            </div>
        </div>
    </header>

