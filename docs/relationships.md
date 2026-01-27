# Party Relationships and Roles Guide

HeyYou models relationships between parties (people, organizations, locations) and role assignments within scopes.

## Party Relationships

Party relationships link two parties together with a typed relationship.

### Creating Relationships

```php
use RobinsonRyan\HeyYou\Models\PartyRelationship;

// Employment: Person works for Company
PartyRelationship::create([
    'from_party_id' => $employee->party->id,
    'to_party_id' => $company->party->id,
    'relationship_type' => 'employment',
    'label' => 'Senior Developer',
    'valid_from' => now(),
    'metadata' => [
        'department' => 'Engineering',
        'employee_id' => 'EMP-001',
    ],
]);

// Location: Warehouse belongs to Company
PartyRelationship::create([
    'from_party_id' => $warehouse->party->id,
    'to_party_id' => $company->party->id,
    'relationship_type' => 'location_of',
    'label' => 'Main Distribution Center',
    'valid_from' => now(),
]);

// Parent/Subsidiary: HQ owns Subsidiary
PartyRelationship::create([
    'from_party_id' => $headquarters->party->id,
    'to_party_id' => $subsidiary->party->id,
    'relationship_type' => 'parent_of',
    'valid_from' => now(),
]);
```

### Default Relationship Types

| Type | Direction | Description |
|------|-----------|-------------|
| `employment` | person → organization | Person is employed by organization |
| `contractor` | person → organization | Person is contractor for organization |
| `location_of` | location → organization | Location belongs to organization |
| `member_of` | organization → organization | Org is member of another org |
| `parent_of` | organization → organization | Parent company / subsidiary |
| `managed_by` | location → person | Location is managed by person |

### Querying Relationships

```php
// From a party's perspective
$party->outgoingRelationships; // Relationships where this party is 'from'
$party->incomingRelationships; // Relationships where this party is 'to'

// Find all employees of a company
$employees = PartyRelationship::where('to_party_id', $company->party->id)
    ->where('relationship_type', 'employment')
    ->current() // Only currently valid
    ->with('fromParty')
    ->get()
    ->pluck('fromParty');

// Find employer of a person
$employer = PartyRelationship::where('from_party_id', $person->party->id)
    ->where('relationship_type', 'employment')
    ->current()
    ->with('toParty')
    ->first()
    ?->toParty;

// Find all locations of a company
$locations = PartyRelationship::where('to_party_id', $company->party->id)
    ->where('relationship_type', 'location_of')
    ->current()
    ->with('fromParty')
    ->get()
    ->pluck('fromParty');
```

### Effective Dating

Relationships support effective dating with `valid_from` and `valid_to`:

```php
// Create a relationship starting in the future
PartyRelationship::create([
    'from_party_id' => $newEmployee->party->id,
    'to_party_id' => $company->party->id,
    'relationship_type' => 'employment',
    'valid_from' => now()->addDays(14), // Starts in 2 weeks
]);

// End a relationship
$relationship->update([
    'valid_to' => now(),
]);

// Query current relationships only
PartyRelationship::current()->get();

// Query relationships valid at a specific date
PartyRelationship::where('valid_from', '<=', $date)
    ->where(function ($q) use ($date) {
        $q->whereNull('valid_to')
          ->orWhere('valid_to', '>=', $date);
    })
    ->get();
```

### The `current()` Scope

```php
// Only returns relationships where:
// - valid_from is null OR valid_from <= now()
// - AND valid_to is null OR valid_to >= now()

PartyRelationship::current()->get();
```

## Role Assignments

Role assignments link a party (usually a person) to a role within a scope (usually an organization or location).

### Creating Role Assignments

```php
use RobinsonRyan\HeyYou\Models\RoleAssignment;

// Make someone the AP contact for a company
RoleAssignment::create([
    'party_id' => $person->party->id,
    'scope_party_id' => $company->party->id,
    'role' => 'accounts_payable_contact',
    'priority' => 1, // Primary AP contact
    'metadata' => [
        'hours' => '9am-5pm EST',
        'backup_for' => $otherPerson->party->id,
    ],
]);

// Make someone the receiving manager at a specific location
RoleAssignment::create([
    'party_id' => $manager->party->id,
    'scope_party_id' => $warehouse->party->id,
    'role' => 'receiving_manager',
    'priority' => 1,
]);

// Secondary/backup role holder
RoleAssignment::create([
    'party_id' => $backup->party->id,
    'scope_party_id' => $company->party->id,
    'role' => 'accounts_payable_contact',
    'priority' => 2, // Lower priority = backup
]);
```

### Default Roles

| Role | Description |
|------|-------------|
| `accounts_payable_contact` | Handles AP inquiries |
| `accounts_receivable_contact` | Handles AR inquiries |
| `hr_contact` | Human resources contact |
| `receiving_manager` | Manages receiving operations |
| `shipping_contact` | Handles shipping inquiries |
| `sales_contact` | Sales representative |
| `support_contact` | Customer support |
| `executive_contact` | Executive/leadership contact |
| `primary_contact` | General primary contact |

### Querying Role Assignments

```php
// Find all role holders for a scope
$apContacts = RoleAssignment::where('scope_party_id', $company->party->id)
    ->where('role', 'accounts_payable_contact')
    ->current()
    ->orderBy('priority')
    ->with('party.contactPoints')
    ->get();

// Find all roles a person holds
$roles = RoleAssignment::where('party_id', $person->party->id)
    ->current()
    ->with('scopeParty')
    ->get();

// Check if someone has a specific role
$hasApRole = RoleAssignment::where('party_id', $person->party->id)
    ->where('scope_party_id', $company->party->id)
    ->where('role', 'accounts_payable_contact')
    ->current()
    ->exists();
```

### Priority Ordering

Lower priority values are preferred. Use priorities to establish primary/backup contacts:

```php
// Priority 1: Primary contact
// Priority 2: First backup
// Priority 3: Second backup, etc.

$apContacts = RoleAssignment::where('scope_party_id', $company->party->id)
    ->where('role', 'accounts_payable_contact')
    ->current()
    ->orderBy('priority')
    ->get();

$primary = $apContacts->first(); // Priority 1
$backup = $apContacts->skip(1)->first(); // Priority 2
```

### Effective Dating

Role assignments also support effective dating:

```php
// Assign role starting next month
RoleAssignment::create([
    'party_id' => $newContact->party->id,
    'scope_party_id' => $company->party->id,
    'role' => 'accounts_payable_contact',
    'priority' => 1,
    'valid_from' => now()->startOfMonth()->addMonth(),
]);

// End current assignment
$currentAssignment->update([
    'valid_to' => now()->endOfMonth(),
]);

// Query current assignments
RoleAssignment::current()->get();
```

## Relationships in Model Access

### From Party Model

```php
$party = $user->party;

// Relationships where this party is the source
$party->outgoingRelationships;

// Relationships where this party is the target
$party->incomingRelationships;

// Role assignments where this party holds the role
$party->roleAssignments;

// Role assignments scoped to this party (as organization)
$party->scopedRoleAssignments;
```

### Traversing Relationships

```php
// Get all employees of a company with their contact points
$company = Company::find(1);
$employees = $company->party->incomingRelationships()
    ->where('relationship_type', 'employment')
    ->current()
    ->with('fromParty.contactPoints')
    ->get()
    ->map(function ($rel) {
        return [
            'party' => $rel->fromParty,
            'contacts' => $rel->fromParty->contactPoints,
        ];
    });

// Get the organization for a location
$location = Location::find(1);
$organization = $location->party->outgoingRelationships()
    ->where('relationship_type', 'location_of')
    ->current()
    ->first()
    ?->toParty;
```

## Integration with Resolver

The resolver uses relationships and role assignments to find contacts:

1. **Scope Hierarchy**: Uses `location_of`, `member_of`, `parent_of` relationships to climb from specific scope to general
2. **Role Holders**: Finds people with matching roles in the target scope
3. **Priority**: Uses role assignment priority to rank contacts

Example resolution flow for "AP contact at Location X":
1. Find `accounts_payable_contact` role holders scoped to Location X
2. If none, follow `location_of` to find parent organization
3. Find `accounts_payable_contact` role holders scoped to that organization
4. Rank by role priority, then by contact point attributes

## Using Factories

```php
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;

// Create parties
$person = Party::factory()->person()->create();
$company = Party::factory()->organization()->create();
$location = Party::factory()->location()->create();

// Create role assignment
RoleAssignment::factory()
    ->forParty($person)
    ->scopedTo($company)
    ->accountsPayable()
    ->create();

// Or with specific attributes
RoleAssignment::factory()->create([
    'party_id' => $person->id,
    'scope_party_id' => $location->id,
    'role' => 'receiving_manager',
    'priority' => 1,
]);
```

## Common Patterns

### Find All Contacts for a Company

```php
// Get everyone who works for the company
$employees = PartyRelationship::where('to_party_id', $company->party->id)
    ->where('relationship_type', 'employment')
    ->current()
    ->with('fromParty.contactPoints')
    ->get()
    ->flatMap(fn ($rel) => $rel->fromParty->contactPoints);

// Get company's own contact points
$companyContacts = $company->party->contactPoints;

// All contacts
$allContacts = $employees->merge($companyContacts);
```

### Find Primary Contact for a Role

```php
function getPrimaryContact(Party $scope, string $role, string $channel): ?ContactPoint
{
    $assignment = RoleAssignment::where('scope_party_id', $scope->id)
        ->where('role', $role)
        ->current()
        ->orderBy('priority')
        ->with('party.contactPoints')
        ->first();

    if (!$assignment) {
        return null;
    }

    return $assignment->party->contactPoints
        ->where('channel', $channel)
        ->where('status', 'active')
        ->sortByDesc('is_primary')
        ->first();
}

$apEmail = getPrimaryContact($company->party, 'accounts_payable_contact', 'email');
```

### Build Organization Chart

```php
function getSubsidiaries(Party $parent): Collection
{
    return PartyRelationship::where('from_party_id', $parent->id)
        ->where('relationship_type', 'parent_of')
        ->current()
        ->with('toParty')
        ->get()
        ->pluck('toParty');
}

function getOrgChart(Party $headquarters): array
{
    $subsidiaries = getSubsidiaries($headquarters);

    return [
        'party' => $headquarters,
        'subsidiaries' => $subsidiaries->map(fn ($sub) => getOrgChart($sub)),
    ];
}
```
