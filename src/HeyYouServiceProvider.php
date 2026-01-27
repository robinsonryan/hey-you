<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou;

use Illuminate\Support\ServiceProvider;
use RobinsonRyan\HeyYou\Checkers\DefaultConsentChecker;
use RobinsonRyan\HeyYou\Checkers\DefaultDncChecker;
use RobinsonRyan\HeyYou\Checkers\RelationshipBasedScopeResolver;
use RobinsonRyan\HeyYou\Contracts\ConsentChecker;
use RobinsonRyan\HeyYou\Contracts\ContactPointPurposeManager;
use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Contracts\DncChecker;
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;
use RobinsonRyan\HeyYou\Contracts\IdentifierGenerator;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\ConsentCategoryRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RelationshipTypeRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RoleRegistry;
use RobinsonRyan\HeyYou\Contracts\ScopeHierarchyResolver;
use RobinsonRyan\HeyYou\Resolver\DefaultContactResolver;
use RobinsonRyan\HeyYou\Support\DefaultContactPointPurposeManager;

final class HeyYouServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/heyyou.php',
            'heyyou',
        );

        $this->registerIdentifierGenerator();
        $this->registerRegistries();
        $this->registerEventDispatcher();
        $this->registerCoreServices();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->loadMigrations();
        $this->publishMigrations();
    }

    protected function registerIdentifierGenerator(): void
    {
        $this->app->bind(
            IdentifierGenerator::class,
            config('heyyou.identifier_generator'),
        );
    }

    protected function registerRegistries(): void
    {
        $this->app->bind(ChannelRegistry::class, config('heyyou.registries.channel'));
        $this->app->bind(PurposeRegistry::class, config('heyyou.registries.purpose'));
        $this->app->bind(RoleRegistry::class, config('heyyou.registries.role'));
        $this->app->bind(RelationshipTypeRegistry::class, config('heyyou.registries.relationship_type'));
        $this->app->bind(ConsentCategoryRegistry::class, config('heyyou.registries.consent_category'));
        $this->app->bind(NormalizerRegistry::class, config('heyyou.registries.normalizer'));
        $this->app->bind(ContactPointPurposeManager::class, DefaultContactPointPurposeManager::class);
    }

    protected function registerEventDispatcher(): void
    {
        $this->app->bind(
            EventDispatcher::class,
            config('heyyou.event_dispatcher'),
        );
    }

    protected function registerCoreServices(): void
    {
        $this->app->bind(ScopeHierarchyResolver::class, RelationshipBasedScopeResolver::class);
        $this->app->bind(DncChecker::class, DefaultDncChecker::class);
        $this->app->bind(ConsentChecker::class, DefaultConsentChecker::class);
        $this->app->bind(ContactResolver::class, DefaultContactResolver::class);
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/heyyou.php' => config_path('heyyou.php'),
        ], 'heyyou-config');
    }

    protected function loadMigrations(): void
    {
        if (! $this->migrationsPublished()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'heyyou-migrations');
    }

    private function migrationsPublished(): bool
    {
        return count(glob(database_path('migrations/*_create_heyyou_*.php')) ?: []) > 0;
    }
}
