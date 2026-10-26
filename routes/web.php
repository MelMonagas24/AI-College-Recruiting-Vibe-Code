<?php

use App\Http\Controllers\QuestionnaireController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/questionnaire', [QuestionnaireController::class, 'create'])->name('questionnaire.create');
Route::post('/questionnaire', [QuestionnaireController::class, 'store'])->name('questionnaire.store');
