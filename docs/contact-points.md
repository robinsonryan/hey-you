# Contact Points Guide

Contact points represent ways to reach a party: email addresses, phone numbers, social media handles, etc.

## Creating Contact Points

### Basic Creation

```php
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'John.Doe@Example.com',
    'label' => 'Work Email',
    'is_primary' => true,
]);
```

### Available Channels

Default channels (configurable via registries):

| Channel | Description | Normalization |
|---------|-------------|---------------|
| `email` | Email address | Lowercase, trimmed |
| `phone` | Voice phone | E.164 format |
| `sms` | SMS-capable number | E.164 format |
| `whatsapp` | WhatsApp | E.164 format |
| `signal` | Signal | E.164 format |
| `facebook` | Facebook | As-is |
| `instagram` | Instagram | As-is |
| `linkedin` | LinkedIn | As-is |
| `twitter` | Twitter/X | As-is |
| `tiktok` | TikTok | As-is |

### Contact Point Status

```php
use RobinsonRyan\HeyYou\Models\ContactPoint;

$contactPoint->status = ContactPoint::STATUS_ACTIVE;      // Working, usable
$contactPoint->status = ContactPoint::STATUS_INACTIVE;    // Not currently in use
$contactPoint->status = ContactPoint::STATUS_BOUNCED;     // Email bounced
$contactPoint->status = ContactPoint::STATUS_UNREACHABLE; // Cannot be reached
$contactPoint->status = ContactPoint::STATUS_BLOCKED;     // Administratively blocked
```

## Automatic Normalization

Contact values are automatically normalized when saved:

```php
// Email normalization (lowercase, trim)
$cp = $party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => '  John.Doe@EXAMPLE.COM  ',
]);
echo $cp->value_normalized; // "john.doe@example.com"

// Phone normalization (E.164)
$cp = $party->contactPoints()->create([
    'channel' => 'phone',
    'value_raw' => '(555) 123-4567',
]);
echo $cp->value_normalized; // "+15551234567"
```

### Custom Normalizers

Create custom normalizers for specific channels:

```php
<?php

namespace App\Normalizers;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

class InstagramNormalizer implements ChannelNormalizer
{
    public function normalize(string $raw): string
    {
        // Remove @ prefix if present, lowercase
        $value = ltrim(strtolower(trim($raw)), '@');
        return $value;
    }

    public function validate(string $raw): bool
    {
        // Instagram usernames: 1-30 chars, letters, numbers, periods, underscores
        return preg_match('/^@?[a-zA-Z0-9._]{1,30}$/', $raw) === 1;
    }

    public function formatForDisplay(string $normalized): string
    {
        return '@' . $normalized;
    }
}
```

Register in a service provider:

```php
use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;

app(NormalizerRegistry::class)->register('instagram', new InstagramNormalizer());
```

## Verification

Track whether contact points have been verified:

```php
// Mark as verified
$contactPoint->update([
    'is_verified' => true,
    'verified_at' => now(),
    'verification_method' => 'email_link', // code, link, imported, carrier_check, manual, oauth
]);

// Check if currently verified (respects expiration)
if ($contactPoint->isCurrentlyVerified()) {
    // Contact point is verified and not expired
}

// Set verification to expire
$contactPoint->update([
    'is_verified' => true,
    'verified_at' => now(),
    'verification_expires_at' => now()->addYear(),
]);
```

### Verification Events (Optional History)

If `config('heyyou.verification.log_history')` is `true`, verification attempts are logged:

```php
use RobinsonRyan\HeyYou\Models\VerificationEvent;

VerificationEvent::create([
    'contact_point_id' => $contactPoint->id,
    'status' => VerificationEvent::STATUS_PENDING,
    'method' => 'email_link',
    'initiated_at' => now(),
]);

// Later, when verified:
$event->update([
    'status' => VerificationEvent::STATUS_VERIFIED,
    'completed_at' => now(),
]);
```

## Purposes

Tag contact points with purposes to indicate what they're used for:

```php
use RobinsonRyan\HeyYou\Contracts\ContactPointPurposeManager;

$manager = app(ContactPointPurposeManager::class);

// Attach a purpose
$manager->attach($contactPoint, 'accounts_payable', [
    'priority' => 1,
    'is_preferred' => true,
]);

// Get all purposes for a contact point
$purposes = $manager->purposes($contactPoint);

// Detach a purpose
$manager->detach($contactPoint, 'accounts_payable');

// Find contact points for a purpose
$contacts = $manager->forPurpose('accounts_payable')
    ->where('party_id', $party->id)
    ->get();
```

### Available Purposes

Default purposes (configurable):

- `general` - General contact
- `billing` - Billing inquiries
- `accounts_payable` - AP contact (parent: billing)
- `accounts_receivable` - AR contact (parent: billing)
- `shipping` - Shipping inquiries
- `receiving` - Receiving (parent: shipping)
- `hr` - Human resources
- `sales` - Sales inquiries
- `support` - Customer support
- `executive` - Executive contact

## Querying Contact Points

### Via the Party

```php
// Get all contact points
$party->contactPoints;

// Filter by channel
$party->contactPoints()->where('channel', 'email')->get();

// Get primary for a channel
$party->contactPoints()
    ->where('channel', 'email')
    ->where('is_primary', true)
    ->first();

// Get verified only
$party->contactPoints()
    ->where('is_verified', true)
    ->where(function ($q) {
        $q->whereNull('verification_expires_at')
          ->orWhere('verification_expires_at', '>', now());
    })
    ->get();
```

### Via the Consumer Model

```php
// Direct access through trait
$user->contactPoints;
$user->contactPoints()->where('channel', 'phone')->get();

// With eager loading
User::with('contactPoints')->get();
```

### Global Lookups

```php
use RobinsonRyan\HeyYou\Models\ContactPoint;

// Find by normalized value (for deduplication/matching)
$existing = ContactPoint::where('channel', 'email')
    ->where('value_normalized', 'jane.doe@example.com')
    ->first();

// Find all active emails
$emails = ContactPoint::where('channel', 'email')
    ->where('status', ContactPoint::STATUS_ACTIVE)
    ->get();
```

## Deduplication

Contact points have a unique constraint on `(party_id, channel, value_normalized)`. This prevents the same contact value from being added twice to the same party for the same channel:

```php
// First creation succeeds
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'test@example.com',
]);

// Second creation fails (duplicate)
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'TEST@EXAMPLE.COM', // Normalizes to same value
]); // Throws QueryException
```

## Metadata

Store additional channel-specific data:

```php
// Phone with extension
$party->contactPoints()->create([
    'channel' => 'phone',
    'value_raw' => '555-123-4567',
    'metadata' => [
        'extension' => '1234',
        'hours' => '9am-5pm EST',
    ],
]);

// Access metadata
$extension = $contactPoint->metadata['extension'] ?? null;
```

## Events

Contact point lifecycle events are dispatched:

```php
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;

// In EventServiceProvider
protected $listen = [
    ContactPointCreated::class => [
        SendVerificationEmail::class,
    ],
    ContactPointVerified::class => [
        UpdateCrmRecord::class,
    ],
];
```
