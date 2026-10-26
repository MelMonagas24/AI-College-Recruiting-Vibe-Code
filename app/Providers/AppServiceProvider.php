<?php

namespace App\Providers;

use App\Recommendations\ClaudeRecommendationExplainer;
use App\Recommendations\RecommendationExplainer;
use Illuminate\Support\Facades\URL;
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
        $this->useSecureAppUrl();
    }

    /**
     * Behind CloudFront, requests reach the server over plain HTTP at the Elastic
     * Beanstalk address. In production, build every link and form from the public
     * HTTPS APP_URL instead, so forms post to the secure CloudFront site.
     */
    public function useSecureAppUrl(): void
    {
        $appUrl = (string) config('app.url');

        if ($this->app->isProduction() && str_starts_with($appUrl, 'https://')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme('https');
        }
    }
}
