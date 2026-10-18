<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;

include __DIR__ . '/header.php';

$csrfToken = Csrf::token();
$safeSiteTitle = Security::e($siteTitle ?? 'Corporate Enterprise');

// Query published Team Members from CPT
$postsService = PostService::getInstance();
$metaService = MetaService::getInstance();

$dbTeam = $postsService->query([
    'post_type' => 'team_member',
    'status'    => 'publish',
    'limit'     => 6,
]);

// Fallback demo team if database has no items yet
$teamMembers = [];
if (!empty($dbTeam)) {
    foreach ($dbTeam as $member) {
        $meta = $metaService->getAllPostMeta((int)$member['id']);
        $teamMembers[] = [
            'id'         => $member['id'],
            'title'      => $member['title'],
            'slug'       => $member['slug'],
            'position'   => $meta['position'] ?? 'Consultant',
            'department' => $meta['department'] ?? 'Beratung',
            'email'      => $meta['email'] ?? 'team@agentur.de',
            'linkedin'   => $meta['linkedin'] ?? '#',
            'avatar'     => $member['thumbnail_url'] ?? '',
        ];
    }
} else {
    $teamMembers = [
        [
            'id'         => 1,
            'title'      => 'Dr. Alexander Becker',
            'slug'       => 'alexander-becker',
            'position'   => 'Managing Director & CEO',
            'department' => 'Geschäftsführung',
            'email'      => 'becker@agentur.de',
            'linkedin'   => 'https://linkedin.com',
            'avatar'     => '',
        ],
        [
            'id'         => 2,
            'title'      => 'Sarah Lindemann',
            'slug'       => 'sarah-lindemann',
            'position'   => 'Head of Cloud Architecture',
            'department' => 'Software Engineering',
            'email'      => 'lindemann@agentur.de',
            'linkedin'   => 'https://linkedin.com',
            'avatar'     => '',
        ],
        [
            'id'         => 3,
            'title'      => 'Marco Rossi',
            'slug'       => 'marco-rossi',
            'position'   => 'Principal UX Strategist',
            'department' => 'UI/UX Design',
            'email'      => 'rossi@agentur.de',
            'linkedin'   => 'https://linkedin.com',
            'avatar'     => '',
        ],
    ];
}

// Query published Case Studies from CPT
$dbCaseStudies = $postsService->query([
    'post_type' => 'case_study',
    'status'    => 'publish',
    'limit'     => 3,
]);

$caseStudies = [];
if (!empty($dbCaseStudies)) {
    foreach ($dbCaseStudies as $cs) {
        $meta = $metaService->getAllPostMeta((int)$cs['id']);
        $caseStudies[] = [
            'id'          => $cs['id'],
            'title'       => $cs['title'],
            'slug'        => $cs['slug'],
            'client_name' => $meta['client_name'] ?? 'Enterprise Kunde',
            'project_year'=> $meta['project_year'] ?? '2026',
            'services'    => $meta['services_provided'] ?? 'Full-Stack Replatforming',
            'metrics'     => '+340% Performance',
        ];
    }
} else {
    $caseStudies = [
        [
            'id'          => 1,
            'title'       => 'Fintech Banking Plattform',
            'slug'        => 'fintech-banking',
            'client_name' => 'NextGen Capital Group',
            'project_year'=> '2026',
            'services'    => 'Cloud Migration & Headless Portal',
            'metrics'     => '+340% Performance & ISO 27001',
        ],
        [
            'id'          => 2,
            'title'       => 'Globales Logistikportal',
            'slug'        => 'global-logistics',
            'client_name' => 'EuroTrans Logistics SE',
            'project_year'=> '2025',
            'services'    => 'Echtzeit-Tracking & API Integration',
            'metrics'     => '99.99% Uptime & 10M+ Requests/Tag',
        ],
        [
            'id'          => 3,
            'title'       => 'HealthTech Telemedizin Suite',
            'slug'        => 'healthtech-suite',
            'client_name' => 'MediConnect Deutschland',
            'project_year'=> '2026',
            'services'    => 'DSGVO-konforme Patientenakte',
            'metrics'     => '100% DSGVO & 0 Third-Party Calls',
        ],
    ];
}
?>

<!-- Hero Section -->
<section class="py-16 md:py-24 relative overflow-hidden bg-gradient-to-b from-[#070b14] via-[#090e1c] to-[#0a0f1d] border-b border-[#1e293b]">
    <!-- Ambient Radial Glow -->
    <div class="absolute top-1/4 left-1/3 w-[600px] h-[350px] bg-blue-600/10 blur-[130px] rounded-full pointer-events-none"></div>

    <div class="<?= $containerWidth ?> mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- Left Column: Copy & Actions -->
            <div class="lg:col-span-7">
                <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold mb-6">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <span>Enterprise B2B Digitalberatung &bull; DACH</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.08] mb-6">
                    Skalierbare Software für digitale Marktführer.
                </h1>

                <p class="text-base sm:text-lg text-slate-300 leading-relaxed mb-8 max-w-xl">
                    Wir entwickeln hochperformante Unternehmensportale, Headless-Architekturen und Webanwendungen mit kompromisslosem Sicherheitsanspruch nach ISO 27001.
                </p>

                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-4 mb-10">
                    <a href="#contact" class="px-7 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-500/25 transition transform hover:scale-105">
                        Strategiegespräch vereinbaren &rarr;
                    </a>
                    <a href="#services" class="px-7 py-4 rounded-xl bg-[#1e293b] hover:bg-[#334155] text-slate-200 font-semibold text-sm transition">
                        Leistungsbereiche &darr;
                    </a>
                </div>

                <!-- Rating & Social Proof -->
                <div class="flex items-center space-x-4 pt-6 border-t border-[#1e293b]">
                    <div class="flex text-amber-400 text-sm">
                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                    </div>
                    <div class="text-xs text-slate-300">
                        <span class="font-bold text-white">4.9 / 5.0</span>
                        <span class="text-slate-400 ml-1">&bull; Verifizierte Kundenzufriedenheit (140+ B2B Unternehmen)</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Visual Frame with Floating Badges (Insuren Inspiration) -->
            <div class="lg:col-span-5 relative group">
                <!-- Background Accent Glow -->
                <div class="absolute -inset-1 bg-gradient-to-tr from-blue-600/30 via-sky-500/20 to-indigo-500/20 rounded-2xl blur-xl opacity-75 group-hover:opacity-100 transition duration-500"></div>

                <!-- Main Image Card -->
                <div class="relative bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden aspect-[4/3] sm:aspect-[16/10]">
                    <img src="/assets/img/hero-corporate.jpg" alt="Corporate Headquarters and Consulting" class="w-full h-full object-cover object-center transform group-hover:scale-[1.02] transition duration-700 ease-out" loading="eager">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f1d]/80 via-transparent to-transparent"></div>
                </div>

                <!-- Floating Achievement Card Top Left -->
                <div class="absolute -top-6 -left-4 sm:-left-6 bg-[#0f172a]/95 backdrop-blur-md border border-[#1e293b] rounded-xl p-3.5 shadow-2xl flex items-center space-x-3 text-left max-w-[240px] transform hover:-translate-y-1 transition duration-300">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 font-bold flex-shrink-0">
                        🛡️
                    </div>
                    <div>
                        <div class="text-xs font-bold text-white">99.98% Uptime</div>
                        <div class="text-[10px] text-slate-400">Enterprise SLA &amp; Disaster Recovery</div>
                    </div>
                </div>

                <!-- Floating Achievement Card Bottom Right -->
                <div class="absolute -bottom-6 -right-4 sm:-right-6 bg-[#0f172a]/95 backdrop-blur-md border border-[#1e293b] rounded-xl p-3.5 shadow-2xl flex items-center space-x-3 text-left max-w-[240px] transform hover:-translate-y-1 transition duration-300">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold flex-shrink-0">
                        📈
                    </div>
                    <div>
                        <div class="text-xs font-bold text-white">45 Mio. €</div>
                        <div class="text-[10px] text-slate-400">Verarbeitetes Transaktionsvolumen</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Trust Ribbon Underneath -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 pt-12 border-t border-[#1e293b] text-center">
            <div>
                <div class="text-2xl sm:text-3xl font-black text-white font-mono">15+</div>
                <div class="text-xs text-slate-400 mt-1">Jahre B2B Markterfahrung</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-blue-400 font-mono">100%</div>
                <div class="text-xs text-slate-400 mt-1">Inhouse DACH Entwicklung</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-white font-mono">ISO 27001</div>
                <div class="text-xs text-slate-400 mt-1">Zertifizierte Sicherheit</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono">0</div>
                <div class="text-xs text-slate-400 mt-1">Drittanbieter-Tracker</div>
            </div>
        </div>
    </div>
</section>

<!-- Corporate Services Grid -->
<section id="services" class="py-20">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-widest text-blue-400 mb-2">Kernkompetenzen</h2>
                <h3 class="text-3xl font-extrabold text-white tracking-tight">Unsere Leistungsbereiche</h3>
            </div>
            <p class="text-xs text-slate-400 max-w-md">End-to-End Digitalisierung von der Anforderungsanalyse bis zum hochverfügbaren Betrieb.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-8 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition group">
                <div class="text-2xl mb-4">🏛️</div>
                <h4 class="text-lg font-bold text-white mb-2">Enterprise Web-Portale</h4>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Maßgeschneiderte CMS- und Portal-Lösungen mit rollenbasierter Rechteverwaltung, Single-Sign-On und flexibler CPT-Inhaltsarchitektur.
                </p>
                <span class="text-xs font-semibold text-blue-400 group-hover:underline">Mehr erfahren &rarr;</span>
            </div>

            <div class="p-8 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition group">
                <div class="text-2xl mb-4">⚡</div>
                <h4 class="text-lg font-bold text-white mb-2">High-Performance APIs</h4>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    REST- und GraphQL-Schnittstellen mit extrem geringer Latenz (&lt;20ms), Multi-Tier Caching und strikter Datensparsamkeit.
                </p>
                <span class="text-xs font-semibold text-blue-400 group-hover:underline">Mehr erfahren &rarr;</span>
            </div>

            <div class="p-8 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition group">
                <div class="text-2xl mb-4">🔒</div>
                <h4 class="text-lg font-bold text-white mb-2">Compliance &amp; IT-Sicherheit</h4>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    100% DSGVO-konforme In-House Datenverarbeitung, Zero-External Requests, Content-Security-Policies und OWASP Top 10 Härtung.
                </p>
                <span class="text-xs font-semibold text-blue-400 group-hover:underline">Mehr erfahren &rarr;</span>
            </div>
        </div>
    </div>
</section>

<!-- CPT-Powered Team Section -->
<section id="team" class="py-20 bg-[#070b14] border-y border-[#1e293b]">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-widest text-blue-400 mb-2">Experten-Team</h2>
                <h3 class="text-3xl font-extrabold text-white tracking-tight">Führungskräfte &amp; Spezialisten</h3>
            </div>
            <a href="/team" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition flex items-center space-x-1">
                <span>Alle Ansprechpartner anzeigen</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($teamMembers as $tm): ?>
                <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition">
                    <div class="w-16 h-16 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xl mb-4">
                        <?= mb_substr((string)$tm['title'], 0, 1) ?>
                    </div>
                    <div class="text-base font-bold text-white"><?= Security::e((string)$tm['title']) ?></div>
                    <div class="text-xs text-blue-400 font-medium mt-0.5"><?= Security::e((string)$tm['position']) ?></div>
                    <div class="inline-block mt-2 px-2.5 py-0.5 rounded-full bg-slate-800 text-[10px] text-slate-400">
                        <?= Security::e((string)$tm['department']) ?>
                    </div>

                    <div class="mt-6 pt-4 border-t border-[#1e293b] flex items-center justify-between text-xs">
                        <a href="mailto:<?= Security::e((string)$tm['email']) ?>" class="text-slate-400 hover:text-white transition flex items-center space-x-1">
                            <span>✉</span>
                            <span>E-Mail senden</span>
                        </a>
                        <?php if (!empty($tm['linkedin'])): ?>
                            <a href="<?= Security::e((string)$tm['linkedin']) ?>" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300 font-semibold">LinkedIn</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CPT-Powered Case Studies Section -->
<section id="case-studies" class="py-20">
    <div class="<?= $containerWidth ?> mx-auto px-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-widest text-blue-400 mb-2">Erfolgsgeschichten</h2>
                <h3 class="text-3xl font-extrabold text-white tracking-tight">Ausgewählte Fallstudien</h3>
            </div>
            <a href="/referenzen" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition">Alle Referenzen &rarr;</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($caseStudies as $cs): ?>
                <div class="p-8 rounded-2xl bg-[#0f172a] border border-[#1e293b] flex flex-col justify-between hover:border-slate-700 transition">
                    <div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 mb-4 font-mono">
                            <span><?= Security::e((string)$cs['client_name']) ?></span>
                            <span><?= Security::e((string)$cs['project_year']) ?></span>
                        </div>
                        <h4 class="text-lg font-bold text-white mb-2"><?= Security::e((string)$cs['title']) ?></h4>
                        <p class="text-xs text-slate-400 leading-relaxed mb-6"><?= Security::e((string)$cs['services']) ?></p>
                    </div>

                    <div class="pt-4 border-t border-[#1e293b] flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-400 font-mono"><?= Security::e((string)$cs['metrics']) ?></span>
                        <a href="/referenzen" class="text-xs text-blue-400 hover:text-blue-300 font-semibold">Details &rarr;</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Contact & Inquiry Section -->
<section id="contact" class="py-24 bg-[#070b14] border-t border-[#1e293b]">
    <div class="max-w-2xl mx-auto px-6">
        <div class="text-center mb-12">
            <h2 class="text-xs font-bold uppercase tracking-widest text-blue-400 mb-2">Kontakt &amp; Beratung</h2>
            <h3 class="text-3xl font-extrabold text-white tracking-tight">Sprechen Sie mit unseren Senior Architects</h3>
        </div>

        <!-- Contact Form -->
        <div x-data="{
            submitting: false,
            success: false,
            error: null,
            form: {
                name: '',
                email: '',
                subject: 'Corporate Anfrage',
                message: '',
                _hb_hp: '',
                csrf_token: '<?= $csrfToken ?>'
            },
            async submit() {
                this.submitting = true;
                this.error = null;
                try {
                    const fd = new FormData();
                    for (const k in this.form) fd.append(k, this.form[k]);
                    const res = await fetch('/api/contact', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    });
                    const d = await res.json();
                    if (d.success) this.success = true;
                    else this.error = d.error || 'Fehler beim Senden.';
                } catch (e) {
                    this.error = 'Netzwerkfehler. Bitte später erneut versuchen.';
                } finally {
                    this.submitting = false;
                }
            }
        }" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-8 shadow-2xl">

            <div x-show="success" x-cloak class="text-center py-8">
                <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-400 mx-auto flex items-center justify-center text-xl mb-3">✓</div>
                <h4 class="text-lg font-bold text-white mb-1">Vielen Dank für Ihre Anfrage</h4>
                <p class="text-xs text-slate-400">Unser Team wird sich innerhalb weniger Stunden mit Ihnen in Verbindung setzen.</p>
            </div>

            <form x-show="!success" @submit.prevent="submit" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div style="display:none !important; position: absolute; left: -9999px;">
                    <input type="text" name="_hb_hp" x-model="form._hb_hp" tabindex="-1" autocomplete="off">
                </div>

                <div x-show="error" x-cloak class="p-3 rounded-xl bg-rose-500/10 text-rose-400 text-xs" x-text="error"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Ihr Name *</label>
                        <input type="text" x-model="form.name" required class="w-full bg-[#0a0f1d] border border-[#1e293b] focus:border-blue-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Geschäftliche E-Mail *</label>
                        <input type="email" x-model="form.email" required class="w-full bg-[#0a0f1d] border border-[#1e293b] focus:border-blue-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Projektumfang / Betreff</label>
                    <input type="text" x-model="form.subject" class="w-full bg-[#0a0f1d] border border-[#1e293b] focus:border-blue-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Projektanforderungen / Nachricht *</label>
                    <textarea x-model="form.message" required rows="4" class="w-full bg-[#0a0f1d] border border-[#1e293b] focus:border-blue-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none resize-none"></textarea>
                </div>

                <button type="submit" :disabled="submitting" class="w-full py-3.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-lg shadow-blue-500/25 transition disabled:opacity-50">
                    <span x-show="!submitting">Anfrage unverbindlich übermitteln &rarr;</span>
                    <span x-show="submitting" x-cloak>Wird übermittelt...</span>
                </button>
            </form>
        </div>
    </div>
</section>

<?php if (!empty($contentHtml)): ?>
    <div class="<?= $containerWidth ?> mx-auto px-6 py-12">
        <?= $contentHtml ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>

