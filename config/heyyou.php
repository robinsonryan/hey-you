<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | All package tables will be prefixed with this value. Set to null or
    | empty string to disable prefixing.
    |
    */
    'table_prefix' => 'heyyou_',

    /*
    |--------------------------------------------------------------------------
    | Identifier Generator
    |--------------------------------------------------------------------------
    |
    | The class responsible for generating primary keys for package models.
    | Must implement \RobinsonRyan\HeyYou\Contracts\IdentifierGenerator.
    |
    */
    'identifier_generator' => RobinsonRyan\HeyYou\Support\AutoIncrementGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Registries
    |--------------------------------------------------------------------------
    |
    | Bindings for the various registry contracts. Replace with your own
    | implementations (e.g., Taxon-backed registries).
    |
    */
    'registries' => [
        'channel' => RobinsonRyan\HeyYou\Registries\ConfigChannelRegistry::class,
        'purpose' => RobinsonRyan\HeyYou\Registries\ConfigPurposeRegistry::class,
        'role' => RobinsonRyan\HeyYou\Registries\ConfigRoleRegistry::class,
        'relationship_type' => RobinsonRyan\HeyYou\Registries\ConfigRelationshipTypeRegistry::class,
        'consent_category' => RobinsonRyan\HeyYou\Registries\ConfigConsentCategoryRegistry::class,
        'normalizer' => RobinsonRyan\HeyYou\Registries\DefaultNormalizerRegistry::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'log_history' => true,
        'default_expiration_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Dispatcher
    |--------------------------------------------------------------------------
    |
    | The class responsible for dispatching package events.
    | Must implement \RobinsonRyan\HeyYou\Contracts\EventDispatcher.
    |
    */
    'event_dispatcher' => RobinsonRyan\HeyYou\Events\LaravelEventDispatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Default Channels
    |--------------------------------------------------------------------------
    |
    | Used by ConfigChannelRegistry. Ignored if using custom registry.
    |
    */
    'channels' => [
        'email' => ['name' => 'Email', 'category' => 'electronic'],
        'phone' => ['name' => 'Phone', 'category' => 'electronic'],
        'sms' => ['name' => 'SMS', 'category' => 'electronic'],
        'whatsapp' => ['name' => 'WhatsApp', 'category' => 'messaging'],
        'signal' => ['name' => 'Signal', 'category' => 'messaging'],
        'facebook' => ['name' => 'Facebook', 'category' => 'social'],
        'instagram' => ['name' => 'Instagram', 'category' => 'social'],
        'linkedin' => ['name' => 'LinkedIn', 'category' => 'social'],
        'twitter' => ['name' => 'Twitter/X', 'category' => 'social'],
        'tiktok' => ['name' => 'TikTok', 'category' => 'social'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Purposes
    |--------------------------------------------------------------------------
    |
    | Used by ConfigPurposeRegistry. Ignored if using custom registry.
    |
    */
    'purposes' => [
        'general' => ['name' => 'General', 'parent' => null],
        'billing' => ['name' => 'Billing', 'parent' => null],
        'accounts_payable' => ['name' => 'Accounts Payable', 'parent' => 'billing'],
        'accounts_receivable' => ['name' => 'Accounts Receivable', 'parent' => 'billing'],
        'shipping' => ['name' => 'Shipping', 'parent' => null],
        'receiving' => ['name' => 'Receiving', 'parent' => 'shipping'],
        'hr' => ['name' => 'Human Resources', 'parent' => null],
        'sales' => ['name' => 'Sales', 'parent' => null],
        'support' => ['name' => 'Support', 'parent' => null],
        'executive' => ['name' => 'Executive', 'parent' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    |
    | Used by ConfigRoleRegistry. Ignored if using custom registry.
    |
    */
    'roles' => [
        'accounts_payable_contact' => ['name' => 'Accounts Payable Contact'],
        'accounts_receivable_contact' => ['name' => 'Accounts Receivable Contact'],
        'hr_contact' => ['name' => 'HR Contact'],
        'receiving_manager' => ['name' => 'Receiving Manager'],
        'shipping_contact' => ['name' => 'Shipping Contact'],
        'sales_contact' => ['name' => 'Sales Contact'],
        'support_contact' => ['name' => 'Support Contact'],
        'executive_contact' => ['name' => 'Executive Contact'],
        'primary_contact' => ['name' => 'Primary Contact'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Relationship Types
    |--------------------------------------------------------------------------
    |
    | Used by ConfigRelationshipTypeRegistry. Ignored if using custom registry.
    |
    */
    'relationship_types' => [
        'employment' => ['name' => 'Employment', 'from' => 'person', 'to' => 'organization'],
        'contractor' => ['name' => 'Contractor', 'from' => 'person', 'to' => 'organization'],
        'location_of' => ['name' => 'Location Of', 'from' => 'location', 'to' => 'organization'],
        'member_of' => ['name' => 'Member Of', 'from' => 'organization', 'to' => 'organization'],
        'parent_of' => ['name' => 'Parent Of', 'from' => 'organization', 'to' => 'organization'],
        'managed_by' => ['name' => 'Managed By', 'from' => 'location', 'to' => 'person'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Consent Categories
    |--------------------------------------------------------------------------
    |
    | Used by ConfigConsentCategoryRegistry. Ignored if using custom registry.
    |
    */
    'consent_categories' => [
        'transactional' => ['name' => 'Transactional'],
        'marketing' => ['name' => 'Marketing'],
        'support' => ['name' => 'Support'],
        'collections' => ['name' => 'Collections'],
    ],

];
