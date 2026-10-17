<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Auth\Auth;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Media\MediaService;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;

/**
 * Controller handling Media Library, Drag-and-Drop uploads, and Media Picker API.
 */
final class MediaController
{
    private MediaService $media;

    public function __construct(?MediaService $media = null)
    {
        $this->media = $media ?? MediaService::getInstance();
    }

    /**
     * Mediathek overview view
     */
    public function index(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_media');
        if ($redirect) return $redirect;

        $search = trim((string)$request->get('s', ''));
        $items = $this->media->getAll(['search' => $search, 'limit' => 60]);
        $totalCount = $this->media->count(['search' => $search]);

        $csrfField = Csrf::field();
        $csrfToken = Csrf::token();
        $safeSearch = Security::e($search);

        $itemsJson = htmlspecialchars(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

        $contentHtml = <<<HTML
<div x-data="{
        mediaList: {$itemsJson},
        selectedItem: null,
        detailModalOpen: false,
        uploading: false,
        uploadProgress: 0,
        viewMode: 'grid',
        searchQuery: '{$safeSearch}',
        openDetail(item) {
            this.selectedItem = item;
            this.detailModalOpen = true;
        },
        closeDetail() {
            this.detailModalOpen = false;
            this.selectedItem = null;
        },
        copyUrl(url) {
            navigator.clipboard.writeText(window.location.origin + url).then(() => {
                if (window.Heartbeat && window.Heartbeat.toast) {
                    window.Heartbeat.toast('success', 'URL in Zwischenablage kopiert!');
                }
            });
        },
        handleDrop(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.uploadFile(files[0]);
            }
        },
        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.uploadFile(files[0]);
            }
        },
        uploadFile(file) {
            this.uploading = true;
            this.uploadProgress = 20;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('_csrf_token', '{$csrfToken}');

            fetch('/admin/media/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{$csrfToken}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => {
                this.uploadProgress = 80;
                return res.json();
            })
            .then(data => {
                this.uploadProgress = 100;
                this.uploading = false;
                if (data.status === 'ok') {
                    this.mediaList.unshift(data.media);
                    if (window.Heartbeat && window.Heartbeat.toast) {
                        window.Heartbeat.toast('success', 'Datei erfolgreich hochgeladen und konvertiert!');
                    }
                } else {
                    if (window.Heartbeat && window.Heartbeat.toast) {
                        window.Heartbeat.toast('error', data.message || 'Upload fehlgeschlagen.');
                    }
                }
            })
            .catch(err => {
                this.uploading = false;
                if (window.Heartbeat && window.Heartbeat.toast) {
                    window.Heartbeat.toast('error', 'Verbindungsfehler beim Upload.');
                }
            });
        }
     }"
     class="space-y-6">

    <!-- Header Actions & Search Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-orange-500/10 text-orange-400 flex items-center justify-center font-bold text-lg">
                🖼️
            </div>
            <div>
                <h2 class="text-base font-bold text-white">Mediathek &amp; Asset-Pipeline</h2>
                <p class="text-xs text-slate-400">Automatische WebP-Konvertierung, responsive Größen und strikte MIME-Prüfung.</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <form method="GET" action="/admin/media" class="relative">
                <input type="text" name="s" value="{$safeSearch}" placeholder="Dateien durchsuchen..."
                       class="w-56 pl-9 pr-3 py-2 text-xs rounded-xl bg-[#090d16] border border-[#1e293b] text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </form>

            <button type="button" @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                    class="p-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-slate-400 hover:text-white transition">
                <span x-show="viewMode === 'grid'">📋</span>
                <span x-show="viewMode === 'list'">🔲</span>
            </button>
        </div>
    </div>

    <!-- Drag & Drop Upload Zone -->
    <div @dragover.prevent
         @drop.prevent="handleDrop($event)"
         class="border-2 border-dashed border-[#1e293b] hover:border-orange-500/50 rounded-2xl p-8 text-center transition bg-[#0f172a]/50 relative">
        
        <input type="file" id="media-file-input" @change="handleFileSelect($event)" class="hidden" accept="image/*,application/pdf">

        <div x-show="!uploading" class="space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-400 mx-auto flex items-center justify-center text-xl font-bold">
                ☁️
            </div>
            <div>
                <label for="media-file-input" class="text-sm font-bold text-white hover:text-orange-400 cursor-pointer transition">
                    Dateien hier ablegen oder <span class="text-orange-400 underline decoration-orange-500/40">durchsuchen</span>
                </label>
                <p class="text-xs text-slate-500 mt-1">Unterstützt JPG, PNG, WebP, AVIF, GIF, SVG, PDF. Bilder werden automatisch optimiert.</p>
            </div>
        </div>

        <!-- Upload Progress Indicator -->
        <div x-show="uploading" x-cloak class="space-y-3 max-w-xs mx-auto py-2">
            <div class="text-xs font-semibold text-slate-300">Wird hochgeladen und konvertiert...</div>
            <div class="w-full h-2 bg-[#090d16] rounded-full overflow-hidden border border-[#1e293b]">
                <div class="h-full bg-gradient-to-r from-orange-500 to-amber-500 transition-all duration-300"
                     :style="'width: ' + uploadProgress + '%'"></div>
            </div>
        </div>
    </div>

    <!-- Media Grid View -->
    <div x-show="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <template x-for="item in mediaList" :key="item.id">
            <div @click="openDetail(item)"
                 class="group relative bg-[#0f172a] border border-[#1e293b] hover:border-orange-500/40 rounded-2xl p-2 cursor-pointer transition shadow-lg overflow-hidden flex flex-col justify-between">
                <div class="aspect-square rounded-xl bg-[#090d16] overflow-hidden flex items-center justify-center relative">
                    <template x-if="item._mime_type && item._mime_type.startsWith('image/')">
                        <img :src="item._webp_url || item._url" :alt="item.title" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    </template>
                    <template x-if="item._mime_type && !item._mime_type.startsWith('image/')">
                        <div class="text-2xl font-bold text-slate-500">📄</div>
                    </template>
                    <template x-if="item._webp_url">
                        <span class="absolute top-1.5 right-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            WebP
                        </span>
                    </template>
                </div>
                <div class="mt-2 px-1">
                    <div class="text-xs font-semibold text-slate-200 truncate" x-text="item.title"></div>
                    <div class="text-[10px] text-slate-500 font-mono mt-0.5" x-text="(item._width ? item._width + 'x' + item._height + ' • ' : '') + Math.round((item._file_size || 0) / 1024) + ' KB'"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Media List View -->
    <div x-show="viewMode === 'list'" x-cloak class="bg-[#0f172a] border border-[#1e293b] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-[#1e293b] bg-[#141e33]/50 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4">Vorschau</th>
                        <th class="py-3 px-4">Titel &amp; Dateiname</th>
                        <th class="py-3 px-4">MIME-Typ</th>
                        <th class="py-3 px-4">Größe</th>
                        <th class="py-3 px-4">WebP</th>
                        <th class="py-3 px-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e293b]">
                    <template x-for="item in mediaList" :key="item.id">
                        <tr class="hover:bg-[#141e33]/40 transition text-xs">
                            <td class="py-2.5 px-4">
                                <div class="w-10 h-10 rounded-lg bg-[#090d16] border border-slate-800 overflow-hidden flex items-center justify-center">
                                    <template x-if="item._mime_type && item._mime_type.startsWith('image/')">
                                        <img :src="item._webp_url || item._url" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!item._mime_type || !item._mime_type.startsWith('image/')">
                                        <span>📄</span>
                                    </template>
                                </div>
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="font-bold text-white" x-text="item.title"></div>
                                <div class="text-[11px] text-slate-500 font-mono" x-text="item._file_path"></div>
                            </td>
                            <td class="py-2.5 px-4 text-slate-400 font-mono" x-text="item._mime_type"></td>
                            <td class="py-2.5 px-4 text-slate-400 font-mono" x-text="Math.round((item._file_size || 0) / 1024) + ' KB'"></td>
                            <td class="py-2.5 px-4">
                                <span x-show="item._webp_url" class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Aktiv</span>
                                <span x-show="!item._webp_url" class="text-[10px] text-slate-500">-</span>
                            </td>
                            <td class="py-2.5 px-4 text-right space-x-2">
                                <button type="button" @click="openDetail(item)" class="text-xs text-orange-400 hover:text-orange-300 font-semibold">Details</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Empty State -->
    <template x-if="mediaList.length === 0">
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-12 text-center shadow-xl">
            <div class="text-3xl mb-3">🖼️</div>
            <h3 class="text-base font-bold text-white mb-1">Keine Mediendateien gefunden</h3>
            <p class="text-xs text-slate-400">Ziehen Sie Bilder in den Upload-Bereich oben, um Ihre Mediathek aufzubauen.</p>
        </div>
    </template>

    <!-- Detail Modal -->
    <div x-show="detailModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div @click.away="closeDetail()"
             class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 max-w-2xl w-full shadow-2xl relative transform transition-all">
            
            <div class="flex items-center justify-between pb-4 border-b border-[#1e293b] mb-5">
                <h3 class="text-base font-bold text-white">Medien-Details</h3>
                <button type="button" @click="closeDetail()" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <template x-if="selectedItem">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Preview Image -->
                    <div class="aspect-square bg-[#090d16] rounded-xl border border-[#1e293b] overflow-hidden flex items-center justify-center p-2">
                        <template x-if="selectedItem._mime_type && selectedItem._mime_type.startsWith('image/')">
                            <img :src="selectedItem._webp_url || selectedItem._url" class="max-w-full max-h-full object-contain rounded-lg">
                        </template>
                        <template x-if="!selectedItem._mime_type || !selectedItem._mime_type.startsWith('image/')">
                            <div class="text-5xl">📄</div>
                        </template>
                    </div>

                    <!-- Metadata & Actions -->
                    <div class="space-y-4 text-xs">
                        <div>
                            <span class="block text-slate-500 font-semibold mb-1">Titel</span>
                            <div class="text-sm font-bold text-white" x-text="selectedItem.title"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="block text-slate-500 font-semibold mb-1">Dateigröße</span>
                                <div class="font-mono text-slate-300" x-text="Math.round((selectedItem._file_size || 0) / 1024) + ' KB'"></div>
                            </div>
                            <div>
                                <span class="block text-slate-500 font-semibold mb-1">Dimensionen</span>
                                <div class="font-mono text-slate-300" x-text="selectedItem._width ? selectedItem._width + ' × ' + selectedItem._height + ' px' : 'N/A'"></div>
                            </div>
                        </div>

                        <div>
                            <span class="block text-slate-500 font-semibold mb-1">MIME-Typ &amp; Pfad</span>
                            <div class="font-mono text-slate-300 truncate" x-text="selectedItem._mime_type"></div>
                            <div class="font-mono text-[11px] text-slate-500 truncate mt-0.5" x-text="selectedItem._file_path"></div>
                        </div>

                        <div>
                            <span class="block text-slate-500 font-semibold mb-1">Öffentliche URL</span>
                            <div class="flex items-center space-x-2">
                                <input type="text" readonly :value="selectedItem._url"
                                       class="w-full px-3 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs font-mono text-slate-300 focus:outline-none">
                                <button type="button" @click="copyUrl(selectedItem._url)"
                                        class="px-3 py-1.5 rounded-lg bg-[#141e33] hover:bg-[#1e293b] text-orange-400 font-semibold transition flex-shrink-0">
                                    Kopieren
                                </button>
                            </div>
                        </div>

                        <!-- WebP conversion status -->
                        <div x-show="selectedItem._webp_url" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-between">
                            <span>✓ Native WebP-Version aktiv</span>
                            <button type="button" @click="copyUrl(selectedItem._webp_url)" class="text-[11px] font-bold underline">WebP kopieren</button>
                        </div>

                        <!-- Delete Form -->
                        <div class="pt-4 border-t border-[#1e293b] flex items-center justify-between">
                            <form method="POST" :action="'/admin/media/delete/' + selectedItem.id"
                                  onsubmit="return confirm('Möchten Sie diese Datei wirklich unwiderruflich von der Festplatte löschen?');">
                                {$csrfField}
                                <button type="submit" class="px-3 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-semibold transition">
                                    Datei löschen
                                </button>
                            </form>
                            <button type="button" @click="closeDetail()" class="px-4 py-2 rounded-xl bg-[#141e33] text-slate-300 hover:text-white transition font-medium">
                                Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Mediathek', 'media', $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'Mediathek', 'url' => '/admin/media'],
        ]));
    }

    /**
     * Handle upload POST request (multipart/form-data)
     */
    public function upload(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_media');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            if ($this->isAjax($request)) {
                return Response::json(['status' => 'error', 'message' => 'CSRF-Token ungültig.'], 403);
            }
            Session::flash('error', 'CSRF-Token ungültig.');
            return Response::redirect('/admin/media');
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || !is_array($file)) {
            if ($this->isAjax($request)) {
                return Response::json(['status' => 'error', 'message' => 'Keine Datei übermittelt.'], 400);
            }
            Session::flash('error', 'Keine Datei übermittelt.');
            return Response::redirect('/admin/media');
        }

        try {
            $authorId = Auth::id() ?? 1;
            $attachment = $this->media->upload($file, $authorId);

            if ($this->isAjax($request)) {
                return Response::json([
                    'status' => 'ok',
                    'media'  => $attachment,
                ]);
            }

            Session::flash('success', "Datei '{$attachment['title']}' erfolgreich hochgeladen und optimiert.");
            return Response::redirect('/admin/media');
        } catch (\Throwable $e) {
            if ($this->isAjax($request)) {
                return Response::json(['status' => 'error', 'message' => $e->getMessage()], 400);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/media');
        }
    }

    /**
     * Return JSON list of media attachments for the Modal Media Picker
     */
    public function apiList(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_content');
        if ($redirect) return $redirect;

        $search = trim((string)$request->get('s', ''));
        $items = $this->media->getAll(['search' => $search, 'limit' => 50]);

        return Response::json([
            'status' => 'ok',
            'items'  => $items,
        ]);
    }

    /**
     * Delete an attachment
     */
    public function delete(Request $request, array $params = []): Response
    {
        $redirect = $this->requireAuth('manage_media');
        if ($redirect) return $redirect;

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Ungültige Datei-ID.');
            return Response::redirect('/admin/media');
        }

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'CSRF-Token ungültig.');
            return Response::redirect('/admin/media');
        }

        if ($this->media->delete($id)) {
            Session::flash('success', 'Datei und zugehörige WebP-Varianten wurden erfolgreich gelöscht.');
        } else {
            Session::flash('error', 'Datei konnte nicht gelöscht werden oder existiert nicht.');
        }

        return Response::redirect('/admin/media');
    }

    private function requireAuth(string $permission): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }

        if (!Auth::can($permission)) {
            Session::flash('error', 'Zugriff verweigert (Client-Guard / RBAC).');
            return Response::redirect('/admin');
        }

        return null;
    }

    private function isAjax(Request $request): bool
    {
        return str_contains((string)$request->header('Accept', ''), 'application/json')
            || (string)$request->header('X-Requested-With', '') === 'XMLHttpRequest';
    }
}
