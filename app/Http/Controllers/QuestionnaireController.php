<?php

namespace App\Http\Controllers;

use App\Enums\SoccerLeague;
use App\Http\Requests\StoreQuestionnaireRequest;
use App\Recommendations\CollegeMatcher;
use App\Recommendations\RecommendationExplainer;
use Illuminate\View\View;

class QuestionnaireController extends Controller
{
    /**
     * Show the athlete questionnaire.
     */
    public function create(): View
    {
        return view('questionnaire.create', [
            'options' => config('questionnaire'),
            'graduationYears' => StoreQuestionnaireRequest::graduationYears(),
            'leagues' => SoccerLeague::cases(),
        ]);
    }

    /**
     * Show recommended colleges and a summary of the submitted answers.
     *
     * Answers are only used for this response; nothing is saved.
     */
    public function store(StoreQuestionnaireRequest $request, CollegeMatcher $matcher, RecommendationExplainer $explainer): View
    {
        $answers = $request->validated();
        $results = $matcher->match($answers);

        return view('questionnaire.results', [
            'answers' => $answers,
            'options' => config('questionnaire'),
            'league' => SoccerLeague::from($answers['league']),
            'results' => $results,
            'explanations' => $explainer->explain($answers, $results),
        ]);
    }
}
