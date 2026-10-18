<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;

include __DIR__ . '/header.php';

$csrfToken = Csrf::token();
$safeSiteTitle = Security::e($siteTitle ?? 'Veloce AI Platform');
?>

<!-- Hero Section (Torino Inspiration) -->
<section class="py-16 md:py-24 relative overflow-hidden text-center">
    <!-- Glowing Radial Gradient -->
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[400px] bg-gradient-to-r from-violet-600/20 via-purple-600/15 to-[#f43f5e]/20 blur-[150px] rounded-full pointer-events-none"></div>

    <div class="<?= $containerWidth ?> mx-auto px-6 relative z-10">
        <!-- Event Launch Badge -->
        <div class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-[#16122e] border border-[#271f4d] text-purple-300 text-xs font-bold mb-6 shadow-sm">
            <span class="px-2 py-0.5 rounded-full bg-gradient-to-r from-violet-600 to-[#f43f5e] text-white text-[10px] font-bold">24.–26. Nov 2026</span>
            <span>STATION Berlin &bull; Der europäische Tech Summit &rarr;</span>
        </div>

        <!-- Headline -->
        <h1 class="text-4xl sm:text-6xl md:text-7xl font-black text-white tracking-tight leading-[1.08] max-w-4xl mx-auto mb-6">
            Die Zukunft von Web-Architektur &amp; <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 via-purple-300 to-[#f43f5e]">AI-Engineering</span>.
        </h1>

        <!-- Subheadline -->
        <p class="text-base sm:text-xl text-slate-300 max-w-2xl mx-auto leading-relaxed mb-8">
            3 Tage Keynotes, Hands-on Coding Labs und Networking mit 1.200 Tech-Leadern zu Autonomous Coding, JIT-Tailwind und kompromissloser Core Web Vitals Höchstleistung.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-4 mb-8">
            <a href="/pricing" class="px-8 py-4 rounded-xl bg-gradient-to-r from-violet-600 via-purple-600 to-[#f43f5e] hover:opacity-90 text-white font-bold text-sm shadow-xl shadow-purple-500/25 transition transform hover:scale-105">
                Early-Bird Tickets sichern (ab 249 €) &rarr;
            </a>
            <a href="#features" class="px-8 py-4 rounded-xl bg-[#16122e] hover:bg-[#231d45] border border-[#271f4d] text-slate-200 font-semibold text-sm transition">
                Agenda &amp; Speaker ansehen &darr;
            </a>
        </div>

        <!-- Social Proof Attendees Strip -->
        <div class="flex items-center justify-center space-x-3 mb-14 text-xs text-purple-300">
            <div class="flex -space-x-2 overflow-hidden">
                <span class="inline-block h-7 w-7 rounded-full ring-2 ring-[#0c0a1a] bg-gradient-to-br from-purple-500 to-indigo-600 text-white font-bold text-[10px] flex items-center justify-center">TB</span>
                <span class="inline-block h-7 w-7 rounded-full ring-2 ring-[#0c0a1a] bg-gradient-to-br from-[#f43f5e] to-rose-600 text-white font-bold text-[10px] flex items-center justify-center">AK</span>
                <span class="inline-block h-7 w-7 rounded-full ring-2 ring-[#0c0a1a] bg-gradient-to-br from-cyan-500 to-blue-600 text-white font-bold text-[10px] flex items-center justify-center">MS</span>
                <span class="inline-block h-7 w-7 rounded-full ring-2 ring-[#0c0a1a] bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-bold text-[10px] flex items-center justify-center">LR</span>
            </div>
            <span>Bereits <strong>850+ Teilnehmer</strong> registriert (Google, SAP, Spotify, BMW)</span>
        </div>

        <!-- Keynote Stage Showcase Visual with Floating Badges (Torino Inspiration) -->
        <div class="relative max-w-5xl mx-auto group">
            <!-- Ambient Glow behind frame -->
            <div class="absolute -inset-1 bg-gradient-to-r from-violet-600/30 via-purple-600/20 to-[#f43f5e]/30 rounded-2xl blur-xl opacity-75 group-hover:opacity-100 transition duration-700"></div>

            <!-- Stage Frame -->
            <div class="relative bg-[#16122e] border border-[#271f4d] rounded-2xl shadow-2xl overflow-hidden aspect-[16/9]">
                <img src="/assets/img/hero-startup.jpg" alt="Heartbeat Tech Summit 2026 Keynote Stage" class="w-full h-full object-cover object-center transform group-hover:scale-[1.02] transition duration-700 ease-out" loading="eager">
                <div class="absolute inset-0 bg-gradient-to-t from-[#0c0a1a] via-transparent to-transparent opacity-50"></div>
            </div>

            <!-- Floating Stage Badge Left -->
            <div class="absolute -bottom-6 -left-4 sm:left-6 bg-[#16122e]/95 backdrop-blur-md border border-[#271f4d] rounded-xl p-4 shadow-2xl flex items-center space-x-3 text-left max-w-[270px] transform hover:-translate-y-1 transition duration-300">
                <div class="w-10 h-10 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 font-bold flex-shrink-0 animate-pulse">
                    🔴
                </div>
                <div>
                    <div class="text-xs font-bold text-white flex items-center space-x-1.5">
                        <span>Main Stage &bull; 4K UHD</span>
                    </div>
                    <div class="text-[11px] text-purple-300 mt-0.5">Live-Stream &amp; interaktive Q&amp;A Session</div>
                </div>
            </div>

            <!-- Floating Stage Badge Right -->
            <div class="absolute -top-6 -right-4 sm:right-6 bg-[#16122e]/95 backdrop-blur-md border border-[#271f4d] rounded-xl p-4 shadow-2xl flex items-center space-x-3 text-left max-w-[260px] transform hover:-translate-y-1 transition duration-300">
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-lg text-purple-400 flex-shrink-0">
                    🎤
                </div>
                <div>
                    <div class="text-xs font-bold text-white">45 Keynote Speaker</div>
                    <div class="text-[11px] text-purple-300 mt-0.5">3 Tracks &bull; 12 Hands-on Workshops</div>
                </div>
            </div>
        </div>

        <!-- Micro-Interaction KPI Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-20 pt-12 border-t border-[#271f4d]/80 max-w-4xl mx-auto">
            <div>
                <div class="text-3xl font-black text-white font-mono">&lt; 20ms</div>
                <div class="text-xs text-purple-300 mt-1">Latenz (TTFB)</div>
            </div>
            <div>
                <div class="text-3xl font-black text-white font-mono">100/100</div>
                <div class="text-xs text-purple-300 mt-1">Google PageSpeed</div>
            </div>
            <div>
                <div class="text-3xl font-black text-white font-mono">99.99%</div>
                <div class="text-xs text-purple-300 mt-1">Garantierte Uptime</div>
            </div>
            <div>
                <div class="text-3xl font-black text-[#f43f5e] font-mono">0</div>
                <div class="text-xs text-purple-300 mt-1">Third-Party Tracker</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Highlights with Micro-Interactions -->
<section id="features" class="py-24 bg-[#080612] border-y border-[#271f4d]">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-2">Technologie</h2>
            <h3 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Entwickelt für Perfektionisten</h3>
            <p class="text-sm text-slate-400 mt-3">Keine Node.js Abhängigkeiten auf dem Server, aber volle JIT Tailwind Power.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-8 rounded-2xl bg-[#16122e] border border-[#271f4d] hover:border-purple-500/50 transition duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition">
                    ⚡
                </div>
                <h4 class="text-lg font-bold text-white mb-2">In-Browser JIT Compiler</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Extrahieren und kompilieren Sie Tailwind CSS Klassen direkt beim Speichern auf dem Server – ohne dass Node.js installiert sein muss.
                </p>
            </div>

            <div class="p-8 rounded-2xl bg-[#16122e] border border-[#271f4d] hover:border-purple-500/50 transition duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition">
                    🧩
                </div>
                <h4 class="text-lg font-bold text-white mb-2">Strukturierte JSON Blöcke</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Saubere Trennung von Layout und Daten. Keine kryptischen HTML-Shortcodes, sondern zukunftssichere JSON-Bäume.
                </p>
            </div>

            <div class="p-8 rounded-2xl bg-[#16122e] border border-[#271f4d] hover:border-purple-500/50 transition duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition">
                    🚀
                </div>
                <h4 class="text-lg font-bold text-white mb-2">1-Klick OTA Updates</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Automatische Pre-Update Backups, nahtloses Entpacken und sofortiges Cache-Purging direkt über das Admin Dashboard.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Pricing Tables (Monthly / Yearly Billing Toggle) -->
<section id="pricing" class="py-24" x-data="{ yearly: false }">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-2">Tarife &amp; Preise</h2>
            <h3 class="text-3xl sm:text-5xl font-black text-white tracking-tight mb-6">Wählen Sie den passenden Plan</h3>

            <!-- Monthly / Yearly Billing Toggle with Discount Badge -->
            <div class="inline-flex items-center space-x-3 p-1.5 rounded-2xl bg-[#16122e] border border-[#271f4d]">
                <button @click="yearly = false"
                        class="px-5 py-2 rounded-xl text-xs font-bold transition"
                        :class="!yearly ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                    Monatlich
                </button>
                <button @click="yearly = true"
                        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2"
                        :class="yearly ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                    <span>Jährlich</span>
                    <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 text-[10px] font-extrabold">Spare 20%</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
            <!-- Tier 1: Starter -->
            <div class="p-8 rounded-3xl bg-[#16122e] border border-[#271f4d] flex flex-col justify-between shadow-xl">
                <div>
                    <h4 class="text-base font-bold text-white mb-2">Starter</h4>
                    <p class="text-xs text-slate-400 mb-6">Ideal für Freelancer und persönliche Portfolio-Projekte.</p>

                    <div class="flex items-baseline space-x-1 mb-8">
                        <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '15 €' : '19 €'">19 €</span>
                        <span class="text-xs text-slate-400">/ Monat</span>
                    </div>

                    <ul class="space-y-3 text-xs text-slate-300 mb-8">
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>1 Produktive Website</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Unbegrenzter Full-Page Cache</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Cookielose DSGVO Analytik</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Community Support</span></li>
                    </ul>
                </div>

                <a href="#early-access" class="w-full py-3 rounded-xl bg-[#271f4d] hover:bg-[#342a66] text-slate-200 text-xs font-bold text-center transition block">
                    Starter wählen
                </a>
            </div>

            <!-- Tier 2: Pro (Highlighted with Glowing Border & Badge) -->
            <div class="p-8 rounded-3xl bg-gradient-to-b from-[#211a47] to-[#16122e] border-2 border-purple-500 shadow-2xl relative flex flex-col justify-between transform md:-translate-y-2">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-gradient-to-r from-violet-600 to-rose-500 text-white text-[10px] font-black uppercase tracking-wider shadow-lg">
                    Beliebteste Wahl
                </div>

                <div>
                    <h4 class="text-base font-bold text-white mb-2">Professional</h4>
                    <p class="text-xs text-slate-300 mb-6">Für wachsende Agenturen und anspruchsvolle Kunden-Websites.</p>

                    <div class="flex items-baseline space-x-1 mb-8">
                        <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '39 €' : '49 €'">49 €</span>
                        <span class="text-xs text-slate-300">/ Monat</span>
                    </div>

                    <ul class="space-y-3 text-xs text-slate-200 mb-8">
                        <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Bis zu 5 Kunden-Websites</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Agency White-Label &amp; Client-Guard</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>In-Browser Tailwind JIT Compiler</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Stripe Checkout &amp; Paywall Engine</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400 font-bold">✓</span><span>Prioritärer 24/7 E-Mail Support</span></li>
                    </ul>
                </div>

                <form method="POST" action="/api/stripe/checkout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="title" value="Heartbeat CMS Pro Plan">
                    <input type="hidden" name="amount_cents" :value="yearly ? 3900 : 4900">
                    <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white text-xs font-bold shadow-xl shadow-purple-500/30 transition transform hover:scale-105">
                        Jetzt Pro aktivieren &rarr;
                    </button>
                </form>
            </div>

            <!-- Tier 3: Enterprise -->
            <div class="p-8 rounded-3xl bg-[#16122e] border border-[#271f4d] flex flex-col justify-between shadow-xl">
                <div>
                    <h4 class="text-base font-bold text-white mb-2">Enterprise</h4>
                    <p class="text-xs text-slate-400 mb-6">Für Großagenturen mit eigener Hosting-Infrastruktur.</p>

                    <div class="flex items-baseline space-x-1 mb-8">
                        <span class="text-4xl font-black text-white font-mono" x-text="yearly ? '159 €' : '199 €'">199 €</span>
                        <span class="text-xs text-slate-400">/ Monat</span>
                    </div>

                    <ul class="space-y-3 text-xs text-slate-300 mb-8">
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Unbegrenzte Installationen</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Dedicated Release Channel &amp; SLA</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Zero-Exec Backup Cronjobs</span></li>
                        <li class="flex items-center space-x-2"><span class="text-emerald-400">✓</span><span>Persönlicher Solution Architect</span></li>
                    </ul>
                </div>

                <a href="#early-access" class="w-full py-3 rounded-xl bg-[#271f4d] hover:bg-[#342a66] text-slate-200 text-xs font-bold text-center transition block">
                    Enterprise anfragen
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Early Access / Newsletter Signup with Honeypot + CSRF -->
<section id="early-access" class="py-24 bg-[#080612] border-t border-[#271f4d]">
    <div class="max-w-xl mx-auto px-6 text-center">
        <h2 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-2">Bleiben Sie auf dem Laufenden</h2>
        <h3 class="text-3xl font-extrabold text-white tracking-tight mb-4">Holen Sie sich jetzt frühen Zugang</h3>
        <p class="text-xs sm:text-sm text-slate-400 mb-8">Tragen Sie sich ein und erhalten Sie exklusive Theme-Updates und Produkt-Releases.</p>

        <div x-data="{
            submitting: false,
            success: false,
            error: null,
            email: '',
            name: 'Newsletter Abonnent',
            _hb_hp: '',
            async submit() {
                this.submitting = true;
                this.error = null;
                try {
                    const fd = new FormData();
                    fd.append('name', this.name);
                    fd.append('email', this.email);
                    fd.append('subject', 'Startup Early Access Registrierung');
                    fd.append('message', 'Benutzer möchte sich für Early Access registrieren.');
                    fd.append('_hb_hp', this._hb_hp);
                    fd.append('csrf_token', '<?= $csrfToken ?>');

                    const res = await fetch('/api/contact', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    });
                    const d = await res.json();
                    if (d.success) this.success = true;
                    else this.error = d.error || 'Fehler beim Eintragen.';
                } catch (e) {
                    this.error = 'Netzwerkfehler.';
                } finally {
                    this.submitting = false;
                }
            }
        }">
            <div x-show="success" x-cloak class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                ✓ Vielen Dank! Wir haben Sie erfolgreich für den frühen Zugang registriert.
            </div>

            <form x-show="!success" @submit.prevent="submit" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div style="display:none !important; position: absolute; left: -9999px;">
                    <input type="text" name="_hb_hp" x-model="_hb_hp" tabindex="-1" autocomplete="off">
                </div>

                <input type="email" x-model="email" required placeholder="Geben Sie Ihre geschäftliche E-Mail ein"
                       class="flex-1 bg-[#16122e] border border-[#271f4d] focus:border-purple-500 rounded-xl px-4 py-3.5 text-xs text-white focus:outline-none transition">

                <button type="submit" :disabled="submitting"
                        class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-bold text-xs shadow-lg shadow-purple-500/20 transition disabled:opacity-50">
                    <span x-show="!submitting">Registrieren &rarr;</span>
                    <span x-show="submitting" x-cloak>Wird eingetragen...</span>
                </button>
            </form>
            <div x-show="error" x-cloak class="text-rose-400 text-xs mt-2" x-text="error"></div>
        </div>
    </div>
</section>

<?php if (!empty($contentHtml)): ?>
    <div class="<?= $containerWidth ?> mx-auto px-6 py-12">
        <?= $contentHtml ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>

