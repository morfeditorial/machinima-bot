<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\screens\Public;

use morfeditorial\screens\AbstractScreen;

class CategoryListScreen extends AbstractScreen
{
    public function render() : void
    {
        $this->bot->getUserStateService()->clearState($this->userId);

        $categoryId = isset($this->data['category_id']) ? (int)$this->data['category_id'] : null;
        $contentService = $this->bot->getContainer()->get('content_service');

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = ['inline_keyboard' => []];
        $message = $this->translate('categories');

        if ($categoryId) {
            // Show content in this category
            $content = $contentService->getContentByCategory($categoryId);
            foreach ($content as $item) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $item['title'], 'callback_data' => 'project:view:' . $item['id']],
                ];
            }

            // Subcategories
            $subcategories = $contentService->getCategoriesByParent($categoryId);
            foreach ($subcategories as $cat) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '📁 ' . $cat['name'], 'callback_data' => 'public:category:' . $cat['id']],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => 'public:categories'], // go to root
            ];

            if (empty($content) && empty($subcategories)) {
                $message = $this->translate('no_content_found');
            }
        } else {
            // Root categories
            $categories = $contentService->getCategoriesByParent(null);
            foreach ($categories as $cat) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '📁 ' . $cat['name'], 'callback_data' => 'public:category:' . $cat['id']],
                ];
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => 'public:main'],
            ];
        }

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $message,
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('category' === $action) {
            $this->data['category_id'] = $params[0] ?? null;
            $this->render();
        } elseif ('categories' === $action) {
            unset($this->data['category_id']);
            $this->render();
        }
    }

    public function handleMessage(string $text) : void {}
}
