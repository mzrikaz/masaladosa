@props(['article'])

<a
    href="{{ route('article.show', ['slug' => $article->slug]) }}"
    wire:navigate
    class="flex items-center gap-4 border-b border-zinc-100 py-4 first:pt-0 last:border-b-0"
>
    <img
        src="{{ $article->image_url }}"
        alt=""
        loading="lazy"
        class="h-20 w-20 shrink-0 rounded-md object-cover"
    >

    <div class="min-w-0">
        <h3 class="font-serif text-lg leading-snug font-bold text-zinc-900">
            {{ $article->rephrased_title }}
        </h3>
        <p class="mt-1 text-sm text-zinc-400">
            {{ $article->original_published_at?->diffForHumans() }}
        </p>
    </div>
</a>
