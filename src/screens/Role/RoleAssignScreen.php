<?php
namespace morfeditorial\screens\Role;

use morfeditorial\screens\AbstractScreen;

class RoleAssignScreen extends AbstractScreen
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

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ($action === 'ask_user') {
            $role_name = $params[0] ?? '';
            $user_state_service->setState($this->userId, ['role_name' => $role_name], 'awaiting_user_id_for_role');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                    ],
                ],
            ];

            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('enter_user_id_for_role_message')), $keyboard);
        }
    }

    public function handleMessage(string $text): void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $state_data = $user_state_service->getState($this->userId, 'awaiting_user_id_for_role');

        if ($state_data) {
            $role_service = $this->bot->getContainer()->get('role_service');
            $target_user_id = (int) $text;

            if ($target_user_id <= 0) {
                $this->bot->sendMessage($this->chatId, $this->translate('invalid_user_id_message'));
                return;
            }

            $role_name = $state_data['role_name'] ?? '';

            if ($role_service->assignRole($target_user_id, $role_name)) {
                $this->bot->sendMessage($this->chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('assign_role_message')));
            } else {
                $this->bot->sendMessage($this->chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_assignment_failure_message')));
            }

            $user_state_service->clearState($this->userId, 'awaiting_user_id_for_role');
        }
    }
}
