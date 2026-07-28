<?php

namespace App\Contracts\Repositories;

use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface SourceRepository
{
    /**
     * @return Collection<int, Source>
     */
    public function active(): Collection;

    public function markScraped(Source $source, string $lastExternalUrl, Carbon $at): void;
}
