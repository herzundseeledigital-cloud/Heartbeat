<?php
declare(strict_types=1);

use Heartbeat\Security\Security;

/** @var \Heartbeat\Themes\Theme $theme */
/** @var string $siteTitle */
$safeSiteTitle = Security::e($siteTitle ?? 'Heartbeat CMS');
$settings = isset($theme) ? $theme->getSettings() : [];
$containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-6xl'));
$year = date('Y');
?>
    <!-- Footer -->
    <footer class="border-t border-[#1e293b] bg-[#090d16] py-12 text-slate-400 text-xs mt-16">
        <div class="<?= $containerWidth ?> mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 pb-8 border-b border-[#1e293b]">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center font-black text-xs">
                        HB
                    </div>
                    <span class="font-bold text-white text-sm"><?= $safeSiteTitle ?></span>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6 text-slate-400">
                    <a href="#hero" class="hover:text-white transition">Nach oben &uarr;</a>
                    <a href="/admin" class="hover:text-white transition">Adminbereich</a>
                    <span class="text-slate-600">&bull;</span>
                    <span class="text-[11px] font-mono text-emerald-400">TTFB &lt; 20ms</span>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    &copy; <?= $year ?> <?= $safeSiteTitle ?> &bull; Erstellt mit Heartbeat CMS. Alle Rechte vorbehalten.
                </div>
                <div class="flex items-center space-x-4">
                    <span>100% DSGVO &bull; Keine Cookies</span>
                    <span>Self-Hosted Google Fonts</span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

