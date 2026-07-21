<?php

namespace aniNya\I18n\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use aniNya\I18n\Service\TranslationService;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;

class TranslateController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        try {
            $actor = RequestUtil::getActor($request);
            $actor->assertRegistered();

            $body = json_decode((string) $request->getBody(), true) ?? [];
            $rawText = $body['raw_text']
                ?? $body['data']['attributes']['raw_text']
                ?? null;

            $settings = app(SettingsRepositoryInterface::class);
            if (! $settings->get('ani-nya-i18n.auto_translate')) {
                return new \Laminas\Diactoros\Response\JsonResponse(['message' => 'disabled']);
            }

            $targetLang = $body['target_lang']
                ?? $body['data']['attributes']['target_lang']
                ?? $settings->get('ani-nya-i18n.target_lang', 'zh-Hans');

            if ($rawText) {
                $translator = app(TranslationService::class);
                $result = $translator->translate(
                    discussionId: -1,
                    field: 'raw',
                    text: $rawText,
                    targetLang: $targetLang
                );

                return new \Laminas\Diactoros\Response\JsonResponse([
                    'data' => [
                        'type' => 'i18n-translations',
                        'id' => '0',
                        'attributes' => [
                            'translated_text' => $result?->translated_text ?? $rawText,
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

        // HTML 标签
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
