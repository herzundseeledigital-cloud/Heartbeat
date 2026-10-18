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
    <a href="/admin/content/edit/{$id}" class="text-xs text-[#fb326f] hover:text-[#fb326f] font-semibold transition">Bearbeiten</a>
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
        <a href="/admin/content/edit/{$id}" class="font-bold text-white hover:text-[#fb326f] transition text-sm">
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
<a href="/admin/content/create?type={$safeType}" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white transition shadow-lg shadow-[#fb326f]/20">
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
                   class="w-56 pl-9 pr-3 py-2 text-xs rounded-xl bg-[#090d16] border border-[#1e293b] text-white placeholder-slate-500 focus:outline-none focus:border-[#fb326f]">
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

        $parentId = (int)$request->post('parent_id', 0);

        $postData = [
            'title'          => $title,
            'slug'           => $slug,
            'post_type'      => $postType,
            'status'         => $status,
            'parent_id'      => $parentId,
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
     * Render the Visual Block Editor & CPT Meta Field Shell (Gutenberg & Spectra style)
     */
    private function renderEditor(?array $post, PostType $cpt, array $blocks, array $meta): Response
    {
        $isEdit = $post !== null;
        $id = $isEdit ? (int)$post['id'] : null;
        $title = $isEdit ? Security::e($post['title']) : '';
        $slug = $isEdit ? Security::e($post['slug']) : '';
        $status = $isEdit ? (string)$post['status'] : 'publish';
        $currentParentId = $isEdit ? (int)($post['parent_id'] ?? 0) : 0;
        $postType = $cpt->getName();
        $safeType = Security::e($postType);
        $safeSingular = Security::e($cpt->getSingular());
        $safePlural = Security::e($cpt->getPlural());

        $csrfField = Csrf::field();
        $formAction = $isEdit ? "/admin/content/save/{$id}" : "/admin/content/save";

        // Query available parent pages (for hierarchical navigation)
        $parentCandidates = $this->posts->query([
            'post_type' => $postType,
            'status'    => ['publish', 'draft'],
            'limit'     => 100,
        ]);
        $parentOptionsHtml = '<option value="0">— Keine (Hauptseite / Top-Level) —</option>';
        foreach ($parentCandidates as $cand) {
            $candId = (int)$cand['id'];
            if ($isEdit && $candId === $id) {
                continue; // Cannot be own parent
            }
            $selected = $candId === $currentParentId ? 'selected' : '';
            $candTitle = Security::e($cand['title']);
            $parentOptionsHtml .= "<option value=\"{$candId}\" {$selected}>{$candTitle}</option>";
        }

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
            $viewLiveBtn = "<a href=\"{$liveUrl}\" target=\"_blank\" rel=\"noopener\" class=\"inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-400 hover:text-white transition px-2.5 py-1.5 rounded-lg hover:bg-slate-800\" title=\"Eintrag im Frontend ansehen\"><span class=\"material-symbols-outlined text-[16px] text-slate-400\">visibility</span><span>Vorschau</span><span class=\"material-symbols-outlined text-[12px] text-slate-500\">open_in_new</span></a>";
        }

        $contentHtml = <<<HTML
<form method="POST" action="{$formAction}" id="content-form"
      x-data="{
          blocks: {$blocksJson},
          catalog: {$blocksCatalogJson},
          viewMode: 'visual', // 'visual' | 'code'
          inserterOpen: false,
          inspectorOpen: (() => { try { return localStorage.getItem('hb_inspector_open') !== 'false'; } catch(e) { return true; } })(),
          inspectorTab: 'document', // 'document' | 'block'
          mediaPickerOpen: false,
          activeTargetBlock: null,
          activeTargetAttr: null,
          activeInsertIndex: null,
          searchQuery: '',
          mediaLibrary: [],
          selectedBlockIndex: null,

          addBlock(type, targetIdx = null) {
              const def = this.catalog[type] || {};
              const newBlock = {
                  id: 'blk_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5),
                  type: type,
                  attrs: JSON.parse(JSON.stringify(def.default_attrs || {})),
                  styles: {},
                  innerBlocks: []
              };
              if (targetIdx !== null && targetIdx >= 0 && targetIdx < this.blocks.length) {
                  this.blocks.splice(targetIdx + 1, 0, newBlock);
                  this.selectedBlockIndex = targetIdx + 1;
              } else {
                  this.blocks.push(newBlock);
                  this.selectedBlockIndex = this.blocks.length - 1;
              }
              this.inspectorTab = 'block';
              this.inserterOpen = false;
              this.activeInsertIndex = null;
          },
          removeBlock(idx) {
              this.blocks.splice(idx, 1);
              if (this.selectedBlockIndex === idx) {
                  this.selectedBlockIndex = null;
                  this.inspectorTab = 'document';
              } else if (this.selectedBlockIndex > idx) {
                  this.selectedBlockIndex--;
              }
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
              this.inspectorTab = 'block';
          },
          openMediaPicker(blockIdx, attrName) {
              this.activeTargetBlock = blockIdx;
              this.activeTargetAttr = attrName;
              this.mediaPickerOpen = true;
              this.fetchMedia();
          },
          selectMedia(url) {
              if (this.activeTargetBlock !== null && this.activeTargetAttr !== null) {
                  if (this.activeTargetBlock === 'featured') {
                      const featInput = document.getElementById('featured_image_input');
                      if (featInput) featInput.value = url;
                  } else if (this.blocks[this.activeTargetBlock] && this.blocks[this.activeTargetBlock].attrs) {
                      this.blocks[this.activeTargetBlock].attrs[this.activeTargetAttr] = url;
                  }
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
                  })
                  .catch(() => {});
          },
          getCardColumns(block, idx) {
              if (block.attrs && block.attrs.columns) {
                  return parseInt(block.attrs.columns, 10);
              }
              // Auto-detect consecutive cards
              let start = idx;
              while (start > 0 && this.blocks[start - 1] && this.blocks[start - 1].type === 'core/card') {
                  start--;
              }
              let end = idx;
              while (end < this.blocks.length - 1 && this.blocks[end + 1] && this.blocks[end + 1].type === 'core/card') {
                  end++;
              }
              const groupLen = (end - start + 1);
              if (groupLen >= 3) return 3;
              if (groupLen === 2) return 2;
              return 1;
          },
          setCardColumns(idx, cols) {
              let start = idx;
              while (start > 0 && this.blocks[start - 1] && this.blocks[start - 1].type === 'core/card') {
                  start--;
              }
              let end = idx;
              while (end < this.blocks.length - 1 && this.blocks[end + 1] && this.blocks[end + 1].type === 'core/card') {
                  end++;
              }
              for (let i = start; i <= end; i++) {
                  if (!this.blocks[i].attrs) this.blocks[i].attrs = {};
                  this.blocks[i].attrs.columns = cols;
              }
          },
          getBlockWidthClass(block, idx) {
              if (block.type !== 'core/card') {
                  return 'editor-col-1';
              }
              const cols = this.getCardColumns(block, idx);
              return 'editor-col-' + (cols || 1);
          },
          getFeaturesGridClass(block) {
              const cols = parseInt(block.attrs?.columns || (block.attrs?.features ? block.attrs.features.length : (block.attrs?.items ? block.attrs.items.length : 3)), 10);
              if (cols === 1) return 'grid-cols-1';
              if (cols === 2) return 'grid-cols-1 md:grid-cols-2';
              if (cols === 4) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4';
              return 'grid-cols-1 md:grid-cols-3';
          },
          addFeatureItem(block) {
              if (!block.attrs) block.attrs = {};
              if (!block.attrs.features && !block.attrs.items) block.attrs.features = [];
              const list = block.attrs.features || block.attrs.items;
              list.push({ icon: '⚡', title: 'Neues Feature', description: 'Kurze Beschreibung des Vorteils...' });
          },
          removeFeatureItem(block, fIdx) {
              const list = block.attrs?.features || block.attrs?.items;
              if (list) list.splice(fIdx, 1);
          },
          init() {
              this.blocks.forEach(b => {
                  if (!b.attrs) b.attrs = {};
                  if (b.type === 'core/hero') {
                      if (b.attrs.cta_text && !b.attrs.primary_btn_text) b.attrs.primary_btn_text = b.attrs.cta_text;
                      if (b.attrs.cta_link && !b.attrs.primary_btn_url) b.attrs.primary_btn_url = b.attrs.cta_link;
                      if (b.attrs.secondary_text && !b.attrs.secondary_btn_text) b.attrs.secondary_btn_text = b.attrs.secondary_text;
                      if (b.attrs.secondary_link && !b.attrs.secondary_btn_url) b.attrs.secondary_btn_url = b.attrs.secondary_link;
                  }
                  if (b.type === 'core/features-grid' && !b.attrs.features && b.attrs.items) {
                      b.attrs.features = b.attrs.items;
                  }
              });
              if (typeof this.\$watch === 'function') {
                  this.\$watch('inspectorOpen', val => {
                      try { localStorage.setItem('hb_inspector_open', val ? 'true' : 'false'); } catch(e) {}
                  });
              }
          }
      }">

    {$csrfField}
    <input type="hidden" name="post_type" value="{$safeType}">
    <input type="hidden" name="content_blocks" :value="JSON.stringify(blocks)">

    <!-- Gutenberg Top Bar -->
    <div class="h-14 -mt-4 sm:-mt-6 md:-mt-8 -mx-4 sm:-mx-6 md:-mx-8 mb-6 px-4 sm:px-6 bg-[#0a0f1d] border-b border-[#1e293b] flex items-center justify-between sticky top-16 z-30">
        <!-- Left: Back Link & Inserter + Mode -->
        <div class="flex items-center space-x-3">
            <a href="/admin/posts?type={$safeType}" class="text-xs text-slate-400 hover:text-white transition flex items-center space-x-1.5" title="Zurück zur Übersicht">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span class="hidden sm:inline">{$safePlural}</span>
            </a>
            <span class="text-slate-700">|</span>

            <!-- Gutenberg Block Inserter Toggle Button (Brand #fb326f) -->
            <button type="button" @click="inserterOpen = !inserterOpen"
                    class="w-8 h-8 rounded-xl flex items-center justify-center transition shadow-md"
                    :class="inserterOpen ? 'bg-slate-700 text-white' : 'bg-[#fb326f] text-white hover:bg-[#e01e5a] shadow-[#fb326f]/20'"
                    title="Block-Bibliothek öffnen (+)">
                <span class="material-symbols-outlined text-[20px]" x-text="inserterOpen ? 'close' : 'add'"></span>
            </button>

            <!-- Mode Switcher -->
            <div class="inline-flex items-center p-0.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs">
                <button type="button" @click="viewMode = 'visual'"
                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                        :class="viewMode === 'visual' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400 hover:text-white'">
                    Visuell
                </button>
                <button type="button" @click="viewMode = 'code'"
                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                        :class="viewMode === 'code' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400 hover:text-white'">
                    Code
                </button>
            </div>
        </div>

        <!-- Right: Preview, Save & Inspector Toggle -->
        <div class="flex items-center space-x-3">
            {$viewLiveBtn}

            <button type="submit"
                    class="px-4 py-1.5 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white text-xs font-semibold shadow-lg shadow-[#fb326f]/20 transition flex items-center space-x-1.5">
                <span class="material-symbols-outlined text-[16px]">save</span>
                <span>Speichern</span>
            </button>

            <button type="button" @click="inspectorOpen = !inspectorOpen"
                    class="p-1.5 rounded-xl border border-[#1e293b] transition flex items-center"
                    :class="inspectorOpen ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-[#141e33]'"
                    title="Seitenleiste ein-/ausblenden">
                <span class="material-symbols-outlined text-[18px]">tune</span>
            </button>
        </div>
    </div>

    <!-- Inserter Backdrop -->
    <div x-show="inserterOpen" x-cloak @click="inserterOpen = false"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 transition-opacity"></div>

    <!-- Gutenberg Inserter Drawer (Slide-Over Panel - Does NOT shrink canvas!) -->
    <div x-show="inserterOpen" x-cloak
         class="fixed inset-y-0 left-0 w-80 bg-[#0a0f1d] border-r border-[#1e293b] p-5 shadow-2xl z-50 flex flex-col space-y-4 overflow-hidden transform transition-transform duration-200">
        
        <div class="flex items-center justify-between pb-3 border-b border-[#1e293b]">
            <div class="flex items-center space-x-2">
                <span class="material-symbols-outlined text-[#fb326f] text-[20px]">add_circle</span>
                <h3 class="text-xs font-bold text-white uppercase tracking-wider">Block einfügen</h3>
            </div>
            <button type="button" @click="inserterOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <!-- Inserter Search -->
        <div class="relative">
            <input type="text" x-model="searchQuery" placeholder="Blöcke durchsuchen..."
                   class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#fb326f]">
            <span class="material-symbols-outlined text-slate-500 text-[16px] absolute left-2.5 top-2 pointer-events-none">search</span>
        </div>

        <!-- Categorized Inserter List -->
        <div class="space-y-4 max-h-[75vh] overflow-y-auto pr-1">
            
            <!-- Text Category -->
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Text &amp; Typografie</h4>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="addBlock('core/heading', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">title</span>
                            <span class="text-xs font-bold">Überschrift</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">H1 bis H6 mit Untertitel</span>
                    </button>
                    <button type="button" @click="addBlock('core/paragraph', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">notes</span>
                            <span class="text-xs font-bold">Absatz</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Fließtext &amp; Lead</span>
                    </button>
                    <button type="button" @click="addBlock('core/quote', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">format_quote</span>
                            <span class="text-xs font-bold">Zitat</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Kundenstimme &amp; Zitat</span>
                    </button>
                </div>
            </div>

            <!-- Layout & Hero Category -->
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Layout &amp; Grid</h4>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="addBlock('core/hero', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">star</span>
                            <span class="text-xs font-bold">Hero Banner</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Großer Startbereich &amp; CTA</span>
                    </button>
                    <button type="button" @click="addBlock('core/card', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">dashboard</span>
                            <span class="text-xs font-bold">Inhaltskarte</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">1 bis 4 Spalten Raster</span>
                    </button>
                    <button type="button" @click="addBlock('core/features', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">grid_view</span>
                            <span class="text-xs font-bold">Feature Raster</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Karten nebeneinander</span>
                    </button>
                    <button type="button" @click="addBlock('core/cta-banner', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">campaign</span>
                            <span class="text-xs font-bold">CTA Banner</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Große Aktionsbox</span>
                    </button>
                </div>
            </div>

            <!-- Medien & Aktionen -->
            <div>
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Medien &amp; Interaktion</h4>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="addBlock('core/image', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">image</span>
                            <span class="text-xs font-bold">Bild</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Mediathek &amp; WebP</span>
                    </button>
                    <button type="button" @click="addBlock('core/button', activeInsertIndex)"
                            class="p-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] hover:bg-[#141e33] transition text-left flex flex-col space-y-1 group">
                        <div class="flex items-center space-x-1.5 text-white group-hover:text-[#fb326f]">
                            <span class="material-symbols-outlined text-[16px]">smart_button</span>
                            <span class="text-xs font-bold">Button CTA</span>
                        </div>
                        <span class="text-[10px] text-slate-500 line-clamp-1">Aktions-Link &amp; Stil</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Main Workspace Layout -->
    <div class="flex flex-col lg:flex-row gap-6 relative items-start">

        <!-- Center Column: Gutenberg Document Canvas (Full Width & Multi-Column Grid) -->
        <div class="flex-1 w-full min-w-0">
            
            <!-- Document Title & Header Area -->
            <div class="mb-8">
                <input type="text" id="post-title" name="title" value="{$title}" required
                       placeholder="Titel hier eingeben..."
                       class="w-full text-3xl sm:text-4xl font-extrabold text-white placeholder-slate-600 bg-transparent border-0 border-b border-transparent hover:border-[#1e293b] focus:border-[#fb326f] focus:outline-none py-2 tracking-tight transition">
                
                <div class="mt-2 flex items-center space-x-2 text-xs text-slate-500">
                    <span class="font-mono">Permalink:</span>
                    <span class="font-mono text-slate-400">http://localhost:8000/</span>
                    <input type="text" name="slug" value="{$slug}" placeholder="automatisch"
                           class="bg-transparent text-[#fb326f] font-mono text-xs border-b border-dashed border-slate-700 hover:border-white focus:outline-none focus:border-[#fb326f] px-1 py-0.5">
                </div>
            </div>

            <!-- Visual Block Canvas Flow (Flex-wrap layout allows cards to sit side by side!) -->
            <div x-show="viewMode === 'visual'" class="flex flex-wrap gap-5 pb-20 items-stretch">

                <!-- Empty State Prompt -->
                <div x-show="blocks.length === 0" class="w-full py-16 text-center border-2 border-dashed border-[#1e293b] rounded-2xl p-8">
                    <div class="w-12 h-12 rounded-2xl bg-[#fb326f]/10 text-[#fb326f] flex items-center justify-center mx-auto mb-3">
                        <span class="material-symbols-outlined text-[24px]">post_add</span>
                    </div>
                    <h3 class="text-sm font-bold text-white mb-1">Dokument ist noch leer</h3>
                    <p class="text-xs text-slate-400 mb-5">Fügen Sie Ihren ersten Inhaltsblock hinzu, um die Seite zu gestalten.</p>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <button type="button" @click="addBlock('core/hero')" class="px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] text-xs font-semibold text-white transition">+ Hero Banner</button>
                        <button type="button" @click="addBlock('core/heading')" class="px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] text-xs font-semibold text-white transition">+ Überschrift</button>
                        <button type="button" @click="addBlock('core/paragraph')" class="px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] text-xs font-semibold text-white transition">+ Textabsatz</button>
                        <button type="button" @click="addBlock('core/card')" class="px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] text-xs font-semibold text-white transition">+ Inhaltskarte</button>
                    </div>
                </div>

                <!-- Repeatable Visual Blocks (Width dynamically assigned according to columns!) -->
                <template x-for="(block, idx) in blocks" :key="block.id">
                    <div class="relative group transition-all"
                         :class="[
                             getBlockWidthClass(block, idx),
                             block.type === 'core/card' 
                                 ? (selectedBlockIndex === idx ? 'rounded-2xl p-6 bg-[#0f172a] border-2 border-[#fb326f] shadow-2xl' : 'rounded-2xl p-6 bg-[#0f172a] border border-[#1e293b] hover:border-slate-700 shadow-md')
                                 : (block.type === 'core/hero' || block.type === 'core/cta-banner'
                                     ? (selectedBlockIndex === idx ? 'rounded-2xl ring-2 ring-[#fb326f] shadow-2xl' : 'rounded-2xl')
                                     : (selectedBlockIndex === idx ? 'rounded-xl p-5 bg-[#0a0f1d] ring-1 ring-[#fb326f] shadow-lg' : 'rounded-xl p-5 bg-transparent border border-transparent hover:border-[#1e293b] hover:bg-[#0a0f1d]/40 transition')
                                 )
                         ]"
                         @click="selectedBlockIndex = idx; inspectorTab = 'block'">
                        
                        <!-- Floating Gutenberg Block Toolbar (Active block only) -->
                        <div x-show="selectedBlockIndex === idx" class="flex flex-wrap items-center justify-between pb-3 mb-4 border-b border-[#1e293b] gap-2">
                            <!-- Block Type Badge & Controls -->
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md bg-[#fb326f]/10 text-[#fb326f] border border-[#fb326f]/20 text-[10px] font-bold font-mono uppercase"
                                      x-text="catalog[block.type]?.name || block.type"></span>

                                <!-- Quick Heading Levels -->
                                <div x-show="block.type === 'core/heading'" class="flex items-center space-x-1 ml-2">
                                    <button type="button" @click.stop="block.attrs.level = 'h1'" :class="block.attrs.level === 'h1' ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">H1</button>
                                    <button type="button" @click.stop="block.attrs.level = 'h2'" :class="block.attrs.level === 'h2' ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">H2</button>
                                    <button type="button" @click.stop="block.attrs.level = 'h3'" :class="block.attrs.level === 'h3' ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">H3</button>
                                </div>

                                <!-- Quick Lead Toggle for Paragraph -->
                                <div x-show="block.type === 'core/paragraph'" class="ml-2">
                                    <button type="button" @click.stop="block.attrs.lead = !block.attrs.lead" :class="block.attrs.lead ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white'" class="px-2 py-0.5 rounded text-[10px] font-bold border border-slate-700">Lead</button>
                                </div>

                                <!-- Quick Column Switcher for Cards -->
                                <div x-show="block.type === 'core/card'" class="flex items-center space-x-1 ml-2">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase mr-1">Spalten:</span>
                                    <button type="button" @click.stop="setCardColumns(idx, 1)" :class="getCardColumns(block, idx) == 1 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold" title="1 Spalte (Volle Breite)">1 Voll</button>
                                    <button type="button" @click.stop="setCardColumns(idx, 2)" :class="getCardColumns(block, idx) == 2 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold" title="2 Spalten (50% Breite)">2er</button>
                                    <button type="button" @click.stop="setCardColumns(idx, 3)" :class="getCardColumns(block, idx) == 3 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold" title="3 Spalten (33% Breite)">3er</button>
                                    <button type="button" @click.stop="setCardColumns(idx, 4)" :class="getCardColumns(block, idx) == 4 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold" title="4 Spalten (25% Breite)">4er</button>
                                </div>

                                <!-- Quick Column Switcher for Features Grid -->
                                <div x-show="block.type === 'core/features' || block.type === 'core/features-grid'" class="flex items-center space-x-1 ml-2">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase mr-1">Raster:</span>
                                    <button type="button" @click.stop="block.attrs.columns = 1" :class="(block.attrs?.columns || 3) == 1 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">1</button>
                                    <button type="button" @click.stop="block.attrs.columns = 2" :class="(block.attrs?.columns || 3) == 2 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">2</button>
                                    <button type="button" @click.stop="block.attrs.columns = 3" :class="(block.attrs?.columns || 3) == 3 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">3</button>
                                    <button type="button" @click.stop="block.attrs.columns = 4" :class="(block.attrs?.columns || 3) == 4 ? 'bg-[#fb326f] text-white' : 'text-slate-400 hover:text-white bg-[#090d16] border border-[#1e293b]'" class="px-1.5 py-0.5 rounded text-[10px] font-bold">4</button>
                                </div>
                            </div>

                            <!-- Actions Toolbar -->
                            <div class="flex items-center space-x-1 text-slate-400">
                                <button type="button" @click.stop="moveBlock(idx, -1)" :disabled="idx === 0" title="Nach oben verschieben"
                                        class="p-1 rounded hover:text-white disabled:opacity-20 transition flex items-center">
                                    <span class="material-symbols-outlined text-[18px]">keyboard_arrow_up</span>
                                </button>
                                <button type="button" @click.stop="moveBlock(idx, 1)" :disabled="idx === blocks.length - 1" title="Nach unten verschieben"
                                        class="p-1 rounded hover:text-white disabled:opacity-20 transition flex items-center">
                                    <span class="material-symbols-outlined text-[18px]">keyboard_arrow_down</span>
                                </button>
                                <button type="button" @click.stop="duplicateBlock(idx)" title="Block duplizieren"
                                        class="p-1 rounded hover:text-white transition flex items-center">
                                    <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                </button>
                                <button type="button" @click.stop="removeBlock(idx)" title="Block entfernen"
                                        class="p-1 rounded hover:text-rose-400 transition flex items-center">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        </div>

                        <!-- In-Place Block Fields (Visual preview & direct editing) -->
                        <div class="space-y-3">
                            
                            <!-- 1. Hero Block -->
                            <div x-show="block.type === 'core/hero'" class="py-12 px-6 sm:px-10 rounded-2xl bg-gradient-to-b from-[#141e33] to-[#090d16] border border-[#1e293b] space-y-4 text-center">
                                <input type="text" x-model="block.attrs.badge" placeholder="Badge (z.B. NEU / AGENTUR)"
                                       class="px-3 py-1 text-xs font-bold rounded-full bg-[#fb326f]/10 text-[#fb326f] border border-[#fb326f]/20 text-center mx-auto inline-block focus:outline-none">
                                <input type="text" x-model="block.attrs.headline" placeholder="Große Hero-Überschrift"
                                       class="w-full text-3xl sm:text-4xl md:text-5xl font-black text-white text-center bg-transparent border-none focus:outline-none tracking-tight leading-tight">
                                <textarea x-model="block.attrs.subheadline" rows="2" placeholder="Prägnanter Untertitel für den Einstiegsbereich..."
                                          class="w-full text-base text-slate-300 text-center bg-transparent border-none focus:outline-none leading-relaxed max-w-3xl mx-auto block"></textarea>
                                <div class="flex flex-wrap items-center justify-center gap-3 pt-3">
                                    <div class="flex items-center space-x-1 px-4 py-2 rounded-xl bg-[#fb326f] text-white">
                                        <input type="text" x-model="block.attrs.primary_btn_text" placeholder="Button Text"
                                               class="bg-transparent text-white font-semibold text-xs border-none focus:outline-none w-28 text-center">
                                    </div>
                                    <div class="flex items-center space-x-1 px-4 py-2 rounded-xl bg-[#1e293b] text-slate-300">
                                        <input type="text" x-model="block.attrs.primary_btn_url" placeholder="Ziel-URL (/kontakt)"
                                               class="bg-transparent text-slate-300 text-xs border-none focus:outline-none w-32 font-mono text-center">
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Heading Block -->
                            <div x-show="block.type === 'core/heading'" class="space-y-1.5 py-1">
                                <input type="text" x-model="block.attrs.subtitle" placeholder="KICKER / UNTERTITEL (OPTIONAL)"
                                       class="w-full text-xs font-bold tracking-widest text-[#fb326f] uppercase bg-transparent border-none placeholder-[#fb326f]/30 focus:outline-none">
                                <input type="text" x-model="block.attrs.content" placeholder="Überschrift eingeben..."
                                       :class="block.attrs.level === 'h1' ? 'text-3xl sm:text-4xl font-black' : (block.attrs.level === 'h3' ? 'text-xl sm:text-2xl font-bold' : 'text-2xl sm:text-3xl font-extrabold')"
                                       class="w-full text-white bg-transparent border-none placeholder-slate-600 focus:outline-none tracking-tight leading-tight">
                            </div>

                            <!-- 3. Paragraph Block -->
                            <div x-show="block.type === 'core/paragraph'" class="py-1">
                                <textarea x-model="block.attrs.content" rows="3" placeholder="Geben Sie hier Ihren Fließtext ein..."
                                          :class="block.attrs.lead ? 'text-lg text-slate-200 font-medium' : 'text-base text-slate-300 font-normal'"
                                          class="w-full leading-relaxed bg-transparent border-none placeholder-slate-600 focus:outline-none resize-y"></textarea>
                            </div>

                            <!-- 4. Card Block (Clean, un-nested Card!) -->
                            <div x-show="block.type === 'core/card'" class="space-y-3 h-full flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center space-x-3 mb-3">
                                        <div class="w-10 h-10 rounded-xl bg-[#fb326f]/10 text-[#fb326f] flex items-center justify-center font-bold text-lg flex-shrink-0">
                                            <input type="text" x-model="block.attrs.icon" placeholder="⚡"
                                                   class="w-full text-center bg-transparent border-none focus:outline-none text-base">
                                        </div>
                                        <input type="text" x-model="block.attrs.title" placeholder="Kartentitel eingeben..."
                                               class="flex-1 py-1 text-base font-bold text-white bg-transparent border-b border-transparent hover:border-[#1e293b] focus:border-[#fb326f] focus:outline-none transition">
                                    </div>
                                    <textarea x-model="block.attrs.description" rows="3" placeholder="Kurze Beschreibung für diese Karte..."
                                              class="w-full text-sm text-slate-300 bg-transparent border-none focus:outline-none leading-relaxed resize-y"></textarea>
                                </div>
                                <div class="pt-3 border-t border-[#1e293b]/60 flex items-center space-x-2 text-xs text-slate-400">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">link</span>
                                    <input type="text" x-model="block.attrs.link_url" placeholder="Link-URL (optional)..." class="bg-transparent flex-1 border-none focus:outline-none text-xs text-slate-400 font-mono">
                                </div>
                            </div>

                            <!-- 5. Features Grid Block (Multi-card Grid) -->
                            <div x-show="block.type === 'core/features' || block.type === 'core/features-grid'" class="space-y-4">
                                <input type="text" x-model="block.attrs.title" placeholder="Raster-Titel eingeben (z.B. Unsere Kernvorteile)..."
                                       class="w-full text-xl sm:text-2xl font-black text-white bg-transparent border-none focus:outline-none tracking-tight">
                                
                                <div class="grid gap-4" :class="getFeaturesGridClass(block)">
                                    <template x-for="(feat, fIdx) in (block.attrs?.features || block.attrs?.items || [])" :key="fIdx">
                                        <div class="p-4 rounded-xl bg-[#0a0f1d] border border-[#1e293b] space-y-2 relative group/card flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <input type="text" x-model="feat.icon" placeholder="Icon" class="w-10 text-center py-1 rounded-lg bg-[#141e33] border border-[#1e293b] text-sm text-white">
                                                    <button type="button" @click.stop="removeFeatureItem(block, fIdx)" class="text-slate-500 hover:text-rose-400 p-1 opacity-0 group-hover/card:opacity-100 transition" title="Karte entfernen">
                                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                                    </button>
                                                </div>
                                                <input type="text" x-model="feat.title" placeholder="Titel..." class="w-full py-1 text-sm font-bold text-white bg-transparent border-b border-[#1e293b] focus:border-[#fb326f] focus:outline-none">
                                                <textarea x-model="feat.description" rows="2" placeholder="Beschreibung..." class="w-full text-xs text-slate-300 bg-transparent border-none focus:outline-none mt-2"></textarea>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="pt-2 text-center">
                                    <button type="button" @click.stop="addFeatureItem(block)" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-[#141e33] border border-[#1e293b] hover:border-[#fb326f] text-slate-300 hover:text-white text-xs font-semibold transition">
                                        <span class="material-symbols-outlined text-[16px] text-[#fb326f]">add</span>
                                        <span>+ Neue Karte im Raster hinzufügen</span>
                                    </button>
                                </div>
                            </div>

                            <!-- 6. CTA Banner Block -->
                            <div x-show="block.type === 'core/cta-banner'" class="p-6 rounded-2xl bg-gradient-to-r from-[#141e33] via-[#1a1435] to-[#251025] border border-[#1e293b] text-center space-y-3">
                                <input type="text" x-model="block.attrs.headline" placeholder="Große CTA-Banner Überschrift..." class="w-full text-xl sm:text-2xl font-black text-white text-center bg-transparent border-none focus:outline-none tracking-tight">
                                <textarea x-model="block.attrs.subheadline" rows="2" placeholder="Optionaler Untertitel..." class="w-full text-xs text-slate-300 text-center bg-transparent border-none focus:outline-none"></textarea>
                                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                                    <div class="flex items-center space-x-1 px-3 py-1.5 rounded-xl bg-[#fb326f] text-white">
                                        <input type="text" x-model="block.attrs.button" placeholder="Button Text" class="bg-transparent text-white font-semibold text-xs border-none focus:outline-none w-32">
                                    </div>
                                    <div class="flex items-center space-x-1 px-3 py-1.5 rounded-xl bg-[#1e293b] text-slate-300">
                                        <input type="text" x-model="block.attrs.link" placeholder="Ziel-URL (/kontakt)" class="bg-transparent text-slate-300 text-xs border-none focus:outline-none w-36 font-mono">
                                    </div>
                                </div>
                            </div>

                            <!-- 7. Image Block -->
                            <div x-show="block.type === 'core/image'" class="space-y-3">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-1">
                                        <input type="text" x-model="block.attrs.url" placeholder="Bild-URL (/uploads/...)"
                                               class="w-full px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white font-mono">
                                    </div>
                                    <button type="button" @click="openMediaPicker(idx, 'url')"
                                            class="px-3 py-1.5 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white text-xs font-semibold shadow transition">
                                        Mediathek
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="block.attrs.alt" placeholder="Alt-Text (Barrierefreiheit)"
                                           class="px-3 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-slate-300">
                                    <input type="text" x-model="block.attrs.caption" placeholder="Bildunterschrift (optional)"
                                           class="px-3 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs text-slate-400">
                                </div>
                            </div>

                            <!-- 8. Button Block -->
                            <div x-show="block.type === 'core/button'" class="flex flex-wrap items-center gap-3">
                                <div class="px-4 py-2 rounded-xl bg-[#fb326f] text-white">
                                    <input type="text" x-model="block.attrs.text" placeholder="Button Text"
                                           class="bg-transparent text-white font-semibold text-xs border-none focus:outline-none w-32">
                                </div>
                                <input type="text" x-model="block.attrs.url" placeholder="Ziel-URL (z.B. /kontakt)"
                                       class="px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300 font-mono flex-1">
                            </div>

                            <!-- 9. Quote Block -->
                            <div x-show="block.type === 'core/quote'" class="pl-4 border-l-2 border-[#fb326f] space-y-2">
                                <textarea x-model="block.attrs.quote" rows="2" placeholder="Zitattext hier eingeben..."
                                          class="w-full text-sm italic text-slate-200 bg-transparent border-none focus:outline-none"></textarea>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="block.attrs.author" placeholder="Name des Zitierten"
                                           class="px-2.5 py-1 rounded bg-[#090d16] border border-[#1e293b] text-xs text-white">
                                    <input type="text" x-model="block.attrs.role" placeholder="Funktion / Unternehmen"
                                           class="px-2.5 py-1 rounded bg-[#090d16] border border-[#1e293b] text-xs text-slate-400">
                                </div>
                            </div>

                        </div>

                        <!-- Inline Inserter Hover Trigger (Between Blocks) -->
                        <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition z-10">
                            <button type="button" @click.stop="activeInsertIndex = idx; inserterOpen = true"
                                    class="w-6 h-6 rounded-full bg-[#fb326f] hover:bg-[#e01e5a] text-white flex items-center justify-center shadow-lg transition transform hover:scale-110"
                                    title="Hier einen Block einfügen (+)">
                                <span class="material-symbols-outlined text-[16px]">add</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Bottom Inserter Trigger -->
                <div class="w-full pt-4 text-center">
                    <button type="button" @click="activeInsertIndex = null; inserterOpen = true"
                            class="inline-flex items-center space-x-1.5 px-4 py-2 rounded-xl border border-dashed border-[#1e293b] hover:border-[#fb326f] text-slate-400 hover:text-white text-xs font-semibold transition">
                        <span class="material-symbols-outlined text-[18px] text-[#fb326f]">add</span>
                        <span>Neuen Block am Ende hinzufügen</span>
                    </button>
                </div>

            </div>

            <!-- Code / Raw JSON View Mode -->
            <div x-show="viewMode === 'code'" x-cloak style="display: none;" class="space-y-3 pb-20">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">JSON Block Baum (Gutenberg Standard)</span>
                    <span class="text-[11px] text-slate-500 font-mono">Direkt editierbar</span>
                </div>
                <textarea rows="20"
                          :value="JSON.stringify(blocks, null, 2)"
                          @input="try { blocks = JSON.parse(\$event.target.value) } catch(e) {}"
                          class="w-full p-4 rounded-xl bg-[#090d16] border border-[#1e293b] font-mono text-xs text-[#fb326f] focus:outline-none focus:border-[#fb326f] leading-relaxed"></textarea>
            </div>

        </div>

        <!-- Right Column: Gutenberg Inspector Sidebar (Tabs: Dokument & Block) -->
        <div x-show="inspectorOpen" x-cloak
             :class="inspectorOpen ? 'block' : 'hidden'"
             class="w-full lg:w-80 flex-shrink-0 bg-[#0a0f1d] border border-[#1e293b] rounded-2xl p-5 shadow-2xl space-y-5 sticky top-32 z-20">
            
            <!-- Inspector Header & Close Button -->
            <div class="flex items-center justify-between pb-1">
                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center space-x-1.5">
                    <span class="material-symbols-outlined text-[16px] text-slate-400">tune</span>
                    <span>Inspektor</span>
                </span>
                <button type="button" @click="inspectorOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-[#141e33] transition"
                        title="Inspektor schließen">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <!-- Inspector Tabs -->
            <div class="flex items-center space-x-1 p-1 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs">
                <button type="button" @click="inspectorTab = 'document'"
                        class="flex-1 py-1.5 rounded-lg font-bold text-center transition"
                        :class="inspectorTab === 'document' ? 'bg-[#fb326f] text-white shadow' : 'text-slate-400 hover:text-white'">
                    Dokument
                </button>
                <button type="button" @click="inspectorTab = 'block'"
                        class="flex-1 py-1.5 rounded-lg font-bold text-center transition"
                        :class="inspectorTab === 'block' ? 'bg-[#fb326f] text-white shadow' : 'text-slate-400 hover:text-white'">
                    Block
                </button>
            </div>

            <!-- TAB 1: Document Settings -->
            <div x-show="inspectorTab === 'document'" class="space-y-5 text-xs">
                
                <!-- Status & Visibility -->
                <div>
                    <label class="block font-semibold text-slate-300 mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-white">
                        <option value="publish" {$this->selected($status, 'publish')}>Veröffentlicht</option>
                        <option value="draft" {$this->selected($status, 'draft')}>Entwurf</option>
                    </select>
                </div>

                <!-- Page Hierarchy (Parent Page) -->
                <div>
                    <label class="block font-semibold text-slate-300 mb-1.5">Übergeordnete Seite</label>
                    <select name="parent_id" class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-white">
                        {$parentOptionsHtml}
                    </select>
                    <p class="text-[10px] text-slate-500 mt-1">Ermöglicht hierarchische Menüs (z.B. Über uns &rarr; Team).</p>
                </div>

                <!-- Featured Image (Thumbnail) -->
                <div>
                    <label class="block font-semibold text-slate-300 mb-1.5">Beitragsbild (Thumbnail)</label>
                    <div class="space-y-2">
                        <input type="text" id="featured_image_input" name="featured_image" value="{$thumbnailUrl}" placeholder="/uploads/..."
                               class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-slate-300 font-mono text-[11px]">
                        <button type="button" @click="openMediaPicker('featured', '_thumbnail_url')"
                                class="w-full py-1.5 rounded-xl bg-[#141e33] border border-[#1e293b] hover:border-[#fb326f] text-[#fb326f] font-semibold flex items-center justify-center space-x-1 transition">
                            <span class="material-symbols-outlined text-[16px]">photo_library</span>
                            <span>Aus Mediathek wählen</span>
                        </button>
                    </div>
                </div>

                <!-- Excerpt -->
                <div>
                    <label class="block font-semibold text-slate-300 mb-1.5">Textauszug (Excerpt)</label>
                    <textarea name="excerpt" rows="3" placeholder="Zusammenfassung..."
                              class="w-full px-3 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-slate-300 leading-normal">{$excerpt}</textarea>
                </div>

                <!-- Custom Meta Fields -->
                {$metaFieldsHtml}

            </div>

            <!-- TAB 2: Active Block Settings -->
            <div x-show="inspectorTab === 'block'" class="space-y-4 text-xs">
                <template x-if="selectedBlockIndex !== null && blocks[selectedBlockIndex]">
                    <div class="space-y-4">
                        <div class="p-3 rounded-xl bg-[#090d16] border border-[#1e293b]">
                            <span class="text-[10px] text-slate-500 font-mono uppercase block">Ausgewählter Block:</span>
                            <span class="text-sm font-bold text-white block mt-0.5" x-text="catalog[blocks[selectedBlockIndex].type]?.name || blocks[selectedBlockIndex].type"></span>
                            <span class="text-[10px] text-slate-400 block mt-1" x-text="catalog[blocks[selectedBlockIndex].type]?.description"></span>
                        </div>

                        <!-- Card Column Setting -->
                        <div x-show="blocks[selectedBlockIndex].type === 'core/card'" class="p-3 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-2">
                            <label class="block font-semibold text-slate-300">Spaltenbreite (Raster)</label>
                            <p class="text-[10px] text-slate-400">Bestimmt, wie viele Karten nebeneinander auf dem Bildschirm Platz finden.</p>
                            <div class="grid grid-cols-4 gap-1.5 pt-1">
                                <button type="button" @click="setCardColumns(selectedBlockIndex, 1)"
                                        :class="getCardColumns(blocks[selectedBlockIndex], selectedBlockIndex) === 1 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">1 Voll</button>
                                <button type="button" @click="setCardColumns(selectedBlockIndex, 2)"
                                        :class="getCardColumns(blocks[selectedBlockIndex], selectedBlockIndex) === 2 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">2er (50%)</button>
                                <button type="button" @click="setCardColumns(selectedBlockIndex, 3)"
                                        :class="getCardColumns(blocks[selectedBlockIndex], selectedBlockIndex) === 3 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">3er (33%)</button>
                                <button type="button" @click="setCardColumns(selectedBlockIndex, 4)"
                                        :class="getCardColumns(blocks[selectedBlockIndex], selectedBlockIndex) === 4 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">4er (25%)</button>
                            </div>
                        </div>

                        <!-- Features Grid Setting -->
                        <div x-show="blocks[selectedBlockIndex].type === 'core/features' || blocks[selectedBlockIndex].type === 'core/features-grid'" class="p-3 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-2">
                            <label class="block font-semibold text-slate-300">Raster-Spalten</label>
                            <div class="grid grid-cols-4 gap-1.5 pt-1">
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.columns = 1"
                                        :class="(blocks[selectedBlockIndex].attrs?.columns || 3) == 1 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">1 Voll</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.columns = 2"
                                        :class="(blocks[selectedBlockIndex].attrs?.columns || 3) == 2 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">2 Spalten</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.columns = 3"
                                        :class="(blocks[selectedBlockIndex].attrs?.columns || 3) == 3 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">3 Spalten</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.columns = 4"
                                        :class="(blocks[selectedBlockIndex].attrs?.columns || 3) == 4 ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">4 Spalten</button>
                            </div>
                        </div>

                        <!-- Heading Level -->
                        <div x-show="blocks[selectedBlockIndex].type === 'core/heading'" class="p-3 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-2">
                            <label class="block font-semibold text-slate-300">Hierarchie-Stufe</label>
                            <div class="grid grid-cols-4 gap-1.5 pt-1">
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.level = 'h1'"
                                        :class="blocks[selectedBlockIndex].attrs.level === 'h1' ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">H1</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.level = 'h2'"
                                        :class="(!blocks[selectedBlockIndex].attrs.level || blocks[selectedBlockIndex].attrs.level === 'h2') ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">H2</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.level = 'h3'"
                                        :class="blocks[selectedBlockIndex].attrs.level === 'h3' ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">H3</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.level = 'h4'"
                                        :class="blocks[selectedBlockIndex].attrs.level === 'h4' ? 'bg-[#fb326f] text-white font-bold' : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]'"
                                        class="py-1.5 rounded-lg text-center text-[11px] transition">H4</button>
                            </div>
                        </div>

                        <!-- Paragraph Style -->
                        <div x-show="blocks[selectedBlockIndex].type === 'core/paragraph'" class="p-3 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-2">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" x-model="blocks[selectedBlockIndex].attrs.lead" class="rounded bg-[#141e33] border-[#1e293b] text-[#fb326f] focus:ring-[#fb326f]">
                                <span class="font-semibold text-slate-300">Lead-Absatz (Hervorgehoben / Große Schrift)</span>
                            </label>
                        </div>

                        <!-- Block Alignment -->
                        <div x-show="blocks[selectedBlockIndex].attrs.alignment !== undefined">
                            <label class="block font-semibold text-slate-300 mb-1.5">Ausrichtung</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.alignment = 'left'"
                                        :class="blocks[selectedBlockIndex].attrs.alignment === 'left' ? 'bg-[#fb326f] text-white' : 'bg-[#090d16] text-slate-400 border border-[#1e293b]'"
                                        class="py-1 rounded-lg text-center font-bold">Links</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.alignment = 'center'"
                                        :class="blocks[selectedBlockIndex].attrs.alignment === 'center' ? 'bg-[#fb326f] text-white' : 'bg-[#090d16] text-slate-400 border border-[#1e293b]'"
                                        class="py-1 rounded-lg text-center font-bold">Zentriert</button>
                                <button type="button" @click="blocks[selectedBlockIndex].attrs.alignment = 'right'"
                                        :class="blocks[selectedBlockIndex].attrs.alignment === 'right' ? 'bg-[#fb326f] text-white' : 'bg-[#090d16] text-slate-400 border border-[#1e293b]'"
                                        class="py-1 rounded-lg text-center font-bold">Rechts</button>
                            </div>
                        </div>

                        <!-- Block ID Anchor -->
                        <div>
                            <label class="block font-semibold text-slate-300 mb-1.5">HTML-Anker (ID)</label>
                            <input type="text" x-model="blocks[selectedBlockIndex].id"
                                   class="w-full px-3 py-1.5 rounded-xl bg-[#090d16] border border-[#1e293b] font-mono text-[11px] text-slate-300">
                            <p class="text-[10px] text-slate-500 mt-1">Eindeutige ID für Ankerlinks (#id).</p>
                        </div>
                    </div>
                </template>

                <template x-if="selectedBlockIndex === null || !blocks[selectedBlockIndex]">
                    <div class="py-10 text-center text-slate-500">
                        <span class="material-symbols-outlined text-[28px] opacity-40 mb-2">touch_app</span>
                        <p>Klicken Sie auf einen Block im Dokument, um dessen Eigenschaften hier anzupassen.</p>
                    </div>
                </template>
            </div>

        </div>

    </div>

    <!-- Media Picker Modal (Only in DOM when open) -->
    <template x-if="mediaPickerOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div @click.outside="mediaPickerOpen = false"
                 class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 max-w-3xl w-full shadow-2xl relative">
                
                <div class="flex items-center justify-between pb-4 border-b border-[#1e293b] mb-4">
                    <div class="flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[#fb326f]">photo_library</span>
                        <h3 class="text-sm font-bold text-white">Bild aus Mediathek wählen</h3>
                    </div>
                    <button type="button" @click="mediaPickerOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <div x-show="mediaLibrary.length === 0" class="py-12 text-center text-slate-500 text-xs">
                    Noch keine Bilder in der Mediathek hochgeladen. Nutzen Sie den Medien-Manager unter <strong>Medien</strong>.
                </div>

                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 max-h-[50vh] overflow-y-auto pr-1">
                    <template x-for="item in mediaLibrary" :key="item.id">
                        <div @click="selectMedia(item._webp_url || item._url)"
                             class="aspect-square rounded-xl bg-[#090d16] border border-[#1e293b] hover:border-[#fb326f] cursor-pointer overflow-hidden p-1 transition">
                            <img :src="item._webp_url || item._url" :alt="item.title" class="w-full h-full object-cover rounded-lg">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

</form>
HTML;

        $navSlug = $postType === 'page' ? 'pages' : 'posts';
        return Response::html(AdminView::renderShell('', $navSlug, $contentHtml, [
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
                ? 'bg-[#fb326f] text-white shadow-lg shadow-[#fb326f]/20 font-bold'
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
            ? 'bg-[#fb326f]/10 text-[#fb326f] border border-[#fb326f]/20 font-bold'
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
