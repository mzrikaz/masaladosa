<?php

use App\Contracts\Repositories\ArticleRepository;
use App\Models\Article;
use Livewire\Component;

new class extends Component
{
    public Article $article;

    public function mount(string $slug, ArticleRepository $articles): void
    {
        $article = $articles->findPublishedBySlug($slug);

        abort_if($article === null, 404);

        $this->article = $article;
    }

    public function title(): string
    {
        return $this->article->rephrased_title;
    }
};
?>

<div>
    <h1 class="font-serif text-3xl leading-tight font-bold text-zinc-900 sm:text-4xl">
        {{ $article->rephrased_title }}
    </h1>

    <p class="mt-4 text-sm text-zinc-500">
        Reported by {{ $article->source->name }}
        <br>
        {{ $article->original_published_at?->diffForHumans() }}
    </p>

    @if ($article->image_url)
        <img
            src="{{ $article->image_url }}"
            alt=""
            class="mt-6 w-full rounded-lg object-cover"
        >
    @endif

    <div class="mt-6 space-y-5 text-lg leading-relaxed text-zinc-800">
        <p class="font-bold">{{ $article->rephrased_summary }}</p>

        @foreach ($article->rephrased_paragraphs as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <a
        href="{{ $article->external_url }}"
        target="_blank"
        rel="noopener noreferrer nofollow"
        class="mt-10 inline-block text-sm font-semibold text-zinc-500 underline underline-offset-4 hover:text-zinc-900"
    >
        Original source: {{ $article->source->name }} &nearr;
    </a>
</div>
