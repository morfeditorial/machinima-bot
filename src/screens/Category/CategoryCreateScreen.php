<?php
namespace morfeditorial\screens\Category;

use morfeditorial\screens\AbstractScreen;

class CategoryCreateScreen extends AbstractScreen
{
    private ?int $parentId = null;

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function render(): void
    {
        if (! $this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_state_service->setState($this->userId, ['parent_id' => $this->parentId], 'awaiting_category_name');

        $back_callback = $this->parentId ? $this->makePayload('category', 'manage', $this->parentId) : $this->makePayload('category', 'manage');
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                ],
            ],
        ];

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_category_name_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params): void
    {
        if ($action === 'create') {
            $subAction = $params[0] ?? '';
            $this->parentId = isset($params[1]) ? (int) $params[1] : null;
            $this->render();
        }
    }

    public function handleMessage(string $text): void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $state_data = $user_state_service->getState($this->userId, 'awaiting_category_name');
        
        if ($state_data) {
            $message_id = $this->data['message']['message_id'] ?? null;
            if ($message_id) {
                $this->bot->deleteMessage($this->chatId, $message_id);
            }

            $content_service = $this->bot->getContainer()->get('content_service');
            $parent_id = $state_data['parent_id'] ?? null;
            $name = $text;

            $content_service->createCategory($name, $parent_id);

            $this->bot->sendMessage($this->chatId, str_replace('{name}', htmlspecialchars($name), $this->translate('category_added_message')));
            $user_state_service->clearState($this->userId);

            // Transition to CategoryManageScreen
            $screen = new CategoryManageScreen($this->bot, $this->data);
            $screen->setParentId($parent_id)->render();
        }
    }
}
