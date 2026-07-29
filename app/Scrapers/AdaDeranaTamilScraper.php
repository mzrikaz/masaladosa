<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
use App\Support\Dom\HtmlDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes adaderanatamil.lk (Next.js). Only the homepage is server-rendered
 * with article cards — the `/categories/{slug}` listing pages hydrate their
 * article list client-side via JS, so a plain HTTP fetch of those pages
 * returns an empty shell. The homepage exposes a smaller "latest" set, but
 * that's enough to keep up with a source polled every 5 minutes. Article
 * pages (`/news/{id}`) are server-rendered in full.
 */
class AdaDeranaTamilScraper implements SourceScraper
{
    private const int MAX_ARTICLES_PER_RUN = 20;

    private const string DOMAIN = 'https://adaderanatamil.lk';

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

        foreach ($dom->elements('//a[starts-with(@href, "/news/")]') as $anchor) {
            $urls[] = self::DOMAIN.$dom->attr($anchor, 'href');
        }

        return array_values(array_unique($urls));
    }

    private function scrapeArticle(string $url): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        $dom = new HtmlDocument($html);

        $title = $dom->text($dom->first(
            '//div[contains(@class, "text-4xl") and '.HtmlDocument::hasClass('font-bold').']'
        ));

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
            imageUrl: $this->extractImage($dom),
            publishedAt: $this->extractPublishedAt($dom),
        );
    }

    private function extractBody(HtmlDocument $dom): string
    {
        $container = $dom->first('//div['.HtmlDocument::hasClass('article-html').']');

        if ($container === null) {
            return '';
        }

        $paragraphs = $dom->paragraphs('.//p', $container);

        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph): bool => $paragraph !== ''));
    }

    private function extractImage(HtmlDocument $dom): ?string
    {
        return $dom->attr(
            $dom->first('//img[starts-with(@src, "https://ada-derana-prod-tamil-news-temp.s3.amazonaws.com/news-articles/")]'),
            'src',
        );
    }

    private function extractPublishedAt(HtmlDocument $dom): ?Carbon
    {
        $node = $dom->first('//p['.HtmlDocument::hasClass('text-xs').' and '.HtmlDocument::hasClass('mr-1').']');

        if ($node === null) {
            return null;
        }

        // The DOM parser already skips the React hydration comment sitting
        // between the time and the trailing "-" (e.g. "Jul 28, 2026 - 11:34
        // PM<!-- --> -"), since comment nodes never contribute to
        // textContent. Only the trailing "-" needs stripping.
        $cleaned = trim(preg_replace('/\s*-\s*$/', '', $dom->text($node) ?? '') ?? '');

        // Displayed in Sri Lanka local time with no explicit offset; anchor
        // to Asia/Colombo then normalize to UTC to match the app timezone.
        return Carbon::createFromFormat('M j, Y - h:i A', $cleaned, 'Asia/Colombo')?->utc();
    }
}
