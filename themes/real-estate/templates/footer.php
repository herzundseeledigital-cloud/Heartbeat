<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
$safeSiteTitle = Security::e($siteTitle ?? 'Immobilien &amp; Residenzen');
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-7xl'));
$year = date('Y');
?>
    <!-- Real Estate Broker Footer -->
    <footer class="border-t border-[#134e3a] bg-[#04120e] py-16 text-slate-400 text-xs mt-20">
        <div class="<?= $containerWidth ?> mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-[#134e3a]">
                <!-- Column 1 -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-black text-sm">
                            RE
                        </div>
                        <span class="font-extrabold text-white text-base tracking-tight"><?= $safeSiteTitle ?></span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Ihr vertrauensvoller Partner für hochwertige Immobilien, Villen und Anlageobjekte in besten Lagen.
                    </p>
                </div>

                <!-- Column 2: Objektarten -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Objektarten</h4>
                    <ul class="space-y-2">
                        <li><a href="#properties" class="hover:text-emerald-400 transition">Eigentumswohnungen</a></li>
                        <li><a href="#properties" class="hover:text-emerald-400 transition">Einfamilienhäuser &amp; Villen</a></li>
                        <li><a href="#properties" class="hover:text-emerald-400 transition">Penthouses</a></li>
                        <li><a href="#properties" class="hover:text-emerald-400 transition">Gewerbeimmobilien</a></li>
                    </ul>
                </div>

                <!-- Column 3: Service -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Maklerservice</h4>
                    <ul class="space-y-2">
                        <li><a href="#contact" class="hover:text-emerald-400 transition">Kostenlose Immobilienbewertung</a></li>
                        <li><a href="#contact" class="hover:text-emerald-400 transition">Verkauf &amp; Vermietung</a></li>
                        <li><a href="#contact" class="hover:text-emerald-400 transition">VIP-Suchauftrag</a></li>
                        <li><a href="#contact" class="hover:text-emerald-400 transition">Diskrete Vermarktung (Off-Market)</a></li>
                    </ul>
                </div>

                <!-- Column 4: Standorte -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Büro München &amp; Berlin</h4>
                    <div class="text-xs text-slate-400 space-y-1.5 leading-relaxed">
                        <div>Maximilianstraße 35, 80539 München</div>
                        <div>Tel: +49 (0) 89 1234 5678</div>
                        <div>Mail: <a href="mailto:info@immobilien.de" class="text-emerald-400">info@immobilien.de</a></div>
                    </div>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    &copy; <?= $year ?> <?= $safeSiteTitle ?> &bull; Erlaubnis nach &sect; 34c GewO erteilt &bull; Powered by Heartbeat CMS
                </div>
                <div class="flex items-center space-x-6">
                    <a href="/" class="hover:text-slate-400">Impressum</a>
                    <a href="/" class="hover:text-slate-400">Datenschutz</a>
                    <span class="text-emerald-400 font-mono">100% DSGVO-Konform</span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

