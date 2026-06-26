<?php
namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorPrivacyScreen extends AbstractScreen
{
    public function render(): void
    {
        // Rendering is handled by redirecting to AuthorProfileScreen after action
    }

    public function handleCallback(string $action, array $params): void
    {
        if ($action === 'set_privacy' || $action === 'set_private') {
            if (!$this->isGranted('moderator')) {
                $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
                return;
            }

            $authorId = (int)$params[0];
            $authorService = $this->bot->getContainer()->get('author_service');
            $author = $authorService->getAuthorById($authorId);
            
            if ($author !== false) {
                $isPrivate = $authorService->isPrivate($authorId);
                $authorService->setPrivate($authorId, !$isPrivate);
                
                // Manually render AuthorProfileScreen
                $this->data['author_id'] = $authorId;
                $screen = new AuthorProfileScreen($this->bot, $this->data);
                $screen->render();
            }
        }
    }

    public function handleMessage(string $text): void
    {
        // Не чекаємо тексту
    }
}
