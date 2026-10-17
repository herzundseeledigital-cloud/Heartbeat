<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
$safeSiteTitle = Security::e($siteTitle ?? 'Corporate Enterprise');
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-6xl'));
$year = date('Y');
?>
    <!-- Corporate Multi-Column Footer -->
    <footer class="border-t border-[#1e293b] bg-[#070b14] py-16 text-slate-400 text-xs mt-20">
        <div class="<?= $containerWidth ?> mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-[#1e293b]">
                <!-- Column 1: Brand & Mission -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm">
                            CP
                        </div>
                        <span class="font-extrabold text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Wir digitalisieren Geschäftsprozesse und entwickeln skalierbare Web-Plattformen für mittelständische Unternehmen und Konzerne.
                    </p>
                </div>

                <!-- Column 2: Navigation -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Unternehmen</h4>
                    <ul class="space-y-2">
                        <li><a href="/team" class="hover:text-blue-400 transition">Team &amp; Ansprechpartner</a></li>
                        <li><a href="/referenzen" class="hover:text-blue-400 transition">Fallstudien</a></li>
                        <li><a href="#services" class="hover:text-blue-400 transition">Leistungsportfolio</a></li>
                        <li><a href="#contact" class="hover:text-blue-400 transition">Karriere</a></li>
                    </ul>
                </div>

                <!-- Column 3: Solutions -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Lösungen</h4>
                    <ul class="space-y-2">
                        <li><a href="#services" class="hover:text-blue-400 transition">Enterprise Portale</a></li>
                        <li><a href="#services" class="hover:text-blue-400 transition">Headless Architekturen</a></li>
                        <li><a href="#services" class="hover:text-blue-400 transition">Cloud Integration</a></li>
                        <li><a href="#services" class="hover:text-blue-400 transition">Sicherheit &amp; Compliance</a></li>
                    </ul>
                </div>

                <!-- Column 4: Contact -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Zentrale</h4>
                    <div class="text-xs text-slate-400 space-y-1.5 leading-relaxed">
                        <div>Friedrichstraße 100, 10117 Berlin</div>
                        <div>Tel: +49 (0) 30 9876543</div>
                        <div>Mail: <a href="mailto:kontakt@agentur.de" class="text-blue-400">kontakt@agentur.de</a></div>
                    </div>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    &copy; <?= $year ?> <?= $safeSiteTitle ?> &bull; Powered by Heartbeat CMS. Alle Rechte vorbehalten.
                </div>
                <div class="flex items-center space-x-6">
                    <a href="/" class="hover:text-slate-400">Impressum</a>
                    <a href="/" class="hover:text-slate-400">Datenschutz</a>
                    <span class="text-emerald-400 font-mono">100% DSGVO zertifiziert</span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

