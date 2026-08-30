<?php

namespace App\Providers;

use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureGates();
        $this->configureSocialite();
    }

    /**
     * Socialite has no first-party Discord driver, so the community provider is
     * registered through the SocialiteWasCalled event.
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', DiscordProvider::class);
        });
    }

    /**
     * Tracker adjustments address their subject by alias, so the aliases have to
     * stay stable even if a model is moved or renamed.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'game' => Game::class,
            'corporation' => Corporation::class,
            'gang' => Gang::class,
            'character' => Character::class,
        ]);
    }

    protected function configureGates(): void
    {
        // The Control area at all: the account-wide flag, or a seat on some
        // game's Control team.
        Gate::define('control', fn (User $user): bool => $user->isControl());

        // One game in it. Named on that game's Control team, or Control of
        // everything. Every route carrying a {game} checks this, so a seat on
        // Saturday's game is not a seat on somebody else's.
        Gate::define('control-game', fn (User $user, Game $game): bool => $user->isControlFor($game));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
