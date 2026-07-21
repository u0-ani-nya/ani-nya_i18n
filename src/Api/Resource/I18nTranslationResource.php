<?php

namespace aniNya\I18n\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use aniNya\I18n\Models\I18nTranslation;
use aniNya\I18n\Service\TranslationService;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;

class I18nTranslationResource extends AbstractDatabaseResource
{
    public function __construct(
        protected TranslationService $translator,
        protected SettingsRepositoryInterface $settings,
    ) {}

    public function type(): string
    {
        return 'i18n-translations';
    }

    public function model(): string
    {
        return I18nTranslation::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $query->orderByDesc('created_at');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 50),

            Endpoint\Show::make(),

            Endpoint\Create::make()
                ->authenticated()
                ->admin(),

            Endpoint\Delete::make()
                ->authenticated()
                ->admin(),
        ];
    }

    protected function translate(FlarumContext $context): array
    {
        $actor = $context->getActor();
        $actor->assertRegistered();

        $discussionId = (int) Arr::get($context->body(), 'data.attributes.discussion_id', 0);

        if (! $discussionId) {
            return ['error' => 'discussion_id required'];
        }

        $discussion = Discussion::find($discussionId);
        if (! $discussion) {
            return ['error' => 'Discussion not found'];
        }

        if (! $this->settings->get('ani-nya-i18n.auto_translate')) {
            return ['message' => 'disabled'];
        }

        $targetLang = $discussion->user?->locale ?? 'zh-Hans';
        $results = [];

        $titleResult = $this->translator->translate(
            discussionId: $discussion->id,
            field: 'title',
            text: $discussion->title,
            targetLang: $targetLang
        );
        if ($titleResult) {
            $results[] = 'title';
        }

        $content = $discussion->content;
        if (! empty($content)) {
            $contentResult = $this->translator->translate(
                discussionId: $discussion->id,
                field: 'content',
                text: $content,
                targetLang: $targetLang
            );
            if ($contentResult) {
                $results[] = 'content';
            }
        }

        return [
            'data' => [
                'type' => 'i18n-translations',
                'id' => (string) $discussionId,
                'attributes' => [
                    'translated' => $results,
                ],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('discussion_id')
                ->requiredOnCreate(),

            Schema\Str::make('field')
                ->requiredOnCreate()
                ->in(['title', 'content']),

            Schema\Str::make('source_lang')
                ->maxLength(10),

            Schema\Str::make('target_lang')
                ->requiredOnCreate()
                ->maxLength(10),

            Schema\Str::make('sourceText')
                ->get(fn (I18nTranslation $t) => $t->source_text),

            Schema\Str::make('translatedText')
                ->get(fn (I18nTranslation $t) => $t->translated_text),

            Schema\Str::make('engine')
                ->maxLength(50),

            Schema\DateTime::make('created_at'),
            Schema\DateTime::make('updated_at'),
        ];
    }
}
