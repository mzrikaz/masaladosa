<?php

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use App\Scrapers\AdaDeranaTamilScraper;
use Illuminate\Support\Facades\Http;

function adaDeranaTamilListingHtml(): string
{
    return <<<'HTML'
        <div class="mt-4"><a href="/news/second"><div class="group hidden md:block"><span class="font-semibold">Second story</span></div></a></div>
        <div class="mt-4"><a href="/news/first"><div class="group hidden md:block"><span class="font-semibold">First story</span></div></a></div>
        HTML;
}

function adaDeranaTamilArticleHtml(string $title, string $datetime): string
{
    return <<<HTML
        <div class="w-full md:text-4xl text-2xl font-bold mt-2 mb-2">{$title}</div>
        <div class="flex justify-between items-center mb-4"><div class="flex items-center"><p class="text-xs mr-1">{$datetime}<!-- --> -</p></div></div>
        <img alt="{$title}" src="https://ada-derana-prod-tamil-news-temp.s3.amazonaws.com/news-articles/second/MediaFile">
        <div class="article-html w-full mt-4"><p>First paragraph.</p><p>&nbsp;</p><p>Second paragraph.</p></div>
        <div class="mt-8 w-full mb-8 font-bold"></div>
        HTML;
}

test('scrapes new articles newer than the cursor and stops at it', function () {
    Http::fake([
        'https://adaderanatamil.lk/' => Http::response(adaDeranaTamilListingHtml()),
        'https://adaderanatamil.lk/news/second' => Http::response(
            adaDeranaTamilArticleHtml('Second story', 'Jul 28, 2026 - 11:34 PM')
        ),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::AdaDeranaTamil,
        'language' => NewsLanguage::Tamil,
        'base_url' => 'https://adaderanatamil.lk/',
        'last_scraped_external_url' => 'https://adaderanatamil.lk/news/first',
    ]);

    $articles = (new AdaDeranaTamilScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(1);
    expect($articles[0]->externalUrl)->toBe('https://adaderanatamil.lk/news/second');
    expect($articles[0]->title)->toBe('Second story');
    expect($articles[0]->body)->toBe("First paragraph.\n\nSecond paragraph.");
    expect($articles[0]->imageUrl)->toBe('https://ada-derana-prod-tamil-news-temp.s3.amazonaws.com/news-articles/second/MediaFile');
    expect($articles[0]->publishedAt->toIso8601String())->toBe('2026-07-28T18:04:00+00:00');
});
