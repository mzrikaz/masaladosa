<?php

use App\Ai\Agents\ArticleRephraser;
use App\Contracts\Repositories\ArticleRepository;
use App\Enums\ArticleStatus;
use App\Jobs\RephraseArticleJob;
use App\Models\Article;

test('publishes the article from the AI JSON response', function () {
    ArticleRephraser::fake([
        '{"title": "Rephrased title", "summary": "Rephrased summary.", "paragraphs": ["Paragraph one.", "Paragraph two."]}',
    ]);

    $article = Article::factory()->create(['status' => ArticleStatus::Pending]);

    (new RephraseArticleJob($article))->handle(app(ArticleRepository::class));

    $article->refresh();

    expect($article->status)->toBe(ArticleStatus::Published);
    expect($article->rephrased_title)->toBe('Rephrased title');
    expect($article->rephrased_summary)->toBe('Rephrased summary.');
    expect($article->rephrased_paragraphs)->toBe(['Paragraph one.', 'Paragraph two.']);
    expect($article->slug)->toStartWith('rephrased-title-');
});

test('tolerates markdown code fences around the JSON', function () {
    ArticleRephraser::fake([
        "```json\n{\"title\": \"T\", \"summary\": \"S\", \"paragraphs\": [\"P\"]}\n```",
    ]);

    $article = Article::factory()->create(['status' => ArticleStatus::Pending]);

    (new RephraseArticleJob($article))->handle(app(ArticleRepository::class));

    expect($article->refresh()->status)->toBe(ArticleStatus::Published);
});

test('marks the article failed when the AI response is not valid JSON', function () {
    ArticleRephraser::fake(['not json at all']);

    $article = Article::factory()->create(['status' => ArticleStatus::Pending]);

    $job = new RephraseArticleJob($article);

    expect(fn () => $job->handle(app(ArticleRepository::class)))
        ->toThrow(RuntimeException::class);

    $job->failed(new RuntimeException('AI response was not valid JSON in the expected shape: not json at all'));

    expect($article->refresh()->status)->toBe(ArticleStatus::Failed);
    expect($article->failure_reason)->not->toBeNull();
});
