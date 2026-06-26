<?php
declare(strict_types=1);

namespace morfeditorial\screens\Project;

use morfeditorial\screens\AbstractScreen;

class ProjectEditScreen extends AbstractScreen
{
    public function render(): void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $content_service = $this->bot->getContainer()->get('content_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');

        $payload = $this->data['payload'] ?? null;
        $message = $this->data['message'] ?? null;
        $message_id = $this->data['message_id'] ?? null;
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ($payload !== null && strpos($payload, 'project:edit') === 0) {
            $parsed = $this->parsePayload($payload);
            $project_id = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 0;
            $sub_action = isset($parsed['params'][1]) ? $parsed['params'][1] : null;

            if ($sub_action === null) {
                // Main edit menu
                $project = $content_service->getContentById($project_id);
                if ($project) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('edit_title'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'title')],
                                ['text' => $this->translate('edit_type'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'type')],
                            ],
                            [
                                ['text' => $this->translate('edit_description'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'description')],
                                ['text' => $this->translate('edit_url'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'url')],
                            ],
                            [
                                ['text' => $this->translate('edit_cover'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'cover')],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'view', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->bot->editMediaMessage($this->chatId, $current_panel, $project['cover_file_id'] ?? $visuals_links[1], $this->translate('edit_project'), $keyboard);
                }
                return;
            } elseif ($sub_action === 'field') {
                $field = $parsed['params'][2] ?? '';
                if ('type' === $field) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('project_type_short'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'short')],
                                ['text' => $this->translate('project_type_series'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'series')],
                            ],
                            [
                                ['text' => $this->translate('project_type_music_video'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'music_video')],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('select_project_type_message'), $keyboard);
                } else {
                    $user_state_service->setState($this->userId, ['project_id' => $project_id, 'field' => $field], 'editing_project_field');
                    $msg_key = 'cover' === $field ? 'upload_project_cover_message' : 'enter_new_value_message';
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate($msg_key), $keyboard);
                }
                return;
            } elseif ($sub_action === 'set_type') {
                $type = $parsed['params'][2] ?? '';
                $content_service->updateContent($project_id, ['type' => $type]);
                $this->bot->callbackAnswer($this->data['callback_query_id'] ?? '', $this->translate('project_updated_message'));
                $this->data['payload'] = $this->makePayload('project', 'edit', (string)$project_id);
                $this->render(); // recursive call
                return;
            }
        }

        // Handle state
        if ($state_data = $user_state_service->getState($this->userId, 'editing_project_field')) {
            $this->bot->deleteMessage($this->chatId, $message_id);
            $user_state_service->clearState($this->userId, 'editing_project_field');
            
            $project_id = $state_data['project_id'];
            $field = $state_data['field'];
            $value = $message;

            if ('cover' === $field) {
                $photo = $this->data['photo'] ?? null;
                if ($photo) {
                    $value = end($photo)['file_id'];
                } else {
                    return; // Ignore if no photo is sent when expecting one
                }
            }

            $content_service->updateContent($project_id, [$field => $value]);
            $this->bot->sendMessage($this->chatId, $this->translate('project_updated_message'));

            $this->data['payload'] = $this->makePayload('project', 'edit', (string)$project_id);
            $this->render(); // recursive call
        }
    }
}
