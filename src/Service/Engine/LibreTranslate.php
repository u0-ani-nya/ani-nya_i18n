<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class LibreTranslate implements EngineInterface
{
    protected Client $client;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $baseUrl = rtrim($this->settings->get('ani-nya-i18n.libre_url') ?: 'https://libretranslate.com', '/');

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        try {
            $payload = [
                'q' => $text,
                'source' => $sourceLang === 'auto' ? 'auto' : $sourceLang,
                'target' => $targetLang,
                'format' => 'text',
            ];

            $apiKey = $this->settings->get('ani-nya-i18n.libre_key') ?? '';
            if (!empty($apiKey)) {
                $payload['api_key'] = $apiKey;
            }

            $response = $this->client->post('/translate', [
                'form_params' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return $body['translatedText'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'libre';
    }
}
