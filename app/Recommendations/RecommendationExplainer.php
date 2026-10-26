<?php

namespace App\Recommendations;

/**
 * Writes short personalized explanations for colleges the matcher already chose.
 */
interface RecommendationExplainer
{
    /**
     * @param  array<string, mixed>  $answers  Validated questionnaire answers.
     */
    public function explain(array $answers, MatchResults $results): ExplanationResult;
}
