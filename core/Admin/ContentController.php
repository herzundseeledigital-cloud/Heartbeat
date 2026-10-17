<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Auth\Auth;
use Heartbeat\Blocks\BlockRegistry;
use Heartbeat\Content\PostService;
use Heartbeat\Content\PostType;
use Heartbeat\Content\PostTypeRegistry;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Media\MediaService;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;

/**
 * Controller handling Content Management (Pages, Posts, CPTs),
 * the Visual JSON Block Builder, and Custom Meta Fields.
 */
final class ContentController
{
    private PostService $posts;
    private PostTypeRegistry $postTypes;
    private BlockRegistry $blockRegistry;
    private MediaService $media;

    public function __construct(
        ?PostService $posts = null,
        ?PostTypeRegistry $postTypes = null,
        ?BlockRegistry $blockRegistry = null,
        ?MediaService $media = null
    ) {
        $this->posts = $posts ?? PostService::getInstance();
        $this->postTypes = $postTypes ?? PostTypeRegistry::getInstance();
        $this->blockRegistry = $blockRegistry ?? BlockRegistry::getInstance();
        $this->media = $media ?? MediaService::getInstance();
    }

    /**
     * Content items list view (Pages, Posts, or specific CPT)
     */
    public function index(Request $request, array $params = []): Response
    {
        $postType = (string)($params['type'] ?? $request->get('type', 'page'));
        $perm = $postType === 'page' ? 'manage_content' : 'edit_posts';
        $redirect = $this->requireAuth($perm);
        if ($redirect) return $redirect;

        $cpt = $this->postTypes->get($postType);
        if (!$cpt) {
            Session::flash('error', "Inhaltstyp '{$postType}' nicht gefunden.");
            return Response::redirect('/admin');
        }

        $status = trim((string)$request->get('status', 'all'));
        $search = trim((string)$request->get('s', ''));

        $queryArgs = [
            'post_type' => $postType,
            'status'    => $status === 'all' ? 'any' : $status,
            'search'    => $search,
            'limit'     => 50,
        ];

        // Do not show trash in 'all' view unless explicitly asked
        if ($status === 'all') {
            $queryArgs['status'] = ['publish', 'draft'];
        }

        $items = $this->posts->query($queryArgs);

        // Calculate counts for status filter pills
        $countAll = $this->posts->count(['post_type' => $postType, 'status' => ['publish', 'draft']]);
        $countPublish = $this->posts->count(['post_type' => $postType, 'status' => 'publish']);
        $countDraft = $this->posts->count(['post_type' => $postType, 'status' => 'draft']);
        $countTrash = $this->posts->count(['post_type' => $postType, 'status' => 'trash']);

        $csrfField = Csrf::field();
        $safeType = Security::e($postType);
        $safePlural = Security::e($cpt->getPlural());
        $safeSingular = Security::e($cpt->getSingular());
        $safeSearch = Security::e($search);

        // CPT Filter switcher tabs if there are custom post types
        $cptTabsHtml = $this->renderCptSwitcher($postType);

        // Status Tabs HTML
        $statusTabsHtml = <<<HTML
<div class="flex items-center space-x-2 text-xs font-semibold">
    <a href="/admin/posts?type={$safeType}&status=all" class="px-3 py-1.5 rounded-lg transition {$this->statusTabClass($status, 'all')}">
        Alle <span class="opacity-60">({$countAll})</span>
    </a>
    <a href="/admin/posts?type={$safeType}&status=publish" class="px-3 py-1.5 rounded-lg transition {$this->statusTabClass($status, 'publish')}">
        Veröffentlicht <span class="opacity-60">({$countPublish})</span>
    </a>
    <a href="/admin/posts?type={$safeType}&status=draft" class="px-3 py-1.5 rounded-lg transition {$this->statusTabClass($status, 'draft')}">
        Entwürfe <span class="opacity-60">({$countDraft})</span>
    </a>
    <a href="/admin/posts?type={$safeType}&status=trash" class="px-3 py-1.5 rounded-lg transition {$this->statusTabClass($status, 'trash')}">
        Papierkorb <span class="opacity-60">({$countTrash})</span>
    </a>
</div>
HTML;

        // Table Rows HTML
        $rowsHtml = '';
        if (empty($items)) {
            $rowsHtml = <<<HTML
<tr>
    <td colspan="5" class="py-12 text-center text-slate-500 text-xs">
        Keine Einträge für '{$safePlural}' gefunden. Klicken Sie oben rechts auf <strong>+ Neuer Eintrag</strong>.
    </td>
</tr>
HTML;
        } else {
            foreach ($items as $item) {
                $id = (int)$item['id'];
                $safeTitle = Security::e($item['title']);
                $safeSlug = Security::e($item['slug']);
                $safeAuthor = Security::e($item['author_name'] ?? 'Admin');
                $dateFormatted = date('d.m.Y H:i', strtotime($item['created_at']));
                $statusBadge = $this->renderStatusBadge($item['status']);

                // View URL
                $viewUrl = $postType === 'page'
                    ? ($item['slug'] === 'home' ? '/' : '/' . $safeSlug)
                    : '/' . ($cpt->getSlugPrefix() ?: $postType) . '/' . $safeSlug;

                // Action buttons depending on status
                if ($item['status'] === 'trash') {
                    $actions = <<<HTML
<div class="flex items-center justify-end space-x-2">
    <form method="POST" action="/admin/content/restore/{$id}">
        {$csrfField}
        <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300 font-semibold">Wiederherstellen</button>
    </form>
    <span class="text-slate-600">|</span>
    <form method="POST" action="/admin/content/delete/{$id}" onsubmit="return confirm('Eintrag unwiderruflich löschen?');">
        {$csrfField}
        <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-semibold">Endgültig löschen</button>
    </form>
</div>
HTML;
                } else {
                    $actions = <<<HTML
<div class="flex items-center justify-end space-x-3">
    <a href="{$viewUrl}" target="_blank" class="text-xs text-slate-400 hover:text-white transition">Vorschau</a>
    <a href="/admin/content/edit/{$id}" class="text-xs text-orange-400 hover:text-orange-300 font-semibold transition">Bearbeiten</a>
    <form method="POST" action="/admin/content/trash/{$id}">
        {$csrfField}
        <button type="submit" class="text-xs text-slate-500 hover:text-rose-400 transition">Papierkorb</button>
    </form>
</div>
HTML;
                }

                $rowsHtml .= <<<HTML
<tr class="hover:bg-[#141e33]/40 transition text-xs border-b border-[#1e293b]">
    <td class="py-3 px-4">
        <a href="/admin/content/edit/{$id}" class="font-bold text-white hover:text-orange-400 transition text-sm">
            {$safeTitle}
        </a>
        <div class="text-[11px] text-slate-500 font-mono mt-0.5">/{$safeSlug}</div>
    </td>
    <td class="py-3 px-4 text-slate-400">{$safeAuthor}</td>
    <td class="py-3 px-4">{$statusBadge}</td>
    <td class="py-3 px-4 text-slate-500 font-mono">{$dateFormatted}</td>
    <td class="py-3 px-4 text-right">{$actions}</td>
</tr>
HTML;
            }
        }

        $headerActionsHtml = <<<HTML
<a href="/admin/content/create?type={$safeType}" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white transition shadow-lg shadow-orange-500/20">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    <span>Neuer Eintrag</span>
</a>
HTML;

        $contentHtml = <<<HTML
{$cptTabsHtml}

<div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl overflow-hidden shadow-xl">
    <!-- Toolbar: Status Filter & Search -->
    <div class="p-4 sm:p-5 border-b border-[#1e293b] flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#141e33]/30">
        {$statusTabsHtml}

        <form method="GET" action="/admin/posts" class="relative">
            <input type="hidden" name="type" value="{$safeType}">
            <input type="hidden" name="status" value="{$status}">
            <input type="text" name="s" value="{$safeSearch}" placeholder="Inhalte durchsuchen..."
                   class="w-56 pl-9 pr-3 py-2 text-xs rounded-xl bg-[#090d16] border border-[#1e293b] text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]">
            <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-[#1e293b] bg-[#090d16]/50 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Titel &amp; Pfad</th>
                    <th class="py-3 px-4">Autor</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Datum</th>
                    <th class="py-3 px-4 text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#1e293b]">
                {$rowsHtml}
            </tbody>
        </table>
    </div>
</div>
HTML;

        $navSlug = $postType === 'page' ? 'pages' : 'posts';
        return Response::html(AdminView::renderShell($cpt->getPlural(), $navSlug, $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => $cpt->getPlural(), 'url' => "/admin/posts?type={$safeType}"],
        ], $headerActionsHtml));
    }

    /**
     * Create new post/page view
     */
    public function create(Request $request, array $params = []): Response
    {
        $postType = (string)($params['type'] ?? $request->get('type', 'page'));
        $perm = $postType === 'page' ? 'manage_content' : 'edit_posts';
        $redirect = $this->requireAuth($perm);
        if ($redirect) return $redirect;

        $cpt = $this->postTypes->get($postType) ?? $this->postTypes->get('page');
        if (!$cpt) {
            return Response::redirect('/admin');
        }

        // Starter block tree for new items
        $initialBlocks = [
            [
                'id'          => 'blk_' . bin2hex(random_bytes(4)),
                'type'        => 'core/hero',
                'attrs'       => [
                    'headline'           => 'Herzlich willkommen',
                    'subheadline'        => 'Dieser Inhalt wurde mit dem Heartbeat Block Builder erstellt.',
                    'badge'              => 'Neu',
                    'primary_btn_text'   => 'Mehr erfahren',
                    'primary_btn_url'    => '#details',
                    'secondary_btn_text' => 'Kontakt',
                    'secondary_btn_url'  => '#contact',
                    'alignment'          => 'center',
                ],
                'styles'      => [],
                'innerBlocks' => [],
            ],
            [
                'id'          => 'blk_' . bin2hex(random_bytes(4)),
                'type'        => 'core/heading',
                'attrs'       => [
                    'level'     => 'h2',
                    'content'   => 'Über dieses Thema',
                    'subtitle'  => 'Hintergrund & Fakten',
                    'alignment' => 'left',
                ],
                'styles'      => [],
                'innerBlocks' => [],
            ],
            [
                'id'          => 'blk_' . bin2hex(random_bytes(4)),
                'type'        => 'core/paragraph',
                'attrs'       => [
                    'content'   => 'Passen Sie diesen Text ganz einfach über den visuellen Editor oder den JSON-Modus an.',
                    'lead'      => true,
                    'alignment' => 'left',
                ],
                'styles'      => [],
                'innerBlocks' => [],
            ],
        ];

        return $this->renderEditor(null, $cpt, $initialBlocks, []);
    }

    /**
     * Edit existing post/page view
     */
    public function edit(Request $request, array $params = []): Response
    {
        $id = (int)($params['id'] ?? 0);
        $post = $this->posts->find($id);
        if (!$post) {
            Session::flash('error', 'Beitrag nicht gefunden.');
            return Response::redirect('/admin/pages');
        }

        $cpt = $this->postTypes->get($post['post_type']) ?? $this->postTypes->get('page');

        $perm = $post['post_type'] === 'page' ? 'manage_content' : 'edit_posts';
        $redirect = $this->requireAuth($perm);
        if ($redirect) return $redirect;

        $blocks = json_decode((string)($post['content_blocks'] ?? '[]'), true);
        if (!is_array($blocks) || empty($blocks)) {
            $blocks = [];
        }

        $meta = (array)($post['meta'] ?? []);

        return $this->renderEditor($post, $cpt, $blocks, $meta);
    }

    /**
     * Save POST action (Create or Update)
     */
    public function save(Request $request, array $params = []): Response
    {
        $id = isset($params['id']) && (int)$params['id'] > 0 ? (int)$params['id'] : null;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Sicherheits-Token (CSRF) ist abgelaufen. Bitte erneut absenden.');
            return Response::redirect($id ? "/admin/content/edit/{$id}" : '/admin/pages');
        }

        $postType = (string)$request->post('post_type', 'page');
        $perm = $postType === 'page' ? 'manage_content' : 'edit_posts';
        $redirect = $this->requireAuth($perm);
        if ($redirect) return $redirect;

        $title = trim((string)$request->post('title', ''));
        if ($title === '') {
            Session::flash('error', 'Der Titel darf nicht leer sein.');
            return Response::redirect($id ? "/admin/content/edit/{$id}" : "/admin/content/create?type={$postType}");
        }

        $slug = trim((string)$request->post('slug', ''));
        $status = (string)$request->post('status', 'publish');
        $blocksRaw = (string)$request->post('content_blocks', '[]');
        $blocks = json_decode($blocksRaw, true);
        if (!is_array($blocks)) {
            $blocks = [];
        }

        // Custom Meta Fields
        $metaInputs = (array)$request->post('meta', []);
        $metaValues = [];

        // Featured image
        if ($request->post('featured_image')) {
            $metaValues['_thumbnail_url'] = (string)$request->post('featured_image');
        }
        if ($request->post('excerpt')) {
            $metaValues['_excerpt'] = (string)$request->post('excerpt');
        }

        // CPT custom fields
        $cpt = $this->postTypes->get($postType);
        if ($cpt) {
            foreach ($cpt->getMetaFields() as $fieldDef) {
                $key = $fieldDef['key'];
                if (isset($metaInputs[$key])) {
                    $metaValues[$key] = $metaInputs[$key];
                }
            }
        }

        $postData = [
            'title'          => $title,
            'slug'           => $slug,
            'post_type'      => $postType,
            'status'         => $status,
            'content_blocks' => $blocks,
            'author_id'      => Auth::id() ?? 1,
        ];

        try {
            if ($id !== null) {
                $this->posts->update($id, $postData, $metaValues);
                \Heartbeat\Themes\ThemeService::getInstance()->compileActiveThemeCss();
                Session::flash('success', "Inhalt '{$title}' erfolgreich aktualisiert!");
                return Response::redirect("/admin/content/edit/{$id}");
            } else {
                $newId = $this->posts->create($postData, $metaValues);
                \Heartbeat\Themes\ThemeService::getInstance()->compileActiveThemeCss();
                Session::flash('success', "Inhalt '{$title}' erfolgreich erstellt!");
                return Response::redirect("/admin/content/edit/{$newId}");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Fehler beim Speichern: ' . $e->getMessage());
            return Response::redirect($id ? "/admin/content/edit/{$id}" : "/admin/content/create?type={$postType}");
        }
    }

    /**
     * Trash action
     */
    public function trash(Request $request, array $params = []): Response
    {
        $id = (int)($params['id'] ?? 0);
        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'CSRF-Fehler.');
            return Response::redirect('/admin/pages');
        }

        $post = $this->posts->find($id);
        if ($post) {
            $this->posts->trash($id);
            Session::flash('info', "Eintrag '{$post['title']}' in den Papierkorb verschoben.");
            return Response::redirect("/admin/posts?type={$post['post_type']}");
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * Restore action
     */
    public function restore(Request $request, array $params = []): Response
    {
        $id = (int)($params['id'] ?? 0);
        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'CSRF-Fehler.');
            return Response::redirect('/admin/pages');
        }

        $post = $this->posts->find($id);
        if ($post) {
            $this->posts->restore($id);
            Session::flash('success', "Eintrag '{$post['title']}' wiederhergestellt.");
            return Response::redirect("/admin/posts?type={$post['post_type']}&status=draft");
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * Delete action
     */
    public function delete(Request $request, array $params = []): Response
    {
        $id = (int)($params['id'] ?? 0);
        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'CSRF-Fehler.');
            return Response::redirect('/admin/pages');
        }

        $post = $this->posts->find($id);
        if ($post) {
            $pt = $post['post_type'];
            $this->posts->delete($id, true);
            Session::flash('success', "Eintrag '{$post['title']}' endgültig gelöscht.");
            return Response::redirect("/admin/posts?type={$pt}&status=trash");
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * Render the Visual Block Editor & CPT Meta Field Shell
     */
    private function renderEditor(?array $post, PostType $cpt, array $blocks, array $meta): Response
    {
        $isEdit = $post !== null;
        $id = $isEdit ? (int)$post['id'] : null;
        $title = $isEdit ? Security::e($post['title']) : '';
        $slug = $isEdit ? Security::e($post['slug']) : '';
        $status = $isEdit ? (string)$post['status'] : 'publish';
        $postType = $cpt->getName();
        $safeType = Security::e($postType);
        $safeSingular = Security::e($cpt->getSingular());
        $safePlural = Security::e($cpt->getPlural());

        $csrfField = Csrf::field();
        $formAction = $isEdit ? "/admin/content/save/{$id}" : "/admin/content/save";

        // JSON block tree representation for Alpine.js
        $blocksJson = htmlspecialchars(json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

        // All registered block definitions for the Inserter
        $allBlocks = $this->blockRegistry->getAll();
        $blocksCatalogJson = htmlspecialchars(json_encode($allBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8');

        // CPT Custom Meta Fields HTML
        $metaFieldsHtml = $this->renderMetaFieldsForm($cpt, $meta);

        // Featured image and excerpt
        $thumbnailUrl = Security::e((string)($meta['_thumbnail_url'] ?? ''));
        $excerpt = Security::e((string)($meta['_excerpt'] ?? ''));

        // View Live Link
        $viewLiveBtn = '';
        if ($isEdit) {
            $liveUrl = $postType === 'page'
                ? ($post['slug'] === 'home' ? '/' : '/' . $post['slug'])
                : '/' . ($cpt->getSlugPrefix() ?: $postType) . '/' . $post['slug'];
            $viewLiveBtn = "<a href=\"{$liveUrl}\" target=\"_blank\" class=\"px-4 py-2 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-slate-300 hover:text-white text-xs font-semibold transition\">Vorschau &rarr;</a>";
        }

        $contentHtml = <<<HTML
<form method="POST" action="{$formAction}" id="content-form"
      x-data="{
          blocks: {$blocksJson},
          catalog: {$blocksCatalogJson},
          viewMode: 'visual', // 'visual' | 'code'
          inserterOpen: false,
          mediaPickerOpen: false,
          activeTargetBlock: null,
          activeTargetAttr: null,
          mediaLibrary: [],
          selectedBlockIndex: null,
          
          addBlock(type) {
              const def = this.catalog[type] || {};
              const newBlock = {
                  id: 'blk_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5),
                  type: type,
                  attrs: JSON.parse(JSON.stringify(def.default_attrs || {})),
                  styles: {},
                  innerBlocks: []
              };
              this.blocks.push(newBlock);
              this.selectedBlockIndex = this.blocks.length - 1;
              this.inserterOpen = false;
          },
          removeBlock(idx) {
              this.blocks.splice(idx, 1);
              this.selectedBlockIndex = null;
          },
          moveBlock(idx, dir) {
              const target = idx + dir;
              if (target < 0 || target >= this.blocks.length) return;
              const temp = this.blocks[idx];
              this.blocks[idx] = this.blocks[target];
              this.blocks[target] = temp;
              this.selectedBlockIndex = target;
          },
          duplicateBlock(idx) {
              const clone = JSON.parse(JSON.stringify(this.blocks[idx]));
              clone.id = 'blk_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
              this.blocks.splice(idx + 1, 0, clone);
              this.selectedBlockIndex = idx + 1;
          },
          openMediaPicker(blockIdx, attrName) {
              this.activeTargetBlock = blockIdx;
              this.activeTargetAttr = attrName;
              this.mediaPickerOpen = true;
              this.fetchMedia();
          },
          selectMedia(url) {
              if (this.activeTargetBlock !== null && this.activeTargetAttr !== null) {
                  this.blocks[this.activeTargetBlock].attrs[this.activeTargetAttr] = url;
              }
              this.mediaPickerOpen = false;
          },
          fetchMedia() {
              fetch('/admin/api/media')
                  .then(r => r.json())
                  .then(data => {
                      if (data.status === 'ok') {
                          this.mediaLibrary = data.items || [];
                      }
                  });
          }
      }">

    {$csrfField}
    <input type="hidden" name="post_type" value="{$safeType}">
    <input type="hidden" name="content_blocks" :value="JSON.stringify(blocks)">

    <!-- Top Action Bar -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl">
        <div class="flex items-center space-x-3">
            <a href="/admin/posts?type={$safeType}" class="p-2 rounded-xl bg-[#090d16] text-slate-400 hover:text-white transition">
                &larr; Zurück
            </a>
            <div>
                <h2 class="text-base font-bold text-white">
                    {$safeSingular} {$this->editorActionLabel($isEdit)}
                </h2>
                <div class="text-xs text-slate-400">JSON Block Builder &amp; Custom Fields Engine</div>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Mode Switcher -->
            <div class="flex items-center p-1 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs font-semibold">
                <button type="button" @click="viewMode = 'visual'"
                        class="px-3 py-1.5 rounded-lg transition"
                        :class="viewMode === 'visual' ? 'bg-[#f97316] text-white shadow' : 'text-slate-400 hover:text-white'">
                    🎨 Visuell
                </button>
                <button type="button" @click="viewMode = 'code'"
                        class="px-3 py-1.5 rounded-lg transition"
                        :class="viewMode === 'code' ? 'bg-[#f97316] text-white shadow' : 'text-slate-400 hover:text-white'">
                    &lt;/&gt; JSON Code
                </button>
            </div>

            {$viewLiveBtn}

            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white font-semibold text-xs transition shadow-lg shadow-orange-500/25">
                Speichern &rarr;
            </button>
        </div>
    </div>

    <!-- Main Workspace Grid: Canvas (Left 8 Cols) + Sidebar Meta (Right 4 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: Block Editor Canvas -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- Title Card -->
            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl">
                <label for="post-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                    Titel des Eintrags
                </label>
                <input type="text" id="post-title" name="title" value="{$title}" required
                       placeholder="z.B. Startseite, Luxusvilla in Berlin oder Über uns..."
                       class="w-full px-4 py-3 rounded-xl bg-[#090d16] border border-[#1e293b] text-lg font-bold text-white placeholder-slate-600 focus:outline-none focus:border-[#f97316]">
                
                <div class="mt-3 flex items-center space-x-2 text-xs">
                    <span class="text-slate-500">URL-Slug:</span>
                    <input type="text" name="slug" value="{$slug}" placeholder="wird automatisch generiert"
                           class="px-2.5 py-1 rounded bg-[#090d16] border border-[#1e293b] text-slate-300 font-mono text-[11px] focus:outline-none focus:border-[#f97316]">
                </div>
            </div>

            <!-- Visual Block Canvas -->
            <div x-show="viewMode === 'visual'" class="space-y-4">
                
                <!-- Block Canvas Container -->
                <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl space-y-4 min-h-[400px]">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-[#1e293b]">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Strukturierte Inhaltsblöcke (<span x-text="blocks.length"></span>)
                        </span>
                        <button type="button" @click="inserterOpen = true"
                                class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 hover:bg-orange-500/20 border border-orange-500/30 text-orange-400 text-xs font-semibold transition">
                            <span>+ Block hinzufügen</span>
                        </button>
                    </div>

                    <!-- Block Items List -->
                    <template x-for="(block, idx) in blocks" :key="block.id">
                        <div class="border rounded-2xl p-4 transition bg-[#090d16]"
                             :class="selectedBlockIndex === idx ? 'border-orange-500 shadow-lg shadow-orange-500/10' : 'border-[#1e293b] hover:border-slate-700'">
                            
                            <!-- Block Header & Controls -->
                            <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#1e293b]/60">
                                <div class="flex items-center space-x-2.5 cursor-pointer" @click="selectedBlockIndex = selectedBlockIndex === idx ? null : idx">
                                    <span class="w-6 h-6 rounded-lg bg-[#141e33] border border-slate-700 flex items-center justify-center text-xs font-bold text-orange-400"
                                          x-text="idx + 1"></span>
                                    <span class="text-xs font-bold text-white" x-text="catalog[block.type]?.name || block.type"></span>
                                    <span class="text-[10px] text-slate-500 font-mono" x-text="'[' + block.type + ']'"></span>
                                </div>

                                <div class="flex items-center space-x-1">
                                    <button type="button" @click="moveBlock(idx, -1)" :disabled="idx === 0" title="Nach oben"
                                            class="p-1 rounded text-slate-400 hover:text-white disabled:opacity-20 transition">&uarr;</button>
                                    <button type="button" @click="moveBlock(idx, 1)" :disabled="idx === blocks.length - 1" title="Nach unten"
                                            class="p-1 rounded text-slate-400 hover:text-white disabled:opacity-20 transition">&darr;</button>
                                    <button type="button" @click="duplicateBlock(idx)" title="Duplizieren"
                                            class="p-1 rounded text-slate-400 hover:text-white transition">⧉</button>
                                    <button type="button" @click="removeBlock(idx)" title="Löschen"
                                            class="p-1 rounded text-rose-400 hover:text-rose-300 transition">&times;</button>
                                </div>
                            </div>

                            <!-- Block Preview / Quick Editor -->
                            <div class="text-xs space-y-3">
                                
                                <!-- Core Hero Attributes -->
                                <template x-if="block.type === 'core/hero'">
                                    <div class="space-y-2">
                                        <input type="text" x-model="block.attrs.headline" placeholder="Hero Überschrift"
                                               class="w-full px-3 py-2 text-sm font-bold rounded-lg bg-[#141e33] border border-[#1e293b] text-white">
                                        <textarea x-model="block.attrs.subheadline" placeholder="Hero Untertitel" rows="2"
                                                  class="w-full px-3 py-2 text-xs rounded-lg bg-[#141e33] border border-[#1e293b] text-slate-300"></textarea>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="text" x-model="block.attrs.primary_btn_text" placeholder="Button 1 Text"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-white">
                                            <input type="text" x-model="block.attrs.primary_btn_url" placeholder="Button 1 URL"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-slate-400">
                                        </div>
                                    </div>
                                </template>

                                <!-- Core Heading Attributes -->
                                <template x-if="block.type === 'core/heading'">
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <select x-model="block.attrs.level" class="w-24 px-2 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-white">
                                                <option value="h1">H1</option>
                                                <option value="h2">H2</option>
                                                <option value="h3">H3</option>
                                                <option value="h4">H4</option>
                                                <option value="h5">H5</option>
                                                <option value="h6">H6</option>
                                            </select>
                                            <input type="text" x-model="block.attrs.content" placeholder="Überschriftstext"
                                                   class="flex-1 px-3 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-sm font-bold text-white">
                                        </div>
                                        <input type="text" x-model="block.attrs.subtitle" placeholder="Optionaler Kicker / Untertitel"
                                               class="w-full px-3 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-orange-400">
                                    </div>
                                </template>

                                <!-- Core Paragraph Attributes -->
                                <template x-if="block.type === 'core/paragraph'">
                                    <div class="space-y-2">
                                        <textarea x-model="block.attrs.content" rows="3" placeholder="Geben Sie hier Ihren Textabsatz ein..."
                                                  class="w-full px-3 py-2 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-slate-200 leading-relaxed"></textarea>
                                        <label class="flex items-center space-x-2">
                                            <input type="checkbox" x-model="block.attrs.lead" class="rounded">
                                            <span class="text-xs text-slate-400">Hervorgehobener Lead-Absatz (größere Schrift)</span>
                                        </label>
                                    </div>
                                </template>

                                <!-- Core Image Attributes -->
                                <template x-if="block.type === 'core/image'">
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <input type="text" x-model="block.attrs.url" placeholder="Bild-URL (/uploads/...)"
                                                   class="flex-1 px-3 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-white font-mono">
                                            <button type="button" @click="openMediaPicker(idx, 'url')"
                                                    class="px-3 py-1.5 rounded-lg bg-[#f97316] text-white text-xs font-semibold">
                                                Mediathek
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="text" x-model="block.attrs.alt" placeholder="Alt-Text (Barrierefreiheit)"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-white">
                                            <input type="text" x-model="block.attrs.caption" placeholder="Bildunterschrift"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-slate-300">
                                        </div>
                                    </div>
                                </template>

                                <!-- Core Button Attributes -->
                                <template x-if="block.type === 'core/button'">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" x-model="block.attrs.text" placeholder="Button Text"
                                               class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-white">
                                        <input type="text" x-model="block.attrs.url" placeholder="Ziel URL"
                                               class="px-2.5 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-slate-300">
                                    </div>
                                </template>

                                <!-- Core Card Attributes -->
                                <template x-if="block.type === 'core/card'">
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <input type="text" x-model="block.attrs.icon" placeholder="⚡" class="w-12 text-center px-2 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-sm text-white">
                                            <input type="text" x-model="block.attrs.title" placeholder="Kartentitel" class="flex-1 px-3 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs font-bold text-white">
                                        </div>
                                        <textarea x-model="block.attrs.description" rows="2" placeholder="Beschreibung"
                                                  class="w-full px-3 py-1.5 rounded-lg bg-[#141e33] border border-[#1e293b] text-xs text-slate-300"></textarea>
                                    </div>
                                </template>

                                <!-- Core Popup & Paywall Attributes -->
                                <template x-if="block.type === 'core/popup'">
                                    <div class="p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-2">
                                        <div class="text-xs font-bold text-orange-400">⚡ Popup &amp; Paywall Bedingung</div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <select x-model="block.attrs.trigger" class="px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-white">
                                                <option value="time_delay">Zeitverzögerung (Sekunden)</option>
                                                <option value="scroll_depth">Scroll-Tiefe (%)</option>
                                                <option value="exit_intent">Exit-Intent (Maus verlässt Fenster)</option>
                                                <option value="role_gate">Paywall Gate (Nur Mitglieder)</option>
                                            </select>
                                            <input type="number" x-model="block.attrs.trigger_value" placeholder="z.B. 5 oder 50"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-white">
                                        </div>
                                        <input type="text" x-model="block.attrs.title" placeholder="Popup Titel"
                                               class="w-full px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-white">
                                        <textarea x-model="block.attrs.message" rows="2" placeholder="Mitteilung / Banner Text"
                                                  class="w-full px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-slate-300"></textarea>
                                    </div>
                                </template>

                            </div>
                        </div>
                    </template>

                    <!-- Inserter Trigger Bottom -->
                    <div class="pt-4 text-center">
                        <button type="button" @click="inserterOpen = true"
                                class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl border border-dashed border-[#1e293b] hover:border-orange-500 text-slate-400 hover:text-white text-xs font-semibold transition">
                            <span>+ Weiteren Block einfügen</span>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Code / Raw JSON Mode -->
            <div x-show="viewMode === 'code'" x-cloak class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Raw JSON Block Tree</span>
                    <span class="text-[11px] text-slate-500 font-mono">100% Schema-konform</span>
                </div>
                <textarea rows="18"
                          :value="JSON.stringify(blocks, null, 2)"
                          @input="try { blocks = JSON.parse(\$event.target.value) } catch(e) {}"
                          class="w-full p-4 rounded-xl bg-[#090d16] border border-[#1e293b] font-mono text-xs text-orange-300 focus:outline-none focus:border-[#f97316] leading-relaxed"></textarea>
            </div>

        </div>

        <!-- Right Column: Metadata, CPT Fields & Status Sidebar -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- Publish / Status Card -->
            <div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Veröffentlichung</h3>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white">
                        <option value="publish" {$this->selected($status, 'publish')}>Veröffentlicht</option>
                        <option value="draft" {$this->selected($status, 'draft')}>Entwurf</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Kurzbeschreibung (Excerpt)</label>
                    <textarea name="excerpt" rows="3" placeholder="Zusammenfassung für SEO &amp; Vorschau..."
                              class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300">{$excerpt}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Beitragsbild (Thumbnail)</label>
                    <div class="flex items-center space-x-2">
                        <input type="text" name="featured_image" value="{$thumbnailUrl}" placeholder="/uploads/..."
                               class="flex-1 px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300 font-mono">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white font-semibold text-xs transition shadow-lg shadow-orange-500/25">
                        Änderungen speichern &rarr;
                    </button>
                </div>
            </div>

            <!-- Custom Post Type Meta Fields -->
            {$metaFieldsHtml}

        </div>

    </div>

    <!-- Block Inserter Modal / Drawer -->
    <div x-show="inserterOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div @click.away="inserterOpen = false"
             class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 max-w-xl w-full shadow-2xl relative transform transition-all">
            
            <div class="flex items-center justify-between pb-4 border-b border-[#1e293b] mb-4">
                <h3 class="text-base font-bold text-white">Block hinzufügen</h3>
                <button type="button" @click="inserterOpen = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto pr-1">
                <template x-for="(blockDef, bType) in catalog" :key="bType">
                    <button type="button" @click="addBlock(bType)"
                            class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-orange-500/50 hover:bg-[#141e33] transition text-left flex flex-col justify-between space-y-2 group">
                        <div class="text-xs font-bold text-white group-hover:text-orange-400 transition" x-text="blockDef.name"></div>
                        <p class="text-[10px] text-slate-400 leading-normal line-clamp-2" x-text="blockDef.description"></p>
                        <span class="text-[9px] font-bold text-orange-400 font-mono" x-text="blockDef.category"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Media Picker Modal -->
    <div x-show="mediaPickerOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div @click.away="mediaPickerOpen = false"
             class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 max-w-3xl w-full shadow-2xl relative transform transition-all">
            
            <div class="flex items-center justify-between pb-4 border-b border-[#1e293b] mb-4">
                <h3 class="text-base font-bold text-white">Bild aus Mediathek wählen</h3>
                <button type="button" @click="mediaPickerOpen = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 max-h-[50vh] overflow-y-auto pr-1">
                <template x-for="item in mediaLibrary" :key="item.id">
                    <div @click="selectMedia(item._webp_url || item._url)"
                         class="aspect-square rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-orange-500 cursor-pointer overflow-hidden p-1 transition">
                        <img :src="item._webp_url || item._url" :alt="item.title" class="w-full h-full object-cover rounded-lg">
                    </div>
                </template>
            </div>
        </div>
    </div>

</form>
HTML;

        $navSlug = $postType === 'page' ? 'pages' : 'posts';
        return Response::html(AdminView::renderShell($cpt->getSingular() . ' bearbeiten', $navSlug, $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => $cpt->getPlural(), 'url' => "/admin/posts?type={$safeType}"],
            ['label' => $isEdit ? $post['title'] : 'Neu', 'url' => '#'],
        ]));
    }

    /**
     * Render dynamic CPT meta fields form
     */
    private function renderMetaFieldsForm(PostType $cpt, array $meta): string
    {
        $fields = $cpt->getMetaFields();
        if (empty($fields)) {
            return '';
        }

        $fieldsHtml = '';
        foreach ($fields as $field) {
            $key = Security::e($field['key']);
            $label = Security::e($field['label']);
            $type = $field['type'] ?? 'text';
            $placeholder = Security::e((string)($field['placeholder'] ?? ''));
            $val = isset($meta[$field['key']]) ? Security::e((string)$meta[$field['key']]) : '';

            if ($type === 'select') {
                $optionsHtml = '';
                foreach ($field['options'] ?? [] as $opt) {
                    $safeOpt = Security::e((string)$opt);
                    $selected = $val === $safeOpt ? 'selected' : '';
                    $optionsHtml .= "<option value=\"{$safeOpt}\" {$selected}>{$safeOpt}</option>";
                }
                $inputHtml = "<select name=\"meta[{$key}]\" class=\"w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white\">{$optionsHtml}</select>";
            } elseif ($type === 'textarea') {
                $inputHtml = "<textarea name=\"meta[{$key}]\" rows=\"3\" placeholder=\"{$placeholder}\" class=\"w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300\">{$val}</textarea>";
            } else {
                $step = isset($field['step']) ? "step=\"{$field['step']}\"" : '';
                $inputHtml = "<input type=\"{$type}\" name=\"meta[{$key}]\" value=\"{$val}\" placeholder=\"{$placeholder}\" {$step} class=\"w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white\">";
            }

            $fieldsHtml .= <<<HTML
<div>
    <label class="block text-xs font-semibold text-slate-300 mb-1.5">{$label}</label>
    {$inputHtml}
</div>
HTML;
        }

        return <<<HTML
<div class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] shadow-xl space-y-4">
    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Custom Fields ({$cpt->getSingular()})</h3>
    <div class="space-y-3">
        {$fieldsHtml}
    </div>
</div>
HTML;
    }

    /**
     * Render CPT switcher tabs
     */
    private function renderCptSwitcher(string $currentType): string
    {
        $allTypes = $this->postTypes->getAll();
        $tabs = '';

        foreach ($allTypes as $pt) {
            $name = $pt->getName();
            $label = Security::e($pt->getPlural());
            $activeClass = $name === $currentType
                ? 'bg-[#f97316] text-white shadow-lg shadow-orange-500/20 font-bold'
                : 'bg-[#0f172a] text-slate-400 hover:text-white border border-[#1e293b]';

            $tabs .= "<a href=\"/admin/posts?type={$name}\" class=\"px-4 py-2 rounded-xl text-xs transition {$activeClass}\">{$label}</a>";
        }

        return <<<HTML
<div class="mb-6 flex items-center space-x-2 overflow-x-auto pb-1">
    {$tabs}
</div>
HTML;
    }

    private function renderStatusBadge(string $status): string
    {
        return match ($status) {
            'publish' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Veröffentlicht</span>',
            'draft'   => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Entwurf</span>',
            'trash'   => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Papierkorb</span>',
            default   => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/10 text-slate-400">' . Security::e($status) . '</span>',
        };
    }

    private function statusTabClass(string $current, string $target): string
    {
        return $current === $target
            ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold'
            : 'text-slate-400 hover:text-white';
    }

    private function editorActionLabel(bool $isEdit): string
    {
        return $isEdit ? 'bearbeiten' : 'erstellen';
    }

    private function selected(string $a, string $b): string
    {
        return $a === $b ? 'selected' : '';
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
}
