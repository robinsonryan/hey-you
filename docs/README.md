# HeyYou Documentation

HeyYou is a Laravel package for modeling contactable entities, contact methods, and deterministic contact resolution.

## Quick Links

### Getting Started

- [Quickstart Guide](quickstart.md) - Get up and running in 10 minutes
- [Installation](installation.md) - Detailed installation and configuration

### Core Guides

- [Contact Points](contact-points.md) - Managing email, phone, and social contact methods
- [Contact Resolution](resolver.md) - Finding the best contact for a purpose
- [Policies](policies.md) - Consent management and Do Not Contact rules
- [Relationships & Roles](relationships.md) - Party relationships and role assignments
- [Addresses](addresses.md) - Physical address management

### Reference

- [Events](events.md) - Domain events for integration
- [Custom Registries](registries.md) - Extending with custom registries
- [Configuration](configuration.md) - Full configuration reference
- [Specification](spec.md) - Complete package specification

## Overview

### What HeyYou Does

- Models **people, organizations, and locations** as contactable entities (Parties)
- Manages **multiple contact methods** per entity (email, phone, SMS, social media)
- Supports **contextual contact routing** ("contact the AP department at Facility 2 via email")
- Enforces **policies** (consent, verification, do-not-contact rules)
- Provides a **deterministic Resolver API** that returns ranked contact options

### What HeyYou Doesn't Do

- Not a CRM (no pipelines, opportunities)
- Not a messaging provider (provides "where/how to contact," not sending)
- Not identity/auth (integrates with your User system but doesn't require it)
- Not multi-tenant aware (your app handles tenancy; access flows through your models)

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────────────────┐
│                        Your Application                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │   User   │  │ Company  │  │ Location │  │   ...    │        │
│  │  Model   │  │  Model   │  │  Model   │  │  Models  │        │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘        │
│       │             │             │             │               │
│       └─────────────┼─────────────┼─────────────┘               │
│                     │  Contactable Trait                        │
└─────────────────────┼───────────────────────────────────────────┘
                      │
┌─────────────────────┼───────────────────────────────────────────┐
│                     ▼          HeyYou Package                    │
│  ┌──────────────────────────────────────────────────────┐       │
│  │                    Party (Identity Map)               │       │
│  └────────────┬───────────────┬──────────────────┬──────┘       │
│               │               │                  │               │
│  ┌────────────▼───┐  ┌────────▼────────┐  ┌─────▼─────────┐    │
│  │ Contact Points │  │  Relationships  │  │ Role Assign.  │    │
│  │ (email, phone) │  │ (employment...)  │  │ (AP contact)  │    │
│  └───────┬────────┘  └─────────────────┘  └───────────────┘    │
│          │                                                       │
│  ┌───────▼────────────────────────────────────────────────┐     │
│  │              Policy Layer (Consent, DNC)                │     │
│  └───────┬────────────────────────────────────────────────┘     │
│          │                                                       │
│  ┌───────▼────────────────────────────────────────────────┐     │
│  │           Contact Resolver (find best contact)          │     │
│  └─────────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
```

## Basic Usage

```php
// 1. Add Contactable trait to your models
class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}

// 2. Create contact points
$user->party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'user@example.com',
    'is_primary' => true,
]);

// 3. Resolve contacts
$result = app(ContactResolver::class)->resolve(new ResolverRequest(
    targetParty: $company->party,
    purpose: 'accounts_payable',
    channel: 'email',
));

$best = $result->best();
// Send to: $best->normalizedValue
```

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Support

- [GitHub Issues](https://github.com/robinsonryan/hey-you/issues)
- [Full Specification](spec.md)
