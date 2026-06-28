<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use App\Entity\Content;
use App\Entity\Comment;
use App\Entity\Category;
use App\Entity\ContentStaff;
use App\Entity\User;
use App\Entity\Author;

class ContentService
{
    private \Doctrine\DBAL\Connection $db;

    public function __construct(
        private EntityManagerInterface $em,
        private WorkflowInterface $workflow
    ) {
        $this->db = $em->getConnection();
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

    public function createContent(array $data) : int
    {
        $content = new Content();
        $content->setTitle($data['title']);
        $content->setType($data['type']);
        $content->setDescription($data['description'] ?? null);
        $content->setUrl($data['url'] ?? null);
        $content->setReleaseDate($data['release_date'] ?? null);
        $content->setStatus($data['status'] ?? 'draft');
        $content->setCoverFileId($data['cover_file_id'] ?? null);

        $user = $this->em->getRepository(User::class)->find($data['created_by']);
        if (!$user) {
            $user = new User();
            $user->setId((int)$data['created_by']);
            $this->em->persist($user);
        }
        $content->setCreatedBy($user);

        $this->em->persist($content);
        $this->em->flush();

        return $content->getId();
    }

    public function updateContent(int $id, array $data) : bool
    {
        $content = $this->em->getRepository(Content::class)->find($id);
        if (!$content) return false;

        if (array_key_exists('title', $data)) $content->setTitle($data['title']);
        if (array_key_exists('type', $data)) $content->setType($data['type']);
        if (array_key_exists('description', $data)) $content->setDescription($data['description']);
        if (array_key_exists('url', $data)) $content->setUrl($data['url']);
        if (array_key_exists('release_date', $data)) $content->setReleaseDate($data['release_date']);
        if (array_key_exists('status', $data)) $content->setStatus($data['status']);
        if (array_key_exists('cover_file_id', $data)) $content->setCoverFileId($data['cover_file_id']);
        
        $content->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->em->flush();
        return true;
    }

    public function getContentById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative('SELECT * FROM content WHERE id = ?', [$id]);
        return $result ?: null;
    }

    public function getProjectById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT c.*, a.name as author_name, a.id as author_profile_id 
             FROM content c 
             LEFT JOIN content_staff cs ON c.id = cs.content_id 
             LEFT JOIN authors a ON cs.author_id = a.id 
             WHERE c.id = ? GROUP BY c.id',
            [$id]
        );
        return $result ?: null;
    }

    public function addComment(int $content_id, int $user_id, string $author_name, string $text, ?int $parent_id = null) : int
    {
        $comment = new Comment();
        
        $content = $this->em->getRepository(Content::class)->find($content_id);
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
        }
        
        $comment->setContent($content);
        $comment->setUser($user);
        $comment->setAuthorName($author_name);
        $comment->setText($text);
        
        if ($parent_id) {
            $parent = $this->em->getRepository(Comment::class)->find($parent_id);
            $comment->setParent($parent);
        }

        $this->em->persist($comment);
        $this->em->flush();

        return $comment->getId();
    }
    
    public function editComment(int $comment_id, int $user_id, string $text) : bool
    {
        $comment = $this->em->getRepository(Comment::class)->findOneBy(['id' => $comment_id, 'user' => $user_id]);
        if (!$comment) return false;

        $comment->setText($text);
        $comment->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->em->flush();
        return true;
    }

    public function getCommentById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative('SELECT * FROM comments WHERE id = ?', [$id]);
        return $result ?: null;
    }

    public function getComments(int $content_id) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM comments WHERE content_id = ? ORDER BY created_at ASC',
            [$content_id]
        );
    }
    
    public function deleteCommentItem(int $id) : bool
    {
        $comment = $this->em->getRepository(Comment::class)->find($id);
        if (!$comment) return false;

        $this->em->remove($comment);
        $this->em->flush();
        return true;
    }

    public function getAllContent() : array
    {
        return $this->db->fetchAllAssociative('SELECT c.*, a.name as author_name, a.id as author_profile_id FROM content c LEFT JOIN content_staff cs ON c.id = cs.content_id LEFT JOIN authors a ON cs.author_id = a.id GROUP BY c.id ORDER BY c.created_at DESC');
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
        $content = $this->em->getRepository(Content::class)->find($id);
        if (!$content) return false;

        $this->em->remove($content);
        $this->em->flush();
        return true;
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
            'SELECT c.*, a.name as author_name, a.id as author_profile_id FROM content c 
             JOIN content_categories cc ON c.id = cc.content_id 
             LEFT JOIN content_staff cs ON c.id = cs.content_id
             LEFT JOIN authors a ON cs.author_id = a.id
             WHERE cc.category_id = ? AND c.status = ?
             GROUP BY c.id
             ORDER BY c.trending_score DESC 
             LIMIT ? OFFSET ?',
            [$category_id, 'published', $limit, $offset]
        );
    }

    public function createCategory(string $name, ?int $parent_id = null) : int
    {
        $category = new Category();
        $category->setName($name);
        if ($parent_id) {
            $parent = $this->em->getRepository(Category::class)->find($parent_id);
            if ($parent) $category->setParent($parent);
        }

        $this->em->persist($category);
        $this->em->flush();
        return $category->getId();
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
        $category = $this->em->getRepository(Category::class)->find($id);
        if (!$category) return false;

        $this->em->remove($category);
        $this->em->flush();
        return true;
    }

    public function assignStaff(int $content_id, int $author_id, string $role) : bool
    {
        $content = $this->em->getRepository(Content::class)->find($content_id);
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if (!$content || !$author) return false;

        $existing = $this->em->getRepository(ContentStaff::class)->findOneBy([
            'content' => $content,
            'author' => $author,
            'role' => $role
        ]);

        if (!$existing) {
            $staff = new ContentStaff();
            $staff->setContent($content);
            $staff->setAuthor($author);
            $staff->setRole($role);
            $this->em->persist($staff);
            $this->em->flush();
        }

        return true;
    }

    public function removeStaff(int $content_id, int $author_id, string $role) : bool
    {
        $staff = $this->em->getRepository(ContentStaff::class)->findOneBy([
            'content' => $content_id,
            'author' => $author_id,
            'role' => $role
        ]);

        if ($staff) {
            $this->em->remove($staff);
            $this->em->flush();
        }

        return true;
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
        $content = $this->em->getRepository(Content::class)->find($content_id);
        $category = $this->em->getRepository(Category::class)->find($category_id);
        if (!$content || !$category) return false;

        if (!$content->getCategories()->contains($category)) {
            $content->addCategory($category);
            $this->em->flush();
        }

        return true;
    }

    public function removeCategory(int $content_id, int $category_id) : bool
    {
        $content = $this->em->getRepository(Content::class)->find($content_id);
        $category = $this->em->getRepository(Category::class)->find($category_id);
        if (!$content || !$category) return false;

        if ($content->getCategories()->contains($category)) {
            $content->removeCategory($category);
            $this->em->flush();
        }

        return true;
    }
}
