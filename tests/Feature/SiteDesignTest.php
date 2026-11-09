<?php

namespace Tests\Feature;

use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SiteDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_has_no_animations_transitions_or_smooth_scrolling(): void
    {
        $files = [public_path('css/app.css'), ...File::allFiles(resource_path('views'))];

        foreach ($files as $file) {
            $contents = strtolower(File::get((string) $file));

            foreach (['transition', '@keyframes', 'animation', 'scroll-behavior: smooth', "behavior: 'smooth'", 'marquee'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $contents, "{$file} must not contain \"{$forbidden}\".");
            }
        }
    }

    public function test_every_page_uses_the_shared_branding_navigation_and_footer(): void
    {
        foreach (['/', route('questionnaire.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('css/app.css', false)
                ->assertSee('Skip to main content')
                ->assertSee('aria-label="Main"', false)
                ->assertSee('Find my matches')
                ->assertSee('Demo project for fictional athlete profiles only. Answers are never saved.')
                ->assertSee('Not affiliated with the NCAA or any college.');
        }
    }

    public function test_the_homepage_has_a_hero_and_explains_how_matching_works(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find the college soccer program that')
            ->assertSeeInOrder(['Get Started', 'How it works', 'Tell us about you', 'We match with verified data', 'Compare honest results'])
            ->assertSee('Verified data, honest costs, careful AI')
            ->assertSee('Labels shown here are examples of the result layout, not real results.')
            ->assertDontSee('<img', false);
    }

    public function test_the_questionnaire_groups_all_13_questions_into_four_sections_with_progress(): void
    {
        $response = $this->get(route('questionnaire.create'))->assertOk();

        $response->assertSee('of 13 answered')
            ->assertSeeInOrder(['Your soccer', 'Questions 1–5 of 13', 'Academics', 'Questions 6–8 of 13', 'Cost &amp; location', 'Questions 9–10 of 13', 'Campus fit', 'Questions 11–13 of 13'], false);

        foreach (range(1, 13) as $number) {
            $response->assertSee('<span class="step">'.$number.'</span>', false);
        }
    }

    public function test_every_college_card_starts_with_key_facts(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), [
            'sport' => 'womens_soccer',
            'graduation_year' => now()->year + 1,
            'division' => 'division_3',
            'position' => 'defender',
            'league' => 'ecnl_rl',
            'gpa' => '3.70',
            'test_taken' => 'none',
            'major' => 'education',
            'budget' => '30k_45k',
            'home_state' => 'NJ',
            'setting' => 'no_preference',
            'size' => 'medium',
            'priority' => 'affordability',
        ]);

        $content = $response->assertOk()->getContent();
        $cards = substr_count($content, '<article class="card match">') + substr_count($content, '<details class="card match also">');

        $this->assertSame($cards, substr_count($content, 'aria-label="Key facts"'));
        $response->assertSeeInOrder(['The College of New Jersey', 'Your major', 'Offered', 'Education', 'Published cost', 'Avg. net price'])
            ->assertSee('In-state students only', false);
    }

    public function test_academic_context_is_expandable_but_keeps_every_detail_and_disclaimer(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), [
            'sport' => 'womens_soccer',
            'graduation_year' => now()->year + 1,
            'division' => 'division_1',
            'position' => 'forward',
            'league' => 'ecnl',
            'gpa' => '3.80',
            'test_taken' => 'sat',
            'sat_score' => 1300,
            'major' => 'undecided',
            'budget' => 'unsure',
            'home_state' => 'NC',
            'setting' => 'no_preference',
            'size' => 'no_preference',
            'priority' => 'academics',
        ]);

        $content = $response->assertOk()->getContent();

        $this->assertSame(substr_count($content, 'class="academic"'), substr_count($content, '<details class="academic-details">'), 'Every college with admissions data has an expandable academic section.');
        $response->assertSee('<details class="card method">', false)
            ->assertSeeInOrder(['<details class="card method">', 'How these matches were chosen', '</summary>', 'Only colleges with a verified active women', 'Federal average net price', 'not a personalized estimate', 'These results do not guarantee admission, scholarships, roster spots, or recruitment.', '</details>'], false);
        $this->assertStringNotContainsString('<details class="card method" open', $content, 'How these matches were chosen starts collapsed.');

        $response->assertSeeInOrder(['<details class="academic-details">', 'Academic context', 'SAT/ACT policy, test score ranges, GPA, and admission rate', '</summary>', 'Overall admission rate:', 'This describes all applicants, not your individual chances.', 'Source:', '</details>'], false);
    }
}
