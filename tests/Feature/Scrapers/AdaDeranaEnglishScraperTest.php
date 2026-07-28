<?php

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use App\Scrapers\AdaDeranaEnglishScraper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function adaDeranaEnglishListingHtml(): string
{
    return <<<'HTML'
        <a href="/news/second"><div class="group"><p class="line-clamp-2 font-medium">Second story</p><span class="text-muted-foreground dark:text-white text-xs">1h ago</span></div></a>
        <a href="/news/first"><div class="group"><p class="line-clamp-2 font-medium">First story</p><span class="text-muted-foreground dark:text-white text-xs">3h ago</span></div></a>
        HTML;
}

function adaDeranaEnglishArticleHtml(string $title): string
{
    return <<<HTML
        <meta property="og:title" content="{$title}"/>
        <meta property="og:image" content="https://ada-derana-prod-english-news-temp.s3.amazonaws.com/articles/second/photo.jpg"/>
        <div class="prose prose-lg mx-auto">
            <div class="w-full dark:text-white">
                <p style="text-align:justify;">First paragraph.</p>
                <p style="text-align:justify;">&nbsp;</p>
                <p style="text-align:justify;">Second paragraph.</p>
            </div>
        </div>
        HTML;
}

test('scrapes new articles newer than the cursor, using listing relative time', function () {
    Carbon::setTestNow('2026-07-28 12:00:00');

    Http::fake([
        'https://adaderana.lk/categories/latest' => Http::response(adaDeranaEnglishListingHtml()),
        'https://adaderana.lk/news/second' => Http::response(adaDeranaEnglishArticleHtml('Second story')),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::AdaDeranaEnglish,
        'language' => NewsLanguage::English,
        'base_url' => 'https://adaderana.lk/categories/latest',
        'last_scraped_external_url' => 'https://adaderana.lk/news/first',
    ]);

    $articles = (new AdaDeranaEnglishScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(1);
    expect($articles[0]->externalUrl)->toBe('https://adaderana.lk/news/second');
    expect($articles[0]->title)->toBe('Second story');
    expect($articles[0]->body)->toBe("First paragraph.\n\nSecond paragraph.");
    expect($articles[0]->imageUrl)->toBe('https://ada-derana-prod-english-news-temp.s3.amazonaws.com/articles/second/photo.jpg');
    expect($articles[0]->publishedAt->toDateTimeString())->toBe('2026-07-28 11:00:00');

    Carbon::setTestNow();
});
