<?php

use App\Contracts\Repositories\ArticleRepository;
use App\Enums\NewsLanguage;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sri Lanka News & Latest Breaking Headlines')] class extends Component
{
    /**
     * @var array<string, \Illuminate\Support\Collection<int, \App\Models\Article>>
     */
    public array $articlesByLanguage = [];

    public function mount(ArticleRepository $articles): void
    {
        foreach (NewsLanguage::cases() as $language) {
            $this->articlesByLanguage[$language->value] = $articles->latestPublished($language, 20);
        }
    }
};
?>

<div x-data="{ tab: 'en' }">
    <section class="border-b border-zinc-100 pb-10 text-center">
        <p class="text-sm font-semibold tracking-wide text-orange-600 uppercase">
            Sri Lanka &middot; English &middot; Tamil &middot; Sinhala
        </p>
        <h1 class="mt-4 font-serif text-4xl leading-tight font-bold text-zinc-900 sm:text-5xl">
            Sri Lanka News &amp; Latest Breaking Headlines
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-zinc-500">
            {{ config('app.name') }} brings you breaking news from across Sri Lanka in clear, single stories — rewritten by AI and updated continuously in English, Sinhala and Tamil.
        </p>
    </section>

    <section class="pt-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <h2 class="flex items-center gap-2 text-sm font-bold tracking-wide text-zinc-900 uppercase">
                <span class="live-dot" aria-hidden="true"></span>
                Latest News
            </h2>

            <div class="flex items-center gap-2">
                @foreach (NewsLanguage::cases() as $language)
                    <button
                        type="button"
                        @click="tab = '{{ $language->value }}'"
                        :class="tab === '{{ $language->value }}' ? 'bg-zinc-900 text-white' : 'border border-zinc-200 text-zinc-600 hover:border-zinc-900 hover:text-zinc-900'"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                    >
                        {{ $language->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        @foreach (NewsLanguage::cases() as $language)
            <template x-if="tab === '{{ $language->value }}'">
                <div>
                    @forelse ($articlesByLanguage[$language->value] as $article)
                        <x-article-card :article="$article" wire:key="home-{{ $article->id }}" />
                    @empty
                        <p class="py-8 text-center text-zinc-400">
                            No {{ $language->label() }} stories yet — check back soon.
                        </p>
                    @endforelse

                    @if ($articlesByLanguage[$language->value]->isNotEmpty())
                        <a
                            href="{{ route('archive', ['locale' => $language->value]) }}"
                            wire:navigate
                            class="mt-6 inline-block text-sm font-semibold text-zinc-900 underline underline-offset-4"
                        >
                            Browse all {{ $language->label() }} Sri Lanka news stories in the archive &rarr;
                        </a>
                    @endif
                </div>
            </template>
        @endforeach
    </section>
</div>
