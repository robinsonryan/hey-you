# Addresses Guide

HeyYou supports physical addresses attached to parties for billing, shipping, and other purposes.

## Creating Addresses

```php
use RobinsonRyan\HeyYou\Models\Address;

$party->addresses()->create([
    'purpose' => 'billing',
    'is_primary' => true,
    'label' => 'Headquarters',
    'line1' => '123 Main Street',
    'line2' => 'Suite 400',
    'city' => 'New York',
    'region' => 'NY',
    'postal_code' => '10001',
    'country_code' => 'US',
    'timezone' => 'America/New_York',
]);
```

## Address Fields

| Field | Type | Description |
|-------|------|-------------|
| `purpose` | string | Purpose slug (billing, shipping, etc.) |
| `is_primary` | bool | Primary address for this purpose |
| `label` | string? | Human-readable label |
| `line1` | string | Street address line 1 |
| `line2` | string? | Street address line 2 |
| `city` | string | City/locality |
| `region` | string? | State/province/region |
| `postal_code` | string? | ZIP/postal code |
| `country_code` | string | ISO 3166-1 alpha-2 country code |
| `geocode` | json? | `{lat: float, lng: float}` |
| `timezone` | string? | IANA timezone |
| `validation_status` | string | unverified, verified, invalid |
| `formatted_cached` | string? | Pre-formatted display string |
| `valid_from` | timestamp? | Effective start date |
| `valid_to` | timestamp? | Effective end date |
| `metadata` | json? | Additional data |

## Address Purposes

Default purposes (configurable via registry):

- `billing` - Billing/invoice address
- `shipping` - Shipping destination
- `general` - General mailing address

Add custom purposes in config:

```php
// config/heyyou.php
'purposes' => [
    // ... existing purposes ...
    'warehouse' => ['name' => 'Warehouse', 'parent' => 'shipping'],
    'legal' => ['name' => 'Legal/Registered', 'parent' => null],
],
```

## Validation Status

```php
use RobinsonRyan\HeyYou\Models\Address;

Address::VALIDATION_UNVERIFIED; // 'unverified' - Not validated
Address::VALIDATION_VERIFIED;   // 'verified' - Confirmed valid
Address::VALIDATION_INVALID;    // 'invalid' - Known invalid
```

## Querying Addresses

### Via Party

```php
// All addresses for a party
$party->addresses;

// Primary billing address
$party->addresses()
    ->where('purpose', 'billing')
    ->where('is_primary', true)
    ->first();

// All shipping addresses
$party->addresses()
    ->where('purpose', 'shipping')
    ->get();

// Currently valid addresses only
$party->addresses()->current()->get();
```

### Via Consumer Model

```php
// Direct access through Contactable trait
$user->addresses;
$company->addresses()->where('purpose', 'billing')->get();

// With eager loading
Company::with('addresses')->get();
```

## Effective Dating

Addresses support `valid_from` and `valid_to` for temporal validity:

```php
// Create address effective in the future (e.g., moving)
$party->addresses()->create([
    'purpose' => 'billing',
    'line1' => '456 New Street',
    'city' => 'Boston',
    'region' => 'MA',
    'postal_code' => '02101',
    'country_code' => 'US',
    'valid_from' => now()->addMonth(), // Effective next month
]);

// End current address validity
$currentAddress->update([
    'valid_to' => now()->addMonth()->subDay(),
]);

// Query current addresses
$party->addresses()->current()->get();
```

### The `current()` Scope

```php
// Only returns addresses where:
// - valid_from is null OR valid_from <= now()
// - AND valid_to is null OR valid_to >= now()

Address::current()->get();
```

## Geocoding

Store latitude/longitude in the `geocode` field:

```php
$address = $party->addresses()->create([
    'purpose' => 'shipping',
    'line1' => '1600 Pennsylvania Avenue NW',
    'city' => 'Washington',
    'region' => 'DC',
    'postal_code' => '20500',
    'country_code' => 'US',
    'geocode' => [
        'lat' => 38.8977,
        'lng' => -77.0365,
    ],
]);

// Access coordinates
$lat = $address->geocode['lat'];
$lng = $address->geocode['lng'];
```

## Timezone

Store IANA timezone for location-aware operations:

```php
$address = $party->addresses()->create([
    // ...
    'timezone' => 'America/Los_Angeles',
]);

// Use with Carbon
$localTime = now()->setTimezone($address->timezone);
```

## Formatted Display

Cache a formatted address string for display:

```php
$address = $party->addresses()->create([
    'line1' => '123 Main St',
    'line2' => 'Suite 100',
    'city' => 'San Francisco',
    'region' => 'CA',
    'postal_code' => '94102',
    'country_code' => 'US',
    'formatted_cached' => "123 Main St\nSuite 100\nSan Francisco, CA 94102\nUnited States",
]);

echo $address->formatted_cached;
```

Update the cached format when address changes:

```php
// In your application
$address->formatted_cached = formatAddress($address);
$address->save();
```

## Metadata

Store additional address-related data:

```php
$address = $party->addresses()->create([
    // ...
    'metadata' => [
        'loading_dock' => true,
        'delivery_instructions' => 'Use rear entrance',
        'contact_phone' => '+1-555-123-4567',
        'hours' => '8am-5pm Mon-Fri',
    ],
]);

// Access metadata
$hasDock = $address->metadata['loading_dock'] ?? false;
```

## Using Factories

```php
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\Party;

// Create address for existing party
$party = Party::factory()->organization()->create();
$address = Address::factory()
    ->forParty($party)
    ->billing()
    ->create();

// Create address with specific purpose
$shippingAddress = Address::factory()
    ->forParty($party)
    ->shipping()
    ->primary()
    ->create();

// Create with custom attributes
Address::factory()->create([
    'party_id' => $party->id,
    'purpose' => 'warehouse',
    'city' => 'Chicago',
    'region' => 'IL',
    'country_code' => 'US',
]);
```

## Common Patterns

### Get or Create Primary Address

```php
function getPrimaryAddress(Party $party, string $purpose): Address
{
    return $party->addresses()
        ->where('purpose', $purpose)
        ->where('is_primary', true)
        ->current()
        ->firstOr(function () use ($party, $purpose) {
            // Return first available for this purpose
            return $party->addresses()
                ->where('purpose', $purpose)
                ->current()
                ->first();
        });
}
```

### Address Validation Integration

```php
// Example with a hypothetical address validation service
use App\Services\AddressValidator;

class ValidateAddressListener
{
    public function __construct(private AddressValidator $validator)
    {
    }

    public function handle(AddressCreated $event): void
    {
        $result = $this->validator->validate([
            'line1' => $event->address->line1,
            'city' => $event->address->city,
            'region' => $event->address->region,
            'postal_code' => $event->address->postal_code,
            'country' => $event->address->country_code,
        ]);

        $event->address->update([
            'validation_status' => $result->valid
                ? Address::VALIDATION_VERIFIED
                : Address::VALIDATION_INVALID,
            'geocode' => $result->coordinates,
            'formatted_cached' => $result->formattedAddress,
        ]);
    }
}
```

### Find Nearest Location

```php
function findNearestAddress(float $lat, float $lng, string $purpose = null): ?Address
{
    $query = Address::whereNotNull('geocode')
        ->where('validation_status', Address::VALIDATION_VERIFIED)
        ->current();

    if ($purpose) {
        $query->where('purpose', $purpose);
    }

    // Simple distance calculation (for small areas)
    // For production, use proper geospatial queries
    return $query->get()
        ->sortBy(function ($address) use ($lat, $lng) {
            $aLat = $address->geocode['lat'];
            $aLng = $address->geocode['lng'];
            return sqrt(pow($aLat - $lat, 2) + pow($aLng - $lng, 2));
        })
        ->first();
}
```

### Copy Address Between Parties

```php
function copyAddress(Address $source, Party $targetParty): Address
{
    return $targetParty->addresses()->create([
        'purpose' => $source->purpose,
        'label' => $source->label,
        'line1' => $source->line1,
        'line2' => $source->line2,
        'city' => $source->city,
        'region' => $source->region,
        'postal_code' => $source->postal_code,
        'country_code' => $source->country_code,
        'geocode' => $source->geocode,
        'timezone' => $source->timezone,
        'validation_status' => $source->validation_status,
        'formatted_cached' => $source->formatted_cached,
    ]);
}
```

## Country Codes

Use ISO 3166-1 alpha-2 codes:

| Code | Country |
|------|---------|
| US | United States |
| CA | Canada |
| GB | United Kingdom |
| DE | Germany |
| FR | France |
| AU | Australia |
| JP | Japan |
| ... | ... |

Reference: [ISO 3166-1 alpha-2](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2)
