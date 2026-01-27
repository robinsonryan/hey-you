<?php

declare(strict_types=1);

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
use RobinsonRyan\HeyYou\Events\LaravelEventDispatcher;
use RobinsonRyan\HeyYou\Registries\ConfigChannelRegistry;
use RobinsonRyan\HeyYou\Registries\ConfigConsentCategoryRegistry;
use RobinsonRyan\HeyYou\Registries\ConfigPurposeRegistry;
use RobinsonRyan\HeyYou\Registries\ConfigRelationshipTypeRegistry;
use RobinsonRyan\HeyYou\Registries\ConfigRoleRegistry;
use RobinsonRyan\HeyYou\Registries\DefaultNormalizerRegistry;
use RobinsonRyan\HeyYou\Resolver\DefaultContactResolver;
use RobinsonRyan\HeyYou\Support\AutoIncrementGenerator;
use RobinsonRyan\HeyYou\Support\DefaultContactPointPurposeManager;

describe('Service Provider bindings', function () {
    it('binds IdentifierGenerator', function () {
        expect(app(IdentifierGenerator::class))->toBeInstanceOf(AutoIncrementGenerator::class);
    });

    it('binds ChannelRegistry', function () {
        expect(app(ChannelRegistry::class))->toBeInstanceOf(ConfigChannelRegistry::class);
    });

    it('binds PurposeRegistry', function () {
        expect(app(PurposeRegistry::class))->toBeInstanceOf(ConfigPurposeRegistry::class);
    });

    it('binds RoleRegistry', function () {
        expect(app(RoleRegistry::class))->toBeInstanceOf(ConfigRoleRegistry::class);
    });

    it('binds RelationshipTypeRegistry', function () {
        expect(app(RelationshipTypeRegistry::class))->toBeInstanceOf(ConfigRelationshipTypeRegistry::class);
    });

    it('binds ConsentCategoryRegistry', function () {
        expect(app(ConsentCategoryRegistry::class))->toBeInstanceOf(ConfigConsentCategoryRegistry::class);
    });

    it('binds NormalizerRegistry', function () {
        expect(app(NormalizerRegistry::class))->toBeInstanceOf(DefaultNormalizerRegistry::class);
    });

    it('binds EventDispatcher', function () {
        expect(app(EventDispatcher::class))->toBeInstanceOf(LaravelEventDispatcher::class);
    });

    it('binds ScopeHierarchyResolver', function () {
        expect(app(ScopeHierarchyResolver::class))->toBeInstanceOf(RelationshipBasedScopeResolver::class);
    });

    it('binds DncChecker', function () {
        expect(app(DncChecker::class))->toBeInstanceOf(DefaultDncChecker::class);
    });

    it('binds ConsentChecker', function () {
        expect(app(ConsentChecker::class))->toBeInstanceOf(DefaultConsentChecker::class);
    });

    it('binds ContactResolver', function () {
        expect(app(ContactResolver::class))->toBeInstanceOf(DefaultContactResolver::class);
    });

    it('binds ContactPointPurposeManager', function () {
        expect(app(ContactPointPurposeManager::class))->toBeInstanceOf(DefaultContactPointPurposeManager::class);
    });
});

describe('Configuration', function () {
    it('has default table prefix', function () {
        expect(config('heyyou.table_prefix'))->toBe('heyyou_');
    });

    it('has default channels', function () {
        $channels = config('heyyou.channels');

        expect($channels)->toBeArray()
            ->and($channels)->toHaveKey('email')
            ->and($channels)->toHaveKey('phone')
            ->and($channels)->toHaveKey('sms');
    });

    it('has default purposes', function () {
        $purposes = config('heyyou.purposes');

        expect($purposes)->toBeArray()
            ->and($purposes)->toHaveKey('general')
            ->and($purposes)->toHaveKey('billing')
            ->and($purposes)->toHaveKey('shipping');
    });

    it('has default roles', function () {
        $roles = config('heyyou.roles');

        expect($roles)->toBeArray()
            ->and($roles)->toHaveKey('accounts_payable_contact')
            ->and($roles)->toHaveKey('primary_contact');
    });

    it('has default consent categories', function () {
        $categories = config('heyyou.consent_categories');

        expect($categories)->toBeArray()
            ->and($categories)->toHaveKey('transactional')
            ->and($categories)->toHaveKey('marketing');
    });
});
