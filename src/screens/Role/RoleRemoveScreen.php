<?php
namespace morfeditorial\screens\Role;

use morfeditorial\screens\AbstractScreen;

class RoleRemoveScreen extends AbstractScreen
{
    public function render(): void
    {
    }

    public function handleCallback(string $action, array $params): void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $role_service = $this->bot->getContainer()->get('role_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ($action === 'select_child') {
            $role_name = $params[0] ?? '';
            $children = $role_service->getChildren($role_name);

            $callback_query_id = $this->data['callback_query_id'] ?? null;

            if (empty($children)) {
                if ($callback_query_id) {
                    $this->bot->callbackAnswer($callback_query_id, $this->translate('no_children_message'));
                }
            } else {
                $keyboard = ['inline_keyboard' => []];

                foreach ($children as $child) {
                    $keyboard['inline_keyboard'][] = [
                        [
                            'text' => $child['role_name'],
                            'callback_data' => $this->makePayload('role', 'remove', 'confirm_remove_child', $role_name, $child['role_name']),
                        ],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                ];

                $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('select_child_to_remove_message')), $keyboard);
            }
        } elseif ($action === 'confirm_remove_child') {
            $parent_name = $params[0] ?? '';
            $child_name = $params[1] ?? '';

            $role_service->removeParentChild($parent_name, $child_name);
            $callback_query_id = $this->data['callback_query_id'] ?? null;
            if ($callback_query_id) {
                $this->bot->callbackAnswer($callback_query_id, str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('child_removed_message')));
            }

            // Refresh to parent's detail panel
            $parents = $role_service->getParents($parent_name);
            $children = $role_service->getChildren($parent_name);
            $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
            $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

            $message_text = str_replace(
                ['{role}', '{parents}', '{children}'],
                [$parent_name, $parents_text, $children_text],
                $this->translate('role_detail_message')
            );

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('add_parent'), 'callback_data' => $this->makePayload('role', 'create', 'add_parent', $parent_name)],
                        ['text' => $this->translate('remove_child'), 'callback_data' => $this->makePayload('role', 'remove', 'select_child', $parent_name)],
                    ],
                    [
                        ['text' => $this->translate('assign_role_to_user'), 'callback_data' => $this->makePayload('role', 'assign', 'ask_user', $parent_name)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view')],
                    ],
                ],
            ];

            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $message_text, $keyboard);
        }
    }

    public function handleMessage(string $text): void
    {
    }
}
