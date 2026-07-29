<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use App\Support\Dom\HtmlDocument;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes lankadeepa.lk. The listing page mixes two "lead" articles (in a
 * 2-column row) with a longer chronological list below — both use plain
 * `/news/...` permalinks, so a single href scan across the whole page
 * captures both in document (newest-first) order without needing to
 * distinguish the two block types.
 */
class LankadeepaScraper implements SourceScraper
{
    private const int MAX_ARTICLES_PER_RUN = 20;

    /**
     * Sinhala month name => numeric month, as printed in the article byline
     * (e.g. "2026 ජූලි 29 | පෙ.ව. 12:01").
     *
     * @var array<string, int>
     */
    private const array SINHALA_MONTHS = [
        'ජනවාරි' => 1,
        'පෙබරවාරි' => 2,
        'මාර්තු' => 3,
        'අප්‍රේල්' => 4,
        'මැයි' => 5,
        'ජූනි' => 6,
        'ජූලි' => 7,
        'අගෝස්තු' => 8,
        'සැප්තැම්බර්' => 9,
        'ඔක්තෝබර්' => 10,
        'නොවැම්බර්' => 11,
        'දෙසැම්බර්' => 12,
    ];

    /**
     * @return array<int, ScrapedArticle>
     */
    public function scrapeLatest(Source $source): array
    {
        $listingHtml = Http::newsSource()->get($source->base_url)->throw()->body();

        $urls = $this->extractListingUrls($listingHtml);

        $newUrls = [];

        foreach ($urls as $url) {
            if ($url === $source->last_scraped_external_url) {
                break;
            }

            $newUrls[] = $url;

            if (count($newUrls) >= self::MAX_ARTICLES_PER_RUN) {
                break;
            }
        }

        $articles = [];

        foreach (array_reverse($newUrls) as $url) {
            $article = $this->scrapeArticle($url);

            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * @return array<int, string>
     */
    private function extractListingUrls(string $html): array
    {
        $dom = new HtmlDocument($html);

        $urls = [];

        foreach ($dom->elements('//a[starts-with(@href, "https://www.lankadeepa.lk/news/")]') as $anchor) {
            $urls[] = $dom->attr($anchor, 'href');
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function scrapeArticle(string $url): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        $dom = new HtmlDocument($html);

        $title = $dom->text($dom->first(
            '//h1['.HtmlDocument::hasClass('article-title').' and '.HtmlDocument::hasClass('sinhala-head').']'
        ));

        if ($title === null) {
            return null;
        }

        $container = $dom->first(
            '//div['.HtmlDocument::hasClass('article-body').' and '.HtmlDocument::hasClass('sinhala-body').']'
        );

        if ($container === null) {
            return null;
        }

        $body = $this->extractParagraphs($dom, $container);

        if ($body === '') {
            return null;
        }

        return new ScrapedArticle(
            externalUrl: $url,
            title: $title,
            body: $body,
            imageUrl: $this->extractImage($dom, $container),
            publishedAt: $this->extractPublishedAt($dom),
        );
    }

    private function extractParagraphs(HtmlDocument $dom, DOMElement $container): string
    {
        $paragraphs = $dom->paragraphs('.//p', $container);

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
    }

    private function extractImage(HtmlDocument $dom, DOMElement $container): ?string
    {
        return $dom->attr($dom->first('.//img', $container), 'src');
    }

    private function extractPublishedAt(HtmlDocument $dom): ?Carbon
    {
        $meta = $dom->text($dom->first('//div['.HtmlDocument::hasClass('article-meta').']'));

        if ($meta === null) {
            return null;
        }

        if (! preg_match('/(\d{4})\s+(\S+)\s+(\d{1,2})\s*\|\s*(පෙ\.ව\.|ප\.ව\.)\s*(\d{1,2}):(\d{2})/u', $meta, $match)) {
            return null;
        }

        [, $year, $monthName, $day, $meridiem, $hour, $minute] = $match;

        $month = self::SINHALA_MONTHS[$monthName] ?? null;

        if ($month === null) {
            return null;
        }

        $hour24 = (int) $hour % 12;

        if ($meridiem === 'ප.ව.') {
            $hour24 += 12;
        }

        // Displayed in Sri Lanka local time with no explicit offset; anchor
        // to Asia/Colombo then normalize to UTC to match the app timezone.
        return Carbon::create((int) $year, $month, (int) $day, $hour24, (int) $minute, 0, 'Asia/Colombo')?->utc();
    }
}
