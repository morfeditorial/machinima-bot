<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
    private $token = '';

    private $offset = 0;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function request($method, $params = [])
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        $out = json_decode(curl_exec($curl), true);

        curl_close($curl);

        return $out;
    }

    public function getUpdates($timeout = 30)
    {
        $params = [
            'timeout' => $timeout,
            'offset' => $this->offset,
        ];
        $updates = $this->request('getUpdates', $params);

        if (isset($updates['result'])) {
            foreach ($updates['result'] as $update) {
                $this->offset = $update['update_id'] + 1;
                $this->handleUpdate($update);
            }
        }
    }

    public function keyboardMakeup($type, $buttonsArray, $additional_params = null)
    {
        $answer = [$type => $buttonsArray];
        if (null != $additional_params) {
            $answer = array_merge($answer, $additional_params);
        }

        return $answer;
    }

    public function inlineButton($text, $callback_query, $additional_params = null)
    {
        $answer = ['text' => $text, 'callback_data' => $callback_query];
        if (null != $additional_params) {
            $answer = array_merge($answer, $additional_params);
        }

        return $answer;
    }

    public function callbackAnswer($callback_query_id, $text = null, $additional_params = null)
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

    public function endCB($id)
    {
        return $this->callbackAnswer($id);
    }

    public function hideKeyboard()
    {
        return ['remove_keyboard' => true];
    }

    public function replyMarkup($reply_markup, $resize = true)
    {
        $keyboards = [
            'resize_keyboard' => $resize,
            'keyboard' => $reply_markup,
        ];

        return $keyboards;
    }

    public function sendChatAction($chat, $action)
    {
        $params = [
            'chat_id' => $chat,
            'action' => $action,
        ];

        return $this->request('sendChatAction', $params);
    }

    public function sendMessage($chat, $text, $keyboard = null, $additional_params = null)
    {
        $params = [
            'parse_mode' => 'HTML',
            'chat_id' => $chat,
            'text' => $text,
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->sendChatAction($chat, 'typing');
        sleep(2);

        return $this->request('sendMessage', $params);
    }

    public function sendMessageWithTypingStatus($chat, $text, $keyboard = null, $additional_params = null)
    {
        $this->sendChatAction($chat, 'typing');
        sleep(3);

        return $this->sendMessage($chat, $text, $keyboard, $additional_params);
    }

    public function sendButton($chat, $text, $keyboard = null, $additional_params = null)
    {
        $params = [
            'parse_mode' => 'HTML',
            'chat_id' => $chat,
            'text' => $text,
        ];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (null != $additional_params) {
            $params = array_merge($params, $additional_params);
        }

        $this->sendChatAction($chat, 'typing');
        sleep(2);

        return $this->request('sendMessage', $params);
    }

    public function deleteMessage($chat_id, $message_id)
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ]);
    }

    public function editMessage($chat_id, $message, int $message_id, ?array $keyboard = null, $additional_params = null) : void
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

    public function editMediaMessage($chat_id, $message_id, $media, $text = null, ?array $keyboard = null, $additional_params = null) : void
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

    public function kick($chat, $userid)
    {
        return $this->request('kickChatMember', ['chat_id' => $chat, 'user_id' => $userid]);
    }

    public function getUserIdByUsername($username)
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

    public function createPoll($chat, $question, $answers = [])
    {
        return $this->request('sendPoll', [
            'chat_id' => $chat,
            'question' => $question,
            'options' => $answers,
        ]);
    }

    public function pictureReply($chat, $text, $url_of_picture, $keyboard = null)
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat, 'caption' => $text, 'photo' => $url_of_picture];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'upload_photo');
        sleep(2);

        return $this->request('sendPhoto', $params);
    }

    public function videoReply($chat, $text, $url_of_video, $keyboard = null)
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat, 'caption' => $text, 'video' => $url_of_video];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'upload_video');
        sleep(2);

        return $this->request('sendVideo', $params);
    }

    public function gifReply($chat, $text, $url_of_gif, $keyboard = null)
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat, 'caption' => $text, 'animation' => $url_of_gif];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'upload_photo');
        sleep(2);

        return $this->request('sendAnimation', $params);
    }

    public function audioReply($chat, $text, $url_of_audio, $keyboard = null)
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat, 'caption' => $text, 'audio' => $url_of_audio];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'upload_audio');
        sleep(2);

        return $this->request('sendAudio', $params);
    }

    public function voiceReply($chat, $text, $url_of_voice, $keyboard = null)
    {
        $params = ['parse_mode' => 'HTML', 'chat_id' => $chat, 'caption' => $text, 'voice' => $url_of_voice];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'upload_voice');
        sleep(2);

        return $this->request('sendVoice', $params);
    }

    public function videoNoteReply($chat, $url_of_vidnote, $keyboard = null) //URL UNSUPPORTED
    {
        $params = ['chat_id' => $chat, 'video' => $url_of_vidnote];
        if (null != $keyboard) {
            $params['reply_markup'] = json_encode($keyboard, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->sendChatAction($chat, 'typing');
        sleep(2);

        return $this->request('sendVideoNote', $params);
    }

    public function setChatTitle($chat, $title)
    {
        return $this->request('setChatTitle', ['chat_id' => $chat, 'title' => $title]);
    }

    public function chatInviteLink($chat)
    {
        return $this->request('exportChatInviteLink', ['chat_id' => $chat])['result'];
    }

    public function pinMessage($chat, $message_id)
    {
        return $this->request('pinChatMessage', ['chat_id' => $chat, 'message_id' => $message_id]);
    }

    public function unpinMessage($chat)
    {
        return $this->request('unpinChatMessage', ['chat_id' => $chat]);
    }

    public function setCommands($commands = '[]', $scope = '{"type":"default"}', $language_code = '')
    {
        $params = [
            'commands' => $commands,
            'scope' => $scope ?? '{"type":"default"}',
            'language_code' => $language_code,
        ];

        return $this->request('setMyCommands', $params);
    }

    public function delCommand($scope = '{"type":"default"}', $language_code = '')
    {
        $params = [
            'scope' => $scope,
            'language_code' => $language_code,
        ];

        return $this->request('deleteMyCommands', $params);
    }

    public function getCommands()
    {
        return $this->request('getMyCommands');
    }

    public function setMyName($name = '', $language_code = '')
    {
        $params = [
            'name' => $name,
            'language_code' => $language_code,
        ];

        return $this->request('setMyName', $params);
    }

    public function getMyName($language_code = '')
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyName', $params);
    }

    public function setMyDescription($description = '', $language_code = '')
    {
        $params = [
            'description' => $description,
            'language_code' => $language_code,
        ];

        return $this->request('setMyDescription', $params);
    }

    public function getMyDescription($language_code = '')
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyDescription', $params);
    }

    public function setMyShortDescription($description = '', $language_code = '')
    {
        $params = [
            'short_description' => $description,
            'language_code' => $language_code,
        ];

        return $this->request('setMyShortDescription', $params);
    }

    public function getMyShortDescription($language_code = '')
    {
        $params = [
            'language_code' => $language_code,
        ];

        return $this->request('getMyShortDescription', $params);
    }

    public function tempban($chat, $userid, $time)
    {
        return $this->request('kickChatMember', [
            'chat_id' => $chat,
            'user_id' => $userid,
            'until_date' => $time,
        ]);
    }

    public function toUnix($mainstr)
    {
        if (mb_stripos($mainstr, 'm')) {
            $end = mb_stripos($mainstr, 'm');
            $timeTEMP = mb_substr($mainstr, 0, $end);
            $time = $timeTEMP * 60;

            return $time;
        } elseif (mb_stripos($mainstr, 's')) {
            $end = mb_stripos($mainstr, 's');
            $timeTEMP = mb_substr($mainstr, 0, $end);

            return $timeTEMP; //in theory this shit works
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
