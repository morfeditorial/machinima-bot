<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\DBAL\Connection;
use morfeditorial\storage\StorageInterface;

class RatingService
{
    private Connection $db;

    // Use a custom epoch for calculations (e.g. 2026-01-01)
    private const EPOCH = 1767225600;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    /**
     * Add or update an interaction (like/dislike)
     */
    public function interact(int $user_id, int $content_id, string $type) : bool
    {
        if (!in_array($type, ['like', 'dislike'])) {
            return false;
        }

        $existing = $this->db->fetchAssociative(
            'SELECT interaction_type FROM content_interactions WHERE user_id = ? AND content_id = ?',
            [$user_id, $content_id]
        );

        if ($existing) {
            if ($existing['interaction_type'] === $type) {
                // Toggle off if clicking the same button
                $this->db->executeStatement(
                    'DELETE FROM content_interactions WHERE user_id = ? AND content_id = ?',
                    [$user_id, $content_id]
                );
            } else {
                // Change like to dislike or vice versa
                $this->db->executeStatement(
                    'UPDATE content_interactions SET interaction_type = ? WHERE user_id = ? AND content_id = ?',
                    [$type, $user_id, $content_id]
                );
            }
        } else {
            // New interaction
            $this->db->executeStatement(
                'INSERT INTO content_interactions (user_id, content_id, interaction_type) VALUES (?, ?, ?)',
                [$user_id, $content_id, $type]
            );
        }

        return $this->recalculateCountersAndScore($content_id);
    }

    /**
     * Get all interactions for a specific user
     */
    public function getUserInteractions(int $user_id) : array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT content_id, interaction_type FROM content_interactions WHERE user_id = ?',
            [$user_id]
        );
        
        $result = [];
        foreach ($rows as $row) {
            $result[$row['content_id']] = $row['interaction_type'];
        }
        return $result;
    }

    /**
     * Add a view to a project
     */
    public function addView(int $content_id, ?int $user_id = null) : bool
    {
        if ($user_id) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM content_views WHERE user_id = ? AND content_id = ?',
                [$user_id, $content_id]
            );

            if ($existing) {
                return false; // Already viewed by this user
            }

            $this->db->executeStatement(
                'INSERT INTO content_views (user_id, content_id) VALUES (?, ?)',
                [$user_id, $content_id]
            );
        } else {
            // Anonymous view, we just insert it (could add IP tracking later)
            $this->db->executeStatement(
                'INSERT INTO content_views (content_id) VALUES (?)',
                [$content_id]
            );
        }

        return $this->recalculateCountersAndScore($content_id);
    }

    /**
     * Recalculates total likes, dislikes, views, and updates the trending score
     */
    private function recalculateCountersAndScore(int $content_id) : bool
    {
        $likes = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_interactions WHERE content_id = ? AND interaction_type = ?', [$content_id, 'like']);
        $dislikes = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_interactions WHERE content_id = ? AND interaction_type = ?', [$content_id, 'dislike']);
        $views = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_views WHERE content_id = ?', [$content_id]);

        $content = $this->db->fetchAssociative('SELECT created_at FROM content WHERE id = ?', [$content_id]);
        if (!$content) {
            return false;
        }

        $createdAt = strtotime($content['created_at']);
        $score = $this->calculateHotScore($likes, $dislikes, $views, $createdAt);

        $this->db->executeStatement(
            'UPDATE content SET likes_count = ?, dislikes_count = ?, views_count = ?, trending_score = ? WHERE id = ?',
            [$likes, $dislikes, $views, $score, $content_id]
        );

        return true;
    }

    /**
     * Get real-time stats for a project
     */
    public function getContentStats(int $content_id) : array
    {
        return $this->db->fetchAssociative(
            'SELECT likes_count as likes, dislikes_count as dislikes, views_count as views FROM content WHERE id = ?',
            [$content_id]
        ) ?: ['likes' => 0, 'dislikes' => 0, 'views' => 0];
    }

    /**
     * Algorithm based on Reddit's Hot formula but with views influence
     */
    private function calculateHotScore(int $likes, int $dislikes, int $views, int $date) : float
    {
        $s = $likes - $dislikes;
        // Views act as a minor positive modifier. 10 views = 1 like equivalent
        $s += ($views / 10); 

        $order = log10(max(abs($s), 1));
        
        if ($s > 0) {
            $sign = 1;
        } elseif ($s < 0) {
            $sign = -1;
        } else {
            $sign = 0;
        }

        $seconds = $date - self::EPOCH;
        
        // 45000 is 12.5 hours. Content gets heavily decayed as time passes.
        return round($sign * $order + $seconds / 45000, 7);
    }

    /**
     * Get top trending content
     */
    public function getTrendingContent(int $limit = 10) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.*, a.name as author_name, a.id as author_profile_id FROM content c 
             LEFT JOIN content_staff cs ON c.id = cs.content_id 
             LEFT JOIN authors a ON cs.author_id = a.id 
             WHERE c.status = ? 
             GROUP BY c.id 
             ORDER BY c.trending_score DESC LIMIT ?',
            ['published', $limit]
        );
    }
}
