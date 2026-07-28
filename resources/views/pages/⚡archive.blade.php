<?php

use App\Contracts\Repositories\ArticleRepository;
use App\Enums\NewsLanguage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public NewsLanguage $locale;

    public function mount(NewsLanguage $locale): void
    {
        $this->locale = $locale;
    }

    public function title(): string
    {
        return "{$this->locale->label()} News Archive";
    }

    #[Computed]
    public function articles(): LengthAwarePaginator
    {
        return app(ArticleRepository::class)->paginatePublished($this->locale, 20);
    }
};
?>

<div>
    <h1 class="font-serif text-3xl font-bold text-zinc-900">
        {{ $this->locale->label() }} News Archive
    </h1>
    <p class="mt-2 text-zinc-500">
        Every {{ $this->locale->label() }} story {{ config('app.name') }} has published, newest first.
    </p>

    <div class="mt-8">
        @forelse ($this->articles as $article)
            <x-article-card :article="$article" wire:key="archive-{{ $article->id }}" />
        @empty
            <p class="py-8 text-center text-zinc-400">No stories published yet — check back soon.</p>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $this->articles->links() }}
    </div>
</div>
