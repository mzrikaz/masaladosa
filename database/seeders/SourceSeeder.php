<?php

namespace Database\Seeders;

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'key' => SourceKey::AdaDeranaEnglish,
                'name' => 'Ada Derana',
                'language' => NewsLanguage::English,
                'base_url' => 'https://adaderana.lk/categories/latest',
            ],
            [
                'key' => SourceKey::NewsWire,
                'name' => 'NewsWire',
                'language' => NewsLanguage::English,
                'base_url' => 'https://www.newswire.lk/category/news/',
            ],
            [
                'key' => SourceKey::NewsFirstEnglish,
                'name' => 'News First',
                'language' => NewsLanguage::English,
                'base_url' => 'https://english.newsfirst.lk/',
            ],
            [
                'key' => SourceKey::AdaDeranaSinhala,
                'name' => 'Ada Derana',
                'language' => NewsLanguage::Sinhala,
                'base_url' => 'https://sinhala.adaderana.lk/sinhala-hot-news.php',
            ],
            [
                'key' => SourceKey::HiruNews,
                'name' => 'Hiru News',
                'language' => NewsLanguage::Sinhala,
                'base_url' => 'https://www.hirunews.lk/',
            ],
            [
                'key' => SourceKey::Lankadeepa,
                'name' => 'Lankadeepa',
                'language' => NewsLanguage::Sinhala,
                'base_url' => 'https://www.lankadeepa.lk/latest-news/1',
            ],
            [
                'key' => SourceKey::AdaDeranaTamil,
                'name' => 'Ada Derana',
                'language' => NewsLanguage::Tamil,
                'base_url' => 'https://adaderanatamil.lk/',
            ],
            [
                'key' => SourceKey::Virakesari,
                'name' => 'Virakesari',
                'language' => NewsLanguage::Tamil,
                'base_url' => 'https://virakesari.lk/',
            ],
            [
                'key' => SourceKey::TamilMirror,
                'name' => 'Tamil Mirror',
                'language' => NewsLanguage::Tamil,
                'base_url' => 'https://www.tamilmirror.lk/',
            ],
        ];

        foreach ($sources as $source) {
            Source::query()->updateOrCreate(
                ['key' => $source['key']],
                $source + ['is_active' => true],
            );
        }
    }
}
