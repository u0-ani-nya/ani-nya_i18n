<?php

use Flarum\Extend;
use aniNya\I18n\Listener\TranslateDiscussion;
use aniNya\I18n\Console\TranslateCommand;
use aniNya\I18n\Models\I18nTranslation;
use Illuminate\Database\Schema\Blueprint;

$schema = app('db')->getSchemaBuilder();
if (! $schema->hasTable('i18n_translations')) {
    $schema->create('i18n_translations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('discussion_id');
        $table->string('field');
        $table->string('source_lang', 10)->nullable();
        $table->string('target_lang', 10);
        $table->text('source_text');
        $table->text('translated_text');
        $table->string('engine', 50)->nullable();
        $table->timestamps();

        $table->foreign('discussion_id')
              ->references('id')
              ->on('discussions')
              ->onDelete('cascade');
        $table->unique(['discussion_id', 'field', 'target_lang']);
    });
}

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    new Extend\ApiResource(\aniNya\I18n\Api\Resource\I18nTranslationResource::class),

    (new Extend\Model(\Flarum\Discussion\Discussion::class))
        ->relationship('i18nTranslations', function ($modelClass) {
            return $modelClass->hasMany(I18nTranslation::class);
        }),

    (new Extend\Event())
        ->listen('Flarum\Discussion\Event\Created', TranslateDiscussion::class),

    (new Extend\Settings())
        ->default('ani-nya-i18n.engine', 'openai')
        ->default('ani-nya-i18n.target_lang', 'zh-Hans')
        ->default('ani-nya-i18n.auto_translate', true)
        ->default('ani-nya-i18n.guest_translate', true)
        ->default('ani-nya-i18n.openai_key', '')
        ->default('ani-nya-i18n.openai_model', 'gpt-4o-mini')
        ->default('ani-nya-i18n.openai_base_url', 'https://api.openai.com/v1')
        ->default('ani-nya-i18n.openai_prompt', "Translate the following text to {target_lang}.\n\nRules:\n1. Preserve all formatting (markdown, links, code blocks, line breaks)\n2. Keep URLs, code, and technical identifiers unchanged\n3. Output ONLY the translation, no explanations\n4. If already in target language, return unchanged\n\nText:\n{text}")
        ->default('ani-nya-i18n.google_key', '')
        ->default('ani-nya-i18n.deepl_key', '')
        ->default('ani-nya-i18n.baidu_appid', '')
        ->default('ani-nya-i18n.baidu_secret', '')
        ->default('ani-nya-i18n.libre_url', 'https://libretranslate.com')
        ->default('ani-nya-i18n.libre_key', '')
        ->default('ani-nya-i18n.yandex_key', '')
        ->serializeToForum('ani-nya-i18n.engine', 'ani-nya-i18n.engine')
        ->serializeToForum('ani-nya-i18n.target_lang', 'ani-nya-i18n.target_lang')
        ->serializeToForum('ani-nya-i18n.auto_translate', 'ani-nya-i18n.auto_translate', 'boolval')
        ->serializeToForum('ani-nya-i18n.guest_translate', 'ani-nya-i18n.guest_translate', 'boolval')
        ->serializeToForum('ani-nya-i18n.openai_model', 'ani-nya-i18n.openai_model')
        ->serializeToForum('ani-nya-i18n.openai_base_url', 'ani-nya-i18n.openai_base_url')
        ->serializeToForum('ani-nya-i18n.openai_prompt', 'ani-nya-i18n.openai_prompt'),

    (new Extend\Console())
        ->command(TranslateCommand::class),

    (new Extend\Routes('api'))
        ->post('/i18n/translate', 'ani-nya-i18n.translate', \aniNya\I18n\Controller\TranslateController::class)
        ->post('/i18n/admin/clear-all', 'ani-nya-i18n.admin.clear-all', \aniNya\I18n\Controller\AdminController::class)
        ->post('/i18n/admin/clear-discussion', 'ani-nya-i18n.admin.clear-discussion', \aniNya\I18n\Controller\AdminController::class)
        ->post('/i18n/admin/fill-all', 'ani-nya-i18n.admin.fill-all', \aniNya\I18n\Controller\AdminController::class)
        ->post('/i18n/admin/fill-discussion', 'ani-nya-i18n.admin.fill-discussion', \aniNya\I18n\Controller\AdminController::class),
];
