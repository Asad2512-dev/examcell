<?php

namespace App\Providers;

use App\Models\AdmitCard;
use App\Models\CalculatedResult;
use App\Models\Conversation;
use App\Models\ExamCourse;
use App\Policies\AdmitCardPolicy;
use App\Policies\CalculatedResultPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\ExamCoursePolicy;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentUserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(ExamCourse::class, ExamCoursePolicy::class);
        Gate::policy(CalculatedResult::class, CalculatedResultPolicy::class);
        Gate::policy(AdmitCard::class, AdmitCardPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by(strtolower($r->input('email', '')).'|'.$r->ip()), Limit::perMinute(30)->by($r->ip())]);
        RateLimiter::for('reset', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('assistant', fn (Request $r) => Limit::perMinute(5)->by($r->user()?->id ?? $r->ip()));
    }
}
