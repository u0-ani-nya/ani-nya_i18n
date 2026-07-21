<?php

namespace aniNya\I18n\Service\Engine;

interface EngineInterface
{
    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string;
    public function getName(): string;
}
