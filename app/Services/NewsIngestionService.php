<?php

namespace App\Services;

use App\Contracts\Repositories\ArticleRepository;
use App\Contracts\Repositories\SourceRepository;
use App\Jobs\RephraseArticleJob;
use App\Models\Source;
use App\Scrapers\ScraperFactory;
use Illuminate\Support\Carbon;

class NewsIngestionService
{
    public function __construct(
        private readonly ScraperFactory $scraperFactory,
        private readonly SourceRepository $sources,
        private readonly ArticleRepository $articles,
    ) {}

    /**
     * Scrape a source for new articles, persist them, and queue each one
     * for AI rephrasing. Returns the number of new articles found.
     */
    public function ingest(Source $source): int
    {
        $scraper = $this->scraperFactory->make($source->key);

        $scrapedArticles = $scraper->scrapeLatest($source);

        $newest = null;

        foreach ($scrapedArticles as $scraped) {
            $newest = $scraped->externalUrl;

            if ($this->articles->existsByExternalUrl($scraped->externalUrl)) {
                continue;
            }

            $article = $this->articles->createFromScrape($source, $scraped);

            RephraseArticleJob::dispatch($article);
        }

        if ($newest !== null) {
            $this->sources->markScraped($source, $newest, Carbon::now());
        }

        return count($scrapedArticles);
    }
}
