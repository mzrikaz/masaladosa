<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Log in')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            $this->addError('email', 'Those credentials do not match our records.');

            return;
        }

        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('home')), navigate: true);
    }
};
?>

<div class="mx-auto max-w-sm py-16">
    <h1 class="font-serif text-3xl font-bold text-zinc-900">Log in</h1>

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-700">Email</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-zinc-900 focus:border-zinc-900 focus:ring-zinc-900 focus:outline-none"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-700">Password</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-zinc-900 focus:border-zinc-900 focus:ring-zinc-900 focus:outline-none"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-zinc-900 px-4 py-2 font-medium text-white transition hover:bg-zinc-700"
        >
            Log in
        </button>
    </form>
</div>
