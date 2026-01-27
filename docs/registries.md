# Custom Registries Guide

HeyYou uses a contract-based registry system for all classification values. This allows you to replace the default config-based registries with database-backed or custom implementations.

## Registry Contracts

All registries follow the same pattern:

```php
interface ChannelRegistry
{
    public function exists(string $slug): bool;
    public function get(string $slug): RegistryItem;
    public function all(): Collection;
    public function forCategory(string $category): Collection;
}
```

### Available Registries

| Registry | Purpose | Default Implementation |
|----------|---------|----------------------|
| `ChannelRegistry` | Communication channels (email, phone, sms) | `ConfigChannelRegistry` |
| `PurposeRegistry` | Contact purposes (billing, shipping, hr) | `ConfigPurposeRegistry` |
| `RoleRegistry` | Roles (accounts_payable_contact, hr_contact) | `ConfigRoleRegistry` |
| `RelationshipTypeRegistry` | Relationship types (employment, location_of) | `ConfigRelationshipTypeRegistry` |
| `ConsentCategoryRegistry` | Consent categories (marketing, transactional) | `ConfigConsentCategoryRegistry` |
| `NormalizerRegistry` | Channel normalizers | `DefaultNormalizerRegistry` |

### RegistryItem Contract

All registry items implement:

```php
interface RegistryItem
{
    public function slug(): string;
    public function name(): string;
    public function metadata(): array;
}
```

## Default Config-Based Registries

By default, registries read from `config/heyyou.php`:

```php
// config/heyyou.php

'channels' => [
    'email' => ['name' => 'Email', 'category' => 'electronic'],
    'phone' => ['name' => 'Phone', 'category' => 'electronic'],
    'sms' => ['name' => 'SMS', 'category' => 'electronic'],
    // Add custom channels here
],

'purposes' => [
    'general' => ['name' => 'General', 'parent' => null],
    'billing' => ['name' => 'Billing', 'parent' => null],
    'accounts_payable' => ['name' => 'Accounts Payable', 'parent' => 'billing'],
    // Add custom purposes here
],

'roles' => [
    'accounts_payable_contact' => ['name' => 'Accounts Payable Contact'],
    'hr_contact' => ['name' => 'HR Contact'],
    // Add custom roles here
],
```

## Adding Custom Values

### Simple: Add to Config

The easiest way to add custom values:

```php
// config/heyyou.php

'channels' => [
    // ... default channels ...
    'slack' => ['name' => 'Slack', 'category' => 'messaging'],
    'discord' => ['name' => 'Discord', 'category' => 'messaging'],
],

'purposes' => [
    // ... default purposes ...
    'legal' => ['name' => 'Legal', 'parent' => null],
    'compliance' => ['name' => 'Compliance', 'parent' => 'legal'],
],

'roles' => [
    // ... default roles ...
    'legal_contact' => ['name' => 'Legal Contact'],
    'compliance_officer' => ['name' => 'Compliance Officer'],
],
```

## Creating Custom Registries

### Database-Backed Registry

```php
<?php

namespace App\Registries;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use App\Models\Channel;

class DatabaseChannelRegistry implements ChannelRegistry
{
    public function exists(string $slug): bool
    {
        return Channel::where('slug', $slug)->exists();
    }

    public function get(string $slug): RegistryItem
    {
        $channel = Channel::where('slug', $slug)->firstOrFail();
        return new ChannelRegistryItem($channel);
    }

    public function all(): Collection
    {
        return Channel::all()->map(fn ($c) => new ChannelRegistryItem($c));
    }

    public function forCategory(string $category): Collection
    {
        return Channel::where('category', $category)
            ->get()
            ->map(fn ($c) => new ChannelRegistryItem($c));
    }
}

class ChannelRegistryItem implements RegistryItem
{
    public function __construct(private Channel $channel)
    {
    }

    public function slug(): string
    {
        return $this->channel->slug;
    }

    public function name(): string
    {
        return $this->channel->name;
    }

    public function metadata(): array
    {
        return [
            'category' => $this->channel->category,
            'icon' => $this->channel->icon,
            'color' => $this->channel->color,
        ];
    }
}
```

### Register Custom Implementation

```php
// config/heyyou.php

'registries' => [
    'channel' => \App\Registries\DatabaseChannelRegistry::class,
    // ... other registries ...
],
```

Or bind in a service provider:

```php
// app/Providers/AppServiceProvider.php

use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use App\Registries\DatabaseChannelRegistry;

public function register(): void
{
    $this->app->bind(ChannelRegistry::class, DatabaseChannelRegistry::class);
}
```

## Purpose Registry with Hierarchy

The `PurposeRegistry` supports parent-child relationships:

```php
interface PurposeRegistry extends BaseRegistry
{
    public function parent(string $slug): ?RegistryItem;
    public function children(string $slug): Collection;
}
```

### Example: Custom Purpose Registry

```php
<?php

namespace App\Registries;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use App\Models\Purpose;

class DatabasePurposeRegistry implements PurposeRegistry
{
    public function exists(string $slug): bool
    {
        return Purpose::where('slug', $slug)->exists();
    }

    public function get(string $slug): RegistryItem
    {
        return new PurposeRegistryItem(
            Purpose::where('slug', $slug)->firstOrFail()
        );
    }

    public function all(): Collection
    {
        return Purpose::all()->map(fn ($p) => new PurposeRegistryItem($p));
    }

    public function forCategory(string $category): Collection
    {
        return Purpose::where('category', $category)
            ->get()
            ->map(fn ($p) => new PurposeRegistryItem($p));
    }

    public function parent(string $slug): ?RegistryItem
    {
        $purpose = Purpose::where('slug', $slug)->first();
        if (!$purpose?->parent_id) {
            return null;
        }
        return new PurposeRegistryItem($purpose->parent);
    }

    public function children(string $slug): Collection
    {
        $purpose = Purpose::where('slug', $slug)->first();
        if (!$purpose) {
            return collect();
        }
        return $purpose->children->map(fn ($p) => new PurposeRegistryItem($p));
    }
}
```

## Normalizer Registry

The `NormalizerRegistry` maps channels to normalizers:

```php
interface NormalizerRegistry
{
    public function for(string $channel): ChannelNormalizer;
    public function register(string $channel, ChannelNormalizer $normalizer): void;
}
```

### Registering Custom Normalizers

```php
// In a service provider

use RobinsonRyan\HeyYou\Contracts\Registries\NormalizerRegistry;

public function boot(): void
{
    $registry = app(NormalizerRegistry::class);

    // Register normalizer for a new channel
    $registry->register('slack', new SlackNormalizer());

    // Override existing normalizer
    $registry->register('phone', new CustomPhoneNormalizer());
}
```

### Creating a Custom Normalizer

```php
<?php

namespace App\Normalizers;

use RobinsonRyan\HeyYou\Contracts\ChannelNormalizer;

class SlackNormalizer implements ChannelNormalizer
{
    public function normalize(string $raw): string
    {
        // Slack member IDs are already normalized
        // Just trim whitespace
        return trim($raw);
    }

    public function validate(string $raw): bool
    {
        // Slack member IDs start with U or W and are 11 characters
        return preg_match('/^[UW][A-Z0-9]{10}$/', trim($raw)) === 1;
    }

    public function formatForDisplay(string $normalized): string
    {
        return '@' . $normalized;
    }
}
```

## Using Registries in Your Code

### Validating Values

```php
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;

$registry = app(ChannelRegistry::class);

// Check if channel exists
if (!$registry->exists($userInput)) {
    throw new \InvalidArgumentException("Unknown channel: {$userInput}");
}

// Get channel details
$channel = $registry->get('email');
echo $channel->name();        // "Email"
echo $channel->metadata()['category']; // "electronic"
```

### Listing Available Options

```php
use RobinsonRyan\HeyYou\Contracts\Registries\PurposeRegistry;

$registry = app(PurposeRegistry::class);

// Get all purposes for a dropdown
$purposes = $registry->all()->mapWithKeys(fn ($p) => [
    $p->slug() => $p->name()
]);

// Get purposes with hierarchy
$tree = [];
foreach ($registry->all() as $purpose) {
    $parent = $registry->parent($purpose->slug());
    if (!$parent) {
        $tree[$purpose->slug()] = [
            'name' => $purpose->name(),
            'children' => $registry->children($purpose->slug())
                ->mapWithKeys(fn ($c) => [$c->slug() => $c->name()])
                ->toArray(),
        ];
    }
}
```

### In Form Validation

```php
use Illuminate\Validation\Rule;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;

$request->validate([
    'channel' => [
        'required',
        'string',
        function ($attribute, $value, $fail) {
            if (!app(ChannelRegistry::class)->exists($value)) {
                $fail("The selected channel is invalid.");
            }
        },
    ],
]);
```

## Caching Registries

For database-backed registries, consider caching:

```php
<?php

namespace App\Registries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;

class CachedDatabaseChannelRegistry implements ChannelRegistry
{
    private const CACHE_TTL = 3600; // 1 hour

    public function all(): Collection
    {
        return Cache::remember('heyyou.channels.all', self::CACHE_TTL, function () {
            return Channel::all()->map(fn ($c) => new ChannelRegistryItem($c));
        });
    }

    public function get(string $slug): RegistryItem
    {
        return Cache::remember("heyyou.channels.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return new ChannelRegistryItem(
                Channel::where('slug', $slug)->firstOrFail()
            );
        });
    }

    public function exists(string $slug): bool
    {
        return $this->all()->contains(fn ($item) => $item->slug() === $slug);
    }

    public function forCategory(string $category): Collection
    {
        return $this->all()->filter(
            fn ($item) => ($item->metadata()['category'] ?? null) === $category
        );
    }

    public function clearCache(): void
    {
        Cache::forget('heyyou.channels.all');
        // Clear individual channel caches as needed
    }
}
```

## Testing with Registries

### Mock Registries in Tests

```php
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

public function test_handles_custom_channel(): void
{
    $mockRegistry = Mockery::mock(ChannelRegistry::class);
    $mockRegistry->shouldReceive('exists')
        ->with('custom')
        ->andReturn(true);
    $mockRegistry->shouldReceive('get')
        ->with('custom')
        ->andReturn(new GenericRegistryItem('custom', 'Custom Channel', []));

    $this->app->instance(ChannelRegistry::class, $mockRegistry);

    // Test code that uses the registry
}
```

### In-Memory Registry for Tests

```php
<?php

namespace Tests\Support;

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Contracts\Registries\ChannelRegistry;
use RobinsonRyan\HeyYou\Contracts\Registries\RegistryItem;
use RobinsonRyan\HeyYou\Support\GenericRegistryItem;

class InMemoryChannelRegistry implements ChannelRegistry
{
    private array $items = [];

    public function add(string $slug, string $name, array $metadata = []): self
    {
        $this->items[$slug] = new GenericRegistryItem($slug, $name, $metadata);
        return $this;
    }

    public function exists(string $slug): bool
    {
        return isset($this->items[$slug]);
    }

    public function get(string $slug): RegistryItem
    {
        if (!$this->exists($slug)) {
            throw new \RuntimeException("Channel not found: {$slug}");
        }
        return $this->items[$slug];
    }

    public function all(): Collection
    {
        return collect($this->items);
    }

    public function forCategory(string $category): Collection
    {
        return $this->all()->filter(
            fn ($item) => ($item->metadata()['category'] ?? null) === $category
        );
    }
}
```
