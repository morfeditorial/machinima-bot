<?php

namespace Morfeditorial\Service\Avatar;

use App\Service\Avatar\AvatarProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(id: AvatarProviderInterface::class)]
class TelegramAvatarProvider implements AvatarProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $botToken,
    ) {}

    public function getAvatarUrl(int $userId) : string
    {
        if (empty($this->botToken)) {
            return 'default';
        }

        try {
            $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$this->botToken}/getUserProfilePhotos", [
                'query' => ['user_id' => $userId, 'limit' => 1],
            ]);
            $data = $response->toArray(false);

            if (!empty($data['result']['photos'][0][0]['file_id'])) {
                $fileId = $data['result']['photos'][0][0]['file_id'];

                $fileResponse = $this->httpClient->request('GET', "https://api.telegram.org/bot{$this->botToken}/getFile", [
                    'query' => ['file_id' => $fileId],
                ]);
                $fileData = $fileResponse->toArray(false);

                if (!empty($fileData['result']['file_path'])) {
                    return "https://api.telegram.org/file/bot{$this->botToken}/".$fileData['result']['file_path'];
                }
            }
        } catch (\Exception $e) {
            // Ignore exceptions and fallback
        }

        return 'default';
    }
}
