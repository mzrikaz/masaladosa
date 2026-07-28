<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;
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
        preg_match_all('/href="(\/news\/[a-zA-Z0-9]+)"/', $html, $matches);

        $urls = array_map(
            fn (string $path): string => self::DOMAIN.$path,
            $matches[1],
        );

        return array_values(array_unique($urls));
    }

    private function scrapeArticle(string $url): ?ScrapedArticle
    {
        $html = Http::newsSource()->get($url)->throw()->body();

        if (! preg_match('/<div class="w-full md:text-4xl text-2xl font-bold mt-2 mb-2">([^<]+)<\/div>/', $html, $titleMatch)) {
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
        if (! preg_match('/<div class="article-html w-full mt-4">(.*?)<\/div>\s*<div class="mt-8 w-full mb-8 font-bold">/s', $html, $match)) {
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
        if (preg_match('/<img[^>]+src="(https:\/\/ada-derana-prod-tamil-news-temp\.s3\.amazonaws\.com\/news-articles\/[^"]+)"/', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function extractPublishedAt(string $html): ?Carbon
    {
        // The markup contains a React hydration comment between the time
        // and the trailing "-" (e.g. "Jul 28, 2026 - 11:34 PM<!-- --> -"),
        // so the raw text is stripped of tags/comments before parsing.
        if (! preg_match('/<p class="text-xs mr-1">(.*?)<\/p>/s', $html, $match)) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s*-\s*$/', '', trim(strip_tags($match[1]))) ?? '');

        // Displayed in Sri Lanka local time with no explicit offset; anchor
        // to Asia/Colombo then normalize to UTC to match the app timezone.
        return Carbon::createFromFormat('M j, Y - h:i A', $cleaned, 'Asia/Colombo')?->utc();
    }
}
