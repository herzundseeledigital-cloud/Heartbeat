<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
$safeSiteTitle = Security::e($siteTitle ?? 'Veloce AI Platform');
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-6xl'));
$year = date('Y');
?>
    <!-- Startup Footer -->
    <footer class="border-t border-[#271f4d] bg-[#080612] py-16 text-slate-400 text-xs mt-20">
        <div class="<?= $containerWidth ?> mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-[#271f4d]">
                <!-- Column 1 -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-violet-600 to-rose-500 flex items-center justify-center text-white font-black text-sm">
                            ⚡
                        </div>
                        <span class="font-extrabold text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Die Entwickler-Plattform der nächsten Generation für extrem schnelle Webanwendungen.
                    </p>
                    <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[11px] font-mono">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Alle Systeme 100% online</span>
                    </div>
                </div>

                <!-- Column 2 -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Produkt</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="hover:text-purple-400 transition">Architektur</a></li>
                        <li><a href="/pricing" class="hover:text-purple-400 transition">Tarife &amp; Preise</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Changelog</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">API Dokumentation</a></li>
                    </ul>
                </div>

                <!-- Column 3 -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Entwickler</h4>
                    <ul class="space-y-2">
                        <li><a href="/admin" class="hover:text-purple-400 transition">CMS Admin Login</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">GitHub Integration</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Community Discord</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Statusseite</a></li>
                    </ul>
                </div>

                <!-- Column 4 -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Rechtliches</h4>
                    <ul class="space-y-2">
                        <li><a href="/" class="hover:text-purple-400 transition">Datenschutz (DSGVO)</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Impressum</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Nutzungsbedingungen</a></li>
                        <li><a href="/" class="hover:text-purple-400 transition">Sicherheitsrichtlinien</a></li>
                    </ul>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    &copy; <?= $year ?> <?= $safeSiteTitle ?> &bull; Built with Heartbeat CMS. Zero External Trackers.
                </div>
                <div class="flex items-center space-x-6">
                    <span class="font-mono text-purple-400">TTFB &lt; 20ms</span>
                    <span>100% German Privacy</span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

