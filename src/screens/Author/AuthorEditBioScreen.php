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

namespace morfeditorial\screens\Author;

use morfeditorial\BaseMachinimaScreen;
use App\Entity\Author;
use App\Entity\User;
use App\Entity\UserState;

class AuthorEditBioScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && in_array($payload['action'], ['edit_bio', 'set_about'])) {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $tempUser = $this->em->find(User::class, $userId);
        $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'set_author_about']) : null;
        $stateCheck = $tempState ? json_decode($tempState->getStateValue(), true) : null;
        if (isset($update['message']) && is_array($stateCheck) && isset($stateCheck['author_id'])) {
            return true;
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        $payload = $this->parsePayload($action);

        if ($payload['domain'] === 'author' && in_array($payload['action'], ['edit_bio', 'set_about'])) {
            $authorId = (int)($payload['params'][0] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'set_author_about']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('set_author_about');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode(['author_id' => $authorId]));
            $this->em->flush();

            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            $msgText = $author->getBiography() ? $this->translate('pending_bio_change') : $this->translate('pending_bio_add');
            $visual = $author->getBiography() ? $visualsLinks[5] : $visualsLinks[4];

            $this->renderPanel($chatId, $userId, $visual, $msgText, $keyboard);
            return;
        }

        if (isset($update['message'])) {
            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'set_author_about']) : null;
            $state = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;
            if ($tmpUser && $tmpState) {
                $this->em->remove($tmpState);
                $this->em->flush();
            }

            $authorId = (int)($state['author_id'] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $bioAuthor = $this->em->find(Author::class, $authorId);
            if ($bioAuthor) {
                $bioAuthor->setBiography(trim($text));
                $this->em->flush();
            }
            $authorStatus = $this->em->find(Author::class, $authorId)?->getState() === 'private';

            $currentPage = $this->em->find(User::class, $userId)?->getCurrentPage();
            $backCallback = $currentPage ? $currentPage : 'admin:panel';

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => $this->translate('change_bio'), 'callback_data' => 'author:edit_bio:' . $authorId],
                        ['text' => ($author->getChannelLink() ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:edit_link:' . $authorId],
                    ],
                ],
            ];

            if ($this->isGranted('ROLE_MODERATOR')) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
            ];

            $visualsLinks = $this->getVisualsLinks();

            $successText = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author->getName()),
                    htmlspecialchars($text),
                    ($author->getChannelLink() ? htmlspecialchars($author->getChannelLink()) : $this->translate('link_not_set'))
                ],
                ($author->getBiography() ? $this->translate('bio_changed_message') : $this->translate('bio_added_message'))
            );

            $visual = $author->getBiography() ? $visualsLinks[8] : $visualsLinks[7];

            $this->renderPanel($chatId, $userId, $visual, $successText, $keyboard);
        }
    }
}
