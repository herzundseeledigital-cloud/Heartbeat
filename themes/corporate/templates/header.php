<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
/** @var string $title */
/** @var string $seoTags */
/** @var array<int, array<string, mixed>> $navPages */

$safeSiteTitle = Security::e($siteTitle ?? 'Corporate Enterprise');
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
<body class="bg-[#0a0f1d] text-slate-100 font-sans antialiased selection:bg-[#2563eb] selection:text-white min-h-screen flex flex-col justify-between"
      x-data="{ mobileOpen: false, openDropdown: null }">

    <!-- Top Info Bar -->
    <div class="bg-[#070b14] border-b border-[#1e293b] text-slate-400 text-[11px] py-1.5 px-6 hidden sm:block">
        <div class="<?= $containerWidth ?> mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span>Enterprise B2B Solutions</span>
                <span>&bull;</span>
                <span>ISO 27001 zertifiziert</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="mailto:kontakt@agentur.de" class="hover:text-blue-400 transition">kontakt@agentur.de</a>
                <span>&bull;</span>
                <a href="/admin" class="hover:text-white transition">CMS Login &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Multi-Level Accessible Navigation Header -->
    <header class="border-b border-[#1e293b] bg-[#0a0f1d]/90 backdrop-blur sticky top-0 z-50">
        <div class="<?= $containerWidth ?> mx-auto px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white font-black text-base shadow-lg shadow-blue-500/20 group-hover:scale-105 transition transform">
                    CP
                </div>
                <div>
                    <span class="font-extrabold text-white text-lg tracking-tight block leading-tight"><?= $safeSiteTitle ?></span>
                    <span class="text-[10px] text-blue-400 uppercase tracking-widest font-semibold">Digital Solutions</span>
                </div>
            </a>

            <!-- Desktop Multi-Level Accessible Menu -->
            <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-slate-300">
                <a href="/" class="hover:text-blue-400 transition py-2">Startseite</a>

                <!-- Multi-Level Dropdown: Unternehmen -->
                <div class="relative" @mouseenter="openDropdown = 'company'" @mouseleave="openDropdown = null">
                    <button @click="openDropdown = (openDropdown === 'company' ? null : 'company')"
                            class="hover:text-blue-400 transition flex items-center space-x-1 py-2 focus:outline-none"
                            aria-haspopup="true" :aria-expanded="openDropdown === 'company'">
                        <span>Unternehmen</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="openDropdown === 'company' ? 'rotate-180 text-blue-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Dropdown Panel with Accessible Roles -->
                    <div x-show="openDropdown === 'company'" x-cloak x-transition
                         class="absolute top-full left-0 w-52 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl p-2 z-50 mt-1"
                         role="menu" aria-label="Unternehmen Menü">
                        <a href="/team" class="flex items-center space-x-2.5 px-3 py-2.5 rounded-xl hover:bg-blue-500/10 hover:text-blue-400 transition" role="menuitem">
                            <span class="text-sm">👥</span>
                            <div>
                                <div class="text-xs font-bold text-white">Team &amp; Führung</div>
                                <div class="text-[10px] text-slate-400">Unsere Experten</div>
                            </div>
                        </a>
                        <a href="#services" class="flex items-center space-x-2.5 px-3 py-2.5 rounded-xl hover:bg-blue-500/10 hover:text-blue-400 transition" role="menuitem">
                            <span class="text-sm">🏢</span>
                            <div>
                                <div class="text-xs font-bold text-white">Über uns</div>
                                <div class="text-[10px] text-slate-400">Mission &amp; Werte</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Multi-Level Dropdown: Leistungen -->
                <div class="relative" @mouseenter="openDropdown = 'services'" @mouseleave="openDropdown = null">
                    <button @click="openDropdown = (openDropdown === 'services' ? null : 'services')"
                            class="hover:text-blue-400 transition flex items-center space-x-1 py-2 focus:outline-none"
                            aria-haspopup="true" :aria-expanded="openDropdown === 'services'">
                        <span>Leistungen</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="openDropdown === 'services' ? 'rotate-180 text-blue-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="openDropdown === 'services'" x-cloak x-transition
                         class="absolute top-full left-0 w-60 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl p-2 z-50 mt-1"
                         role="menu" aria-label="Leistungen Menü">
                        <a href="#services" class="flex items-center space-x-2.5 px-3 py-2.5 rounded-xl hover:bg-blue-500/10 hover:text-blue-400 transition" role="menuitem">
                            <span class="text-sm">💻</span>
                            <div>
                                <div class="text-xs font-bold text-white">Webentwicklung</div>
                                <div class="text-[10px] text-slate-400">Portale &amp; Webanwendungen</div>
                            </div>
                        </a>
                        <a href="#services" class="flex items-center space-x-2.5 px-3 py-2.5 rounded-xl hover:bg-blue-500/10 hover:text-blue-400 transition" role="menuitem">
                            <span class="text-sm">☁️</span>
                            <div>
                                <div class="text-xs font-bold text-white">Cloud Architecture</div>
                                <div class="text-[10px] text-slate-400">Skalierung &amp; Sicherheit</div>
                            </div>
                        </a>
                    </div>
                </div>

                <a href="/referenzen" class="hover:text-blue-400 transition py-2">Referenzen</a>
                <a href="#contact" class="hover:text-blue-400 transition py-2">Kontakt</a>

                <?php foreach ($navPages as $np): ?>
                    <a href="/<?= Security::e((string)$np['slug']) ?>" class="hover:text-blue-400 transition py-2"><?= Security::e((string)$np['title']) ?></a>
                <?php endforeach; ?>
            </nav>

            <!-- Header Action -->
            <div class="hidden sm:flex items-center space-x-4">
                <a href="#contact" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-lg shadow-blue-500/20 transition transform hover:scale-105">
                    Beratung anfordern &rarr;
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-lg bg-[#1e293b] text-slate-300 hover:text-white" aria-label="Menü umschalten">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileOpen" x-cloak x-transition class="md:hidden bg-[#0f172a] border-b border-[#1e293b] px-6 py-4 space-y-2">
            <a @click="mobileOpen = false" href="/" class="block py-2 text-sm text-slate-200">Startseite</a>
            <a @click="mobileOpen = false" href="/team" class="block py-2 text-sm text-slate-200">&bull; Team &amp; Ansprechpartner</a>
            <a @click="mobileOpen = false" href="#services" class="block py-2 text-sm text-slate-200">&bull; Leistungen &amp; Services</a>
            <a @click="mobileOpen = false" href="/referenzen" class="block py-2 text-sm text-slate-200">&bull; Fallstudien &amp; Referenzen</a>
            <a @click="mobileOpen = false" href="#contact" class="block py-2 text-sm text-blue-400 font-semibold">Kontakt &rarr;</a>
        </div>
    </header>

