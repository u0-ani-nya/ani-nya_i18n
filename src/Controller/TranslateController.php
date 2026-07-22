<?php

namespace aniNya\I18n\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use aniNya\I18n\Service\TranslationService;
use aniNya\I18n\Models\I18nTranslation;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;

class TranslateController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        try {
            $actor = RequestUtil::getActor($request);
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $settings = app(SettingsRepositoryInterface::class);
            $isGuest = $actor->isGuest();

            $rawText = $body['raw_text']
                ?? $body['data']['attributes']['raw_text']
                ?? null;

            $targetLang = $body['target_lang']
                ?? $body['data']['attributes']['target_lang']
                ?? $settings->get('ani-nya-i18n.target_lang', 'zh-Hans');

            if ($rawText) {
                if ($isGuest) {
                    $cached = I18nTranslation::where('field', 'raw')
                        ->where('target_lang', $targetLang)
                        ->where('source_text', $rawText)
                        ->first();
                    $translated = $cached?->translated_text ?? $rawText;
                } else {
                    $translator = app(TranslationService::class);
                    $result = $translator->translate(
                        discussionId: -1,
                        field: 'raw',
                        text: $rawText,
                        targetLang: $targetLang
                    );
                    $translated = $result?->translated_text ?? $rawText;
                }

                return new \Laminas\Diactoros\Response\JsonResponse([
                    'data' => [
                        'type' => 'i18n-translations',
                        'id' => '0',
                        'attributes' => [
                            'translated_text' => $translated,
                        ],
                    ],
                ]);
            }

            $discussionId = $body['discussion_id']
                ?? $body['data']['attributes']['discussion_id']
                ?? null;

            if (! $discussionId) {
                return new \Laminas\Diactoros\Response\JsonResponse(
                    ['error' => 'discussion_id required'], 422
                );
            }

            $discussion = Discussion::find($discussionId);
            if (! $discussion) {
                return new \Laminas\Diactoros\Response\JsonResponse(
                    ['error' => 'Discussion not found'], 404
                );
            }

            $targetLang = $body['target_lang']
                ?? $body['data']['attributes']['target_lang']
                ?? $discussion->user?->locale
                ?? $settings->get('ani-nya-i18n.target_lang', 'zh-Hans');

            if ($isGuest) {
                $posts = $discussion->posts()->where('type', 'comment')->orderBy('number')->get();
                $postMap = [];
                foreach ($posts as $post) {
                    $postMap[$post->id] = $post;
                }

                $cachedTranslations = I18nTranslation::where('discussion_id', $discussionId)
                    ->where('target_lang', $targetLang)
                    ->get();

                $translations = [];
                foreach ($cachedTranslations as $cached) {
                    $translatedText = $cached->translated_text;

                    if (str_starts_with($cached->field, 'post_')) {
                        $postId = (int) substr($cached->field, 5);
                        $post = $postMap[$postId] ?? null;
                        if ($post) {
                            $rawContent = $post->formatContent($request) ?? '';
                            [, $protected] = $this->extractProtected($rawContent);
                            $translatedText = $this->restoreProtected($translatedText, $protected);
                        }
                    }

                    $translations[] = [
                        'field'           => $cached->field,
                        'post_id'         => str_starts_with($cached->field, 'post_')
                            ? (int) substr($cached->field, 5)
                            : null,
                        'source_lang'     => $cached->source_lang,
                        'target_lang'     => $cached->target_lang,
                        'source_text'     => $cached->source_text,
                        'translated_text' => $translatedText,
                    ];
                }

                return new \Laminas\Diactoros\Response\JsonResponse([
                    'data' => [
                        'type' => 'i18n-translations',
                        'id' => (string) $discussionId,
                        'attributes' => [
                            'target_lang' => $targetLang,
                            'translations' => $translations,
                        ],
                    ],
                ]);
            }

            $translator = app(TranslationService::class);
            $translations = [];

            // 翻译标题
            $titleResult = $translator->translate(
                discussionId: $discussion->id,
                field: 'title',
                text: $discussion->title,
                targetLang: $targetLang
            );
            if ($titleResult) {
                $translations[] = [
                    'field' => 'title',
                    'post_id' => null,
                    'source_lang' => $titleResult->source_lang,
                    'target_lang' => $titleResult->target_lang,
                    'source_text' => $titleResult->source_text,
                    'translated_text' => $titleResult->translated_text,
                ];
            }

            // 翻译所有帖子
            $posts = $discussion->posts()->where('type', 'comment')->orderBy('number')->get();
            foreach ($posts as $post) {
                $rawContent = $post->formatContent($request) ?? '';
                if (empty($rawContent)) continue;

                [$plainText, $protected] = $this->extractProtected($rawContent);

                if (empty(trim($plainText))) continue;

                $checkText = preg_replace('/\{I\d+\}/', '', $plainText);
                if (empty(trim($checkText))) continue;

                $contentResult = $translator->translate(
                    discussionId: $discussion->id,
                    field: 'post_' . $post->id,
                    text: $plainText,
                    targetLang: $targetLang
                );

                if ($contentResult) {
                    $translatedHtml = $this->restoreProtected(
                        $contentResult->translated_text,
                        $protected
                    );

                    $translations[] = [
                        'field' => 'content',
                        'post_id' => $post->id,
                        'source_lang' => $contentResult->source_lang,
                        'target_lang' => $contentResult->target_lang,
                        'source_text' => $rawContent,
                        'translated_text' => $translatedHtml,
                    ];
                }
            }

            return new \Laminas\Diactoros\Response\JsonResponse([
                'data' => [
                    'type' => 'i18n-translations',
                    'id' => (string) $discussionId,
                    'attributes' => [
                        'target_lang' => $targetLang,
                        'translations' => $translations,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return new \Laminas\Diactoros\Response\JsonResponse(
                ['error' => $e->getMessage()], 500
            );
        }
    }

    /**
     * 提取受保护的内容（BBCode、HTML标签），用占位符替换
     */
    private function extractProtected(string $html): array
    {
        $protected = [];
        $index = 0;

        // BBCode [...]
        $text = preg_replace_callback('/\[[^\]]+\]/u', function ($matches) use (&$protected, &$index) {
            $id = '{I' . $index . '}';
            $protected[$id] = $matches[0];
            $index++;
            return $id;
        }, $html);

        // <a> 链接/提及（整体替换，包括内容）
        $text = preg_replace_callback('/<a\b[^>]*>.*?<\/a>/us', function ($matches) use (&$protected, &$index) {
            $id = '{I' . $index . '}';
            $protected[$id] = $matches[0];
            $index++;
            return $id;
        }, $text);

        // 其他 HTML 标签（只替换标签本身，保留内容）
        $text = preg_replace_callback('/<[^>]+>/u', function ($matches) use (&$protected, &$index) {
            $id = '{I' . $index . '}';
            $protected[$id] = $matches[0];
            $index++;
            return $id;
        }, $text);

        // 清理多余空白
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return [$text, $protected];
    }

    /**
     * 还原受保护的内容
     */
    private function restoreProtected(string $translated, array $protected): string
    {
        foreach ($protected as $placeholder => $original) {
            $translated = str_replace($placeholder, $original, $translated);
        }
        return $translated;
    }
}
