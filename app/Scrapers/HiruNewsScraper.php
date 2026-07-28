<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes hirunews.lk. The homepage mixes an embedded English "World News"
 * widget in with the Sinhala "Latest News" cards, but only the Sinhala
 * cards use the `card-v1` class, so matching on that class keeps the two
 * apart without needing to isolate the surrounding container.
 *
 * The article detail page only prints the publish date (no time), so the
 * full datetime is captured from the listing card instead.
 */
class HiruNewsScraper implements SourceScraper
{
    private const int MAX_ARTICLES_PER_RUN = 20;

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
            '/class="card-v1" href="([^"]+)".*?<span class="update-category">[^<]*<\/span><span>([^<]+)<\/span>/s',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $seen = [];
        $cards = [];

        foreach ($matches as $match) {
            if (isset($seen[$match[1]])) {
                continue;
            }

            $seen[$match[1]] = true;

            $cards[] = [
                'url' => $match[1],
                'publishedAt' => $this->parseDateTime($match[2]),
            ];
        }

        return $cards;
    }

    private function scrapeArticle(string $url, ?Carbon $publishedAt): ?ScrapedArticle
    {
        $html = Http::get($url)->throw()->body();

        if (! preg_match('/<h1 class="head-title">([^<]+)<\/h1>/', $html, $titleMatch)) {
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
        if (! preg_match('/<div class="description-content col-md-9" id="this-article">(.*?)<\/div>/s', $html, $match)) {
            return '';
        }

        $paragraphs = preg_split('/(?:<br\s*\/?>\s*){1,}/', $match[1]) ?: [];

        $paragraphs = array_map(
            fn (string $paragraph): string => trim(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($paragraph)))),
            $paragraphs,
        );

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
    }

    private function extractImage(string $html): ?string
    {
        if (preg_match('/<div class="featured-image">\s*<img src="([^"]+)"/', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function parseDateTime(string $text): ?Carbon
    {
        // Displayed in Sri Lanka local time with no explicit offset; anchor
        // to Asia/Colombo then normalize to UTC to match the app timezone.
        return Carbon::createFromFormat('j F Y g:i A', trim($text), 'Asia/Colombo')?->utc();
    }
}
