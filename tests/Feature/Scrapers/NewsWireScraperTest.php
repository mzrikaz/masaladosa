<?php

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use App\Models\Source;
use App\Scrapers\NewsWireScraper;
use Illuminate\Support\Facades\Http;

function newswireListingHtml(): string
{
    return <<<'HTML'
        <div id="archive-wrap" class="archive-wrap">
        <article id="post-2"><a href="https://www.newswire.lk/2026/07/28/second-story/" rel="bookmark" itemprop="url">Second story</a></article>
        <article id="post-1"><a href="https://www.newswire.lk/2026/07/28/first-story/" rel="bookmark" itemprop="url">First story</a></article>
        </div>
        HTML;
}

function newswireArticleHtml(string $title, string $datetime): string
{
    return <<<HTML
        <meta property="og:image" content="https://www.newswire.lk/wp-content/uploads/2026/07/photo.jpg"/>
        <h1>{$title}</h1>
        <article id="post-1" class="entry">
            <div class="entry-content" itemprop="articleBody">
                <p><img src="photo.jpg"></p>
                <p>First paragraph of the story.</p>
                <p>Second paragraph of the story.</p>
                <p>&nbsp;</p>
            </div>
            <div class="screen-reader-text" itemprop="datePublished">2026-07-28</div>
            <footer class="entry-footer">
                <time class="entry-published updated" datetime="{$datetime}">July 28, 2026</time>
            </footer>
        </article>
        HTML;
}

test('scrapes new articles newer than the cursor and stops at it', function () {
    Http::fake([
        'https://www.newswire.lk/category/news/' => Http::response(newswireListingHtml()),
        'https://www.newswire.lk/2026/07/28/second-story/' => Http::response(
            newswireArticleHtml('Second story', '2026-07-28T20:00:00+05:30')
        ),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::NewsWire,
        'language' => NewsLanguage::English,
        'base_url' => 'https://www.newswire.lk/category/news/',
        'last_scraped_external_url' => 'https://www.newswire.lk/2026/07/28/first-story/',
    ]);

    $articles = (new NewsWireScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(1);
    expect($articles[0]->externalUrl)->toBe('https://www.newswire.lk/2026/07/28/second-story/');
    expect($articles[0]->title)->toBe('Second story');
    expect($articles[0]->body)->toBe("First paragraph of the story.\n\nSecond paragraph of the story.");
    expect($articles[0]->imageUrl)->toBe('https://www.newswire.lk/wp-content/uploads/2026/07/photo.jpg');
    expect($articles[0]->publishedAt->toIso8601String())->toBe('2026-07-28T14:30:00+00:00');
});

test('returns every article when the source has no cursor yet', function () {
    Http::fake([
        'https://www.newswire.lk/category/news/' => Http::response(newswireListingHtml()),
        'https://www.newswire.lk/2026/07/28/second-story/' => Http::response(
            newswireArticleHtml('Second story', '2026-07-28T20:00:00+05:30')
        ),
        'https://www.newswire.lk/2026/07/28/first-story/' => Http::response(
            newswireArticleHtml('First story', '2026-07-28T19:00:00+05:30')
        ),
    ]);

    $source = Source::factory()->create([
        'key' => SourceKey::NewsWire,
        'base_url' => 'https://www.newswire.lk/category/news/',
        'last_scraped_external_url' => null,
    ]);

    $articles = (new NewsWireScraper)->scrapeLatest($source);

    expect($articles)->toHaveCount(2);
    // Oldest first, so callers can persist/dispatch in chronological order.
    expect($articles[0]->externalUrl)->toBe('https://www.newswire.lk/2026/07/28/first-story/');
    expect($articles[1]->externalUrl)->toBe('https://www.newswire.lk/2026/07/28/second-story/');
});
