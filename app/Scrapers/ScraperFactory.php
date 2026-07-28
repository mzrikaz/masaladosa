<?php

namespace App\Scrapers;

use App\Contracts\Scrapers\SourceScraper;
use App\Enums\SourceKey;

class ScraperFactory
{
    public function has(SourceKey $key): bool
    {
        return $this->scraperClass($key) !== null;
    }

    public function make(SourceKey $key): SourceScraper
    {
        $scraperClass = $this->scraperClass($key);

        if ($scraperClass === null) {
            throw new \RuntimeException("No scraper implemented for source [{$key->value}] yet.");
        }

        return app($scraperClass);
    }

    /**
     * @return class-string<SourceScraper>|null
     */
    private function scraperClass(SourceKey $key): ?string
    {
        return match ($key) {
            SourceKey::NewsWire => NewsWireScraper::class,
            SourceKey::AdaDeranaEnglish => AdaDeranaEnglishScraper::class,
            SourceKey::AdaDeranaTamil => AdaDeranaTamilScraper::class,
            SourceKey::Lankadeepa => LankadeepaScraper::class,
            default => null,
        };
    }
}
