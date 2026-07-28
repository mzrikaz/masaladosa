<?php

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use App\Scrapers\HiruNewsScraper;
use Illuminate\Support\Facades\Http;

function hiruNewsListingHtml(): string
{
    return <<<'HTML'
        <div class="featured-cards">
        <a class="card-v1" href="https://hirunews.lk/2/second"><div class="content-bottom"><div class="update-wrp-lg"><span class="update-category">General</span><span>28 July 2026 2:36 PM</span></div></div></a>
        <a class="card-v1" href="https://hirunews.lk/1/first"><div class="content-bottom"><div class="update-wrp-lg"><span class="update-category">General</span><span>28 July 2026 11:07 AM</span></div></div></a>
        </div>
        <a href="https://hirunews.lk/en/999/english-world-news-item">English widget item, not card-v1, must be ignored</a>
        HTML;
}

function hiruNewsArticleHtml(string $title): string
{
    return <<<HTML
        <h1 class="head-title">{$title}</h1>
        <div class="featured-image">
            <img src="https://cdn.hirunews.lk/photo.jpg" width="100%" alt="{$title}">
        </div>
        <div class="description-content col-md-9" id="this-article">First paragraph.<br><br>Second paragraph.</div>
        HTML;
}

test('scrapes new articles newer than the cursor and stops at it, ignoring non card-v1 links', function () {
    Http::fake([
        'https://www.hirunews.lk/' => Http::response(hiruNewsListingHtml()),
        'https://hirunews.lk/2/second' => Http::response(hiruNewsArticleHtml('Second story')),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::HiruNews,
        'language' => NewsLanguage::Sinhala,
        'base_url' => 'https://www.hirunews.lk/',
        'last_scraped_external_url' => 'https://hirunews.lk/1/first',
    ]);

    $articles = (new HiruNewsScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(1);
    expect($articles[0]->externalUrl)->toBe('https://hirunews.lk/2/second');
    expect($articles[0]->title)->toBe('Second story');
    expect($articles[0]->body)->toBe("First paragraph.\n\nSecond paragraph.");
    expect($articles[0]->imageUrl)->toBe('https://cdn.hirunews.lk/photo.jpg');
    expect($articles[0]->publishedAt->toIso8601String())->toBe('2026-07-28T09:06:00+00:00');
});
