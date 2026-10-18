<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;

include __DIR__ . '/header.php';

$csrfToken = Csrf::token();
$safeSiteTitle = Security::e($siteTitle ?? 'Heartbeat CMS');
?>

<!-- Hero Section -->
<section id="hero" class="relative overflow-hidden pt-16 pb-24 md:pt-24 md:pb-32 text-center">
    <!-- Glow Backdrop Effect -->
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[750px] h-[400px] bg-gradient-to-tr from-[#f97316]/20 via-[#ea580c]/10 to-violet-600/10 blur-[130px] rounded-full pointer-events-none"></div>

    <div class="<?= $containerWidth ?> mx-auto px-6 relative z-10">
        <!-- Badge -->
        <div class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-[#f97316]/10 border border-[#f97316]/25 text-[#f97316] text-xs font-bold mb-6 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-[#f97316] animate-pulse"></span>
            <span>Digitale Spitzenleistung &bull; Agentur Site-Factory</span>
        </div>

        <!-- Headline -->
        <h1 class="text-4xl sm:text-6xl md:text-7xl font-black text-white tracking-tight leading-[1.08] max-w-4xl mx-auto mb-6">
            Digitale Exzellenz, die Kunden begeistert.
        </h1>

        <!-- Subheadline -->
        <p class="text-base sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed mb-10">
            Wir konzipieren und entwickeln maßgeschneiderte High-End Webseiten mit kompromissloser Performance, blitzschnellen Ladezeiten und modernem Design.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-4 mb-16">
            <a href="#contact" class="px-8 py-4 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white font-bold text-sm shadow-xl shadow-[#f97316]/25 transition transform hover:scale-105">
                Kostenlose Erstberatung anfordern &rarr;
            </a>
            <a href="#features" class="px-8 py-4 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-300 hover:text-white font-semibold text-sm transition">
                Vorteile entdecken &darr;
            </a>
        </div>

        <!-- Showcase Visual Frame with Floating Badges (MegaOne Inspiration) -->
        <div class="relative max-w-5xl mx-auto group">
            <!-- Ambient Glow behind frame -->
            <div class="absolute -inset-1 bg-gradient-to-r from-[#f97316]/30 via-purple-500/20 to-sky-500/20 rounded-2xl blur-xl opacity-70 group-hover:opacity-100 transition duration-700"></div>

            <!-- Device/Browser Chrome -->
            <div class="relative bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden">
                <!-- Browser Header Bar -->
                <div class="bg-[#0b1120] px-4 py-3 border-b border-[#1e293b] flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                    </div>
                    <div class="bg-[#1e293b]/70 px-6 py-1 rounded-md text-[11px] font-mono text-slate-400 flex items-center space-x-2">
                        <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                        <span>https://heartbeat-studio.de</span>
                    </div>
                    <div class="text-[10px] text-slate-500 font-mono hidden sm:block">LIVE PREVIEW</div>
                </div>

                <!-- Main Hero Image -->
                <div class="relative overflow-hidden bg-slate-950 aspect-[16/9]">
                    <img src="/assets/img/hero-onepager.jpg" alt="Heartbeat Studio Digital Showcase" class="w-full h-full object-cover object-center transform group-hover:scale-[1.02] transition duration-700 ease-out" loading="eager">
                    <!-- Subtle Dark Gradient at bottom of image -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0f172a] via-transparent to-transparent opacity-40"></div>
                </div>
            </div>

            <!-- Floating Trust Badge Left -->
            <div class="absolute -bottom-6 -left-4 sm:left-6 bg-[#0f172a]/95 backdrop-blur-md border border-[#1e293b] rounded-xl p-4 shadow-2xl flex items-center space-x-3 text-left max-w-[260px] transform hover:-translate-y-1 transition duration-300">
                <div class="w-10 h-10 rounded-lg bg-[#f97316]/10 border border-[#f97316]/20 flex items-center justify-center text-lg text-[#f97316] flex-shrink-0">
                    ★
                </div>
                <div>
                    <div class="flex items-center space-x-1 text-amber-400 text-xs">
                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                        <span class="text-white font-bold ml-1">4.9 / 5</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">120+ Agenturprojekte erfolgreich realisiert</div>
                </div>
            </div>

            <!-- Floating Trust Badge Right -->
            <div class="absolute -top-6 -right-4 sm:right-6 bg-[#0f172a]/95 backdrop-blur-md border border-[#1e293b] rounded-xl p-4 shadow-2xl flex items-center space-x-3 text-left max-w-[250px] transform hover:-translate-y-1 transition duration-300">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-lg text-emerald-400 flex-shrink-0">
                    ⚡
                </div>
                <div>
                    <div class="text-xs font-bold text-white flex items-center space-x-1.5">
                        <span>100/100 PageSpeed</span>
                        <span class="px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-400 text-[9px]">A+</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">&lt; 20ms Latenz &bull; 0 Tracker</div>
                </div>
            </div>
        </div>

        <!-- Trust KPI Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-20 pt-12 border-t border-[#1e293b]/60 max-w-4xl mx-auto">
            <div>
                <div class="text-2xl sm:text-3xl font-black text-white font-mono">&lt; 20ms</div>
                <div class="text-xs text-slate-400 mt-1">Ladezeit (TTFB)</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-[#f97316] font-mono">100%</div>
                <div class="text-xs text-slate-400 mt-1">DSGVO-Konform</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-white font-mono">0</div>
                <div class="text-xs text-slate-400 mt-1">Externe Abhängigkeiten</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono">24/7</div>
                <div class="text-xs text-slate-400 mt-1">Zuverlässigkeit</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid Section -->
<section id="features" class="py-20 bg-[#0f172a]/50 border-y border-[#1e293b]">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-xs font-bold uppercase tracking-widest text-[#f97316] mb-2">Warum wir</h2>
            <h3 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Ihre Vorteile auf einen Blick</h3>
            <p class="text-sm text-slate-400 mt-3">Alles, was eine erfolgreiche Webpräsenz benötigt – ohne unnötigen Ballast.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="p-8 rounded-2xl bg-[#090d16] border border-[#1e293b] hover:border-[#f97316]/40 transition duration-300 group shadow-lg">
                <div class="w-12 h-12 rounded-xl bg-[#f97316]/10 text-[#f97316] border border-[#f97316]/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition transform">
                    ⚡
                </div>
                <h4 class="text-lg font-bold text-white mb-2">Maximale Geschwindigkeit</h4>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Dank serverseitigem Multi-Tier Caching liefern wir Seiten in Rekordzeit aus. Bessere Google-Rankings und höhere Konversionen garantiert.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="p-8 rounded-2xl bg-[#090d16] border border-[#1e293b] hover:border-[#f97316]/40 transition duration-300 group shadow-lg">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition transform">
                    🛡️
                </div>
                <h4 class="text-lg font-bold text-white mb-2">Sicherheit &amp; DSGVO</h4>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    100% prepared SQL, CSRF-Schutz, lokale Google Fonts und cookielose Analytik. Keine Abmahnrisiken, keine US-Drittanbieteraufrufe.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="p-8 rounded-2xl bg-[#090d16] border border-[#1e293b] hover:border-[#f97316]/40 transition duration-300 group shadow-lg">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition transform">
                    📱
                </div>
                <h4 class="text-lg font-bold text-white mb-2">Modernes Responsive UI</h4>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Perfekt optimiert für Smartphone, Tablet und Desktop. Flüssige Animationen und gestochen scharfe Vektorgrafiken.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Services / Workflow Section -->
<section id="services" class="py-20">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-xs font-bold uppercase tracking-widest text-[#f97316] mb-2">Unser Prozess</h2>
            <h3 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">In 4 Schritten zum Erfolg</h3>
            <p class="text-sm text-slate-400 mt-3">Ein klarer, transparenter Ablauf garantiert reibungslose Ergebnisse termingerecht.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] relative">
                <div class="text-3xl font-black text-[#f97316]/30 font-mono mb-2">01</div>
                <h4 class="text-base font-bold text-white mb-1.5">Analyse &amp; Konzept</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Wir definieren Ihre Zielgruppe, Ziele und Architektur für maximalen Erfolg.</p>
            </div>

            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] relative">
                <div class="text-3xl font-black text-[#f97316]/30 font-mono mb-2">02</div>
                <h4 class="text-base font-bold text-white mb-1.5">UI/UX Design</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Individuelle Design-Entwürfe im Dark- oder Light-Look passend zu Ihrer Marke.</p>
            </div>

            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] relative">
                <div class="text-3xl font-black text-[#f97316]/30 font-mono mb-2">03</div>
                <h4 class="text-base font-bold text-white mb-1.5">Entwicklung</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Sauberer, blitzschneller Code mit barrierefreiem HTML5 und Tailwind CSS.</p>
            </div>

            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] relative">
                <div class="text-3xl font-black text-[#f97316]/30 font-mono mb-2">04</div>
                <h4 class="text-base font-bold text-white mb-1.5">Livegang &amp; Support</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Reibungsloser Rollout auf Ihrem Server, inklusive Schulung und Wartung.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section id="testimonials" class="py-20 bg-[#0f172a]/40 border-y border-[#1e293b]">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-xs font-bold uppercase tracking-widest text-[#f97316] mb-2">Kundenstimmen</h2>
            <h3 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Erfolgsgeschichten unserer Partner</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="p-8 rounded-2xl bg-[#090d16] border border-[#1e293b] shadow-xl">
                <div class="text-amber-400 text-sm tracking-widest mb-3">★★★★★</div>
                <p class="text-sm text-slate-300 italic mb-6 leading-relaxed">
                    &bdquo;Die Ladezeit unserer neuen Seite ist atemberaubend! Unsere Conversion-Rate hat sich im ersten Monat direkt verdoppelt.&ldquo;
                </p>
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-[#f97316]/20 text-[#f97316] font-bold flex items-center justify-center text-xs">
                        MH
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white">Michael Hoffmann</div>
                        <div class="text-xs text-slate-500">Geschäftsführer, AlphaTech Media</div>
                    </div>
                </div>
            </div>

            <div class="p-8 rounded-2xl bg-[#090d16] border border-[#1e293b] shadow-xl">
                <div class="text-amber-400 text-sm tracking-widest mb-3">★★★★★</div>
                <p class="text-sm text-slate-300 italic mb-6 leading-relaxed">
                    &bdquo;Endlich keine überladenen Plugins mehr. Alles funktioniert aus einem Guss, DSGVO-konform und extrem intuitiv zu pflegen.&ldquo;
                </p>
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 text-blue-400 font-bold flex items-center justify-center text-xs">
                        SK
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white">Sarah König</div>
                        <div class="text-xs text-slate-500">Marketingleitung, Horizon Dynamics</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Accordion Section -->
<section id="faq" class="py-20" x-data="{ activeFaq: 1 }">
    <div class="max-w-3xl mx-auto px-6">
        <div class="text-center mb-12">
            <h2 class="text-xs font-bold uppercase tracking-widest text-[#f97316] mb-2">FAQ</h2>
            <h3 class="text-3xl font-extrabold text-white tracking-tight">Häufig gestellte Fragen</h3>
        </div>

        <div class="space-y-4">
            <!-- FAQ 1 -->
            <div class="border border-[#1e293b] rounded-2xl bg-[#0f172a] overflow-hidden transition">
                <button @click="activeFaq = (activeFaq === 1 ? null : 1)" class="w-full p-5 text-left flex items-center justify-between text-sm font-bold text-white focus:outline-none">
                    <span>Wie schnell kann ein One-Pager realisiert werden?</span>
                    <span class="text-[#f97316] text-lg" x-text="activeFaq === 1 ? '−' : '+'">+</span>
                </button>
                <div x-show="activeFaq === 1" x-collapse x-cloak class="px-5 pb-5 text-xs text-slate-400 leading-relaxed border-t border-[#1e293b]/50 pt-3">
                    In der Regel ist ein individueller One-Pager innerhalb von 5 bis 10 Werktagen vollständig konzipiert, gestaltet und auf Ihrem Hosting schlüsselfertig installiert.
                </div>
            </div>

            <!-- FAQ 2 -->
            <div class="border border-[#1e293b] rounded-2xl bg-[#0f172a] overflow-hidden transition">
                <button @click="activeFaq = (activeFaq === 2 ? null : 2)" class="w-full p-5 text-left flex items-center justify-between text-sm font-bold text-white focus:outline-none">
                    <span>Funktioniert das Theme auf normalem Webhosting?</span>
                    <span class="text-[#f97316] text-lg" x-text="activeFaq === 2 ? '−' : '+'">+</span>
                </button>
                <div x-show="activeFaq === 2" x-collapse x-cloak class="px-5 pb-5 text-xs text-slate-400 leading-relaxed border-t border-[#1e293b]/50 pt-3">
                    Ja! Heartbeat CMS benötigt weder Node.js noch SSH auf dem Server und läuft reibungslos auf jedem Standard-Tarif von Webgo, IONOS, HostEurope oder Mittwald.
                </div>
            </div>

            <!-- FAQ 3 -->
            <div class="border border-[#1e293b] rounded-2xl bg-[#0f172a] overflow-hidden transition">
                <button @click="activeFaq = (activeFaq === 3 ? null : 3)" class="w-full p-5 text-left flex items-center justify-between text-sm font-bold text-white focus:outline-none">
                    <span>Sind Cookies oder ein Consent-Banner erforderlich?</span>
                    <span class="text-[#f97316] text-lg" x-text="activeFaq === 3 ? '−' : '+'">+</span>
                </button>
                <div x-show="activeFaq === 3" x-collapse x-cloak class="px-5 pb-5 text-xs text-slate-400 leading-relaxed border-t border-[#1e293b]/50 pt-3">
                    Nein. Da alle Schriften lokal gehostet werden und unsere integrierte Besucherstatistik 100% cookielos arbeitet, benötigen Sie kein störendes Cookie-Banner.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section with Honeypot + CSRF Protection -->
<section id="contact" class="py-24 bg-gradient-to-b from-[#0f172a] to-[#090d16] border-t border-[#1e293b]">
    <div class="max-w-2xl mx-auto px-6">
        <div class="text-center mb-12">
            <h2 class="text-xs font-bold uppercase tracking-widest text-[#f97316] mb-2">Kontakt</h2>
            <h3 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Lassen Sie uns starten</h3>
            <p class="text-sm text-slate-400 mt-2">Schreiben Sie uns eine Nachricht – wir antworten innerhalb von 24 Stunden.</p>
        </div>

        <!-- Alpine.js Reactive Contact Form -->
        <div x-data="{
            submitting: false,
            success: false,
            error: null,
            form: {
                name: '',
                email: '',
                subject: 'Website-Projektanfrage',
                message: '',
                _hb_hp: '', // Honeypot Field for Spam Bots
                csrf_token: '<?= $csrfToken ?>'
            },
            async submitContact() {
                this.submitting = true;
                this.error = null;
                try {
                    const formData = new FormData();
                    formData.append('name', this.form.name);
                    formData.append('email', this.form.email);
                    formData.append('subject', this.form.subject);
                    formData.append('message', this.form.message);
                    formData.append('_hb_hp', this.form._hb_hp);
                    formData.append('csrf_token', this.form.csrf_token);

                    const res = await fetch('/api/contact', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.success = true;
                    } else {
                        this.error = data.error || 'Fehler beim Senden der Nachricht.';
                    }
                } catch (e) {
                    this.error = 'Netzwerkfehler. Bitte versuchen Sie es später erneut.';
                } finally {
                    this.submitting = false;
                }
            }
        }" class="bg-[#090d16] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-2xl relative">

            <!-- Success State Message -->
            <div x-show="success" x-cloak class="text-center py-8">
                <div class="w-14 h-14 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mx-auto flex items-center justify-center text-2xl font-bold mb-4">
                    ✓
                </div>
                <h4 class="text-xl font-bold text-white mb-2">Nachricht erfolgreich gesendet!</h4>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-md mx-auto mb-6">
                    Vielen Dank für Ihre Anfrage. Wir haben Ihre Nachricht erhalten und melden uns schnellstmöglich bei Ihnen.
                </p>
                <button @click="success = false; form.message = ''" class="px-5 py-2.5 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-200 text-xs font-semibold transition">
                    Weitere Nachricht senden
                </button>
            </div>

            <!-- Form -->
            <form x-show="!success" @submit.prevent="submitContact" method="POST" action="/contact" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <!-- Anti-Spam Honeypot Field (Hidden for real users, filled by bots) -->
                <div style="display:none !important; position: absolute; left: -9999px;">
                    <label for="_hb_hp">Website (Bitte leer lassen):</label>
                    <input type="text" id="_hb_hp" name="_hb_hp" x-model="form._hb_hp" tabindex="-1" autocomplete="off">
                </div>

                <!-- Error Alert -->
                <div x-show="error" x-cloak class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center space-x-2">
                    <span>⚠️</span>
                    <span x-text="error"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Name *</label>
                        <input type="text" name="name" x-model="form.name" required placeholder="Ihr vollständiger Name" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-4 py-3 text-xs sm:text-sm text-white focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">E-Mail *</label>
                        <input type="email" name="email" x-model="form.email" required placeholder="name@beispiel.de" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-4 py-3 text-xs sm:text-sm text-white focus:outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Projektart / Betreff</label>
                    <select name="subject" x-model="form.subject" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-4 py-3 text-xs sm:text-sm text-white focus:outline-none transition">
                        <option value="Neues Webprojekt">Neues Webprojekt</option>
                        <option value="Redesign bestehender Website">Redesign bestehender Website</option>
                        <option value="Performance & SEO Optimierung">Performance &amp; SEO Optimierung</option>
                        <option value="Allgemeine Anfrage">Allgemeine Anfrage</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Ihre Nachricht *</label>
                    <textarea name="message" x-model="form.message" required rows="4" placeholder="Erzählen Sie uns kurz von Ihrem Vorhaben..." class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-4 py-3 text-xs sm:text-sm text-white focus:outline-none transition resize-none"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" :disabled="submitting" class="w-full py-4 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white font-bold text-sm shadow-xl shadow-[#f97316]/25 transition flex items-center justify-center space-x-2 disabled:opacity-50">
                        <span x-show="!submitting">Nachricht jetzt absenden &rarr;</span>
                        <span x-show="submitting" x-cloak>Wird gesendet...</span>
                    </button>
                    <div class="text-[11px] text-slate-500 text-center mt-3">
                        Ihre Daten werden verschlüsselt übertragen und vertraulich behandelt.
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if (!empty($contentHtml)): ?>
    <!-- Custom Blocks Injected via Block Editor -->
    <div class="<?= $containerWidth ?> mx-auto px-6 py-12">
        <?= $contentHtml ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>

