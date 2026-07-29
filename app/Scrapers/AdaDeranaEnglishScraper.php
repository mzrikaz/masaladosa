<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use App\Support\Dom\HtmlDocument;
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
        $listingHtml = Http::newsSource()->get($source->base_url)->throw()->body();

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
        $dom = new HtmlDocument($html);

        $seen = [];
        $cards = [];

        foreach ($dom->elements('//a[starts-with(@href, "/news/")]') as $anchor) {
            $url = self::DOMAIN.$dom->attr($anchor, 'href');

            if (isset($seen[$url])) {
                continue;
            }

            $timeNode = $dom->first('.//*['.HtmlDocument::hasClass('text-muted-foreground').']', $anchor);

            if ($timeNode === null) {
                continue;
            }

            $seen[$url] = true;

            $cards[] = [
                'url' => $url,
                'publishedAt' => $this->parseRelativeTime($dom->text($timeNode) ?? ''),
            ];
        }

        return $cards;
    }

    private function scrapeArticle(string $url, ?Carbon $publishedAt): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        $dom = new HtmlDocument($html);

        $title = $dom->attr($dom->first('//meta[@property="og:title"]'), 'content');

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
            publishedAt: $publishedAt,
        );
    }

    private function extractBody(HtmlDocument $dom): string
    {
        $container = $dom->first(
            '//div['.HtmlDocument::hasClass('prose').' and '.HtmlDocument::hasClass('prose-lg').']'
        );

        if ($container === null) {
            return '';
        }

        $paragraphs = $dom->paragraphs('.//p', $container);

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
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
