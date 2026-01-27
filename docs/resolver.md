# Contact Resolution Guide

The Contact Resolver is the core API for finding the best contact point to reach someone for a specific purpose and channel.

## Basic Usage

```php
use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;

$resolver = app(ContactResolver::class);

$result = $resolver->resolve(new ResolverRequest(
    targetParty: $company->party,
    purpose: 'accounts_payable',
    channel: 'email',
));

if (!$result->isEmpty()) {
    $best = $result->best();
    mail($best->normalizedValue, 'Invoice', $body);
}
```

## Resolver Request

### Required Parameters

```php
new ResolverRequest(
    targetParty: $party,      // The party you want to contact
    purpose: 'billing',       // The purpose/context of contact
    channel: 'email',         // The communication channel
);
```

### Optional Parameters

```php
new ResolverRequest(
    targetParty: $party,
    purpose: 'billing',
    channel: 'email',
    scopeParty: $location->party,  // Start resolution from this scope
    constraints: new ResolverConstraints(...),
    limit: 10,                      // Maximum matches to return
);
```

## Resolver Constraints

Control filtering behavior:

```php
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;

$constraints = new ResolverConstraints(
    requireVerified: true,          // Only return verified contacts
    requireConsent: true,           // Only return contacts with consent
    consentCategory: 'marketing',   // Required when requireConsent = true
    allowFallback: true,            // Allow climbing scope hierarchy
    excludeContactPointIds: [1, 2], // Exclude specific contact points
);
```

## Resolver Result

### Checking Results

```php
$result = $resolver->resolve($request);

// Check if any contacts found
if ($result->isEmpty()) {
    // No contacts available
}

// Get the best match
$best = $result->best(); // ResolverMatch or null

// Get all matches
foreach ($result->matches as $match) {
    echo $match->normalizedValue;
}
```

### Resolver Match Properties

```php
$match = $result->best();

$match->contactPoint;      // ContactPoint model
$match->owningParty;       // Party that owns the contact point
$match->channel;           // Channel string
$match->normalizedValue;   // Normalized contact value
$match->matchedPurpose;    // Purpose that matched (exact, parent, or null)
$match->matchedRole;       // Role if found via role assignment
$match->scopeParty;        // Scope this match came from
$match->flags;             // Array: ['verified', 'consent_ok', 'is_primary']
$match->rank;              // Numeric rank (lower = better)
```

### Explanation

Every result includes an explanation of how resolution proceeded:

```php
$explanation = $result->explanation;

$explanation->candidatesConsidered;  // Total candidates evaluated
$explanation->exclusionSummary;      // ['dnc' => 2, 'no_consent' => 1]
$explanation->fallbackUsed;          // Whether scope hierarchy was climbed
$explanation->fallbackPath;          // 'location:45 → org:12'
```

## Resolution Algorithm

The resolver follows a deterministic, rule-based ranking:

### 1. Exclusions (filtered out)

- DNC rule matches
- Missing consent (when required)
- Status is `blocked`
- Verification required but not verified
- Contact point in excludeContactPointIds

### 2. Candidate Gathering

For organization/location targets:

1. **Role Holders** - People with matching roles in the scope
2. **Scope Contact Points** - Contact points owned directly by scope (e.g., AP inbox)
3. **Generic Contacts** - Primary contact point for that channel
4. **Fallback** - Climb to parent scope (location → organization → parent org)

### 3. Ranking (fixed priority order)

| Priority | Factor | Winner |
|----------|--------|--------|
| 1 | Status | active > inactive > bounced > unreachable |
| 2 | Verification | verified > unverified |
| 3 | Purpose match | exact > parent > none |
| 4 | Scope specificity | direct match > parent scope |
| 5 | Primary flag | is_primary=true > is_primary=false |
| 6 | Purpose priority | lower priority value wins |
| 7 | Role priority | lower priority value wins |
| 8 | Created date | older (established) > newer |

## Practical Examples

### Finding AP Contact for a Company

```php
$result = $resolver->resolve(new ResolverRequest(
    targetParty: $company->party,
    purpose: 'accounts_payable',
    channel: 'email',
));
```

Resolution order:
1. Find people with `accounts_payable_contact` role at company
2. Get their email contact points tagged for `accounts_payable`
3. Check for shared AP inbox owned by company
4. Fall back to company's primary email contact

### Finding Contact at a Location

```php
$result = $resolver->resolve(new ResolverRequest(
    targetParty: $location->party,
    purpose: 'receiving',
    channel: 'phone',
    constraints: new ResolverConstraints(allowFallback: true),
));
```

Resolution order:
1. Find `receiving_manager` role holders at location
2. Check location's own phone contacts
3. Fall back to parent company's receiving contacts
4. Fall back to company's primary phone

### Verified-Only for Marketing

```php
$result = $resolver->resolve(new ResolverRequest(
    targetParty: $customer->party,
    purpose: 'marketing',
    channel: 'email',
    constraints: new ResolverConstraints(
        requireVerified: true,
        requireConsent: true,
        consentCategory: 'marketing',
    ),
));
```

This will only return contacts that:
- Are verified (and not expired)
- Have marketing consent
- Are not blocked by DNC rules

### Skip Previously Tried Contacts

```php
$alreadyTried = [123, 456]; // Contact point IDs that bounced

$result = $resolver->resolve(new ResolverRequest(
    targetParty: $party,
    purpose: 'support',
    channel: 'email',
    constraints: new ResolverConstraints(
        excludeContactPointIds: $alreadyTried,
    ),
));
```

## Scope Hierarchy

The resolver traverses the scope hierarchy when `allowFallback` is true (default).

### Hierarchy Traversal

```
Location → Organization → Parent Organization → ...
```

Relationships used for hierarchy:
- `location_of` - Location belongs to organization
- `member_of` - Organization is member of another
- `parent_of` - Parent/subsidiary relationship

### Limiting Fallback

```php
// Disable fallback (only look at target party)
$constraints = new ResolverConstraints(allowFallback: false);

// Or specify a starting scope
$result = $resolver->resolve(new ResolverRequest(
    targetParty: $company->party,
    scopeParty: $location->party,  // Start here, can fall back to company
    purpose: 'receiving',
    channel: 'phone',
));
```

## Handling Empty Results

```php
$result = $resolver->resolve($request);

if ($result->isEmpty()) {
    // Log why no contacts were found
    $explanation = $result->explanation;

    logger()->warning('No contact found', [
        'target' => $request->targetParty->id,
        'purpose' => $request->purpose,
        'channel' => $request->channel,
        'candidates_considered' => $explanation->candidatesConsidered,
        'exclusions' => $explanation->exclusionSummary,
    ]);

    // Maybe try a different channel?
    $result = $resolver->resolve(new ResolverRequest(
        targetParty: $request->targetParty,
        purpose: $request->purpose,
        channel: 'phone', // Try phone instead
    ));
}
```

## Events

The resolver dispatches an event after resolution:

```php
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;

// In EventServiceProvider
protected $listen = [
    ContactResolved::class => [
        LogContactResolution::class,
        UpdateAnalytics::class,
    ],
];
```

The event contains the full request and result for auditing.
