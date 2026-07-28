<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes adaderana.lk (Next.js, same platform family as
 * AdaDeranaTamilScraper but with different markup/classes per locale).
 *
 * The article detail page has no reliable published timestamp — its only
 * "Day, d M Y - h:i A" span reflects the moment the page was
 * server-rendered (i.e. roughly "now"), not when the article was
 * published, so it can't be used. Only the listing page's relative time
 * ("2h ago") is meaningful, so it's captured per-card and converted to an
 * absolute timestamp at scrape time.
 */
class AdaDeranaEnglishScraper implements SourceScraper
{
    private const int MAX_ARTICLES_PER_RUN = 20;

    private const string DOMAIN = 'https://adaderana.lk';

    /**
     * @return array<int, ScrapedArticle>
     */
    public function scrapeLatest(Source $source): array
    {
        $listingHtml = Http::get($source->base_url)->throw()->body();

        $cards = $this->extractListingCards($listingHtml);

        $newCards = [];

        foreach ($cards as $card) {
            if ($card['url'] === $source->last_scraped_external_url) {
                break;
            }

            $newCards[] = $card;

            if (count($newCards) >= self::MAX_ARTICLES_PER_RUN) {
                break;
            }
        }

        $articles = [];

        foreach (array_reverse($newCards) as $card) {
            $article = $this->scrapeArticle($card['url'], $card['publishedAt']);

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * @return array<int, array{url: string, publishedAt: ?Carbon}>
     */
    private function extractListingCards(string $html): array
    {
        preg_match_all(
            '/href="(\/news\/[a-zA-Z0-9]+)".*?text-muted-foreground[^>]*>([^<]+)<\/span>/s',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $seen = [];
        $cards = [];

        foreach ($matches as $match) {
            $url = self::DOMAIN.$match[1];

            if (isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;

            $cards[] = [
                'url' => $url,
                'publishedAt' => $this->parseRelativeTime(trim($match[2])),
            ];
        }

        return $cards;
    }

    private function scrapeArticle(string $url, ?Carbon $publishedAt): ?ScrapedArticle
    {
        $html = Http::get($url)->throw()->body();

        if (! preg_match('/<meta property="og:title" content="([^"]*)"\/>/', $html, $titleMatch)) {
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
            publishedAt: $publishedAt,
        );
    }

    private function extractBody(string $html): string
    {
        if (! preg_match('/<div class="prose prose-lg mx-auto">\s*<div class="w-full dark:text-white">(.*?)<\/div>\s*<\/div>/s', $html, $match)) {
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
        if (preg_match('/<meta property="og:image" content="([^"]*)"\/>/', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function parseRelativeTime(string $text): ?Carbon
    {
        if (preg_match('/^just now$/i', $text)) {
            return Carbon::now();
        }

        if (! preg_match('/^(\d+)\s*([mhd])/i', $text, $match)) {
            return null;
        }

        return match (strtolower($match[2])) {
            'm' => Carbon::now()->subMinutes((int) $match[1]),
            'h' => Carbon::now()->subHours((int) $match[1]),
            'd' => Carbon::now()->subDays((int) $match[1]),
            default => null,
        };
    }
}
