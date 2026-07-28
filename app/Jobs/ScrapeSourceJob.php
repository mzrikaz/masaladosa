<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\NewsIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Backoff(30)]
#[Timeout(300)]
class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Source $source,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NewsIngestionService $ingestion): void
    {
        $ingestion->ingest($this->source);
    }
}
