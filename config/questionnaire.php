<?php

/*
|--------------------------------------------------------------------------
| Athlete Questionnaire Options
|--------------------------------------------------------------------------
|
| The answer choices shown on the athlete questionnaire, keyed by the value
| submitted with the form. Soccer leagues live in the SoccerLeague enum so
| they can carry their approximate competitive tier.
|
*/

return [

    'sports' => [
        'womens_soccer' => "Women's soccer",
    ],

    'divisions' => [
        'division_1' => 'Division I',
        'division_2' => 'Division II',
        'division_3' => 'Division III',
        'open' => 'Open to multiple divisions',
    ],

    'positions' => [
        'goalkeeper' => 'Goalkeeper',
        'defender' => 'Defender',
        'midfielder' => 'Midfielder',
        'forward' => 'Forward',
    ],

    'tests' => [
        'sat' => 'SAT',
        'act' => 'ACT',
        'both' => 'Both',
        'none' => "Haven't taken either",
    ],

    'majors' => [
        'business' => 'Business',
        'nursing' => 'Nursing',
        'psychology' => 'Psychology',
        'biology' => 'Biology',
        'education' => 'Education',
        'undecided' => 'Undecided',
        'other' => 'Other',
    ],

    'budgets' => [
        'under_15k' => 'Under $15,000',
        '15k_30k' => '$15,000–$30,000',
        '30k_45k' => '$30,000–$45,000',
        '45k_plus' => '$45,000+',
        'unsure' => 'Unsure',
    ],

    'settings' => [
        'urban' => 'Urban',
        'suburban' => 'Suburban',
        'rural' => 'Rural',
        'no_preference' => 'No preference',
    ],

    'sizes' => [
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'no_preference' => 'No preference',
    ],

    'priorities' => [
        'academics' => 'Academics',
        'affordability' => 'Affordability',
        'soccer' => 'Soccer opportunities',
        'social_life' => 'Social life',
        'balance' => 'A balance of everything',
    ],

];
