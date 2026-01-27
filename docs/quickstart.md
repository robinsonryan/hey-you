# HeyYou Quickstart Guide

Get up and running with HeyYou in 10 minutes.

## Installation

```bash
composer require robinsonryan/hey-you
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=heyyou-config
```

Run the migrations:

```bash
php artisan migrate
```

## Basic Setup

### 1. Add the Contactable Trait

Add the `Contactable` trait to any model that should have contact information:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Traits\Contactable;

class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}

class Company extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->legal_name;
    }
}
```

When you create a User or Company, a `Party` record is automatically created:

```php
$user = User::create(['name' => 'Jane Doe']);
$user->party; // Party instance is auto-created
```

### 2. Add Contact Points

Add contact information to any party:

```php
// Add an email
$user->party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'Jane.Doe@Example.COM',  // Auto-normalized to: jane.doe@example.com
    'label' => 'Work Email',
    'is_primary' => true,
]);

// Add a phone number
$user->party->contactPoints()->create([
    'channel' => 'phone',
    'value_raw' => '(555) 123-4567',  // Auto-normalized to E.164: +15551234567
    'label' => 'Mobile',
]);
```

### 3. Link People to Organizations

Create employment relationships:

```php
use RobinsonRyan\HeyYou\Models\PartyRelationship;

$employee = User::find(1);
$company = Company::find(1);

PartyRelationship::create([
    'from_party_id' => $employee->party->id,
    'to_party_id' => $company->party->id,
    'relationship_type' => 'employment',
    'valid_from' => now(),
]);
```

### 4. Assign Roles

Make someone the accounts payable contact:

```php
use RobinsonRyan\HeyYou\Models\RoleAssignment;

RoleAssignment::create([
    'party_id' => $employee->party->id,
    'scope_party_id' => $company->party->id,
    'role' => 'accounts_payable_contact',
    'priority' => 1,
]);
```

### 5. Resolve Contacts

Find the best contact for a specific purpose:

```php
use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;

$resolver = app(ContactResolver::class);

$result = $resolver->resolve(new ResolverRequest(
    targetParty: $company->party,
    purpose: 'accounts_payable',
    channel: 'email',
    constraints: new ResolverConstraints(
        requireVerified: false,
    ),
    limit: 5,
));

if ($result->isEmpty()) {
    // No contacts found
    echo "No AP contact available";
} else {
    $best = $result->best();
    echo "Send to: " . $best->normalizedValue;
    echo "Owner: " . $best->owningParty->display_name_cached;
}
```

## What's Next?

- [Contact Points Guide](contact-points.md) - Managing contact information
- [Contact Resolution Guide](resolver.md) - Finding the right contact
- [Policies Guide](policies.md) - Consent and DNC rules
- [Events Reference](events.md) - Listening to domain events
- [Configuration Reference](configuration.md) - Full configuration options
