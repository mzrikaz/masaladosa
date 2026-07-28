<?php

namespace App\Repositories;

use App\Contracts\Repositories\ArticleRepository;
use App\DataTransferObjects\ScrapedArticle;
use App\Enums\ArticleStatus;
use App\Enums\NewsLanguage;
use App\Models\Article;
use App\Models\Source;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EloquentArticleRepository implements ArticleRepository
{
    public function existsByExternalUrl(string $externalUrl): bool
    {
        return Article::query()->where('external_url', $externalUrl)->exists();
    }

    public function createFromScrape(Source $source, ScrapedArticle $scraped): Article
    {
        return Article::query()->create([
            'source_id' => $source->id,
            'language' => $source->language,
            'status' => ArticleStatus::Pending,
            'external_url' => $scraped->externalUrl,
            'image_url' => $scraped->imageUrl,
            'original_title' => strip_tags($scraped->title),
            'original_body' => strip_tags($scraped->body),
            'original_published_at' => $scraped->publishedAt,
        ]);
    }

    /**
     * @param  array<int, string>  $paragraphs
     */
    public function publish(Article $article, string $title, string $summary, array $paragraphs): Article
    {
        $article->update([
            'status' => ArticleStatus::Published,
            'slug' => $this->uniqueSlug($title, $article->id),
            'rephrased_title' => $title,
            'rephrased_summary' => $summary,
            'rephrased_paragraphs' => $paragraphs,
            'rephrased_at' => Carbon::now(),
        ]);

        return $article;
    }

    public function markFailed(Article $article, string $reason): Article
    {
        $article->update([
            'status' => ArticleStatus::Failed,
            'failure_reason' => $reason,
        ]);

        return $article;
    }

    /**
     * @return Collection<int, Article>
     */
    public function latestPublished(NewsLanguage $language, int $limit): Collection
    {
        return Article::query()
            ->where('language', $language)
            ->where('status', ArticleStatus::Published)
            ->latest('original_published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function paginatePublished(NewsLanguage $language, int $perPage): LengthAwarePaginator
    {
        return Article::query()
            ->where('language', $language)
            ->where('status', ArticleStatus::Published)
            ->latest('original_published_at')
            ->paginate($perPage);
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        return Article::query()
            ->with('source')
            ->where('slug', $slug)
            ->where('status', ArticleStatus::Published)
            ->first();
    }

    private function uniqueSlug(string $title, int $articleId): string
    {
        return Str::slug($title).'-'.$articleId;
    }
}
