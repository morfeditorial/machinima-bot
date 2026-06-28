<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface RatingServiceInterface
{
    public function interact(int $user_id, int $content_id, string $type): bool;
    public function getUserInteractions(int $user_id): array;
    public function addView(int $content_id, ?int $user_id = null): bool;
    public function getContentStats(int $content_id): array;
    public function getTrendingContent(int $limit = 10): array;
}
