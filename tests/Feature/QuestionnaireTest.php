<?php

namespace Tests\Feature;

use App\Enums\CompetitiveTier;
use App\Enums\SoccerLeague;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A complete set of answers for a fictional athlete.
     *
     * @return array<string, int|string|null>
     */
    private function fictionalAthlete(array $overrides = []): array
    {
        return array_merge([
            'sport' => 'womens_soccer',
            'graduation_year' => now()->year + 2,
            'division' => 'open',
            'position' => 'midfielder',
            'league' => 'ecnl_rl',
            'gpa' => '3.65',
            'test_taken' => 'sat',
            'sat_score' => 1250,
            'act_score' => null,
            'major' => 'nursing',
            'budget' => '15k_30k',
            'home_state' => 'NJ',
            'setting' => 'suburban',
            'size' => 'medium',
            'priority' => 'balance',
        ], $overrides);
    }

    public function test_homepage_links_to_the_questionnaire(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Get Started')
            ->assertDontSee('Coming Soon')
            ->assertSee(route('questionnaire.create'));
    }

    public function test_questionnaire_page_shows_every_question_and_the_demo_notice(): void
    {
        $this->get(route('questionnaire.create'))
            ->assertOk()
            ->assertSee('Demo only')
            ->assertSee('fictional athlete profiles')
            ->assertSee('What sport do you want to play in college?')
            ->assertSee('What matters most to you when choosing a college?')
            ->assertSee('Girls Academy (GA)')
            ->assertSee('after</strong> grants and scholarships', false);
    }

    public function test_a_fictional_athlete_can_submit_the_questionnaire(): void
    {
        $this->post(route('questionnaire.store'), $this->fictionalAthlete())
            ->assertOk()
            ->assertSee('Open to multiple divisions')
            ->assertSee('ECNL Regional League (ECNL RL)')
            ->assertSee('Tier 2')
            ->assertSee('SAT 1250')
            ->assertSee('$15,000–$30,000');
    }

    public function test_test_scores_are_optional(): void
    {
        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'test_taken' => 'none',
            'sat_score' => 1250,
        ]))
            ->assertOk()
            ->assertSee('No scores entered')
            ->assertDontSee('SAT 1250');
    }

    public function test_required_answers_are_validated(): void
    {
        $this->post(route('questionnaire.store'), [])
            ->assertSessionHasErrors(['sport', 'graduation_year', 'division', 'position', 'league', 'gpa', 'major', 'budget', 'setting', 'size', 'priority'])
            ->assertSessionDoesntHaveErrors(['test_taken', 'sat_score', 'act_score']);
    }

    public function test_gpa_must_be_on_a_four_point_scale(): void
    {
        $this->post(route('questionnaire.store'), $this->fictionalAthlete(['gpa' => '4.5']))
            ->assertSessionHasErrors('gpa');
    }

    public function test_test_scores_must_be_in_range(): void
    {
        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'test_taken' => 'both',
            'sat_score' => 1700,
            'act_score' => 40,
        ]))->assertSessionHasErrors(['sat_score', 'act_score']);
    }

    public function test_leagues_are_grouped_into_approximate_tiers(): void
    {
        $this->assertSame(CompetitiveTier::Tier1, SoccerLeague::Ecnl->tier());
        $this->assertSame(CompetitiveTier::Tier1, SoccerLeague::GirlsAcademy->tier());
        $this->assertSame(CompetitiveTier::Tier2, SoccerLeague::DevelopmentPlayerLeague->tier());
        $this->assertSame(CompetitiveTier::Tier3, SoccerLeague::NationalPremierLeagues->tier());
        $this->assertSame(CompetitiveTier::Tier4, SoccerLeague::TravelLeague->tier());
        $this->assertSame(CompetitiveTier::Tier5, SoccerLeague::Recreational->tier());
        $this->assertSame(CompetitiveTier::HighSchoolOnly, SoccerLeague::HighSchoolOnly->tier());
        $this->assertSame(CompetitiveTier::OtherOrNotSure, SoccerLeague::OtherOrNotSure->tier());
    }
}
