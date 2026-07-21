<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class FreeGoogleTranslate implements EngineInterface
{
    protected Client $client;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $this->client = new Client([
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        try {
            $sl = $sourceLang === 'auto' ? 'auto' : $sourceLang;

            $response = $this->client->get('https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => $sl,
                    'tl' => $targetLang,
                    'dt' => 't',
                    'q' => $text,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!is_array($body) || empty($body[0])) {
                return null;
            }

            $translated = '';
            foreach ($body[0] as $segment) {
                if (!empty($segment[0])) {
                    $translated .= $segment[0];
                }
            }

            return !empty($translated) ? $translated : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'free_google';
    }
}
