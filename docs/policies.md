# Policies Guide: Consent and Do Not Contact

HeyYou provides a layered policy system for managing consent and blocking rules.

## Consent System

Consent exists at two levels:
1. **Party-level** - Applies to all contact points for a party
2. **Contact-point-level** - Applies to a specific contact point (overrides party-level)

### Consent Status

```php
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;

// Status values
PartyConsent::STATUS_OPTED_IN;   // 'opted_in'
PartyConsent::STATUS_OPTED_OUT;  // 'opted_out'
```

### Party-Level Consent

```php
use RobinsonRyan\HeyYou\Models\PartyConsent;

// Grant marketing consent for all channels
PartyConsent::create([
    'party_id' => $party->id,
    'channel' => null,                    // All channels
    'purpose_category' => 'marketing',
    'status' => PartyConsent::STATUS_OPTED_IN,
    'captured_at' => now(),
    'source' => 'web_form',
    'evidence' => [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'form_version' => 'v2.1',
    ],
]);

// Opt out of SMS marketing specifically
PartyConsent::create([
    'party_id' => $party->id,
    'channel' => 'sms',                   // SMS only
    'purpose_category' => 'marketing',
    'status' => PartyConsent::STATUS_OPTED_OUT,
    'captured_at' => now(),
    'source' => 'user_request',
]);
```

### Contact-Point-Level Consent

```php
use RobinsonRyan\HeyYou\Models\ContactPointConsent;

// This email has opted in to marketing
ContactPointConsent::create([
    'contact_point_id' => $contactPoint->id,
    'purpose_category' => 'marketing',
    'status' => ContactPointConsent::STATUS_OPTED_IN,
    'captured_at' => now(),
    'source' => 'double_opt_in',
    'evidence' => [
        'confirmation_token' => $token,
        'confirmed_at' => now()->toIso8601String(),
    ],
]);
```

### Consent Precedence Rules

1. **Contact-point-level overrides party-level** (more specific wins)
2. **Channel-specific overrides generic** (sms consent overrides all-channel consent)
3. **Most recent wins** when timestamps differ

Example:
```php
// Party opted IN to all marketing
PartyConsent::create(['channel' => null, 'purpose_category' => 'marketing', 'status' => 'opted_in']);

// Party opted OUT of SMS marketing
PartyConsent::create(['channel' => 'sms', 'purpose_category' => 'marketing', 'status' => 'opted_out']);

// But this specific phone opted back IN
ContactPointConsent::create(['purpose_category' => 'marketing', 'status' => 'opted_in']);

// Result for that phone: OPTED IN (contact-point-level wins)
```

### Checking Consent

```php
use RobinsonRyan\HeyYou\Contracts\ConsentChecker;

$checker = app(ConsentChecker::class);

$result = $checker->hasConsent($contactPoint, 'marketing');

$result->allowed;      // bool - is contact allowed?
$result->level;        // 'contact_point', 'party', or 'none'
$result->status;       // 'opted_in', 'opted_out', or null
$result->capturedAt;   // Carbon or null
```

### Consent Categories

Default categories (configurable via registries):

- `transactional` - Order confirmations, shipping updates
- `marketing` - Promotional materials
- `support` - Customer service communications
- `collections` - Payment/collection notices

## Do Not Contact (DNC)

DNC rules block contact attempts at various scopes.

### DNC Scope Interpretation

| party_id | contact_point_id | channel | purpose | Meaning |
|----------|------------------|---------|---------|---------|
| ✓ | null | null | null | Block ALL contact to this party |
| ✓ | null | ✓ | null | Block this channel for this party |
| ✓ | null | null | ✓ | Block this purpose for this party |
| ✓ | null | ✓ | ✓ | Block this channel for this purpose |
| ✓ | ✓ | null | null | Block this specific contact point |

### Creating DNC Rules

```php
use RobinsonRyan\HeyYou\Models\DoNotContact;

// Block all marketing to this party
DoNotContact::create([
    'party_id' => $party->id,
    'purpose' => 'marketing',
    'reason' => 'Customer requested no marketing',
    'source' => 'user_request',
    'effective_at' => now(),
]);

// Block all SMS to this party
DoNotContact::create([
    'party_id' => $party->id,
    'channel' => 'sms',
    'reason' => 'Regulatory compliance - wireless consent not obtained',
    'source' => 'compliance',
    'effective_at' => now(),
]);

// Block a specific phone number
DoNotContact::create([
    'party_id' => $party->id,
    'contact_point_id' => $phoneContactPoint->id,
    'reason' => 'Number disconnected',
    'source' => 'system',
    'effective_at' => now(),
]);

// Temporary block (expires)
DoNotContact::create([
    'party_id' => $party->id,
    'channel' => 'phone',
    'reason' => 'Customer requested 30-day break',
    'source' => 'user_request',
    'effective_at' => now(),
    'expires_at' => now()->addDays(30),
]);

// Full party-wide DNC (legal hold)
DoNotContact::create([
    'party_id' => $party->id,
    'reason' => 'Pending litigation - all contact suspended',
    'source' => 'legal',
    'effective_at' => now(),
    'created_by_type' => User::class,
    'created_by_id' => auth()->id(),
]);
```

### Checking DNC Status

```php
use RobinsonRyan\HeyYou\Contracts\DncChecker;

$checker = app(DncChecker::class);

// Check if blocked for any purpose
$result = $checker->isBlocked($contactPoint);

// Check if blocked for a specific purpose
$result = $checker->isBlocked($contactPoint, 'marketing');

$result->blocked;   // bool - is contact blocked?
$result->scope;     // 'party', 'channel', 'purpose', 'contact_point', or null
$result->reason;    // The reason string from the DNC rule
$result->rule;      // The DoNotContact model (if blocked)
```

### DNC Scope Determination

The `DoNotContact` model can determine its own scope:

```php
$dnc = DoNotContact::find(1);
$scope = $dnc->determineScope();
// Returns: 'party', 'channel', 'purpose', 'channel_purpose', or 'contact_point'
```

### Querying Active DNC Rules

```php
// Get all active DNC rules for a party
$activeRules = DoNotContact::where('party_id', $party->id)
    ->active()  // Uses effective_at/expires_at
    ->get();

// Check for any active party-wide block
$isFullyBlocked = DoNotContact::where('party_id', $party->id)
    ->whereNull('contact_point_id')
    ->whereNull('channel')
    ->whereNull('purpose')
    ->active()
    ->exists();
```

## Integration with Resolver

The resolver automatically applies both consent and DNC policies:

```php
$result = $resolver->resolve(new ResolverRequest(
    targetParty: $party,
    purpose: 'marketing',
    channel: 'email',
    constraints: new ResolverConstraints(
        requireConsent: true,
        consentCategory: 'marketing',
    ),
));

// Excluded contacts are tracked in explanation
$result->explanation->exclusionSummary;
// ['dnc' => 2, 'no_consent' => 3, 'status' => 1]
```

## Events

Policy changes dispatch events:

```php
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;

// In EventServiceProvider
protected $listen = [
    ConsentGranted::class => [
        SyncToMarketingPlatform::class,
    ],
    ConsentRevoked::class => [
        RemoveFromMarketingLists::class,
    ],
    DncRuleCreated::class => [
        UpdateSuppressionList::class,
    ],
];
```

## Best Practices

### 1. Always Store Evidence

```php
PartyConsent::create([
    // ...
    'evidence' => [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'form_id' => 'signup-v3',
        'checkbox_text' => 'I agree to receive marketing emails',
    ],
]);
```

### 2. Use Meaningful Sources

Common sources:
- `web_form` - Website form submission
- `double_opt_in` - Email confirmation link clicked
- `verbal` - Phone consent recorded
- `import` - Migrated from another system
- `api` - API integration
- `user_request` - Customer explicitly requested
- `compliance` - Regulatory requirement
- `legal` - Legal department directive
- `system` - Automated system action

### 3. Record Who Created DNC Rules

```php
DoNotContact::create([
    'party_id' => $party->id,
    // ...
    'created_by_type' => User::class,
    'created_by_id' => auth()->id(),
]);
```

### 4. Use Expiring DNC for Temporary Blocks

```php
DoNotContact::create([
    'party_id' => $party->id,
    'channel' => 'phone',
    'reason' => 'Customer traveling, prefers no calls until return',
    'source' => 'user_request',
    'effective_at' => now(),
    'expires_at' => now()->addWeeks(2),
]);
```

### 5. Check Policies Before Sending

```php
$dncChecker = app(DncChecker::class);
$consentChecker = app(ConsentChecker::class);

foreach ($recipients as $contactPoint) {
    $dncResult = $dncChecker->isBlocked($contactPoint, 'marketing');
    if ($dncResult->blocked) {
        logger()->info("Skipping {$contactPoint->id}: DNC - {$dncResult->reason}");
        continue;
    }

    $consentResult = $consentChecker->hasConsent($contactPoint, 'marketing');
    if (!$consentResult->allowed) {
        logger()->info("Skipping {$contactPoint->id}: No consent");
        continue;
    }

    // Safe to send
    sendEmail($contactPoint);
}
```
