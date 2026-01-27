# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x

## Install via Composer

```bash
composer require robinsonryan/hey-you
```

## Publish Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=heyyou-config
```

This creates `config/heyyou.php` where you can configure:

- Table prefix (default: `heyyou_`)
- Registry implementations
- Default channels, purposes, and roles
- Verification settings

## Run Migrations

```bash
php artisan migrate
```

This creates 10 tables:

1. `heyyou_parties` - Identity map for contactable entities
2. `heyyou_party_relationships` - Links between parties
3. `heyyou_contact_points` - Email, phone, social handles
4. `heyyou_contact_point_purposes` - Purpose tags on contact points
5. `heyyou_addresses` - Physical addresses
6. `heyyou_role_assignments` - Role assignments within scopes
7. `heyyou_party_consents` - Party-level consent records
8. `heyyou_contact_point_consents` - Contact-point-level consent
9. `heyyou_do_not_contacts` - DNC blocking rules
10. `heyyou_verification_events` - Verification history (optional)

## Publish Migrations (Optional)

If you need to customize the migrations (e.g., change column types or add indexes):

```bash
php artisan vendor:publish --tag=heyyou-migrations
```

Published migrations take precedence over package migrations.

## Configure Your Models

Add the `Contactable` trait to models that should have contact information:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

class User extends Model
{
    use Contactable;

    /**
     * Return the display name for this party.
     * This is cached in the parties table for quick lookups.
     */
    public function getDisplayNameForParty(): string
    {
        return $this->full_name ?? $this->name ?? $this->email;
    }
}
```

## Table Prefix

By default, all tables use the `heyyou_` prefix. To change or disable:

```php
// config/heyyou.php

return [
    'table_prefix' => 'contacts_',  // Custom prefix
    // or
    'table_prefix' => null,         // No prefix
    // or
    'table_prefix' => '',           // No prefix
];
```

**Note:** If you change the prefix after running migrations, you'll need to rename existing tables manually.

## Custom Identifier Strategy

By default, HeyYou uses auto-incrementing integers for primary keys. To use UUIDs or other strategies:

```php
// config/heyyou.php

return [
    'identifier_generator' => \App\Support\UuidGenerator::class,
];
```

Your generator must implement `RobinsonRyan\HeyYou\Contracts\IdentifierGenerator`:

```php
<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use RobinsonRyan\HeyYou\Contracts\IdentifierGenerator;

class UuidGenerator implements IdentifierGenerator
{
    public function generate(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    public function columnDefinition(Blueprint $table, string $column): void
    {
        $table->uuid($column)->primary();
    }
}
```

## Verifying Installation

After installation, verify everything is working:

```php
use RobinsonRyan\HeyYou\Models\Party;

// Should not throw any errors
Party::query()->count();
```

Or run the test suite if you've cloned the package:

```bash
composer test
```
