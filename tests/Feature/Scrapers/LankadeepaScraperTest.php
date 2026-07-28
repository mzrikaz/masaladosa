<?php

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use App\Scrapers\LankadeepaScraper;
use Illuminate\Support\Facades\Http;

function lankadeepaListingHtml(): string
{
    return <<<'HTML'
        <article class="cat-lead-story mb-4 pb-4 border-bottom">
            <a href="https://www.lankadeepa.lk/news/second-story/101-2" class="text-decoration-none text-reset d-block">
                <h2 class="sinhala-head cat-lead-title mb-2">Second story</h2>
            </a>
        </article>
        <article class="cat-list-item d-flex flex-column flex-md-row gap-3 border-bottom pb-3 mb-3">
            <div class="cat-list-text flex-grow-1">
                <a href="https://www.lankadeepa.lk/news/first-story/101-1" class="text-decoration-none text-reset">
                    <h3 class="sinhala-head cat-item-title">First story</h3>
                </a>
            </div>
            <div class="cat-list-thumb flex-shrink-0">
                <a href="https://www.lankadeepa.lk/news/first-story/101-1">thumb</a>
            </div>
        </article>
        HTML;
}

function lankadeepaArticleHtml(string $title): string
{
    return <<<HTML
        <h1 class="article-title sinhala-head mb-3">
            {$title}
        </h1>
        <div class="article-meta small text-muted">
            2026 ජූලි 28 | ප.ව. 2:36
        </div>
        <div class="article-body sinhala-body">
            <p><img src="https://cdn.lankadeepa.lk/assets/uploads/photo.jpg"></p>
            <p>First paragraph.</p>
            <p>&nbsp;</p>
            <p>Second paragraph.</p>
        </div>
        HTML;
}

test('scrapes new articles newer than the cursor and stops at it', function () {
    Http::fake([
        'https://www.lankadeepa.lk/latest-news/1' => Http::response(lankadeepaListingHtml()),
        'https://www.lankadeepa.lk/news/second-story/101-2' => Http::response(
            lankadeepaArticleHtml('Second story')
        ),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::Lankadeepa,
        'language' => NewsLanguage::Sinhala,
        'base_url' => 'https://www.lankadeepa.lk/latest-news/1',
        'last_scraped_external_url' => 'https://www.lankadeepa.lk/news/first-story/101-1',
    ]);

    $articles = (new LankadeepaScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(1);
    expect($articles[0]->externalUrl)->toBe('https://www.lankadeepa.lk/news/second-story/101-2');
    expect($articles[0]->title)->toBe('Second story');
    expect($articles[0]->body)->toBe("First paragraph.\n\nSecond paragraph.");
    expect($articles[0]->imageUrl)->toBe('https://cdn.lankadeepa.lk/assets/uploads/photo.jpg');
    expect($articles[0]->publishedAt->toIso8601String())->toBe('2026-07-28T09:06:00+00:00');
});
