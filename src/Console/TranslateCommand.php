<?php

namespace aniNya\I18n\Console;

use Flarum\Discussion\Discussion;
use Illuminate\Console\Command;
use aniNya\I18n\Service\TranslationService;
use Flarum\Settings\SettingsRepositoryInterface;

class TranslateCommand extends Command
{
    protected $signature = 'i18n:translate {discussion_id?} {--lang=}';
    protected $description = 'Translate discussions';

    public function __construct(
        protected TranslationService $translator,
        protected SettingsRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $discussionId = $this->argument('discussion_id');
        $targetLang = $this->option('lang') ?: $this->settings->get('ani-nya-i18n.target_lang');

        if ($discussionId) {
            $discussion = Discussion::find($discussionId);
            if (! $discussion) {
                $this->error("Discussion #{$discussionId} not found");

                return 1;
            }

            $this->translateDiscussion($discussion, $targetLang);
        } else {
            $discussions = Discussion::latest()->limit(100)->get();
            $this->info("Translating {$discussions->count()} discussions...");

            foreach ($discussions as $discussion) {
                $this->translateDiscussion($discussion, $targetLang);
            }
        }

        return 0;
    }

    protected function translateDiscussion(Discussion $discussion, string $targetLang): void
    {
        $this->info("Translating #{$discussion->id}: {$discussion->title}");

        $result = $this->translator->translate(
            discussionId: $discussion->id,
            field: 'title',
            text: $discussion->title,
            targetLang: $targetLang
        );

        if ($result) {
            $this->info("Title translated: " . substr($result->translated_text, 0, 50));
            $this->info("Saved ID: " . $result->id);
        } else {
            $this->warn("Title translation returned null");
        }

        $this->info("Done #{$discussion->id}");
    }
}
