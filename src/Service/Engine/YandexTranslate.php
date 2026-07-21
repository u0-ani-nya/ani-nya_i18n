<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class YandexTranslate implements EngineInterface
{
    protected Client $client;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $apiKey = $this->settings->get('ani-nya-i18n.yandex_key') ?? '';

        $this->client = new Client([
            'base_uri' => 'https://translate.api.cloud.yandex.net',
            'headers' => [
                'Authorization' => 'Api-Key ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        $apiKey = $this->settings->get('ani-nya-i18n.yandex_key') ?? '';
        if (empty($apiKey)) {
            return null;
        }

        try {
            $payload = [
                'targetLanguageCode' => $targetLang,
                'texts' => [$text],
            ];

            if ($sourceLang !== 'auto') {
                $payload['sourceLanguageCode'] = $sourceLang;
            }

            $response = $this->client->post('/translate/v2/translate', [
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return $body['translations'][0]['text'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'yandex';
    }
}
