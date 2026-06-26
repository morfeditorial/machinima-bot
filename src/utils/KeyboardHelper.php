<?php

declare(strict_types=1);

namespace morfeditorial\utils;

use morfeditorial\services\AuthorService;
use morfeditorial\Translator;

class KeyboardHelper
{
    public static function generateAuthorsKeyboard(
        Translator $translator,
        AuthorService $authorService,
        int $page_number = 1,
        int $buttons_per_page = 3,
        int $authors_per_row = 1,
        string $prefix = 'author:profile:',
        string $page_prefix = 'author:list:',
        ?array $authors = null
    ) : array {
        $authors = $authors ?? $authorService->getAllAuthors();
        $total_buttons = count($authors);
        $total_pages = (int) ceil($total_buttons / $buttons_per_page);
        $page_number = max(1, min($page_number, max(1, $total_pages)));

        $sliced_authors = array_slice($authors, ($page_number - 1) * $buttons_per_page, $buttons_per_page);
        $keyboard = ['inline_keyboard' => []];

        $current_row = [];
        foreach ($sliced_authors as $author) {
            $current_row[] = ['text' => $author['name'], 'callback_data' => $prefix . $author['id']];
            if (count($current_row) === $authors_per_row) {
                $keyboard['inline_keyboard'][] = $current_row;
                $current_row = [];
            }
        }
        if (! empty($current_row)) {
            $keyboard['inline_keyboard'][] = $current_row;
        }

        if ($total_pages > 1) {
            $pagination = [];
            if ($page_number > 1) {
                $pagination[] = ['text' => $translator->translate('previous_page'), 'callback_data' => $page_prefix . ($page_number - 1)];
            }
            if ($page_number < $total_pages) {
                $pagination[] = ['text' => $translator->translate('next_page'), 'callback_data' => $page_prefix . ($page_number + 1)];
            }
            $keyboard['inline_keyboard'][] = $pagination;
        }

        if (is_null($authors)) {
            $keyboard['inline_keyboard'][] = [['text' => $translator->translate('go_back'), 'callback_data' => 'admin:panel']];
        }

        return $keyboard;
    }
}
