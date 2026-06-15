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

namespace morfeditorial;

ini_set('display_errors', 1);

error_reporting(E_ALL);

class tgLib
{
    private string $token = '';

    private int $offset = 0;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function request(string $method, array $params = []) : ?array
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        $response = curl_exec($curl);

        if (false === $response) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function getUpdates() : array
    {
        $updates = $this->request('getUpdates', ['offset' => $this->offset]);

        if (! is_array($updates) || ! isset($updates['result'])) {
            return [];
        }

        $result = [];

        foreach ($updates['result'] as $update) {
            $this->offset = $update['update_id'] + 1;
            $result[] = $update;
        }

        return $result;
    }

    public function keyboardMakeup(string $type, array $buttonsArray, ?array $additional_params = null) : array
    {
        $answer = [$type => $buttonsArray];
        if (null != $additional_params) {
            $answer = array_merge($answer, $additional_params);
        }

        return $answer;
    }

    public function inlineButton(string $text, string $callback_query, ?array $additional_params = null) : array
    {
        $answer = ['text' => $text, 'callback_data' => $callback_query];
        if (null != $additional_params) {
            $answer = array_merge($answer, $additional_params);
        }

        return $answer;
    }

    public function callbackAnswer(string $callback_query_id, ?string $text = null, ?array $additional_params = null) : array
    {
        $params = ['callback_query_id' => $callback_query_id];
        if (null != $text) {
            $params['text'] = $text;
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        return $this->request('answerCallbackQuery', $params);
    }

    public function endCallback(string $id) : array
    {
        return $this->callbackAnswer($id);
    }

    public function hideKeyboard() : array
    {
        return ['remove_keyboard' => true];
    }

    public function replyMarkup(array $reply_markup, bool $resize = true) : array
    {
        $keyboards = [
            'resize_keyboard' => $resize,
            'keyboard' => $reply_markup,
        ];

        return $keyboards;
    }

    public function sendChatAction(int $chat_id, string $action) : array
    {
        $params = [
            'chat_id' => $chat_id,
            'action' => $action,
        ];

        return $this->request('sendChatAction', $params);
    }

    public function sendMessage(int $chat_id, string $text, ?array $keyboard = null, ?array $additional_params = null) : array
    {
        $params = [
            'parse_mode' => 'HTML',
            'chat_id' => $chat_id,
            'text' => $text,
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->sendChatAction($chat_id, 'typing');
        sleep(2);

        return $this->request('sendMessage', $params);
    }

    public function sendMessageWithTypingStatus(int $chat_id, string $text, ?array $keyboard = null, ?array $additional_params = null) : array
    {
        $this->sendChatAction($chat_id, 'typing');
        sleep(3);

        return $this->sendMessage($chat_id, $text, $keyboard, $additional_params);
    }

    public function sendButton(int $chat_id, string $text, ?array $keyboard = null, ?array $additional_params = null) : array
    {
        $params = [
            'parse_mode' => 'HTML',
            'chat_id' => $chat_id,
            'text' => $text,
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->sendChatAction($chat_id, 'typing');
        sleep(2);

        return $this->request('sendMessage', $params);
    }

    public function deleteMessage(int $chat_id, int $message_id) : array
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ]);
    }

    public function editMessage(int $chat_id, string $message, int $message_id, ?array $keyboard = null, ?array $additional_params = null) : void
    {
        $params = [
            'message_id' => $message_id,
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard,
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->request('editMessageText', $params);
    }

    public function editMediaMessage(int $chat_id, int $message_id, string $media, ?string $text = null, ?array $keyboard = null, ?array $additional_params = null) : void
    {
        $photo = [
            'type' => 'photo',
            'media' => $media,
            'caption' => $text,
            'parse_mode' => 'HTML',
        ];
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'media' => json_encode($photo),
            'reply_markup' => json_encode($keyboard),
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->request('editMessageMedia', $params);
    }

    public function kick(int $chat_id, int $user_id) : array
    {
        return $this->request('kickChatMember', ['chat_id' => $chat_id, 'user_id' => $user_id]);
    }

    public function getUserIdByUsername(string $username) : int|false
    {
        if (preg_match('/^@?([a-zA-Z0-9_]{5,32})$/', $username, $matches)) {
            $username = $matches[1];
        }
        $response = $this->request('getChat', ['chat_id' => $username]);
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['ok']) && true === $responseData['ok']) {
            return $responseData['result']['id'];
        }

        return false;
    }

    public function createPoll(int $chat_id, string $question, array $answers = []) : array
    {
        return $this->request('sendPoll', [
            'chat_id' => $chat_id,
            'question' => $question,
            'options' => $answers,
        ]);
    }

    public function pictureReply(int $chat_id, string $text, string $url_of_picture, ?array $keyboard = null) : array
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat_id, 'caption' => $text, 'photo' => $url_of_picture];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'upload_photo');
        sleep(2);

        return $this->request('sendPhoto', $params);
    }

    public function videoReply(int $chat_id, string $text, string $url_of_video, ?array $keyboard = null) : array
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat_id, 'caption' => $text, 'video' => $url_of_video];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'upload_video');
        sleep(2);

        return $this->request('sendVideo', $params);
    }

    public function gifReply(int $chat_id, string $text, string $url_of_gif, ?array $keyboard = null) : array
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat_id, 'caption' => $text, 'animation' => $url_of_gif];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'upload_photo');
        sleep(2);

        return $this->request('sendAnimation', $params);
    }

    public function audioReply(int $chat_id, string $text, string $url_of_audio, ?array $keyboard = null) : array
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat_id, 'caption' => $text, 'audio' => $url_of_audio];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'upload_audio');
        sleep(2);

        return $this->request('sendAudio', $params);
    }

    public function voiceReply(int $chat_id, string $text, string $url_of_voice, ?array $keyboard = null) : array
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat_id, 'caption' => $text, 'voice' => $url_of_voice];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'upload_voice');
        sleep(2);

        return $this->request('sendVoice', $params);
    }

    public function videoNoteReply(int $chat_id, string $url_of_vidnote, ?array $keyboard = null) : array
    {
        $params = ['chat_id' => $chat_id, 'video' => $url_of_vidnote];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat_id, 'typing');
        sleep(2);

        return $this->request('sendVideoNote', $params);
    } // URL UNSUPPORTED

    public function setChatTitle(int $chat_id, string $title) : array
    {
        return $this->request('setChatTitle', ['chat_id' => $chat_id, 'title' => $title]);
    }

    public function chatInviteLink(int $chat_id) : string
    {
        return $this->request('exportChatInviteLink', ['chat_id' => $chat_id])['result'];
    }

    public function pinMessage(int $chat_id, int $message_id) : array
    {
        return $this->request('pinChatMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
    }

    public function unpinMessage(int $chat_id) : array
    {
        return $this->request('unpinChatMessage', ['chat_id' => $chat_id]);
    }

    public function setCommands(string $commands = '[]', ?string $scope = '{"type":"default"}', string $language_code = '') : array
    {
        $params = [
            'commands' => $commands,
            'scope' => $scope ?? '{"type":"default"}',
            'language_code' => $language_code,
        ];

        return $this->request('setMyCommands', $params);
    }

    public function delCommand(string $scope = '{"type":"default"}', string $language_code = '') : array
    {
        $params = [
            'scope' => $scope,
            'language_code' => $language_code,
        ];

        return $this->request('deleteMyCommands', $params);
    }

    public function getCommands() : array
    {
        return $this->request('getMyCommands');
    }

    public function setMyName(string $name = '', string $language_code = '') : array
    {
        $params = [
            'name' => $name,
            'language_code' => $language_code,
        ];

        return $this->request('setMyName', $params);
    }

    public function getMyName(string $language_code = '') : array
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyName', $params);
    }

    public function setMyDescription(string $description = '', string $language_code = '') : array
    {
        $params = [
            'description' => $description,
            'language_code' => $language_code,
        ];

        return $this->request('setMyDescription', $params);
    }

    public function getMyDescription(string $language_code = '') : array
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyDescription', $params);
    }

    public function setMyShortDescription(string $description = '', string $language_code = '') : array
    {
        $params = [
            'short_description' => $description,
            'language_code' => $language_code,
        ];

        return $this->request('setMyShortDescription', $params);
    }

    public function getMyShortDescription(string $language_code = '') : array
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyShortDescription', $params);
    }

    public function tempban(int $chat_id, int $user_id, int $time) : array
    {
        return $this->request('kickChatMember', [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
            'until_date' => $time,
        ]);
    }

    public function toUnix(string $mainstr) : int|array
    {
        if (mb_stripos($mainstr, 'm')) {
            $end = mb_stripos($mainstr, 'm');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 60;

            return $time;
        } elseif (mb_stripos($mainstr, 's')) {
            $end = mb_stripos($mainstr, 's');
            $timeTEMP = mb_substr($mainstr, 0, $end);

            return $timeTEMP; // in theory this shit works
        } elseif (mb_stripos($mainstr, 'h')) {
            $end = mb_stripos($mainstr, 'h');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 3600;

            return $time;
        } elseif (mb_stripos($mainstr, 'd')) {
            $end = mb_stripos($mainstr, 'd');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 86400;

            return $time;
        } elseif (mb_stripos($mainstr, 'w')) {
            $end = mb_stripos($mainstr, 'w');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 604800;

            return $time;
        } elseif (mb_stripos($mainstr, 'M')) {
            $end = mb_stripos($mainstr, 'M');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 2629743;

            return $time;
        } elseif (mb_stripos($mainstr, 'y')) {
            $end = mb_stripos($mainstr, 'y');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 31556926;

            return $time;
        } else {
            $array = ['error_code' => 'NO_TIME', 'description' => "TIME ISN'T POINTED OR INCORRECT"];

            return $array;
        }
    }
}
