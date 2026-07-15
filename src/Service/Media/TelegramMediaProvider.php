<?php

namespace Morfeditorial\MachinimaBotBundle\Service\Media;

use Morfeditorial\MachinimaCoreBundle\Service\Media\MediaProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(id: MediaProviderInterface::class)]
class TelegramMediaProvider implements MediaProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $botToken,
    ) {}

    public function getMediaUrl(string $fileId) : string
    {
        if (empty($this->botToken)) {
            return 'default';
        }

        try {
            $fileResponse = $this->httpClient->request('GET', "https://api.telegram.org/bot{$this->botToken}/getFile", [
                'query' => ['file_id' => $fileId],
            ]);

            $fileData = $fileResponse->toArray(false);

            if (!empty($fileData['result']['file_path'])) {
                return "https://api.telegram.org/file/bot{$this->botToken}/".$fileData['result']['file_path'];
            }
        } catch (\Exception $e) {
            // Ignore exceptions and fallback
        }

        return 'default';
    }
}
