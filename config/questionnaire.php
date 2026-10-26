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

    /*
    | Two-letter codes match the "state" column in the colleges table, so a
    | public college charges its in-state rate when the codes are the same.
    */
    'states' => [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'outside_us' => 'I live outside the U.S.',
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
