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

namespace Morfeditorial\screens\Author;

use App\Entity\Author;
use Morfeditorial\BaseMachinimaScreen;
use Morfeditorial\utils\KeyboardHelper;

class AuthorListScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && 'list' === $payload['action']) {
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

        if ('author' === $payload['domain'] && 'list' === $payload['action']) {
            $page = (int)($payload['params'][0] ?? 1);
            $this->userRepo->setCurrentPage($userId, 'author:list:' . $page);

            $visualsLinks = $this->getVisualsLinks();

            $allAuthors = $this->em->getRepository(Author::class)->findAll();
            $keyboard = KeyboardHelper::generateAuthorsKeyboard(
                $this->getTranslator(),
                $allAuthors,
                $page,
                3,
                1,
                'author:profile:',
                'author:list:'
            );

            $authors = $allAuthors;
            $messageText = empty($authors) ? $this->translate('empty_authors_list_message') : $this->translate('list_of_authors_message');

            $this->renderPanel($chatId, $userId, $visualsLinks[9], $messageText, $keyboard, true);
        }
    }
}
