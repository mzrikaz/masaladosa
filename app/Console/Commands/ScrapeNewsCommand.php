<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\SourceRepository;
use App\Jobs\ScrapeSourceJob;
use App\Scrapers\ScraperFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:scrape')]
#[Description('Dispatch a scrape job for every active news source that has a scraper implemented.')]
class ScrapeNewsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SourceRepository $sources, ScraperFactory $scraperFactory): void
    {
        $activeSources = $sources->active();

        foreach ($activeSources as $source) {
            if (! $scraperFactory->has($source->key)) {
                $this->components->warn("Skipping [{$source->name}] — no scraper implemented yet.");

                continue;
            }

            ScrapeSourceJob::dispatch($source);

            $this->components->info("Queued scrape for [{$source->name}].");
        }
    }
}
