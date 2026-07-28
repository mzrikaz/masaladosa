@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name').' — Sri Lanka News' }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-white font-sans text-zinc-900 antialiased">
        <header class="border-b border-zinc-100">
            <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <a href="{{ route('home') }}" wire:navigate class="font-serif text-2xl font-bold tracking-tight text-zinc-900">
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('home') }}"
                        wire:navigate
                        class="rounded-full border border-zinc-200 px-4 py-1.5 text-sm text-zinc-600 transition hover:border-zinc-900 hover:text-zinc-900"
                    >
                        Today
                    </a>
                    @foreach (\App\Enums\NewsLanguage::cases() as $language)
                        <a
                            href="{{ route('archive', ['locale' => $language->value]) }}"
                            wire:navigate
                            class="rounded-full border border-zinc-200 px-4 py-1.5 text-sm text-zinc-600 transition hover:border-zinc-900 hover:text-zinc-900"
                        >
                            {{ $language->label() }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-2xl border-t border-zinc-100 px-4 py-8 text-sm text-zinc-400 sm:px-6">
            <p>
                {{ config('app.name') }} rephrases breaking news from Sri Lankan news portals using AI. Every story links back to its original source.
            </p>

            @auth
                <p class="mt-4 flex items-center gap-4">
                    <a href="/horizon" class="underline underline-offset-4 hover:text-zinc-700">Horizon</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="underline underline-offset-4 hover:text-zinc-700">Log out</button>
                    </form>
                </p>
            @endauth
        </footer>

        @livewireScripts
    </body>
</html>
