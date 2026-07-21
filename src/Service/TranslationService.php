<?php

namespace aniNya\I18n\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use aniNya\I18n\Models\I18nTranslation;
use aniNya\I18n\Service\Engine\EngineInterface;
use aniNya\I18n\Service\Engine\OpenAI;
use aniNya\I18n\Service\Engine\GoogleTranslate;
use aniNya\I18n\Service\Engine\DeepL;
use aniNya\I18n\Service\Engine\YandexTranslate;
use aniNya\I18n\Service\Engine\BaiduTranslate;
use aniNya\I18n\Service\Engine\LibreTranslate;
use aniNya\I18n\Service\Engine\FreeGoogleTranslate;

class TranslationService
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {}

    public function translate(
        int $discussionId,
        string $field,
        string $text,
        string $targetLang,
        string $sourceLang = 'auto'
    ): ?I18nTranslation {
        if (empty(trim($text))) {
            return null;
        }

        $existing = I18nTranslation::where('discussion_id', $discussionId)
            ->where('field', $field)
            ->where('target_lang', $targetLang)
            ->first();

        if ($existing && $existing->source_text === $text) {
            return $existing;
        }

        if ($existing) {
            $existing->delete();
        }

        if ($sourceLang === 'auto') {
            $sourceLang = $this->detectLanguage($text);
        }

        if ($sourceLang === $targetLang) {
            return null;
        }

        $engine = $this->getEngine();
        $translated = $engine->translate($text, $targetLang, $sourceLang);

        if (empty($translated)) {
            return null;
        }

        $result = I18nTranslation::create([
            'discussion_id'   => $discussionId,
            'field'           => $field,
            'source_lang'     => $sourceLang,
            'target_lang'     => $targetLang,
            'source_text'     => $text,
            'translated_text' => $translated,
            'engine'          => $this->settings->get('ani-nya-i18n.engine'),
        ]);

        return $result;
    }

    protected function detectLanguage(string $text): string
    {
        preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text, $chinese);
        $chineseCount = count($chinese[0]);
        $totalChars = mb_strlen($text);

        if ($totalChars > 0 && $chineseCount / $totalChars > 0.3) {
            return 'zh';
        }

        preg_match_all('/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text, $japanese);
        if (count($japanese[0]) > 0) {
            return 'ja';
        }

        preg_match_all('/[\x{ac00}-\x{d7af}]/u', $text, $korean);
        if (count($korean[0]) > 0) {
            return 'ko';
        }

        return 'en';
    }

    protected function getEngine(): EngineInterface
    {
        $engine = $this->settings->get('ani-nya-i18n.engine');

        return match ($engine) {
            'openai' => new OpenAI($this->settings),
            'google' => new GoogleTranslate($this->settings),
            'deepl' => new DeepL($this->settings),
            'yandex' => new YandexTranslate($this->settings),
            'baidu' => new BaiduTranslate($this->settings),
            'libre' => new LibreTranslate($this->settings),
            'free_google' => new FreeGoogleTranslate($this->settings),
            default => new OpenAI($this->settings),
        };
    }
}
