<?php

use App\Enums\ArticleStatus;
use App\Enums\NewsLanguage;
use App\Models\Article;

test('home page renders', function () {
    $this->get(route('home'))->assertOk();
});

test('archive page renders for each language', function (NewsLanguage $language) {
    $this->get(route('archive', ['locale' => $language->value]))->assertOk();
})->with(NewsLanguage::cases());

test('published article page renders', function () {
    $article = Article::factory()->published()->create(['status' => ArticleStatus::Published]);

    $this->get(route('article.show', ['slug' => $article->slug]))
        ->assertOk()
        ->assertSee($article->rephrased_title);
});

test('unknown article slug returns 404', function () {
    $this->get(route('article.show', ['slug' => 'does-not-exist']))->assertNotFound();
});

test('unpublished article is not reachable by slug', function () {
    $article = Article::factory()->create(['status' => ArticleStatus::Pending, 'slug' => null]);

    $this->get(route('article.show', ['slug' => 'pending-article']))->assertNotFound();

    expect($article->slug)->toBeNull();
});
