<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
/** @var string $title */
/** @var string $seoTags */
/** @var array<int, array<string, mixed>> $navPages */

$safeSiteTitle = Security::e($siteTitle ?? 'Veloce AI Platform');
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
<body class="bg-[#0c0a1a] text-slate-100 font-sans antialiased selection:bg-[#8b5cf6] selection:text-white min-h-screen flex flex-col justify-between"
      x-data="{
          mobileNav: false,
          isDark: true,
          initTheme() {
              const saved = localStorage.getItem('hb_startup_theme');
              if (saved === 'light') {
                  this.isDark = false;
                  document.documentElement.classList.remove('dark');
              } else {
                  this.isDark = true;
                  document.documentElement.classList.add('dark');
              }
          },
          toggleDarkMode() {
              this.isDark = !this.isDark;
              if (this.isDark) {
                  document.documentElement.classList.add('dark');
                  localStorage.setItem('hb_startup_theme', 'dark');
              } else {
                  document.documentElement.classList.remove('dark');
                  localStorage.setItem('hb_startup_theme', 'light');
              }
          }
      }"
      x-init="initTheme()">

    <!-- Header Navigation -->
    <header class="border-b border-[#271f4d] bg-[#0c0a1a]/80 backdrop-blur sticky top-0 z-50 transition-all">
        <div class="<?= $containerWidth ?> mx-auto px-6 h-18 py-4 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 via-purple-600 to-rose-500 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-purple-500/25 group-hover:scale-110 transition transform">
                    ⚡
                </div>
                <span class="font-black text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center space-x-8 text-xs font-semibold text-slate-300">
                <a href="/" class="hover:text-purple-400 transition">Produkt</a>
                <a href="#features" class="hover:text-purple-400 transition">Features</a>
                <a href="/pricing" class="hover:text-purple-400 transition">Preise</a>
                <a href="#faq" class="hover:text-purple-400 transition">FAQ</a>

                <?php foreach ($navPages as $np): ?>
                    <a href="/<?= Security::e((string)$np['slug']) ?>" class="hover:text-purple-400 transition"><?= Security::e((string)$np['title']) ?></a>
                <?php endforeach; ?>
            </nav>

            <!-- Actions: Dark/Light Mode Toggle + CTA -->
            <div class="hidden sm:flex items-center space-x-4">
                <!-- Dark / Light Mode Toggle Button -->
                <button @click="toggleDarkMode()"
                        class="p-2 rounded-xl bg-[#16122e] border border-[#271f4d] text-slate-300 hover:text-white transition hover:border-purple-500/50"
                        title="Dark / Light Mode umschalten">
                    <!-- Sun Icon (shown when dark) -->
                    <svg x-show="isDark" class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <!-- Moon Icon (shown when light) -->
                    <svg x-show="!isDark" x-cloak class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>

                <a href="/admin/login" class="text-xs text-slate-400 hover:text-white transition font-semibold">Login</a>

                <a href="/pricing" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white text-xs font-bold shadow-lg shadow-purple-500/25 transition transform hover:scale-105">
                    Kostenlos starten &rarr;
                </a>
            </div>

            <!-- Mobile Hamburger -->
            <button @click="mobileNav = !mobileNav" class="md:hidden p-2 rounded-lg bg-[#16122e] text-slate-300 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileNav" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path x-show="mobileNav" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileNav" x-cloak x-transition class="md:hidden bg-[#16122e] border-b border-[#271f4d] px-6 py-4 space-y-2">
            <a @click="mobileNav = false" href="/" class="block py-2 text-sm text-slate-200">Produkt</a>
            <a @click="mobileNav = false" href="#features" class="block py-2 text-sm text-slate-200">Features</a>
            <a @click="mobileNav = false" href="/pricing" class="block py-2 text-sm text-purple-400 font-semibold">Preise &amp; Tarife</a>
            <div class="pt-2 flex items-center justify-between border-t border-[#271f4d]">
                <span class="text-xs text-slate-400">Farbschema:</span>
                <button @click="toggleDarkMode()" class="text-xs text-purple-400 font-bold" x-text="isDark ? '☀️ Helles Design' : '🌙 Dunkles Design'"></button>
            </div>
        </div>
    </header>

