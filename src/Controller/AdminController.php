<?php

namespace aniNya\I18n\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use aniNya\I18n\Models\I18nTranslation;
use aniNya\I18n\Service\TranslationService;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;

class AdminController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (str_contains($path, 'clear-all')) {
            return $this->clearAll($request);
        }
        if (str_contains($path, 'clear-discussion')) {
            return $this->clearDiscussion($request);
        }
        if (str_contains($path, 'fill-all')) {
            return $this->fillAll($request);
        }
        if (str_contains($path, 'fill-discussion')) {
            return $this->fillDiscussion($request);
        }

        return new \Laminas\Diactoros\Response\JsonResponse(['error' => 'Not found'], 404);
    }

    private function clearAll(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $count = I18nTranslation::query()->count();
        I18nTranslation::query()->delete();

        return new \Laminas\Diactoros\Response\JsonResponse([
            'data' => ['cleared' => $count],
        ]);
    }

    private function clearDiscussion(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $discussionId = $body['discussion_id'] ?? null;

        if (! $discussionId) {
            return new \Laminas\Diactoros\Response\JsonResponse(
                ['error' => 'discussion_id required'], 422
            );
        }

        $count = I18nTranslation::where('discussion_id', $discussionId)->count();
        I18nTranslation::where('discussion_id', $discussionId)->delete();

        return new \Laminas\Diactoros\Response\JsonResponse([
            'data' => ['cleared' => $count],
        ]);
    }

    private function fillAll(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $settings = app(SettingsRepositoryInterface::class);
        $targetLang = $settings->get('ani-nya-i18n.target_lang', 'zh-Hans');
        $translator = app(TranslationService::class);

        $discussions = Discussion::latest()->limit(200)->get();
        $translated = 0;
        $skipped = 0;

        foreach ($discussions as $discussion) {
            $existing = I18nTranslation::where('discussion_id', $discussion->id)
                ->where('target_lang', $targetLang)
                ->count();

            if ($existing > 0) {
                $skipped++;
                continue;
            }

            $translator->translate(
                discussionId: $discussion->id,
                field: 'title',
                text: $discussion->title,
                targetLang: $targetLang
            );

            $posts = $discussion->posts()->where('type', 'comment')->orderBy('number')->get();
            foreach ($posts as $post) {
                $rawContent = $post->formatContent($request) ?? '';
                if (empty(trim($rawContent))) continue;

                $translator->translate(
                    discussionId: $discussion->id,
                    field: 'post_' . $post->id,
                    text: $rawContent,
                    targetLang: $targetLang
                );
            }

            $translated++;
        }

        return new \Laminas\Diactoros\Response\JsonResponse([
            'data' => [
                'total' => $discussions->count(),
                'translated' => $translated,
                'skipped' => $skipped,
            ],
        ]);
    }

    private function fillDiscussion(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $discussionId = $body['discussion_id'] ?? null;

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

        $settings = app(SettingsRepositoryInterface::class);
        $targetLang = $discussion->user?->locale
            ?? $settings->get('ani-nya-i18n.target_lang', 'zh-Hans');
        $translator = app(TranslationService::class);

        $titleResult = $translator->translate(
            discussionId: $discussion->id,
            field: 'title',
            text: $discussion->title,
            targetLang: $targetLang
        );

        $posts = $discussion->posts()->where('type', 'comment')->orderBy('number')->get();
        $translatedPosts = 0;
        foreach ($posts as $post) {
            $existing = I18nTranslation::where('discussion_id', $discussion->id)
                ->where('field', 'post_' . $post->id)
                ->where('target_lang', $targetLang)
                ->first();

            if ($existing) {
                continue;
            }

            $rawContent = $post->formatContent($request) ?? '';
            if (empty(trim($rawContent))) continue;

            $translator->translate(
                discussionId: $discussion->id,
                field: 'post_' . $post->id,
                text: $rawContent,
                targetLang: $targetLang
            );
            $translatedPosts++;
        }

        return new \Laminas\Diactoros\Response\JsonResponse([
            'data' => [
                'discussion_id' => $discussionId,
                'title_translated' => $titleResult ? true : false,
                'posts_translated' => $translatedPosts,
            ],
        ]);
    }
}
