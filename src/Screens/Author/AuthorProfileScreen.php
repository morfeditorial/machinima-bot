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

namespace Morfeditorial\MachinimaBotBundle\Screens\Author;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;
use Morfeditorial\MachinimaCoreBundle\Entity\Author;

class AuthorProfileScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && in_array($payload['action'], ['profile', 'set_private', 'unlink_telegram'])) {
            return true;
        }

        return false;
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        $payload = $this->parsePayload($action);
        $route = $payload['action'];
        $params = $payload['params'];

        if ('profile' === $route) {
            $authorId = (int)($params[0] ?? 0);
            $this->renderProfile($chatId, $userId, $authorId);
        } elseif ('set_private' === $route) {
            $authorId = (int)($params[0] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && $author->getUser() && (int) $author->getUser()->getId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            if (null !== $author) {
                $isPrivate = 'private' === $author->getState();
                $privAuthor = $this->em->find(Author::class, $authorId);
                if ($privAuthor) {
                    $privAuthor->setState(!$isPrivate ? 'private' : 'public');
                    $this->em->flush();
                }
                $this->renderProfile($chatId, $userId, $authorId);
            }
        } elseif ('unlink_telegram' === $route) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                return;
            }
            $authorId = (int)($params[0] ?? 0);
            $tgAuthor = $this->em->find(Author::class, $authorId);
            if ($tgAuthor) {
                $tgAuthor->setUser(null);
                $this->em->flush();
            }
            $this->renderProfile($chatId, $userId, $authorId);
        }
    }

    private function renderProfile(int $chatId, int $userId, int $authorId) : void
    {
        $this->userStateRepo->clear($userId);

        $author = $this->em->find(Author::class, $authorId);

        $isOwnProfile = $author && $author->getUser() && (int) $author->getUser()->getId() === $userId;

        if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $visualsLinks = $this->getVisualsLinks();

        if (null !== $author) {
            $authorStatus = 'private' === $author->getState();
            $currentPage = $this->userRepo->getCurrentPage($userId);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => ($author->getBiography() ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'author:edit_bio:' . $authorId],
                        ['text' => ($author->getChannelLink() ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:edit_link:' . $authorId],
                    ],
                ],
            ];

            if ($this->isGranted('ROLE_ADMIN')) {
                if ($author->getUser()) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('unlink_telegram'), 'callback_data' => 'author:unlink_telegram:' . $authorId],
                    ];
                } else {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('link_telegram'), 'callback_data' => 'author:link_telegram:' . $authorId],
                    ];
                }
            }

            if ($this->isGranted('ROLE_MODERATOR') || $isOwnProfile) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $currentPage ? $currentPage : 'admin:panel'],
            ];

            $text = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author->getName()),
                    ($author->getBiography() ? htmlspecialchars($author->getBiography()) : $this->translate('bio_not_set')),
                    ($author->getChannelLink() ? htmlspecialchars($author->getChannelLink()) : $this->translate('link_not_set'))
                ],
                $this->translate('author_info_message')
            );

            $this->renderPanel($chatId, $userId, $visualsLinks[11], $text, $keyboard);
            return;
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                ],
            ],
        ];

        $this->renderPanel($chatId, $userId, $visualsLinks[1], $this->translate('author_not_found_message'), $keyboard);
    }
}
