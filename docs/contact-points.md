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

HeyYou records verification outcomes; it never performs verification. Sending the
code, the link or the carrier lookup is your application's job — the package is
told what happened.

Use the intent methods. Each one moves the model, dispatches its event, and
writes the history row as a single act, so the three can never disagree:

```php
// Your app has just sent a magic link. Optional — records a pending attempt.
$contactPoint->startVerification('link');

// The recipient clicked it.
$contactPoint->markVerified('link');

// ...or with an explicit expiry.
$contactPoint->markVerified('link', now()->addYear());

// The code came back wrong, the carrier said the number is dead, etc.
// Does not change is_verified — a failure is not an un-verification.
$contactPoint->markVerificationFailed('code', 'Recipient entered the wrong code');

// A verification has aged out. Clears the flag and its timestamps.
// A no-op if the contact point holds no verification.
$contactPoint->markVerificationExpired();

// Check if currently verified (respects expiration)
if ($contactPoint->isCurrentlyVerified()) {
    // Contact point is verified and not expired
}
```

Methods: `code`, `link`, `imported`, `carrier_check`, `manual`, `oauth`.

`markVerified()` completes an open pending attempt **started under the same
method**, rather than opening a second row. A verification under a different
method gets its own row and leaves the pending one open — otherwise the history
would claim an attempt succeeded that never did.

Assigning the attributes directly still works and still dispatches
`ContactPointVerified`:

```php
$contactPoint->update(['is_verified' => true, 'verified_at' => now()]);
```

...but prefer the intent methods. The raw form cannot express failure or expiry
at all — those are not attribute changes, so no model hook can observe them —
and it is how a verification ends up in the history with no event, or an event
with no history.

> **Quiet and bulk writes bypass this entirely.** `saveQuietly()`,
> `updateQuietly()`, and query-builder updates like
> `ContactPoint::query()->update(['is_verified' => true])` fire no model events,
> so they produce no dispatch *and* no history row. Both halves go missing
> together, which keeps the record consistent — but a point verified that way has
> no audit trail at all.

### Verification history

Every path above writes a `VerificationEvent` row when
`config('heyyou.verification.log_history')` is `true` (the default). Set it to
`false` and the events still dispatch — signalling is never gated by the history
switch — but nothing is recorded.

```php
$contactPoint->verificationEvents; // pending / verified / failed / expired, over time
```

Do not write these rows by hand. A row created directly records history that no
listener ever heard about, which is the exact inconsistency the intent methods
exist to prevent.

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
