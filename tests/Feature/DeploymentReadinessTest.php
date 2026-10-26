<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    private const HOOK = '.platform/hooks/predeploy/01_build_college_database.sh';

    private function bootAs(string $environment, string $appUrl): void
    {
        $this->app['env'] = $environment;
        config(['app.url' => $appUrl]);
        $this->app->getProvider(AppServiceProvider::class)->useSecureAppUrl();
    }

    public function test_production_links_and_forms_use_the_public_https_url(): void
    {
        $this->bootAs('production', 'https://example.cloudfront.net');

        $this->get('http://my-env.us-east-2.elasticbeanstalk.com/')
            ->assertOk()
            ->assertSee('href="https://example.cloudfront.net/questionnaire"', false);

        $this->get('http://my-env.us-east-2.elasticbeanstalk.com/questionnaire')
            ->assertOk()
            ->assertSee('action="https://example.cloudfront.net/questionnaire"', false);
    }

    public function test_links_are_not_forced_outside_production_or_without_an_https_url(): void
    {
        $this->bootAs('local', 'https://example.cloudfront.net');
        $this->get('http://localhost:8000/')->assertSee('href="http://localhost:8000/questionnaire"', false);

        $this->bootAs('production', 'http://localhost');
        $this->get('http://localhost:8000/')->assertSee('href="http://localhost:8000/questionnaire"', false);
    }

    public function test_the_deploy_hook_rebuilds_the_college_database_safely(): void
    {
        $script = file_get_contents(base_path(self::HOOK));

        $this->assertStringStartsWith("#!/bin/bash\n", $script);
        $this->assertStringNotContainsString("\r", $script, 'The hook must use Linux line endings.');
        $this->assertStringContainsString('set -euo pipefail', $script);
        $this->assertStringContainsString('php artisan migrate --force', $script);
        $this->assertStringContainsString('php artisan db:seed --class=CollegeSeeder --force', $script);
        $this->assertStringContainsString('chown -R webapp:webapp database storage bootstrap/cache', $script);
    }

    public function test_the_deploy_hook_is_executable_in_git(): void
    {
        $mode = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files -s '.escapeshellarg(self::HOOK)));

        if ($mode === '') {
            $this->markTestSkipped('Git is not available or the hook is not tracked yet.');
        }

        $this->assertStringStartsWith('100755', $mode);
    }

    public function test_elastic_beanstalk_settings_are_production_safe_and_contain_no_secrets(): void
    {
        $config = file_get_contents(base_path('.ebextensions/01-laravel.config'));

        $this->assertStringContainsString('APP_DEBUG: false', $config);
        $this->assertStringContainsString('DB_CONNECTION: sqlite', $config);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE: true', $config);
        $this->assertStringContainsString('--no-dev', $config);

        foreach (['APP_KEY', 'ANTHROPIC_API_KEY', 'AWS_SECRET_ACCESS_KEY', 'sk-ant-'] as $secret) {
            $this->assertStringNotContainsString($secret, $config);
        }
    }

    public function test_the_local_database_and_env_file_are_never_committed(): void
    {
        $this->assertStringContainsString('*.sqlite*', file_get_contents(base_path('database/.gitignore')));
        $this->assertMatchesRegularExpression('/^\.env$/m', file_get_contents(base_path('.gitignore')));
    }
}
