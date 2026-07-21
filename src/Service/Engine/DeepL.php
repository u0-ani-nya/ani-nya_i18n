<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class DeepL implements EngineInterface
{
    protected Client $client;

    private const LANG_MAP = [
        'zh-Hans' => 'ZH',
        'zh-Hant' => 'ZH',
        'zh'      => 'ZH',
        'ja'      => 'JA',
        'ko'      => 'KO',
        'en'      => 'EN',
        'de'      => 'DE',
        'fr'      => 'FR',
        'es'      => 'ES',
        'pt'      => 'PT-BR',
        'ru'      => 'RU',
        'it'      => 'IT',
        'nl'      => 'NL',
        'pl'      => 'PL',
    ];

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $apiKey = $this->settings->get('ani-nya-i18n.deepl_key') ?? '';
        $baseUrl = str_ends_with($apiKey, ':fx')
            ? 'https://api-free.deepl.com'
            : 'https://api.deepl.com';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        $apiKey = $this->settings->get('ani-nya-i18n.deepl_key') ?? '';
        if (empty($apiKey)) {
            return null;
        }

        $deeplTarget = self::LANG_MAP[$targetLang] ?? strtoupper($targetLang);

        $payload = [
            'text' => [$text],
            'target_lang' => $deeplTarget,
        ];

        try {
            $response = $this->client->post('/v2/translate', [
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
        return 'deepl';
    }
}
