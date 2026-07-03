<?php

namespace Morfeditorial\Controller\Webhook;

use Doctrine\ORM\EntityManagerInterface;
use Morfeditorial\TelegramBotBundle\Routing\UpdateDispatcher;
use Morfeditorial\Translator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class TelegramController extends AbstractController
{
    public function __construct(
        private UpdateDispatcher $updateDispatcher,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private Translator $translator,
    ) {}

    #[Route('/webhook/telegram', name: 'webhook_telegram', methods: ['POST'])]
    public function handle(Request $request) : Response
    {
        $update = json_decode($request->getContent(), true);
        if ($update) {
            $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? null;
            if ($userId) {
                // We use the full class name since this bundle relies on the host app's entity
                $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
                if ($user) {
                    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                    $this->tokenStorage->setToken($token);
                }

                $languageCode = $update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? 'en';
                $this->translator->setUserLocale($languageCode);
            }

            $this->updateDispatcher->dispatch($update);
            $this->tokenStorage->setToken(null);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
