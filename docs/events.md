# Events Reference

HeyYou dispatches domain events for model lifecycle changes and key operations.

## Event Dispatcher

All events are dispatched through the `EventDispatcher` contract, which defaults to Laravel's event system:

```php
use RobinsonRyan\HeyYou\Contracts\EventDispatcher;

// Default implementation
app(EventDispatcher::class)->dispatch($event);

// Same as:
event($event);
```

## Available Events

### Party Events

```php
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
```

**PartyCreated**

Dispatched when a new Party is created (typically via Contactable trait).

```php
class PartyCreated
{
    public Party $party;
    public Model $partyable;  // The consumer model (User, Company, etc.)
}
```

**PartyUpdated**

Dispatched when a Party is modified.

```php
class PartyUpdated
{
    public Party $party;
    public Model $partyable;
    public array $changedAttributes;
}
```

**PartyDeleted**

Dispatched when a Party is soft-deleted.

```php
class PartyDeleted
{
    public Party $party;
    public Model $partyable;
}
```

### Contact Point Events

```php
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
```

**ContactPointCreated**

Dispatched when a new contact point is added.

```php
class ContactPointCreated
{
    public ContactPoint $contactPoint;
    public Party $party;
}
```

**ContactPointUpdated**

Dispatched when a contact point is modified.

```php
class ContactPointUpdated
{
    public ContactPoint $contactPoint;
    public Party $party;
    public array $changedAttributes;
}
```

**ContactPointVerified**

Dispatched when a contact point is verified.

```php
class ContactPointVerified
{
    public ContactPoint $contactPoint;
    public string $method;      // verification method used
    public Carbon $verifiedAt;
}
```

**ContactPointDeleted**

Dispatched when a contact point is soft-deleted.

```php
class ContactPointDeleted
{
    public ContactPoint $contactPoint;
    public Party $party;
}
```

### Consent Events

```php
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
```

**ConsentGranted**

Dispatched when consent is granted (status = opted_in).

```php
class ConsentGranted
{
    public PartyConsent|ContactPointConsent $consent;
    public string $level;           // 'party' or 'contact_point'
    public string $purposeCategory;
    public ?string $channel;
}
```

**ConsentRevoked**

Dispatched when consent is revoked (status = opted_out).

```php
class ConsentRevoked
{
    public PartyConsent|ContactPointConsent $consent;
    public string $level;
    public string $purposeCategory;
    public ?string $channel;
}
```

### DNC Events

```php
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;
```

**DncRuleCreated**

Dispatched when a new DNC rule is created.

```php
class DncRuleCreated
{
    public DoNotContact $dncRule;
    public Party $party;
    public string $scope;  // 'party', 'channel', 'purpose', etc.
}
```

**DncRuleRemoved**

Dispatched when a DNC rule is removed (soft-deleted).

```php
class DncRuleRemoved
{
    public DoNotContact $dncRule;
    public Party $party;
    public string $scope;
}
```

### Resolver Events

```php
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
```

**ContactResolved**

Dispatched after every contact resolution.

```php
class ContactResolved
{
    public ResolverRequest $request;
    public ResolverResult $result;
}
```

## Listening to Events

### In EventServiceProvider

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ContactPointCreated::class => [
            \App\Listeners\SendVerificationEmail::class,
            \App\Listeners\NotifyAdminOfNewContact::class,
        ],
        ContactPointVerified::class => [
            \App\Listeners\SyncToCrm::class,
        ],
        ConsentGranted::class => [
            \App\Listeners\AddToMarketingList::class,
        ],
        ContactResolved::class => [
            \App\Listeners\LogContactResolution::class,
        ],
    ];
}
```

### With Event Subscribers

```php
<?php

namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;

class ContactPointEventSubscriber
{
    public function handleCreated(ContactPointCreated $event): void
    {
        // Send verification email for email channels
        if ($event->contactPoint->channel === 'email') {
            // dispatch verification job
        }
    }

    public function handleVerified(ContactPointVerified $event): void
    {
        // Sync to CRM
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            ContactPointCreated::class,
            [self::class, 'handleCreated']
        );

        $events->listen(
            ContactPointVerified::class,
            [self::class, 'handleVerified']
        );
    }
}
```

Register in EventServiceProvider:

```php
protected $subscribe = [
    \App\Listeners\ContactPointEventSubscriber::class,
];
```

### With Closures

```php
use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;

Event::listen(PartyCreated::class, function (PartyCreated $event) {
    logger()->info('New party created', [
        'party_id' => $event->party->id,
        'partyable' => get_class($event->partyable),
    ]);
});
```

## Practical Examples

### Send Verification Email on Contact Creation

```php
<?php

namespace App\Listeners;

use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use App\Mail\VerifyContactPoint;
use Illuminate\Support\Facades\Mail;

class SendVerificationEmail
{
    public function handle(ContactPointCreated $event): void
    {
        if ($event->contactPoint->channel !== 'email') {
            return;
        }

        if ($event->contactPoint->is_verified) {
            return; // Already verified (e.g., imported)
        }

        Mail::to($event->contactPoint->value_normalized)
            ->send(new VerifyContactPoint($event->contactPoint));
    }
}
```

### Audit Contact Resolutions

```php
<?php

namespace App\Listeners;

use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
use App\Models\ContactResolutionLog;

class LogContactResolution
{
    public function handle(ContactResolved $event): void
    {
        ContactResolutionLog::create([
            'target_party_id' => $event->request->targetParty->id,
            'purpose' => $event->request->purpose,
            'channel' => $event->request->channel,
            'matches_count' => $event->result->matches->count(),
            'best_contact_point_id' => $event->result->best()?->contactPoint->id,
            'candidates_considered' => $event->result->explanation->candidatesConsidered,
            'exclusions' => $event->result->explanation->exclusionSummary,
            'fallback_used' => $event->result->explanation->fallbackUsed,
        ]);
    }
}
```

### Sync Consent to External System

```php
<?php

namespace App\Listeners;

use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use App\Services\MarketingPlatform;

class SyncConsentToMarketing
{
    public function __construct(private MarketingPlatform $marketing)
    {
    }

    public function handleGranted(ConsentGranted $event): void
    {
        if ($event->purposeCategory !== 'marketing') {
            return;
        }

        if ($event->level === 'contact_point') {
            $this->marketing->subscribe(
                $event->consent->contactPoint->value_normalized
            );
        }
    }

    public function handleRevoked(ConsentRevoked $event): void
    {
        if ($event->purposeCategory !== 'marketing') {
            return;
        }

        if ($event->level === 'contact_point') {
            $this->marketing->unsubscribe(
                $event->consent->contactPoint->value_normalized
            );
        }
    }
}
```

## Custom Event Dispatcher

To customize event dispatching (e.g., for testing or async processing):

```php
<?php

namespace App\Events;

use RobinsonRyan\HeyYou\Contracts\EventDispatcher;

class QueuedEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        // Dispatch to queue instead of synchronously
        dispatch(new ProcessHeyYouEvent($event));
    }
}
```

Register in config:

```php
// config/heyyou.php
'event_dispatcher' => \App\Events\QueuedEventDispatcher::class,
```

## Testing with Events

### Fake Events in Tests

```php
use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;

public function test_creates_contact_point(): void
{
    Event::fake([ContactPointCreated::class]);

    $party = Party::factory()->create();
    $party->contactPoints()->create([
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    Event::assertDispatched(ContactPointCreated::class, function ($event) {
        return $event->contactPoint->value_normalized === 'test@example.com';
    });
}
```

### Assert Event Properties

```php
Event::assertDispatched(ContactPointCreated::class, function ($event) use ($party) {
    return $event->party->id === $party->id
        && $event->contactPoint->channel === 'email';
});
```
