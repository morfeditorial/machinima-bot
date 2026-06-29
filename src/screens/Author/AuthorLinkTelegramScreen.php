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

class AuthorLinkTelegramScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && $payload['action'] === 'link_telegram') {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $tempUser = $this->em->find(User::class, $userId);
        $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'link_telegram']) : null;
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

        if ($payload['domain'] === 'author' && $payload['action'] === 'link_telegram') {
            $authorId = (int)($payload['params'][0] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'link_telegram']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('link_telegram');
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

            $this->renderPanel($chatId, $userId, $visualsLinks[3], $this->translate('pending_telegram_link'), $keyboard);
            return;
        }

        if (isset($update['message'])) {
            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'link_telegram']) : null;
            $stateData = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;
            $authorId = (int)($stateData['author_id'] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN') || 0 === $authorId) {
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'link_telegram']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }
                return;
            }

            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $telegramId = (int) trim($text);
            if ($telegramId <= 0) {
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'link_telegram']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }
                // The old code instantiates AuthorProfileScreen and calls render.
                // In new architecture, we might need to simulate an update or redirect.
                // We'll simulate a callback to author:profile
                $profileScreen = new AuthorProfileScreen();
                $profileScreen->setDependencies($this->container, $this->security);
                // We fake the update
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'from' => ['id' => $userId],
                    'message' => ['chat' => ['id' => $chatId]],
                    'data' => 'author:profile:' . $authorId
                ];
                unset($fakeUpdate['message']);
                $profileScreen->handle($fakeUpdate);
                return;
            }

            $existingAuthor = $this->em->getRepository(Author::class)->findOneBy(['telegramUserId' => $telegramId]);
            if (null !== $existingAuthor) {
                $this->client->sendMessage($chatId, $this->translate('telegram_already_linked'));
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'link_telegram']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }
                
                $profileScreen = new AuthorProfileScreen();
                $profileScreen->setDependencies($this->container, $this->security);
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'from' => ['id' => $userId],
                    'message' => ['chat' => ['id' => $chatId]],
                    'data' => 'author:profile:' . $authorId
                ];
                unset($fakeUpdate['message']);
                $profileScreen->handle($fakeUpdate);
                return;
            }

            $tgAuthor = $this->em->find(Author::class, $authorId);
            if ($tgAuthor) {
                $tgAuthor->setTelegramUserId($telegramId);
                $this->em->flush();
            }
            $userObj = $this->em->find(User::class, $userId);
            if ($userObj) {
                $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'link_telegram']);
                if ($state) {
                    $this->em->remove($state);
                    $this->em->flush();
                }
            }

            $profileScreen = new AuthorProfileScreen();
            $profileScreen->setDependencies($this->container, $this->security);
            $fakeUpdate = $update;
            $fakeUpdate['callback_query'] = [
                'from' => ['id' => $userId],
                'message' => ['chat' => ['id' => $chatId]],
                'data' => 'author:profile:' . $authorId
            ];
            unset($fakeUpdate['message']);
            $profileScreen->handle($fakeUpdate);
        }
    }
}
