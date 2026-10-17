<?php
declare(strict_types=1);

namespace Heartbeat\Content;

use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Database\Database;
use Heartbeat\Security\Security;
use InvalidArgumentException;

/**
 * Post & Content Management Service
 * Handles querying, CRUD, slug uniqueness, and meta hydration for Pages, Posts, and Custom Post Types.
 */
final class PostService
{
    private static ?PostService $instance = null;
    private Database $db;
    private MetaService $meta;
    private BlockRenderer $renderer;

    public function __construct(?Database $db = null, ?MetaService $meta = null, ?BlockRenderer $renderer = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->meta = $meta ?? MetaService::getInstance();
        $this->renderer = $renderer ?? BlockRenderer::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Find single post by ID and hydrate metadata
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $post = $this->db->fetchOne(
            "SELECT * FROM {prefix}posts WHERE id = :id LIMIT 1",
            ['id' => $id]
        );

        if (!$post) {
            return null;
        }

        $post['meta'] = $this->meta->getAllPostMeta((int)$post['id']);
        return $post;
    }

    /**
     * Find single post by slug and post type
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, string $postType = 'page'): ?array
    {
        $post = $this->db->fetchOne(
            "SELECT * FROM {prefix}posts WHERE slug = :slug AND post_type = :type LIMIT 1",
            ['slug' => $slug, 'type' => $postType]
        );

        if (!$post) {
            return null;
        }

        $post['meta'] = $this->meta->getAllPostMeta((int)$post['id']);
        return $post;
    }

    /**
     * Query posts with flexible filters
     * @param array<string, mixed> $args
     * @return array<array<string, mixed>>
     */
    public function query(array $args = []): array
    {
        $postType = $args['post_type'] ?? 'page';
        $status = $args['status'] ?? 'publish';
        $search = trim((string)($args['search'] ?? ''));
        $authorId = isset($args['author_id']) ? (int)$args['author_id'] : null;
        $limit = max(1, min(100, (int)($args['limit'] ?? 20)));
        $offset = max(0, (int)($args['offset'] ?? 0));
        $orderBy = isset($args['order_by']) && in_array($args['order_by'], ['id', 'title', 'created_at', 'updated_at', 'published_at'], true)
            ? (string)$args['order_by']
            : 'created_at';
        $orderDir = strtoupper((string)($args['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        // Post Type filter
        if ($postType !== 'any') {
            if (is_array($postType)) {
                $placeholders = [];
                foreach ($postType as $i => $pt) {
                    $key = 'pt_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $pt;
                }
                $where[] = 'p.post_type IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'p.post_type = :pt';
                $params['pt'] = $postType;
            }
        }

        // Status filter
        if ($status !== 'any') {
            if (is_array($status)) {
                $placeholders = [];
                foreach ($status as $i => $st) {
                    $key = 'st_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $st;
                }
                $where[] = 'p.status IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'p.status = :status';
                $params['status'] = $status;
            }
        }

        // Author filter
        if ($authorId !== null) {
            $where[] = 'p.author_id = :author_id';
            $params['author_id'] = $authorId;
        }

        // Search query
        if ($search !== '') {
            $where[] = '(p.title LIKE :search OR p.slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT p.*, u.username as author_name 
                FROM {prefix}posts p 
                LEFT JOIN {prefix}users u ON p.author_id = u.id 
                {$whereSql} 
                ORDER BY p.{$orderBy} {$orderDir} 
                LIMIT {$limit} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        // Hydrate meta if needed
        foreach ($rows as &$row) {
            $row['meta'] = $this->meta->getAllPostMeta((int)$row['id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Count matching posts
     * @param array<string, mixed> $args
     */
    public function count(array $args = []): int
    {
        $postType = $args['post_type'] ?? 'page';
        $status = $args['status'] ?? 'publish';
        $search = trim((string)($args['search'] ?? ''));
        $authorId = isset($args['author_id']) ? (int)$args['author_id'] : null;

        $where = [];
        $params = [];

        if ($postType !== 'any') {
            if (is_array($postType)) {
                $placeholders = [];
                foreach ($postType as $i => $pt) {
                    $key = 'pt_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $pt;
                }
                $where[] = 'post_type IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'post_type = :pt';
                $params['pt'] = $postType;
            }
        }

        if ($status !== 'any') {
            if (is_array($status)) {
                $placeholders = [];
                foreach ($status as $i => $st) {
                    $key = 'st_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $st;
                }
                $where[] = 'status IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }
        }

        if ($authorId !== null) {
            $where[] = 'author_id = :author_id';
            $params['author_id'] = $authorId;
        }

        if ($search !== '') {
            $where[] = '(title LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM {prefix}posts {$whereSql}", $params);
    }

    /**
     * Create a new post or page
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function create(array $data, array $meta = []): int
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Post title cannot be empty.');
        }

        $postType = (string)($data['post_type'] ?? 'page');
        $slug = !empty($data['slug']) ? (string)$data['slug'] : $title;
        $uniqueSlug = $this->generateUniqueSlug($slug, $postType);

        $blocks = $data['content_blocks'] ?? [];
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : [];
        }
        $blocksJson = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';

        // Pre-render content_html for fast retrieval
        $contentHtml = $this->renderer->render($blocks);

        $now = date('Y-m-d H:i:s');
        $status = (string)($data['status'] ?? 'publish');
        $authorId = (int)($data['author_id'] ?? 1);

        $postId = (int)$this->db->insert('{prefix}posts', [
            'title'          => $title,
            'slug'           => $uniqueSlug,
            'content_blocks' => $blocksJson,
            'content_html'   => $contentHtml,
            'post_type'      => $postType,
            'status'         => $status,
            'author_id'      => $authorId,
            'created_at'     => $now,
            'updated_at'     => $now,
            'published_at'   => $data['published_at'] ?? $now,
        ]);

        // Save meta data
        if (!empty($meta)) {
            $this->meta->setMultiple('post', $postId, $meta);
        }

        if (class_exists(\Heartbeat\Cache\Cache::class)) {
            \Heartbeat\Cache\Cache::page()->flush();
        }

        return $postId;
    }

    /**
     * Update an existing post
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function update(int $id, array $data, array $meta = []): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $updateData = [];

        if (isset($data['title'])) {
            $title = trim((string)$data['title']);
            if ($title === '') {
                throw new InvalidArgumentException('Post title cannot be empty.');
            }
            $updateData['title'] = $title;
        }

        if (isset($data['slug'])) {
            $rawSlug = trim((string)$data['slug']);
            if ($rawSlug !== $existing['slug']) {
                $updateData['slug'] = $this->generateUniqueSlug($rawSlug ?: ($updateData['title'] ?? $existing['title']), $existing['post_type'], $id);
            }
        }

        if (isset($data['content_blocks'])) {
            $blocks = $data['content_blocks'];
            if (is_string($blocks)) {
                $decoded = json_decode($blocks, true);
                $blocks = is_array($decoded) ? $decoded : [];
            }
            $updateData['content_blocks'] = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
            $updateData['content_html'] = $this->renderer->render($blocks);
        }

        if (isset($data['status'])) {
            $updateData['status'] = (string)$data['status'];
        }

        if (isset($data['author_id'])) {
            $updateData['author_id'] = (int)$data['author_id'];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db->update('{prefix}posts', $updateData, 'id = :id', ['id' => $id]);

        // Update meta data
        if (!empty($meta)) {
            $this->meta->setMultiple('post', $id, $meta);
        }

        if (class_exists(\Heartbeat\Cache\Cache::class)) {
            \Heartbeat\Cache\Cache::page()->flush();
        }

        return true;
    }

    /**
     * Move post to trash
     */
    public function trash(int $id): bool
    {
        $ok = $this->db->update('{prefix}posts', ['status' => 'trash', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]) > 0;
        if ($ok && class_exists(\Heartbeat\Cache\Cache::class)) {
            \Heartbeat\Cache\Cache::page()->flush();
        }
        return $ok;
    }

    /**
     * Restore post from trash
     */
    public function restore(int $id, string $status = 'draft'): bool
    {
        $ok = $this->db->update('{prefix}posts', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]) > 0;
        if ($ok && class_exists(\Heartbeat\Cache\Cache::class)) {
            \Heartbeat\Cache\Cache::page()->flush();
        }
        return $ok;
    }

    /**
     * Delete post permanently or move to trash
     */
    public function delete(int $id, bool $force = false): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        if (!$force && $existing['status'] !== 'trash') {
            return $this->trash($id);
        }

        // Force permanent deletion
        $this->meta->deletePostMeta($id);
        $this->db->delete('{prefix}term_relationships', 'object_id = :id', ['id' => $id]);
        $deleted = $this->db->delete('{prefix}posts', 'id = :id', ['id' => $id]) > 0;

        if ($deleted && class_exists(\Heartbeat\Cache\Cache::class)) {
            \Heartbeat\Cache\Cache::page()->flush();
        }

        return $deleted;
    }

    /**
     * Generate a collision-free URL slug for a given post type
     */
    public function generateUniqueSlug(string $titleOrSlug, string $postType, ?int $excludeId = null): string
    {
        $base = Security::slugify($titleOrSlug);
        if ($base === '') {
            $base = 'eintrag';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $postType, $excludeId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, string $postType, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM {prefix}posts WHERE slug = :slug AND post_type = :type";
        $params = ['slug' => $slug, 'type' => $postType];

        if ($excludeId !== null) {
            $sql .= " AND id != :ex_id";
            $params['ex_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        return (bool)$this->db->fetchOne($sql, $params);
    }
}
