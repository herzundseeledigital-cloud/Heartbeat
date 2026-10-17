<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;

include __DIR__ . '/header.php';

$csrfToken = Csrf::token();
?>

<main class="<?= $containerWidth ?> mx-auto px-6 py-20 flex-grow w-full" x-data="{ yearly: true }">
    <div class="text-center max-w-2xl mx-auto mb-16">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-purple-500/10 text-purple-400 text-xs font-semibold mb-3">
            <span>Transparente Preisgestaltung</span>
        </div>
        <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight mb-6">Investieren Sie in Erfolg</h1>
        <p class="text-sm text-slate-300 leading-relaxed mb-8">
            Alle Tarife beinhalten unbegrenzte statische Page-Cache Auslieferungen (&lt;20ms TTFB), automatisierte Backups und vollen Zugriff auf das Headless JSON Block Framework.
        </p>

        <!-- Toggle Switch -->
        <div class="inline-flex items-center space-x-3 p-1.5 rounded-2xl bg-[#16122e] border border-[#271f4d]">
            <button @click="yearly = false"
                    class="px-5 py-2 rounded-xl text-xs font-bold transition"
                    :class="!yearly ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                Monatliche Zahlung
            </button>
            <button @click="yearly = true"
                    class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2"
                    :class="yearly ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                <span>Jährliche Zahlung</span>
                <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 text-[10px] font-extrabold">20% Rabatt</span>
            </button>
        </div>
    </div>

    <!-- Pricing Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch mb-20">
        <!-- Starter -->
        <div class="p-8 rounded-3xl bg-[#16122e] border border-[#271f4d] flex flex-col justify-between shadow-xl">
            <div>
                <h3 class="text-lg font-bold text-white mb-2">Starter</h3>
                <p class="text-xs text-slate-400 mb-6">Ideal für Einsteiger, Blogs und persönliche Portfolios.</p>
                <div class="flex items-baseline space-x-1 mb-8">
                    <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '15 €' : '19 €'">15 €</span>
                    <span class="text-xs text-slate-400">/ Monat</span>
                </div>
                <ul class="space-y-3 text-xs text-slate-300 mb-8">
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>1 Produktive Website</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Static HTML &amp; Object Cache</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Cookielose DSGVO Analytik</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Standard E-Mail Support</span></li>
                </ul>
            </div>
            <a href="/" class="w-full py-3 rounded-xl bg-[#271f4d] hover:bg-[#342a66] text-slate-200 text-xs font-bold text-center transition block">
                Jetzt starten
            </a>
        </div>

        <!-- Pro -->
        <div class="p-8 rounded-3xl bg-gradient-to-b from-[#211a47] to-[#16122e] border-2 border-purple-500 shadow-2xl relative flex flex-col justify-between transform md:-translate-y-2">
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-gradient-to-r from-violet-600 to-rose-500 text-white text-[10px] font-black uppercase tracking-wider shadow-lg">
                Empfohlener Tarif
            </div>
            <div>
                <h3 class="text-lg font-bold text-white mb-2">Professional</h3>
                <p class="text-xs text-slate-300 mb-6">Für Agenturen, Webdesigner und Unternehmens-Websites.</p>
                <div class="flex items-baseline space-x-1 mb-8">
                    <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '39 €' : '49 €'">39 €</span>
                    <span class="text-xs text-slate-300">/ Monat</span>
                </div>
                <ul class="space-y-3 text-xs text-slate-200 mb-8">
                    <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Bis zu 5 Kunden-Websites</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Agency Mode &amp; White-Label Branding</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Client-Guard Sicherheitssperre</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>In-Browser Tailwind JIT Compiler</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Prioritärer 24/7 Support</span></li>
                </ul>
            </div>
            <form method="POST" action="/api/stripe/checkout">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="title" value="Heartbeat CMS Pro Plan (Yearly)">
                <input type="hidden" name="amount_cents" :value="yearly ? 3900 : 4900">
                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white text-xs font-bold shadow-xl shadow-purple-500/30 transition transform hover:scale-105">
                    Pro Tarif wählen &rarr;
                </button>
            </form>
        </div>

        <!-- Enterprise -->
        <div class="p-8 rounded-3xl bg-[#16122e] border border-[#271f4d] flex flex-col justify-between shadow-xl">
            <div>
                <h3 class="text-lg font-bold text-white mb-2">Enterprise</h3>
                <p class="text-xs text-slate-400 mb-6">Für Großagenturen und Enterprise-Infrastrukturen.</p>
                <div class="flex items-baseline space-x-1 mb-8">
                    <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '159 €' : '199 €'">159 €</span>
                    <span class="text-xs text-slate-400">/ Monat</span>
                </div>
                <ul class="space-y-3 text-xs text-slate-300 mb-8">
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Unbegrenzte Installationen</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Individuelle CPT &amp; Block Blueprints</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Zero-Exec Backup Cronjobs</span></li>
                    <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Dedizierter Solution Engineer</span></li>
                </ul>
            </div>
            <a href="/" class="w-full py-3 rounded-xl bg-[#271f4d] hover:bg-[#342a66] text-slate-200 text-xs font-bold text-center transition block">
                Vertrieb kontaktieren
            </a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

