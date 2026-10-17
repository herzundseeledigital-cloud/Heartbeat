<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;

include __DIR__ . '/header.php';

$csrfToken = Csrf::token();
$safeSiteTitle = Security::e($siteTitle ?? 'Immobilien &amp; Residenzen');

// Query published Properties from CPT
$postsService = PostService::getInstance();
$metaService = MetaService::getInstance();

$dbProperties = $postsService->query([
    'post_type' => 'property',
    'status'    => 'publish',
    'limit'     => 50,
]);

$properties = [];
if (!empty($dbProperties)) {
    foreach ($dbProperties as $p) {
        $meta = $metaService->getAllPostMeta((int)$p['id']);
        $properties[] = [
            'id'            => (int)$p['id'],
            'title'         => (string)$p['title'],
            'slug'          => (string)$p['slug'],
            'price'         => (int)($meta['price'] ?? 0),
            'rooms'         => (float)($meta['rooms'] ?? 0),
            'living_space'  => (int)($meta['living_space'] ?? 0),
            'location'      => (string)($meta['location'] ?? 'Deutschland'),
            'property_type' => (string)($meta['property_type'] ?? 'Wohnung'),
            'status'        => (string)($meta['status'] ?? 'Verfügbar'),
            'energy_class'  => (string)($meta['energy_class'] ?? 'A'),
            'image'         => !empty($p['thumbnail_url']) ? $p['thumbnail_url'] : '/assets/img/placeholder.webp',
            'gallery'       => [
                !empty($p['thumbnail_url']) ? $p['thumbnail_url'] : '/assets/img/placeholder.webp',
            ],
        ];
    }
} else {
    // Realistic fallback luxury property portfolio
    $properties = [
        [
            'id'            => 101,
            'title'         => 'Moderne Penthouse-Wohnung mit Spreeblick',
            'slug'          => 'penthouse-spreeblick',
            'price'         => 1250000,
            'rooms'         => 4.5,
            'living_space'  => 185,
            'location'      => 'Berlin-Mitte',
            'property_type' => 'Penthouse',
            'status'        => 'Verfügbar',
            'energy_class'  => 'A+',
            'image'         => '',
            'gallery'       => ['Wohnsalon mit Panoramafenstern', 'Master-Schlafzimmer mit En-Suite Bad', 'Dachterrasse mit Spreeblick'],
        ],
        [
            'id'            => 102,
            'title'         => 'Exklusive Bauhaus-Villa im Grünen',
            'slug'          => 'bauhaus-villa-gruenwald',
            'price'         => 2850000,
            'rooms'         => 6.0,
            'living_space'  => 340,
            'location'      => 'München-Grünwald',
            'property_type' => 'Villa',
            'status'        => 'Verfügbar',
            'energy_class'  => 'A',
            'image'         => '',
            'gallery'       => ['Architekten-Fassade mit Pool', 'Großzügiger Wohn- & Essbereich', 'Wellnessbereich im Untergeschoss'],
        ],
        [
            'id'            => 103,
            'title'         => 'Helle 3-Zimmer Altbauwohnung mit Stuck',
            'slug'          => 'altbauwohnung-eppendorf',
            'price'         => 680000,
            'rooms'         => 3.0,
            'living_space'  => 92,
            'location'      => 'Hamburg-Eppendorf',
            'property_type' => 'Wohnung',
            'status'        => 'Verfügbar',
            'energy_class'  => 'B',
            'image'         => '',
            'gallery'       => ['Hohe Decken mit historischem Stuck', 'Designerküche mit Balkonzugang'],
        ],
        [
            'id'            => 104,
            'title'         => 'Luxuriöses Designer-Loft im Industriedenkmal',
            'slug'          => 'designer-loft-westend',
            'price'         => 950000,
            'rooms'         => 3.5,
            'living_space'  => 140,
            'location'      => 'Frankfurt-Westend',
            'property_type' => 'Wohnung',
            'status'        => 'Reserviert',
            'energy_class'  => 'A',
            'image'         => '',
            'gallery'       => ['Offene Industrie-Architektur', 'Edles Fischgrät-Parkett'],
        ],
        [
            'id'            => 105,
            'title'         => 'Familienfreundliches Einfamilienhaus mit Garten',
            'slug'          => 'einfamilienhaus-killesberg',
            'price'         => 1150000,
            'rooms'         => 5.0,
            'living_space'  => 210,
            'location'      => 'Stuttgart-Killesberg',
            'property_type' => 'Einfamilienhaus',
            'status'        => 'Verfügbar',
            'energy_class'  => 'B',
            'image'         => '',
            'gallery'       => ['Sonniger Süd-Garten', 'Kinderzimmer mit Weitblick'],
        ],
        [
            'id'            => 106,
            'title'         => 'Modernes Stadthaus in ruhiger Lage',
            'slug'          => 'stadthaus-lindenthal',
            'price'         => 890000,
            'rooms'         => 4.0,
            'living_space'  => 160,
            'location'      => 'Köln-Lindenthal',
            'property_type' => 'Einfamilienhaus',
            'status'        => 'Verfügbar',
            'energy_class'  => 'A',
            'image'         => '',
            'gallery'       => ['Helle Fassade', 'Kompakter Stadtgarten'],
        ],
    ];
}

$propertiesJson = json_encode($properties, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<div x-data="{
    properties: <?= $propertiesJson ?>,
    filterLocation: '',
    filterType: 'all',
    filterStatus: 'all',
    filterMaxPrice: 0,
    filterMinRooms: 0,

    // Lightbox Modal State
    lightboxOpen: false,
    activeGallery: [],
    activeGalleryIndex: 0,
    activeGalleryTitle: '',

    // Inquiry Modal State
    inquiryOpen: false,
    inquiryProperty: null,
    inquirySubmitting: false,
    inquirySuccess: false,
    inquiryError: null,
    inquiryForm: {
        name: '',
        email: '',
        phone: '',
        message: '',
        _hb_hp: '',
        csrf_token: '<?= $csrfToken ?>'
    },

    openLightbox(prop) {
        this.activeGalleryTitle = prop.title;
        this.activeGallery = prop.gallery || ['Hauptansicht'];
        this.activeGalleryIndex = 0;
        this.lightboxOpen = true;
    },

    openInquiry(prop) {
        this.inquiryProperty = prop;
        this.inquirySuccess = false;
        this.inquiryError = null;
        this.inquiryForm.message = 'Guten Tag, ich interessiere mich für das Objekt \'' + prop.title + '\' (Objekt-Nr. #' + prop.id + ') und bitte um Zusendung des Exposés sowie Vereinbarung eines Besichtigungstermins.';
        this.inquiryOpen = true;
    },

    async submitInquiry() {
        this.inquirySubmitting = true;
        this.inquiryError = null;
        try {
            const fd = new FormData();
            fd.append('name', this.inquiryForm.name);
            fd.append('email', this.inquiryForm.email);
            fd.append('subject', 'Exposé-Anfrage: ' + this.inquiryProperty.title);
            fd.append('message', this.inquiryForm.message + (this.inquiryForm.phone ? '\nTelefon: ' + this.inquiryForm.phone : ''));
            fd.append('property_id', this.inquiryProperty.id);
            fd.append('property_title', this.inquiryProperty.title);
            fd.append('_hb_hp', this.inquiryForm._hb_hp);
            fd.append('csrf_token', this.inquiryForm.csrf_token);

            const res = await fetch('/api/contact', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                this.inquirySuccess = true;
            } else {
                this.inquiryError = data.error || 'Fehler beim Senden der Anfrage.';
            }
        } catch (e) {
            this.inquiryError = 'Netzwerkfehler. Bitte versuchen Sie es später erneut.';
        } finally {
            this.inquirySubmitting = false;
        }
    },

    get filteredProperties() {
        return this.properties.filter(p => {
            // Location / Search Filter
            if (this.filterLocation.trim() !== '') {
                const q = this.filterLocation.toLowerCase();
                const matchLoc = p.location.toLowerCase().includes(q);
                const matchTitle = p.title.toLowerCase().includes(q);
                if (!matchLoc && !matchTitle) return false;
            }
            // Property Type Filter
            if (this.filterType !== 'all' && p.property_type !== this.filterType) {
                return false;
            }
            // Status Filter
            if (this.filterStatus !== 'all' && p.status !== this.filterStatus) {
                return false;
            }
            // Max Price Filter
            if (this.filterMaxPrice > 0 && p.price > this.filterMaxPrice) {
                return false;
            }
            // Min Rooms Filter
            if (this.filterMinRooms > 0 && p.rooms < this.filterMinRooms) {
                return false;
            }
            return true;
        });
    },

    formatPrice(val) {
        return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(val);
    }
}">

    <!-- Real Estate Hero Section -->
    <section class="py-20 md:py-28 relative overflow-hidden bg-gradient-to-b from-[#04120e] to-[#061a14] border-b border-[#134e3a]">
        <div class="<?= $containerWidth ?> mx-auto px-6 relative z-10 text-center">
            <div class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold mb-6">
                <span>●</span>
                <span>Exklusive Immobilien in den begehrtesten Lagen</span>
            </div>

            <h1 class="text-4xl sm:text-6xl md:text-7xl font-black text-white tracking-tight leading-tight max-w-4xl mx-auto mb-6">
                Finden Sie Ihr neues Zuhause mit bleibendem Wert.
            </h1>

            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed mb-10">
                Entdecken Sie unser erstklassiges Portfolio an Wohnungen, Penthouses und Villen – diskret, professionell und transparent vermittelt.
            </p>

            <!-- Quick Jump to Filter Bar -->
            <a href="#properties" class="inline-flex items-center space-x-2 px-8 py-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xl shadow-emerald-500/25 transition transform hover:scale-105">
                <span>Aktuelle Angebote durchsuchen</span>
                <span>&darr;</span>
            </a>
        </div>
    </section>

    <!-- Interactive Alpine.js Property Search & Filter Grid -->
    <section id="properties" class="py-16">
        <div class="<?= $containerWidth ?> mx-auto px-6">

            <!-- Filter Controls Bar -->
            <div class="bg-[#0a2e23] border border-[#134e3a] rounded-2xl p-6 shadow-2xl mb-12">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search Input -->
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-1.5">Standort / Suchbegriff</label>
                        <input type="text" x-model="filterLocation" placeholder="z.B. Berlin, München..." class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none transition">
                    </div>

                    <!-- Property Type -->
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-1.5">Objektart</label>
                        <select x-model="filterType" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none transition">
                            <option value="all">Alle Objektarten</option>
                            <option value="Wohnung">Wohnung</option>
                            <option value="Penthouse">Penthouse</option>
                            <option value="Villa">Villa</option>
                            <option value="Einfamilienhaus">Einfamilienhaus</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-1.5">Status</label>
                        <select x-model="filterStatus" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none transition">
                            <option value="all">Alle Status</option>
                            <option value="Verfügbar">Verfügbar</option>
                            <option value="Reserviert">Reserviert</option>
                        </select>
                    </div>

                    <!-- Max Price -->
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-1.5">Max. Kaufpreis</label>
                        <select x-model.number="filterMaxPrice" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none transition">
                            <option value="0">Kein Limit</option>
                            <option value="750000">bis 750.000 €</option>
                            <option value="1000000">bis 1.000.000 €</option>
                            <option value="1500000">bis 1.500.000 €</option>
                            <option value="3000000">bis 3.000.000 €</option>
                        </select>
                    </div>

                    <!-- Min Rooms -->
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-1.5">Min. Zimmer</label>
                        <select x-model.number="filterMinRooms" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none transition">
                            <option value="0">Beliebig</option>
                            <option value="3">Ab 3 Zimmer</option>
                            <option value="4">Ab 4 Zimmer</option>
                            <option value="5">Ab 5 Zimmer</option>
                        </select>
                    </div>
                </div>

                <!-- Active Filter Reset & Live Counter -->
                <div class="mt-4 pt-4 border-t border-[#134e3a] flex items-center justify-between text-xs">
                    <span class="text-slate-300 font-semibold" x-text="filteredProperties.length + ' Immobilien gefunden'"></span>
                    <button @click="filterLocation = ''; filterType = 'all'; filterStatus = 'all'; filterMaxPrice = 0; filterMinRooms = 0"
                            class="text-emerald-400 hover:text-emerald-300 font-medium">
                        Filter zurücksetzen
                    </button>
                </div>
            </div>

            <!-- Property Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <template x-for="prop in filteredProperties" :key="prop.id">
                    <div class="bg-[#0a2e23] border border-[#134e3a] rounded-2xl overflow-hidden shadow-xl hover:border-emerald-500/50 transition duration-300 flex flex-col justify-between group">
                        <div>
                            <!-- Visual Header Placeholder / Image -->
                            <div class="h-48 bg-gradient-to-tr from-[#061a14] to-[#0d3b2e] relative p-4 flex flex-col justify-between overflow-hidden">
                                <div class="flex items-center justify-between relative z-10">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase"
                                          :class="prop.status === 'Verfügbar' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                                          x-text="prop.status"></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-slate-900/80 text-slate-300 font-mono" x-text="'Energie: ' + prop.energy_class"></span>
                                </div>

                                <div class="text-center relative z-10">
                                    <span class="text-4xl">🏛️</span>
                                </div>

                                <div class="flex items-center justify-between text-xs text-white relative z-10">
                                    <span class="font-bold bg-black/60 px-2.5 py-1 rounded-lg backdrop-blur" x-text="prop.location"></span>
                                    <button @click="openLightbox(prop)" class="px-2.5 py-1 rounded-lg bg-emerald-600/80 hover:bg-emerald-600 text-white text-[11px] font-semibold transition">
                                        📷 Galerie (<span x-text="prop.gallery ? prop.gallery.length : 1"></span>)
                                    </button>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-6">
                                <div class="text-xl font-black text-emerald-400 font-mono mb-2" x-text="formatPrice(prop.price)"></div>
                                <h3 class="text-base font-bold text-white mb-4 line-clamp-2 leading-snug group-hover:text-emerald-300 transition" x-text="prop.title"></h3>

                                <!-- Key Stats Badges -->
                                <div class="grid grid-cols-2 gap-2 text-xs text-slate-300 py-3 border-y border-[#134e3a] mb-4">
                                    <div>📐 Wohnfläche: <strong class="text-white" x-text="prop.living_space + ' m²'"></strong></div>
                                    <div>🚪 Zimmer: <strong class="text-white" x-text="prop.rooms"></strong></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div class="px-6 pb-6 pt-2 flex items-center justify-between gap-3">
                            <button @click="openInquiry(prop)" class="flex-1 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-lg shadow-emerald-500/20 transition text-center">
                                Exposé anfragen &rarr;
                            </button>
                            <button @click="openLightbox(prop)" class="py-2.5 px-3 rounded-xl bg-[#061a14] hover:bg-slate-900 border border-[#134e3a] text-slate-300 hover:text-white text-xs font-semibold transition" title="Fotos ansehen">
                                🔍
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty Search State -->
            <div x-show="filteredProperties.length === 0" x-cloak class="text-center py-16 bg-[#0a2e23] border border-[#134e3a] rounded-2xl mt-8">
                <div class="text-4xl mb-3">🔍</div>
                <h4 class="text-base font-bold text-white mb-1">Keine passenden Immobilien gefunden</h4>
                <p class="text-xs text-slate-400 mb-6">Bitte passen Sie Ihre Suchfilter an oder geben Sie einen individuellen Suchauftrag auf.</p>
                <button @click="filterLocation = ''; filterType = 'all'; filterStatus = 'all'; filterMaxPrice = 0; filterMinRooms = 0" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold">
                    Alle Filter aufheben
                </button>
            </div>
        </div>
    </section>

    <!-- Gallery Lightbox Modal -->
    <div x-show="lightboxOpen" x-cloak x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
         @keydown.escape.window="lightboxOpen = false">
        <div @click.away="lightboxOpen = false" class="bg-[#0a2e23] border border-[#134e3a] rounded-2xl max-w-3xl w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-bold text-white truncate" x-text="activeGalleryTitle"></h4>
                <button @click="lightboxOpen = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <!-- Image Stage -->
            <div class="aspect-video bg-[#04120e] rounded-xl flex items-center justify-center border border-[#134e3a] relative overflow-hidden mb-4">
                <div class="text-center p-8">
                    <div class="text-5xl mb-3">📸</div>
                    <div class="text-sm font-bold text-white" x-text="activeGallery[activeGalleryIndex] || 'Exposé Foto'"></div>
                    <div class="text-xs text-emerald-400 font-mono mt-1" x-text="'Bild ' + (activeGalleryIndex + 1) + ' von ' + activeGallery.length"></div>
                </div>

                <!-- Prev/Next Navigation Controls -->
                <button @click="activeGalleryIndex = (activeGalleryIndex > 0 ? activeGalleryIndex - 1 : activeGallery.length - 1)"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/60 hover:bg-black text-white flex items-center justify-center font-bold text-sm">
                    &larr;
                </button>
                <button @click="activeGalleryIndex = (activeGalleryIndex < activeGallery.length - 1 ? activeGalleryIndex + 1 : 0)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/60 hover:bg-black text-white flex items-center justify-center font-bold text-sm">
                    &rarr;
                </button>
            </div>

            <!-- Thumbnail Dots -->
            <div class="flex items-center justify-center space-x-2">
                <template x-for="(item, idx) in activeGallery" :key="idx">
                    <button @click="activeGalleryIndex = idx"
                            class="w-3 h-3 rounded-full transition-all"
                            :class="activeGalleryIndex === idx ? 'bg-emerald-500 w-6' : 'bg-slate-700'"></button>
                </template>
            </div>
        </div>
    </div>

    <!-- Property-Specific Inquiry Modal (Honeypot + CSRF Protected) -->
    <div x-show="inquiryOpen" x-cloak x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="inquiryOpen = false">
        <div @click.away="inquiryOpen = false" class="bg-[#0a2e23] border border-[#134e3a] rounded-2xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="text-[10px] text-emerald-400 uppercase tracking-widest font-bold">Exposé-Anforderung</span>
                    <h4 class="text-base font-bold text-white" x-text="inquiryProperty ? inquiryProperty.title : 'Objekt anfragen'"></h4>
                </div>
                <button @click="inquiryOpen = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <div x-show="inquirySuccess" x-cloak class="text-center py-8">
                <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-400 mx-auto flex items-center justify-center text-xl mb-3">✓</div>
                <h4 class="text-base font-bold text-white mb-1">Anfrage erfolgreich übermittelt</h4>
                <p class="text-xs text-slate-300 mb-6">Wir haben Ihre Anfrage erhalten. Das vollständige Exposé senden wir Ihnen in Kürze per E-Mail zu.</p>
                <button @click="inquiryOpen = false" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-semibold">Schließen</button>
            </div>

            <form x-show="!inquirySuccess" @submit.prevent="submitInquiry" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <!-- Anti-Spam Honeypot Field -->
                <div style="display:none !important; position: absolute; left: -9999px;">
                    <input type="text" name="_hb_hp" x-model="inquiryForm._hb_hp" tabindex="-1" autocomplete="off">
                </div>

                <div x-show="inquiryError" x-cloak class="p-3 rounded-xl bg-rose-500/10 text-rose-400 text-xs" x-text="inquiryError"></div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-300 mb-1">Ihr Name *</label>
                    <input type="text" x-model="inquiryForm.name" required placeholder="Vor- und Nachname" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">E-Mail *</label>
                        <input type="email" x-model="inquiryForm.email" required placeholder="name@beispiel.de" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-300 mb-1">Telefon</label>
                        <input type="tel" x-model="inquiryForm.phone" placeholder="+49 ..." class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-300 mb-1">Nachricht an den Makler</label>
                    <textarea x-model="inquiryForm.message" rows="3" class="w-full bg-[#061a14] border border-[#134e3a] focus:border-emerald-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none resize-none"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" :disabled="inquirySubmitting" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-lg shadow-emerald-500/20 transition disabled:opacity-50">
                        <span x-show="!inquirySubmitting">Exposé jetzt kostenlos anfordern &rarr;</span>
                        <span x-show="inquirySubmitting" x-cloak>Wird gesendet...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($contentHtml)): ?>
    <div class="<?= $containerWidth ?> mx-auto px-6 py-12">
        <?= $contentHtml ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>

