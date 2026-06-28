<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Author;
use App\Entity\Content;
use App\Entity\ContentStaff;

class AuthorService
{
    private const STATE_PRIVATE = 'private';
    private const STATE_PUBLIC = 'public';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function createAuthor(string $name, ?int $telegram_user_id = null) : int
    {
        $author = new Author();
        $author->setName(trim($name));
        $author->setState(self::STATE_PRIVATE);
        $author->setTelegramUserId($telegram_user_id);

        $this->em->persist($author);
        $this->em->flush();

        return $author->getId();
    }

    public function deleteAuthor(int $author_id) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $this->em->remove($author);
            $this->em->flush();
        }
    }

    public function getTopAuthors(int $limit = 10) : array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.id', 'a.name', 'a.biography', 'a.channelLink', 'a.createdAt', 'a.state', 'a.telegramUserId', 'COUNT(cs.content) as projects_count')
           ->from(Author::class, 'a')
           ->leftJoin(ContentStaff::class, 'cs', 'WITH', 'cs.author = a')
           ->leftJoin(Content::class, 'c', 'WITH', 'cs.content = c AND c.status = :status')
           ->where('a.state = :state')
           ->setParameter('status', 'published')
           ->setParameter('state', self::STATE_PUBLIC)
           ->groupBy('a.id')
           ->orderBy('projects_count', 'DESC')
           ->setMaxResults($limit);

        return $qb->getQuery()->getArrayResult();
    }

    public function getAuthorById(int $author_id) : ?array
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if (!$author) {
            return null;
        }

        return [
            'id' => $author->getId(),
            'name' => $author->getName(),
            'biography' => $author->getBiography(),
            'channel_link' => $author->getChannelLink(),
            'created_at' => $author->getCreatedAt(),
            'state' => $author->getState(),
            'telegram_user_id' => $author->getTelegramUserId()
        ];
    }

    public function getAllAuthors() : array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.id', 'a.name', 'a.biography', 'a.channelLink as channel_link', 'a.createdAt as created_at', 'a.state', 'a.telegramUserId as telegram_user_id')
           ->from(Author::class, 'a');

        return $qb->getQuery()->getArrayResult();
    }

    public function getAuthorProjects(int $author_id, int $limit = 10, int $offset = 0) : array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('c.id', 'c.title', 'c.description', 'c.url', 'c.cover', 'c.type', 'c.status', 'c.publishedAt', 'c.trendingScore', 'a.name as author_name', 'a.id as author_profile_id')
           ->from(Content::class, 'c')
           ->join(ContentStaff::class, 'cs', 'WITH', 'cs.content = c')
           ->join(Author::class, 'a', 'WITH', 'cs.author = a')
           ->where('cs.author = :author_id')
           ->andWhere('c.status = :status')
           ->setParameter('author_id', $author_id)
           ->setParameter('status', 'published')
           ->groupBy('c.id', 'a.id')
           ->orderBy('c.trendingScore', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($limit);

        return $qb->getQuery()->getArrayResult();
    }

    public function updateAuthorName(int $author_id, string $name) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $author->setName(trim($name));
            $this->em->flush();
        }
    }

    public function setBiography(int $author_id, string $biography) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $author->setBiography(trim($biography));
            $this->em->flush();
        }
    }

    public function setChannelLink(int $author_id, string $link) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $author->setChannelLink(trim($link));
            $this->em->flush();
        }
    }

    public function setTelegramId(int $author_id, ?int $telegram_user_id) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $author->setTelegramUserId($telegram_user_id);
            $this->em->flush();
        }
    }

    public function setPrivate(int $author_id, bool $private = true) : void
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        if ($author) {
            $author->setState($private ? self::STATE_PRIVATE : self::STATE_PUBLIC);
            $this->em->flush();
        }
    }

    public function isPrivate(int $author_id) : bool
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        return $author && $author->getState() === self::STATE_PRIVATE;
    }

    public function getAuthorCreationTime(int $author_id) : ?string
    {
        $author = $this->em->getRepository(Author::class)->find($author_id);
        return $author ? $author->getCreatedAt() : null;
    }

    public function countAuthors() : int
    {
        return $this->em->getRepository(Author::class)->count([]);
    }

    public function getAuthorByTelegramId(int $telegram_user_id) : ?array
    {
        $author = $this->em->getRepository(Author::class)->findOneBy(['telegramUserId' => $telegram_user_id]);
        if (!$author) {
            return null;
        }

        return [
            'id' => $author->getId(),
            'name' => $author->getName(),
            'biography' => $author->getBiography(),
            'channel_link' => $author->getChannelLink(),
            'created_at' => $author->getCreatedAt(),
            'state' => $author->getState(),
            'telegram_user_id' => $author->getTelegramUserId()
        ];
    }
}
