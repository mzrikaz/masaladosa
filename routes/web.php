<?php

use App\Enums\NewsLanguage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/{locale}/archive', 'pages::archive')
    ->name('archive')
    ->whereIn('locale', array_column(NewsLanguage::cases(), 'value'));

Route::livewire('/news/{slug}', 'pages::article')->name('article.show');

Route::livewire('/login', 'pages::login')
    ->middleware('throttle:5,1')
    ->name('login');

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');
