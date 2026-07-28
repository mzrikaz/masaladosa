<?php

namespace App\Providers;

use App\Contracts\Repositories\ArticleRepository;
use App\Contracts\Repositories\SourceRepository;
use App\Repositories\EloquentArticleRepository;
use App\Repositories\EloquentSourceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SourceRepository::class, EloquentSourceRepository::class);
        $this->app->bind(ArticleRepository::class, EloquentArticleRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // News sites commonly block Laravel/Guzzle's default User-Agent as an
        // obvious bot signature. Scrapers use this macro instead of Http::get()
        // directly to send a realistic desktop browser UA.
        Http::macro('newsSource', fn () => Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        ])->timeout(20));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
