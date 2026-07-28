<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\NewsLanguage;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_id
 * @property NewsLanguage $language
 * @property ArticleStatus $status
 * @property string $external_url
 * @property string|null $image_url
 * @property string $original_title
 * @property string $original_body
 * @property Carbon|null $original_published_at
 * @property string|null $slug
 * @property string|null $rephrased_title
 * @property string|null $rephrased_summary
 * @property array<int, string>|null $rephrased_paragraphs
 * @property Carbon|null $rephrased_at
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'source_id',
    'language',
    'status',
    'external_url',
    'image_url',
    'original_title',
    'original_body',
    'original_published_at',
    'slug',
    'rephrased_title',
    'rephrased_summary',
    'rephrased_paragraphs',
    'rephrased_at',
    'failure_reason',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => NewsLanguage::class,
            'status' => ArticleStatus::class,
            'original_published_at' => 'datetime',
            'rephrased_paragraphs' => 'array',
            'rephrased_at' => 'datetime',
        ];
    }
}
