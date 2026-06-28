<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface ContentServiceInterface
{
    public function applyTransition(int $content_id, string $transition): bool;
    public function createContent(array $data): int;
    public function updateContent(int $id, array $data): bool;
    public function getContentById(int $id): ?array;
    public function getProjectById(int $id): ?array;
    public function addComment(int $content_id, int $user_id, string $author_name, string $text, ?int $parent_id = null): int;
    public function editComment(int $comment_id, int $user_id, string $text): bool;
    public function getCommentById(int $id): ?array;
    public function getComments(int $content_id): array;
    public function deleteCommentItem(int $id): bool;
    public function getAllContent(): array;
    public function getProjectsByOwner(int $user_id): array;
    public function canManageProject(int $user_id, int $project_id, bool $is_moderator = false): bool;
    public function deleteContent(int $id): bool;
    public function searchContent(string $query): array;
    public function getRandomContent(): ?array;
    public function getCategoryProjects(int $category_id, int $limit = 10, int $offset = 0): array;
    public function createCategory(string $name, ?int $parent_id = null): int;
    public function getCategoryById(int $id): ?array;
    public function getAllCategories(): array;
    public function getCategoriesByParent(?int $parent_id): array;
    public function deleteCategory(int $id): bool;
    public function assignStaff(int $content_id, int $author_id, string $role): bool;
    public function removeStaff(int $content_id, int $author_id, string $role): bool;
    public function getStaffByContentId(int $content_id): array;
    public function getCategoriesByContentId(int $content_id): array;
    public function assignCategory(int $content_id, int $category_id): bool;
    public function removeCategory(int $content_id, int $category_id): bool;
}
