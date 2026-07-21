<?php

namespace aniNya\I18n\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Support\Facades\DB;

class I18nTranslation extends AbstractModel
{
    public function getTable()
    {
        return 'i18n_translations';
    }

    protected $fillable = [
        'discussion_id',
        'field',
        'source_lang',
        'target_lang',
        'source_text',
        'translated_text',
        'engine',
    ];

    protected $casts = [
        'discussion_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function discussion()
    {
        return $this->belongsTo(\Flarum\Discussion\Discussion::class);
    }
}
