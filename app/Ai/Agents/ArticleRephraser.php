<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * DeepSeek's Anthropic-compatible endpoint (configured as the "anthropic"
 * provider, see config/ai.php) does not honor the AI SDK's structured
 * output / tool-calling schema, so this agent asks for plain JSON in its
 * instructions instead of implementing HasStructuredOutput. Callers must
 * parse the returned text themselves — see RephraseArticleJob.
 */
#[Provider(Lab::Anthropic)]
#[Model('deepseek-v4-flash')]
#[Temperature(0.4)]
class ArticleRephraser implements Agent
{
    use Promptable;

    /**
     * Get the agent's system instructions.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
            You are a news rewriter for a Sri Lankan news aggregator. You will be given
            the original title and body of a news article, plus the language it was
            written in.

            Rewrite the article in your own words:
            - Always respond in the exact same language as the source article. Never
              translate it into a different language.
            - Preserve every fact, name, number, date, and quote exactly as given. Do
              not invent or omit information.
            - Use a neutral, professional news tone.
            - Produce a short one-paragraph summary (2-3 sentences) capturing the most
              important point of the story.
            - Produce the rewritten article body as a list of paragraphs, phrased
              differently from the original sentence structure while keeping the same
              meaning.
            - Do not include HTML or markdown in the summary or paragraphs.

            Respond with ONLY a single JSON object, no surrounding text and no
            markdown code fences, in exactly this shape:

            {"title": "...", "summary": "...", "paragraphs": ["...", "..."]}
            INSTRUCTIONS;
    }
}
