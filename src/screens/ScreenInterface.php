<?php
namespace morfeditorial\screens;

interface ScreenInterface
{
    /** Відображення екрана (відправка/редагування повідомлення) */
    public function render(): void;

    /** Обробка натискання кнопки саме на цьому екрані */
    public function handleCallback(string $action, array $params): void;

    /** Обробка текстового повідомлення, якщо екран чекає вводу */
    public function handleMessage(string $text): void;
}
