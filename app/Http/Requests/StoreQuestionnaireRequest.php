<?php

namespace App\Http\Requests;

use App\Enums\SoccerLeague;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionnaireRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sport' => ['required', Rule::in(array_keys(config('questionnaire.sports')))],
            'graduation_year' => ['required', 'integer', Rule::in(self::graduationYears())],
            'division' => ['required', Rule::in(array_keys(config('questionnaire.divisions')))],
            'position' => ['required', Rule::in(array_keys(config('questionnaire.positions')))],
            'league' => ['required', Rule::enum(SoccerLeague::class)],
            'gpa' => ['required', 'numeric', 'min:0', 'max:4', 'decimal:0,2'],
            'test_taken' => ['nullable', Rule::in(array_keys(config('questionnaire.tests')))],
            'sat_score' => ['exclude_unless:test_taken,sat,both', 'nullable', 'integer', 'between:400,1600'],
            'act_score' => ['exclude_unless:test_taken,act,both', 'nullable', 'integer', 'between:1,36'],
            'major' => ['required', Rule::in(array_keys(config('questionnaire.majors')))],
            'budget' => ['required', Rule::in(array_keys(config('questionnaire.budgets')))],
            'home_state' => ['required', Rule::in(array_keys(config('questionnaire.states')))],
            'setting' => ['required', Rule::in(array_keys(config('questionnaire.settings')))],
            'size' => ['required', Rule::in(array_keys(config('questionnaire.sizes')))],
            'priority' => ['required', Rule::in(array_keys(config('questionnaire.priorities')))],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gpa.max' => 'Please enter a GPA on a 4.0 scale (0.00 to 4.00).',
            'gpa.decimal' => 'Please use no more than two decimal places for GPA (for example, 3.75).',
            'sat_score.between' => 'SAT scores range from 400 to 1600.',
            'act_score.between' => 'ACT scores range from 1 to 36.',
            'home_state.required' => 'Please choose a state (any state is fine for a fictional athlete).',
        ];
    }

    /**
     * Get the graduation years an athlete can choose from.
     *
     * @return array<int, int>
     */
    public static function graduationYears(): array
    {
        return range(now()->year, now()->year + 5);
    }
}
