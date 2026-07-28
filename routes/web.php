<?php

use App\Enums\NewsLanguage;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/{locale}/archive', 'pages::archive')
    ->name('archive')
    ->whereIn('locale', array_column(NewsLanguage::cases(), 'value'));

Route::livewire('/news/{slug}', 'pages::article')->name('article.show');
