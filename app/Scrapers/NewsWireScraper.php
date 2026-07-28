<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
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
        preg_match_all(
            '/<a href="(https:\/\/www\.newswire\.lk\/[^"]+)" rel="bookmark" itemprop="url">([^<]+)<\/a>/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(
            fn (array $match): array => [
                'url' => $match[1],
                'title' => html_entity_decode(trim($match[2])),
            ],
            $matches,
        );
    }

    private function scrapeArticle(string $url): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        if (! preg_match('/<h1[^>]*>([^<]+)<\/h1>/', $html, $titleMatch)) {
            return null;
        }

        $body = $this->extractBody($html);

        if ($body === '') {
            return null;
        }

        return new ScrapedArticle(
            externalUrl: $url,
            title: html_entity_decode(trim($titleMatch[1])),
            body: $body,
            imageUrl: $this->extractImage($html),
            publishedAt: $this->extractPublishedAt($html),
        );
    }

    private function extractBody(string $html): string
    {
        if (! preg_match('/<div class="entry-content" itemprop="articleBody">(.*?)<\/div>\s*(?:<div class="screen-reader-text"|<footer)/s', $html, $match)) {
            return '';
        }

        preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $match[1], $paragraphMatches);

        $paragraphs = array_map(
            fn (string $paragraph): string => trim(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($paragraph)))),
            $paragraphMatches[1],
        );

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
    }

    private function extractImage(string $html): ?string
    {
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function extractPublishedAt(string $html): ?Carbon
    {
        if (! preg_match('/<time class="entry-published updated" datetime="([^"]+)"/', $html, $match)) {
            return null;
        }

        // The site publishes with an explicit +05:30 offset; normalize to
        // UTC so it round-trips correctly through the "UTC" app timezone.
        return Carbon::parse($match[1])->utc();
    }
}
