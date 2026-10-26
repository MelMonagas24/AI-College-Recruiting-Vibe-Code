<?php

namespace App\Providers;

use App\Recommendations\ClaudeRecommendationExplainer;
use App\Recommendations\RecommendationExplainer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RecommendationExplainer::class, fn (): ClaudeRecommendationExplainer => new ClaudeRecommendationExplainer(
            apiKey: config('services.anthropic.api_key'),
            model: config('services.anthropic.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
