<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ContentInteraction;
use App\Entity\ContentView;
use App\Entity\Content;
use App\Entity\User;

class RatingService
{
    private \Doctrine\DBAL\Connection $db;

    private const EPOCH = 1767225600;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->db = $em->getConnection();
    }

    public function interact(int $user_id, int $content_id, string $type) : bool
    {
        if (!in_array($type, ['like', 'dislike'])) {
            return false;
        }

        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
            $this->em->flush();
        }

        $content = $this->em->getRepository(Content::class)->find($content_id);
        if (!$content) return false;

        $existing = $this->em->getRepository(ContentInteraction::class)->findOneBy(['user' => $user, 'content' => $content]);

        if ($existing) {
            if ($existing->getInteractionType() === $type) {
                $this->em->remove($existing);
            } else {
                $existing->setInteractionType($type);
            }
        } else {
            $interaction = new ContentInteraction();
            $interaction->setUser($user);
            $interaction->setContent($content);
            $interaction->setInteractionType($type);
            $this->em->persist($interaction);
        }

        $this->em->flush();
        return $this->recalculateCountersAndScore($content_id);
    }

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

    public function addView(int $content_id, ?int $user_id = null) : bool
    {
        $content = $this->em->getRepository(Content::class)->find($content_id);
        if (!$content) return false;

        if ($user_id) {
            $user = $this->em->getRepository(User::class)->find($user_id);
            if (!$user) {
                $user = new User();
                $user->setId($user_id);
                $this->em->persist($user);
                $this->em->flush();
            }

            $existing = $this->em->getRepository(ContentView::class)->findOneBy(['user' => $user, 'content' => $content]);

            if ($existing) {
                return false;
            }

            $view = new ContentView();
            $view->setUser($user);
            $view->setContent($content);
            $this->em->persist($view);
        } else {
            $view = new ContentView();
            $view->setContent($content);
            $this->em->persist($view);
        }

        $this->em->flush();
        return $this->recalculateCountersAndScore($content_id);
    }

    private function recalculateCountersAndScore(int $content_id) : bool
    {
        $likes = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_interactions WHERE content_id = ? AND interaction_type = ?', [$content_id, 'like']);
        $dislikes = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_interactions WHERE content_id = ? AND interaction_type = ?', [$content_id, 'dislike']);
        $views = (int) $this->db->fetchOne('SELECT COUNT(*) FROM content_views WHERE content_id = ?', [$content_id]);

        $content = $this->em->getRepository(Content::class)->find($content_id);
        if (!$content) {
            return false;
        }

        $createdAt = strtotime($content->getCreatedAt() ?? date('Y-m-d H:i:s'));
        $score = $this->calculateHotScore($likes, $dislikes, $views, $createdAt);

        $content->setLikesCount($likes);
        $content->setDislikesCount($dislikes);
        $content->setViewsCount($views);
        $content->setTrendingScore($score);
        
        $this->em->flush();
        return true;
    }

    public function getContentStats(int $content_id) : array
    {
        return $this->db->fetchAssociative(
            'SELECT likes_count as likes, dislikes_count as dislikes, views_count as views FROM content WHERE id = ?',
            [$content_id]
        ) ?: ['likes' => 0, 'dislikes' => 0, 'views' => 0];
    }

    private function calculateHotScore(int $likes, int $dislikes, int $views, int $date) : float
    {
        $s = $likes - $dislikes;
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
        return round($sign * $order + $seconds / 45000, 7);
    }

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
