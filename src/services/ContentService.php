<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is licensed under the CSSM Unlimited License v2.0.
 * Copyright (c) 2024 Serhii Cherneha
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\DBAL\Connection;
use morfeditorial\storage\StorageInterface;
use Symfony\Component\Workflow\Workflow;

class ContentService
{
    private Connection $db;

    public function __construct(
        private StorageInterface $storage,
        private Workflow $workflow
    ) {
        $this->db = $storage->getConnection();
    }

    public function applyTransition(int $content_id, string $transition) : bool
    {
        $content = $this->getContentById($content_id);
        if (!$content) {
            return false;
        }

        $contentItem = new ContentItem($content_id, $content['status']);

        if ($this->workflow->can($contentItem, $transition)) {
            $this->workflow->apply($contentItem, $transition);
            $this->updateContent($content_id, ['status' => $contentItem->getStatus()]);
            return true;
        }

        return false;
    }

    // --- Content CRUD ---

    public function createContent(array $data) : int
    {
        $this->db->executeStatement(
            'INSERT INTO content (title, type, description, url, release_date, status, cover_file_id, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['type'],
                $data['description'] ?? null,
                $data['url'] ?? null,
                $data['release_date'] ?? null,
                $data['status'] ?? 'draft',
                $data['cover_file_id'] ?? null,
                $data['created_by'],
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateContent(int $id, array $data) : bool
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ('id' === $key) {
                continue;
            }
            $fields[] = "{$key} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        return (bool) $this->db->executeStatement(
            'UPDATE content SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            $params
        );
    }

    public function getContentById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative('SELECT * FROM content WHERE id = ?', [$id]);

        return $result ?: null;
    }

    public function getAllContent() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM content ORDER BY created_at DESC');
    }

    public function getProjectsByOwner(int $user_id) : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM content WHERE created_by = ? ORDER BY created_at DESC', [$user_id]);
    }

    public function canManageProject(int $user_id, int $project_id, bool $is_moderator = false) : bool
    {
        if ($is_moderator) {
            return true;
        }

        $project = $this->getContentById($project_id);

        return $project && (int) $project['created_by'] === $user_id;
    }

    public function deleteContent(int $id) : bool
    {
        return (bool) $this->db->executeStatement('DELETE FROM content WHERE id = ?', [$id]);
    }

    public function searchContent(string $query) : array
    {
        $likeQuery = '%' . $query . '%';
        return $this->db->fetchAllAssociative(
            'SELECT * FROM content WHERE (title LIKE ? OR description LIKE ?) AND status = ?',
            [$likeQuery, $likeQuery, 'published']
        );
    }

    public function getRandomContent() : ?array
    {
        $result = $this->db->fetchAllAssociative('SELECT * FROM content WHERE status = ? ORDER BY RANDOM() LIMIT 1', ['published']);
        return $result[0] ?? null;
    }

    public function getCategoryProjects(int $category_id, int $limit = 10, int $offset = 0) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.* FROM content c 
             JOIN content_categories cc ON c.id = cc.content_id 
             WHERE cc.category_id = ? AND c.status = ?
             ORDER BY c.trending_score DESC 
             LIMIT ? OFFSET ?',
            [$category_id, 'published', $limit, $offset]
        );
    }

    // --- Category Management ---

    public function createCategory(string $name, ?int $parent_id = null) : int
    {
        $this->db->executeStatement(
            'INSERT INTO categories (name, parent_id) VALUES (?, ?)',
            [$name, $parent_id]
        );

        return (int) $this->db->lastInsertId();
    }

    public function getCategoryById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative('SELECT * FROM categories WHERE id = ?', [$id]);

        return $result ?: null;
    }

    public function getAllCategories() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM categories ORDER BY name ASC');
    }

    public function getCategoriesByParent(?int $parent_id) : array
    {
        $query = 'SELECT * FROM categories WHERE parent_id ' . (null === $parent_id ? 'IS NULL' : '= ?');
        $params = null === $parent_id ? [] : [$parent_id];

        return $this->db->fetchAllAssociative($query, $params);
    }

    public function deleteCategory(int $id) : bool
    {
        return (bool) $this->db->executeStatement('DELETE FROM categories WHERE id = ?', [$id]);
    }

    // --- Staff Management ---

    public function assignStaff(int $content_id, int $author_id, string $role) : bool
    {
        return (bool) $this->db->executeStatement(
            'INSERT OR IGNORE INTO content_staff (content_id, author_id, role) VALUES (?, ?, ?)',
            [$content_id, $author_id, $role]
        );
    }

    public function removeStaff(int $content_id, int $author_id, string $role) : bool
    {
        return (bool) $this->db->executeStatement(
            'DELETE FROM content_staff WHERE content_id = ? AND author_id = ? AND role = ?',
            [$content_id, $author_id, $role]
        );
    }

    public function getStaffByContentId(int $content_id) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT cs.*, a.name as author_name 
             FROM content_staff cs 
             JOIN authors a ON cs.author_id = a.id 
             WHERE cs.content_id = ?',
            [$content_id]
        );
    }

    // --- Content Category Assignment ---

    public function getCategoriesByContentId(int $content_id) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.* FROM categories c
             JOIN content_categories cc ON c.id = cc.category_id
             WHERE cc.content_id = ?',
            [$content_id]
        );
    }

    public function assignCategory(int $content_id, int $category_id) : bool
    {
        return (bool) $this->db->executeStatement(
            'INSERT OR IGNORE INTO content_categories (content_id, category_id) VALUES (?, ?)',
            [$content_id, $category_id]
        );
    }

    public function removeCategory(int $content_id, int $category_id) : bool
    {
        return (bool) $this->db->executeStatement(
            'DELETE FROM content_categories WHERE content_id = ? AND category_id = ?',
            [$content_id, $category_id]
        );
    }
}
