<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class BaiduTranslate implements EngineInterface
{
    protected Client $client;

    private const LANG_MAP = [
        'zh-Hans' => 'zh',
        'zh-Hant' => 'cht',
        'zh'      => 'zh',
        'ja'      => 'jp',
        'ko'      => 'kor',
        'en'      => 'en',
        'fr'      => 'fra',
        'es'      => 'spa',
        'de'      => 'de',
        'ru'      => 'ru',
        'pt'      => 'pt',
        'it'      => 'it',
        'nl'      => 'nl',
        'pl'      => 'pl',
        'th'      => 'th',
        'ar'      => 'ara',
        'el'      => 'el',
        'cs'      => 'cs',
        'da'      => 'dan',
        'fi'      => 'fin',
        'hu'      => 'hu',
        'ro'      => 'rom',
        'sv'      => 'swe',
        'bg'      => 'bul',
        'sl'      => 'slo',
        'vi'      => 'vie',
        'id'      => 'id',
        'uk'      => 'ukr',
    ];

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $this->client = new Client([
            'base_uri' => 'https://fanyi-api.baidu.com',
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        $appid = trim($this->settings->get('ani-nya-i18n.baidu_appid') ?? '');
        $secret = trim($this->settings->get('ani-nya-i18n.baidu_secret') ?? '');

        if (empty($appid) || empty($secret)) {
            return null;
        }

        $baiduTo = self::LANG_MAP[$targetLang] ?? $targetLang;
        $baiduFrom = $sourceLang === 'auto' ? 'auto' : (self::LANG_MAP[$sourceLang] ?? $sourceLang);

        $salt = (string) mt_rand(10000, 99999);
        $sign = md5($appid . $text . $salt . $secret);

        try {
            $response = $this->client->post('/api/trans/vip/translate', [
                'form_params' => [
                    'q' => $text,
                    'from' => $baiduFrom,
                    'to' => $baiduTo,
                    'appid' => $appid,
                    'salt' => $salt,
                    'sign' => $sign,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!empty($body['error_code'])) {
                return null;
            }

            return $body['trans_result'][0]['dst'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'baidu';
    }
}
