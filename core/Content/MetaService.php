<?php
declare(strict_types=1);

namespace Heartbeat\Content;

use Heartbeat\Database\Database;
use JsonException;

/**
 * Custom Meta Data Service
 * Handles key-value and JSON storage in {prefix}meta_data for posts, pages, CPTs, and users.
 */
final class MetaService
{
    private static ?MetaService $instance = null;
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieve a single metadata value.
     * Automatically decodes JSON if stored as JSON.
     */
    public function get(string $objectType, int $objectId, string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetchOne(
            "SELECT meta_value FROM {prefix}meta_data 
             WHERE object_type = :type AND object_id = :id AND meta_key = :key 
             LIMIT 1",
            [
                'type' => $objectType,
                'id'   => $objectId,
                'key'  => $key,
            ]
        );

        if (!$row || $row['meta_value'] === null) {
            return $default;
        }

        $raw = $row['meta_value'];

        // If it starts with [ or { or is "true"/"false"/"null", attempt json decode
        if (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '[') || $raw === 'true' || $raw === 'false' || $raw === 'null')) {
            try {
                return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $raw;
            }
        }

        return $raw;
    }

    /**
     * Retrieve all metadata for an object as an associative array.
     * @return array<string, mixed>
     */
    public function getAll(string $objectType, int $objectId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT meta_key, meta_value FROM {prefix}meta_data 
             WHERE object_type = :type AND object_id = :id",
            [
                'type' => $objectType,
                'id'   => $objectId,
            ]
        );

        $result = [];
        foreach ($rows as $row) {
            $key = $row['meta_key'];
            $raw = $row['meta_value'];

            if ($raw === null) {
                $result[$key] = null;
                continue;
            }

            if (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '[') || $raw === 'true' || $raw === 'false' || $raw === 'null')) {
                try {
                    $result[$key] = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $result[$key] = $raw;
                }
            } else {
                $result[$key] = $raw;
            }
        }

        return $result;
    }

    /**
     * Set a metadata value (insert or update).
     * Automatically encodes arrays and objects to JSON.
     */
    public function set(string $objectType, int $objectId, string $key, mixed $value): void
    {
        $serialized = is_array($value) || is_object($value) || is_bool($value)
            ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : (string)$value;

        $existing = $this->db->fetchOne(
            "SELECT id FROM {prefix}meta_data 
             WHERE object_type = :type AND object_id = :id AND meta_key = :key 
             LIMIT 1",
            [
                'type' => $objectType,
                'id'   => $objectId,
                'key'  => $key,
            ]
        );

        if ($existing) {
            $this->db->update(
                '{prefix}meta_data',
                ['meta_value' => $serialized],
                'id = :mid',
                ['mid' => $existing['id']]
            );
        } else {
            $this->db->insert('{prefix}meta_data', [
                'object_type' => $objectType,
                'object_id'   => $objectId,
                'meta_key'    => $key,
                'meta_value'  => $serialized,
            ]);
        }
    }

    /**
     * Set multiple metadata values at once.
     * @param array<string, mixed> $meta
     */
    public function setMultiple(string $objectType, int $objectId, array $meta): void
    {
        foreach ($meta as $key => $val) {
            $this->set($objectType, $objectId, (string)$key, $val);
        }
    }

    /**
     * Delete metadata.
     * If key is null, all metadata for the object will be deleted.
     */
    public function delete(string $objectType, int $objectId, ?string $key = null): void
    {
        if ($key === null) {
            $this->db->delete(
                '{prefix}meta_data',
                'object_type = :type AND object_id = :id',
                ['type' => $objectType, 'id' => $objectId]
            );
        } else {
            $this->db->delete(
                '{prefix}meta_data',
                'object_type = :type AND object_id = :id AND meta_key = :key',
                ['type' => $objectType, 'id' => $objectId, 'key' => $key]
            );
        }
    }

    // --- Post Meta Helpers ---

    public function getPostMeta(int $postId, string $key, mixed $default = null): mixed
    {
        return $this->get('post', $postId, $key, $default);
    }

    public function getAllPostMeta(int $postId): array
    {
        return $this->getAll('post', $postId);
    }

    public function setPostMeta(int $postId, string $key, mixed $value): void
    {
        $this->set('post', $postId, $key, $value);
    }

    public function deletePostMeta(int $postId, ?string $key = null): void
    {
        $this->delete('post', $postId, $key);
    }
}

