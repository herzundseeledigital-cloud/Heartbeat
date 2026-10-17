<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Content\MetaService;

include __DIR__ . '/header.php';

$metaService = MetaService::getInstance();
$propId = isset($post['id']) ? (int)$post['id'] : 0;
$meta = $propId > 0 ? $metaService->getAllPostMeta($propId) : [];

$price = (int)($meta['price'] ?? 0);
$formattedPrice = number_format($price, 0, ',', '.') . ' €';
$rooms = (float)($meta['rooms'] ?? 0);
$livingSpace = (int)($meta['living_space'] ?? 0);
$location = (string)($meta['location'] ?? 'Deutschland');
$propertyType = (string)($meta['property_type'] ?? 'Wohnung');
$status = (string)($meta['status'] ?? 'Verfügbar');
$energyClass = (string)($meta['energy_class'] ?? 'A');
$csrfToken = Csrf::token();
?>

<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs text-slate-400 mb-6">
        <a href="/" class="hover:text-emerald-400">Startseite</a>
        <span>&rsaquo;</span>
        <a href="/#properties" class="hover:text-emerald-400">Immobilien</a>
        <span>&rsaquo;</span>
        <span class="text-white"><?= $safePageTitle ?></span>
    </div>

    <!-- Title & Price Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between pb-8 border-b border-[#134e3a] gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <?= Security::e($status) ?>
                </span>
                <span class="text-xs text-slate-400">Objekt-ID: #<?= $propId ?></span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight"><?= $safePageTitle ?></h1>
            <p class="text-xs sm:text-sm text-emerald-400 font-semibold mt-1">📍 <?= Security::e($location) ?> &bull; <?= Security::e($propertyType) ?></p>
        </div>

        <div class="text-left md:text-right">
            <div class="text-xs text-slate-400 uppercase font-semibold">Kaufpreis</div>
            <div class="text-3xl sm:text-4xl font-black text-emerald-400 font-mono"><?= $formattedPrice ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Main Content & Gallery -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Hero Image Stage -->
            <div class="aspect-video bg-[#04120e] rounded-2xl border border-[#134e3a] flex items-center justify-center p-12 text-center shadow-xl">
                <div>
                    <div class="text-6xl mb-3">🏛️</div>
                    <div class="text-base font-bold text-white"><?= $safePageTitle ?></div>
                    <div class="text-xs text-slate-400 mt-1">Exposé-Fotogalerie</div>
                </div>
            </div>

            <!-- Key Facts Table -->
            <div class="p-6 rounded-2xl bg-[#0a2e23] border border-[#134e3a]">
                <h3 class="text-base font-bold text-white mb-4">Eckdaten &amp; Fakten</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Wohnfläche</span>
                        <strong class="text-white text-sm"><?= $livingSpace ?> m²</strong>
                    </div>
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Zimmeranzahl</span>
                        <strong class="text-white text-sm"><?= $rooms ?></strong>
                    </div>
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Energieeffizienz</span>
                        <strong class="text-emerald-400 text-sm font-mono">Klasse <?= Security::e($energyClass) ?></strong>
                    </div>
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Objektart</span>
                        <strong class="text-white"><?= Security::e($propertyType) ?></strong>
                    </div>
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Standort</span>
                        <strong class="text-white"><?= Security::e($location) ?></strong>
                    </div>
                    <div class="p-3 bg-[#061a14] rounded-xl border border-[#134e3a]">
                        <span class="text-slate-400 block mb-0.5">Courtage</span>
                        <strong class="text-white">Provisionsfrei für Käufer</strong>
                    </div>
                </div>
            </div>

            <!-- Detailed Description -->
            <div class="p-6 rounded-2xl bg-[#0a2e23] border border-[#134e3a] prose prose-invert max-w-none text-slate-300 text-xs sm:text-sm leading-relaxed">
                <h3 class="text-base font-bold text-white mb-3">Objektbeschreibung</h3>
                <?= $contentHtml ?? '<p>Dieses exklusive Objekt besticht durch seine hervorragende Lage, hochwertige Ausstattung und durchdachten Grundriss. Vereinbaren Sie noch heute einen Besichtigungstermin.</p>' ?>
            </div>
        </div>

        <!-- Sidebar: Property Specific Inquiry Form -->
        <div class="space-y-6">
            <div class="p-6 rounded-2xl bg-[#0a2e23] border border-[#134e3a] sticky top-28 shadow-2xl"
                 x-data="{
                     submitting: false,
                     success: false,
                     error: null,
                     name: '',
                     email: '',
                     phone: '',
                     message: 'Guten Tag, bitte senden Sie mir das vollständige Exposé zu diesem Objekt zu.',
                     _hb_hp: '',
                     async submit() {
                         this.submitting = true;
                         this.error = null;
                         try {
                             const fd = new FormData();
                             fd.append('name', this.name);
                             fd.append('email', this.email);
                             fd.append('subject', 'Exposé-Anfrage #<?= $propId ?>: <?= $safePageTitle ?>');
                             fd.append('message', this.message + (this.phone ? '\nTelefon: ' + this.phone : ''));
                             fd.append('property_id', '<?= $propId ?>');
                             fd.append('property_title', '<?= $safePageTitle ?>');
                             fd.append('_hb_hp', this._hb_hp);
                             fd.append('csrf_token', '<?= $csrfToken ?>');

                             const res = await fetch('/api/contact', {
                                 method: 'POST',
                                 headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                 body: fd
                             });
                             const d = await res.json();
                             if (d.success) this.success = true;
                             else this.error = d.error || 'Fehler beim Senden.';
                         } catch (e) {
                             this.error = 'Netzwerkfehler.';
                         } finally {
                             this.submitting = false;
                         }
                     }
                 }">

                <h3 class="text-base font-bold text-white mb-1">Exposé anfordern</h3>
                <p class="text-xs text-slate-400 mb-4">Erhalten Sie das ausführliche Exposé mit Grundrissen und Adressdaten.</p>

                <div x-show="success" x-cloak class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs text-center">
                    ✓ Vielen Dank! Das Exposé wurde an Ihre E-Mail-Adresse versendet.
                </div>

                <form x-show="!success" @submit.prevent="submit" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div style="display:none !important; position: absolute; left: -9999px;">
                        <input type="text" name="_hb_hp" x-model="_hb_hp" tabindex="-1" autocomplete="off">
                    </div>

                    <div x-show="error" x-cloak class="p-2.5 rounded-xl bg-rose-500/10 text-rose-400 text-xs" x-text="error"></div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">Ihr Name *</label>
                        <input type="text" x-model="name" required class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">Ihre E-Mail *</label>
                        <input type="email" x-model="email" required class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">Telefonnummer</label>
                        <input type="tel" x-model="phone" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">Ihre Nachricht</label>
                        <textarea x-model="message" rows="3" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none resize-none"></textarea>
                    </div>

                    <button type="submit" :disabled="submitting" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-lg shadow-emerald-500/20 transition disabled:opacity-50">
                        <span x-show="!submitting">Exposé kostenlos anfordern &rarr;</span>
                        <span x-show="submitting" x-cloak>Wird übermittelt...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

