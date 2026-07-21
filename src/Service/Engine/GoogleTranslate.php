<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class GoogleTranslate implements EngineInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $this->apiKey = $this->settings->get('ani-nya-i18n.google_key');

        $this->client = new Client([
            'base_uri' => 'https://translation.googleapis.com/',
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        if (empty($this->apiKey)) {

            return null;
        }

        try {
            $response = $this->client->post('/language/translate/v2', [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'q' => $text,
                    'target' => $targetLang,
                    'source' => $sourceLang === 'auto' ? null : $sourceLang,
                    'format' => 'text',
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return $body['data']['translations'][0]['translatedText'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'google';
    }
}
