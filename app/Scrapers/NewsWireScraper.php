<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use App\Support\Dom\HtmlDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes newswire.lk (WordPress). Listing cards live inside
 * `#archive-wrap`, one `<article id="post-{id}">` per story, with the
 * permalink+title in `<a href="..." rel="bookmark" itemprop="url">`.
 * Article pages repeat the same `<article id="post-{id}">` wrapper with
 * the body inside `<div class="entry-content" itemprop="articleBody">`.
 */
class NewsWireScraper implements SourceScraper
{
    private const int MAX_ARTICLES_PER_RUN = 20;

    /**
     * @return array<int, ScrapedArticle>
     */
    public function scrapeLatest(Source $source): array
    {
        $listingHtml = Http::newsSource()->get($source->base_url)->throw()->body();

        $links = $this->extractListingLinks($listingHtml);

        $newLinks = [];

        foreach ($links as $link) {
            if ($link['url'] === $source->last_scraped_external_url) {
                break;
            }

            $newLinks[] = $link;

            if (count($newLinks) >= self::MAX_ARTICLES_PER_RUN) {
                break;
            }
        }

        $articles = [];

        foreach (array_reverse($newLinks) as $link) {
            $article = $this->scrapeArticle($link['url']);

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * @return array<int, array{url: string, title: string}>
     */
    private function extractListingLinks(string $html): array
    {
        $dom = new HtmlDocument($html);

        $links = [];

        foreach ($dom->elements('//a[@rel="bookmark" and @itemprop="url" and starts-with(@href, "https://www.newswire.lk/")]') as $node) {
            $links[] = [
                'url' => $dom->attr($node, 'href') ?? '',
                'title' => $dom->text($node) ?? '',
            ];
        }

        return $links;
    }

    private function scrapeArticle(string $url): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        $dom = new HtmlDocument($html);

        $title = $dom->text($dom->first('(//h1)[1]'));

        if ($title === null) {
            return null;
        }

        $body = $this->extractBody($dom);

        if ($body === '') {
            return null;
        }

        return new ScrapedArticle(
            externalUrl: $url,
            title: $title,
            body: $body,
            imageUrl: $dom->attr($dom->first('//meta[@property="og:image"]'), 'content'),
            publishedAt: $this->extractPublishedAt($dom),
        );
    }

    private function extractBody(HtmlDocument $dom): string
    {
        $container = $dom->first(
            '//div[@itemprop="articleBody" and '.HtmlDocument::hasClass('entry-content').']'
        );

        if ($container === null) {
            return '';
        }

        $paragraphs = $dom->paragraphs('.//p', $container);

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
    }

    private function extractPublishedAt(HtmlDocument $dom): ?Carbon
    {
        $datetime = $dom->attr(
            $dom->first('//time[@datetime and '.HtmlDocument::hasClass('entry-published').']'),
            'datetime',
        );

        if ($datetime === null) {
            return null;
        }

        // The site publishes with an explicit +05:30 offset; normalize to
        // UTC so it round-trips correctly through the "UTC" app timezone.
        return Carbon::parse($datetime)->utc();
    }
}
