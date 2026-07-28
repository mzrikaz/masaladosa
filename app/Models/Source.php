<?php

namespace App\Models;

use App\Enums\NewsLanguage;
use App\Enums\SourceKey;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SourceKey $key
 * @property string $name
 * @property NewsLanguage $language
 * @property string $base_url
 * @property bool $is_active
 * @property string|null $last_scraped_external_url
 * @property Carbon|null $last_scraped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'language', 'base_url', 'is_active', 'last_scraped_external_url', 'last_scraped_at'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SourceKey::class,
            'language' => NewsLanguage::class,
            'is_active' => 'boolean',
            'last_scraped_at' => 'datetime',
        ];
    }
}
