<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Carbon;

final readonly class ScrapedArticle
{
    public function __construct(
        public string $externalUrl,
        public string $title,
        public string $body,
        public ?string $imageUrl,
        public ?Carbon $publishedAt,
    ) {}
}
