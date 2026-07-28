<?php

namespace App\Contracts\Scrapers;

use App\DataTransferObjects\ScrapedArticle;
use App\Models\Source;

interface SourceScraper
{
    /**
     * Fetch the source's listing page and return articles published after
     * the source's cursor (newest first), stopping once the previously
     * scraped article is reached.
     *
     * @return array<int, ScrapedArticle>
     */
    public function scrapeLatest(Source $source): array;
}
