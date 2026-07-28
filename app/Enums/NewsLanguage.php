<?php

namespace App\Enums;

enum NewsLanguage: string
{
    case English = 'en';
    case Sinhala = 'si';
    case Tamil = 'ta';

    /**
     * Get the native-script display label for the language.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Sinhala => 'සිංහල',
            self::Tamil => 'தமிழ்',
        };
    }
}
