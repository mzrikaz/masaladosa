<?php

namespace App\Contracts\Repositories;

use App\DataTransferObjects\ScrapedArticle;
use App\Enums\NewsLanguage;
use App\Models\Article;
use App\Models\Source;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ArticleRepository
{
    public function existsByExternalUrl(string $externalUrl): bool;

    public function createFromScrape(Source $source, ScrapedArticle $scraped): Article;

    /**
     * @param  array<int, string>  $paragraphs
     */
    public function publish(Article $article, string $title, string $summary, array $paragraphs): Article;

    public function markFailed(Article $article, string $reason): Article;

    /**
     * @return Collection<int, Article>
     */
    public function latestPublished(NewsLanguage $language, int $limit): Collection;

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function paginatePublished(NewsLanguage $language, int $perPage): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?Article;
}
