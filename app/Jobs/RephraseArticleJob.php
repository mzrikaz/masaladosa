<?php

namespace App\Jobs;

use App\Ai\Agents\ArticleRephraser;
use App\Contracts\Repositories\ArticleRepository;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
class RephraseArticleJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Article $article,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ArticleRepository $articles): void
    {
        $response = (new ArticleRephraser)->prompt(
            "Language: {$this->article->language->label()}\n\n".
            "Original title: {$this->article->original_title}\n\n".
            "Original article:\n{$this->article->original_body}"
        );

        $rephrased = $this->parseResponse($response->text);

        $articles->publish(
            $this->article,
            $rephrased['title'],
            $rephrased['summary'],
            $rephrased['paragraphs'],
        );
    }

    /**
     * @return array{title: string, summary: string, paragraphs: array<int, string>}
     */
    private function parseResponse(string $text): array
    {
        $json = trim($text);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $json) ?? $json;

        $decoded = json_decode($json, true);

        if (! is_array($decoded)
            || ! isset($decoded['title'], $decoded['summary'], $decoded['paragraphs'])
            || ! is_array($decoded['paragraphs'])
        ) {
            throw new \RuntimeException('AI response was not valid JSON in the expected shape: '.$text);
        }

        return [
            'title' => (string) $decoded['title'],
            'summary' => (string) $decoded['summary'],
            'paragraphs' => array_values(array_map(strval(...), $decoded['paragraphs'])),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        app(ArticleRepository::class)->markFailed(
            $this->article,
            $exception?->getMessage() ?? 'Unknown rephrasing failure.',
        );
    }
}
