<?php

namespace App\Http\Controllers;

use App\Enums\SoccerLeague;
use App\Http\Requests\StoreQuestionnaireRequest;
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
     * Show a summary of the submitted answers.
     *
     * Answers are only displayed back to the athlete; nothing is saved.
     */
    public function store(StoreQuestionnaireRequest $request): View
    {
        $answers = $request->validated();

        return view('questionnaire.summary', [
            'answers' => $answers,
            'options' => config('questionnaire'),
            'league' => SoccerLeague::from($answers['league']),
        ]);
    }
}
