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

namespace Morfeditorial\MachinimaBotBundle\Screens\Project;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;
use Morfeditorial\MachinimaCoreBundle\Entity\Content;
use Morfeditorial\MachinimaCoreBundle\Security\Voter\PostVoter;

class ProjectEditScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (0 === strpos($action, 'project:edit')) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId && $this->userStateRepo->get($userId, 'editing_project_field')) {
            return true;
        }

        return false;
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';
        $message_id = $update['message']['message_id'] ?? null;
        $photo = $update['message']['photo'] ?? null;
        $callbackQueryId = $update['callback_query']['id'] ?? '';

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->container->get('content_service');
        $visuals_links = $this->getVisualsLinks();

        $project_id = 0;
        $state_data = null;
        if (0 === strpos($action, 'project:edit')) {
            $parsed = $this->parsePayload($action);
            $project_id = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 0;
        } elseif ($state_data = $this->userStateRepo->get($userId, 'editing_project_field')) {
            $project_id = (int)$state_data['project_id'];
        }

        if ($project_id > 0) {
            $project = $this->em->find(Content::class, $project_id);
            if (!$project || !$this->isGranted(PostVoter::EDIT, $project)) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
        }

        if (0 === strpos($action, 'project:edit')) {
            $sub_action = isset($parsed['params'][1]) ? $parsed['params'][1] : null;

            if (null === $sub_action) {
                $this->userStateRepo->clear($userId);
                $projectData = $content_service->getContentById($project_id);
                if ($projectData) {
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
                    $cover = $projectData['cover_file_id'] ?: $visuals_links[1];
                    $this->renderPanel($chatId, $userId, $cover, $this->translate('edit_project'), $keyboard);
                }
            } elseif ('field' === $sub_action) {
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
                    $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('select_project_type_message'), $keyboard);
                } else {
                    $this->userStateRepo->set($userId, ['project_id' => $project_id, 'field' => $field], 'editing_project_field');
                    $msg_key = 'cover' === $field ? 'upload_project_cover_message' : 'enter_new_value_message';
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate($msg_key), $keyboard);
                }
            } elseif ('set_type' === $sub_action) {
                $type = $parsed['params'][2] ?? '';
                $content_service->updateContent($project_id, ['type' => $type]);
                if ($callbackQueryId) {
                    $this->client->request('answerCallbackQuery', [
                        'callback_query_id' => $callbackQueryId,
                        'text' => $this->translate('project_updated_message')
                    ]);
                }

                $updateCopy = $update;
                $updateCopy['callback_query']['data'] = $this->makePayload('project', 'edit', (string)$project_id);
                $this->handle($updateCopy);
            }
        } elseif ($state_data) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $this->userStateRepo->clear($userId, 'editing_project_field');

            $project_id = $state_data['project_id'];
            $field = $state_data['field'];
            $value = $text;

            if ('cover' === $field) {
                if ($photo) {
                    $value = end($photo)['file_id'];
                } else {
                    return; // Ignore if no photo is sent
                }
            }

            $content_service->updateContent($project_id, [$field => $value]);
            $this->client->sendMessage($chatId, $this->translate('project_updated_message'));

            $updateCopy = $update;
            $updateCopy['callback_query'] = [
                'data' => $this->makePayload('project', 'edit', (string)$project_id),
                'message' => ['chat' => ['id' => $chatId]],
                'from' => ['id' => $userId]
            ];
            $this->handle($updateCopy);
        }
    }
}
