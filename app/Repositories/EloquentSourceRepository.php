<?php

namespace App\Repositories;

use App\Contracts\Repositories\SourceRepository;
use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentSourceRepository implements SourceRepository
{
    /**
     * @return Collection<int, Source>
     */
    public function active(): Collection
    {
        return Source::query()->where('is_active', true)->get();
    }

    public function markScraped(Source $source, string $lastExternalUrl, Carbon $at): void
    {
        $source->update([
            'last_scraped_external_url' => $lastExternalUrl,
            'last_scraped_at' => $at,
        ]);
    }
}
