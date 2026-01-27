# Configuration Reference

Full reference for `config/heyyou.php`.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=heyyou-config
```

## Full Configuration File

```php
<?php

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
    | Options:
    | - AutoIncrementGenerator (default) - Standard auto-incrementing integers
    | - Custom implementation for UUIDs, ULIDs, etc.
    |
    */
    'identifier_generator' => \RobinsonRyan\HeyYou\Support\AutoIncrementGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Registries
    |--------------------------------------------------------------------------
    |
    | Bindings for the various registry contracts. Replace with your own
    | implementations (e.g., database-backed registries).
    |
    */
    'registries' => [
        'channel' => \RobinsonRyan\HeyYou\Registries\ConfigChannelRegistry::class,
        'purpose' => \RobinsonRyan\HeyYou\Registries\ConfigPurposeRegistry::class,
        'role' => \RobinsonRyan\HeyYou\Registries\ConfigRoleRegistry::class,
        'relationship_type' => \RobinsonRyan\HeyYou\Registries\ConfigRelationshipTypeRegistry::class,
        'consent_category' => \RobinsonRyan\HeyYou\Registries\ConfigConsentCategoryRegistry::class,
        'normalizer' => \RobinsonRyan\HeyYou\Registries\DefaultNormalizerRegistry::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */
    'verification' => [
        // Log verification attempts to verification_events table
        'log_history' => true,

        // Default expiration for verifications in days (null = never expires)
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
    'event_dispatcher' => \RobinsonRyan\HeyYou\Events\LaravelEventDispatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Default Channels
    |--------------------------------------------------------------------------
    |
    | Used by ConfigChannelRegistry. Ignored if using custom registry.
    |
    | Each channel needs:
    | - name: Display name
    | - category: Grouping (electronic, messaging, social)
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
    | Each purpose needs:
    | - name: Display name
    | - parent: Parent purpose slug (null for top-level)
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
    | Each role needs:
    | - name: Display name
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
    | Each type needs:
    | - name: Display name
    | - from: Expected party type for 'from' side (person, organization, location)
    | - to: Expected party type for 'to' side
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
    | Each category needs:
    | - name: Display name
    |
    */
    'consent_categories' => [
        'transactional' => ['name' => 'Transactional'],
        'marketing' => ['name' => 'Marketing'],
        'support' => ['name' => 'Support'],
        'collections' => ['name' => 'Collections'],
    ],

];
```

## Configuration Options

### Table Prefix

```php
'table_prefix' => 'heyyou_',
```

All package tables are prefixed with this value. To disable:

```php
'table_prefix' => null,
// or
'table_prefix' => '',
```

**Note:** Changing the prefix after running migrations requires manual table renaming.

### Identifier Generator

```php
'identifier_generator' => \RobinsonRyan\HeyYou\Support\AutoIncrementGenerator::class,
```

Controls primary key generation. Create custom implementations for UUIDs:

```php
'identifier_generator' => \App\Support\UuidGenerator::class,
```

See [Installation Guide](installation.md#custom-identifier-strategy) for implementation details.

### Registries

```php
'registries' => [
    'channel' => \RobinsonRyan\HeyYou\Registries\ConfigChannelRegistry::class,
    // ...
],
```

Replace with custom implementations for database-backed registries:

```php
'registries' => [
    'channel' => \App\Registries\DatabaseChannelRegistry::class,
],
```

See [Registries Guide](registries.md) for implementation details.

### Verification Settings

```php
'verification' => [
    'log_history' => true,
    'default_expiration_days' => null,
],
```

| Option | Type | Description |
|--------|------|-------------|
| `log_history` | bool | Whether to log verification events to the `verification_events` table |
| `default_expiration_days` | int\|null | Default expiration for verifications (null = never expires) |

### Event Dispatcher

```php
'event_dispatcher' => \RobinsonRyan\HeyYou\Events\LaravelEventDispatcher::class,
```

Replace with custom implementation for async dispatching or testing:

```php
'event_dispatcher' => \App\Events\QueuedEventDispatcher::class,
```

### Channels

```php
'channels' => [
    'email' => ['name' => 'Email', 'category' => 'electronic'],
    // ...
],
```

Add custom channels:

```php
'channels' => [
    // ... default channels ...
    'slack' => ['name' => 'Slack', 'category' => 'messaging'],
    'teams' => ['name' => 'Microsoft Teams', 'category' => 'messaging'],
],
```

**Categories:**
- `electronic` - Traditional electronic channels (email, phone, SMS)
- `messaging` - Messaging apps (WhatsApp, Signal, Slack)
- `social` - Social media platforms

### Purposes

```php
'purposes' => [
    'general' => ['name' => 'General', 'parent' => null],
    'billing' => ['name' => 'Billing', 'parent' => null],
    'accounts_payable' => ['name' => 'Accounts Payable', 'parent' => 'billing'],
    // ...
],
```

Purposes support hierarchy via the `parent` key. Child purposes inherit from their parents during resolution (e.g., `accounts_payable` matches requests for `billing`).

Add custom purposes:

```php
'purposes' => [
    // ... default purposes ...
    'legal' => ['name' => 'Legal', 'parent' => null],
    'compliance' => ['name' => 'Compliance', 'parent' => 'legal'],
    'contracts' => ['name' => 'Contracts', 'parent' => 'legal'],
],
```

### Roles

```php
'roles' => [
    'accounts_payable_contact' => ['name' => 'Accounts Payable Contact'],
    // ...
],
```

Add custom roles:

```php
'roles' => [
    // ... default roles ...
    'legal_contact' => ['name' => 'Legal Contact'],
    'it_contact' => ['name' => 'IT Contact'],
    'emergency_contact' => ['name' => 'Emergency Contact'],
],
```

### Relationship Types

```php
'relationship_types' => [
    'employment' => ['name' => 'Employment', 'from' => 'person', 'to' => 'organization'],
    // ...
],
```

The `from` and `to` values are hints for validation/UI - they're not enforced by the package.

Add custom relationship types:

```php
'relationship_types' => [
    // ... default types ...
    'franchise_of' => ['name' => 'Franchise Of', 'from' => 'organization', 'to' => 'organization'],
    'vendor_for' => ['name' => 'Vendor For', 'from' => 'organization', 'to' => 'organization'],
],
```

### Consent Categories

```php
'consent_categories' => [
    'transactional' => ['name' => 'Transactional'],
    'marketing' => ['name' => 'Marketing'],
    // ...
],
```

Add custom consent categories:

```php
'consent_categories' => [
    // ... default categories ...
    'surveys' => ['name' => 'Surveys'],
    'newsletters' => ['name' => 'Newsletters'],
    'product_updates' => ['name' => 'Product Updates'],
],
```

## Environment Variables

While the configuration file doesn't use environment variables by default, you can add them:

```php
// config/heyyou.php

'table_prefix' => env('HEYYOU_TABLE_PREFIX', 'heyyou_'),

'verification' => [
    'log_history' => env('HEYYOU_LOG_VERIFICATION', true),
    'default_expiration_days' => env('HEYYOU_VERIFICATION_EXPIRY_DAYS'),
],
```

## Per-Environment Configuration

Use Laravel's config merging in environment-specific files:

```php
// config/heyyou.php (default)
'verification' => [
    'log_history' => true,
],

// In testing environment, override via service provider or test setup
config(['heyyou.verification.log_history' => false]);
```

## Accessing Configuration

```php
// Get a value
$prefix = config('heyyou.table_prefix');

// Get nested value
$logHistory = config('heyyou.verification.log_history');

// Get with default
$expiry = config('heyyou.verification.default_expiration_days', 365);

// Get all channels
$channels = config('heyyou.channels');
```
