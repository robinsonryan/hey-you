<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

/**
 * These tests demonstrate that HeyYou works correctly in multi-tenant scenarios.
 *
 * Note: HeyYou itself is NOT tenant-aware. Multi-tenancy is handled by the host
 * application. These tests demonstrate that contact data properly flows through
 * consumer models, ensuring tenant isolation is maintained at the application layer.
 */
describe('Multi-Tenant Scenarios', function () {
    it('isolates contact data between separate organizations', function () {
        // Simulate two tenants as separate companies
        $tenantA = Company::create(['legal_name' => 'Tenant A Corp']);
        $tenantB = Company::create(['legal_name' => 'Tenant B Inc']);

        // Create employees for each tenant
        $employeeA1 = User::create(['name' => 'Alice from A', 'email' => 'alice@tenanta.com']);
        $employeeA2 = User::create(['name' => 'Bob from A', 'email' => 'bob@tenanta.com']);
        $employeeB1 = User::create(['name' => 'Charlie from B', 'email' => 'charlie@tenantb.com']);

        // Establish employment relationships
        PartyRelationship::create([
            'from_party_id' => $employeeA1->party->id,
            'to_party_id' => $tenantA->party->id,
            'relationship_type' => 'employment',
        ]);

        PartyRelationship::create([
            'from_party_id' => $employeeA2->party->id,
            'to_party_id' => $tenantA->party->id,
            'relationship_type' => 'employment',
        ]);

        PartyRelationship::create([
            'from_party_id' => $employeeB1->party->id,
            'to_party_id' => $tenantB->party->id,
            'relationship_type' => 'employment',
        ]);

        // Create contact points for employees
        $emailA1 = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($employeeA1->party)
            ->create(['value_raw' => 'alice@tenanta.com']);

        $emailA2 = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($employeeA2->party)
            ->create(['value_raw' => 'bob@tenanta.com']);

        $emailB1 = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($employeeB1->party)
            ->create(['value_raw' => 'charlie@tenantb.com']);

        // Assign AP roles scoped to their respective organizations
        RoleAssignment::create([
            'party_id' => $employeeA1->party->id,
            'scope_party_id' => $tenantA->party->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        RoleAssignment::create([
            'party_id' => $employeeB1->party->id,
            'scope_party_id' => $tenantB->party->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        $resolver = app(ContactResolver::class);

        // Resolve for Tenant A - should only find Alice
        $resultA = $resolver->resolve(new ResolverRequest(
            targetParty: $tenantA->party,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $tenantA->party,
            limit: 10,
        ));

        expect($resultA->matches)->toHaveCount(1);
        expect($resultA->best()->contactPoint->value_normalized)->toBe('alice@tenanta.com');

        // Resolve for Tenant B - should only find Charlie
        $resultB = $resolver->resolve(new ResolverRequest(
            targetParty: $tenantB->party,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $tenantB->party,
            limit: 10,
        ));

        expect($resultB->matches)->toHaveCount(1);
        expect($resultB->best()->contactPoint->value_normalized)->toBe('charlie@tenantb.com');
    });

    it('allows an employee to have roles in multiple organizations', function () {
        // Create two separate organizations
        $companyA = Company::create(['legal_name' => 'Company A']);
        $companyB = Company::create(['legal_name' => 'Company B']);

        // Create a consultant who works for both
        $consultant = User::create(['name' => 'Diana Consultant', 'email' => 'diana@consulting.com']);

        // Add contact points
        $consultantEmail = ContactPoint::factory()
            ->email()
            ->verified()
            ->primary()
            ->forParty($consultant->party)
            ->create(['value_raw' => 'diana@consulting.com']);

        $consultantPhoneA = ContactPoint::factory()
            ->phone()
            ->verified()
            ->forParty($consultant->party)
            ->labeled('Company A Line')
            ->create(['value_raw' => '+15551234567']);

        $consultantPhoneB = ContactPoint::factory()
            ->phone()
            ->verified()
            ->forParty($consultant->party)
            ->labeled('Company B Line')
            ->create(['value_raw' => '+15559876543']);

        // Diana is AP contact for both companies
        RoleAssignment::create([
            'party_id' => $consultant->party->id,
            'scope_party_id' => $companyA->party->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        RoleAssignment::create([
            'party_id' => $consultant->party->id,
            'scope_party_id' => $companyB->party->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        $resolver = app(ContactResolver::class);

        // Both companies can resolve to Diana's email
        $resultA = $resolver->resolve(new ResolverRequest(
            targetParty: $companyA->party,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $companyA->party,
            limit: 5,
        ));

        $resultB = $resolver->resolve(new ResolverRequest(
            targetParty: $companyB->party,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $companyB->party,
            limit: 5,
        ));

        // Both resolve to Diana's email
        expect($resultA->best()->contactPoint->value_normalized)->toBe('diana@consulting.com');
        expect($resultB->best()->contactPoint->value_normalized)->toBe('diana@consulting.com');

        // Same person, different contexts
        expect($resultA->best()->owningParty->id)->toBe($consultant->party->id);
        expect($resultB->best()->owningParty->id)->toBe($consultant->party->id);
    });

    it('handles organization-specific DNC rules without affecting other orgs', function () {
        $companyA = Company::create(['legal_name' => 'Company A']);
        $companyB = Company::create(['legal_name' => 'Company B']);

        // Shared vendor contact
        $vendor = User::create(['name' => 'Vendor Contact', 'email' => 'vendor@suppliers.com']);

        $vendorEmail = ContactPoint::factory()
            ->email()
            ->verified()
            ->primary()
            ->forParty($vendor->party)
            ->create(['value_raw' => 'vendor@suppliers.com']);

        // Vendor is sales contact for both companies
        RoleAssignment::create([
            'party_id' => $vendor->party->id,
            'scope_party_id' => $companyA->party->id,
            'role' => 'sales_contact',
            'priority' => 1,
        ]);

        RoleAssignment::create([
            'party_id' => $vendor->party->id,
            'scope_party_id' => $companyB->party->id,
            'role' => 'sales_contact',
            'priority' => 1,
        ]);

        // Company A adds DNC on the vendor for marketing
        DoNotContact::create([
            'party_id' => $vendor->party->id,
            'purpose' => 'marketing',
            'reason' => 'Requested no marketing from Company A',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        $resolver = app(ContactResolver::class);

        // Company A resolves sales contact - should still work (DNC is for marketing)
        $resultASales = $resolver->resolve(new ResolverRequest(
            targetParty: $companyA->party,
            purpose: 'sales',
            channel: 'email',
            scopeParty: $companyA->party,
            limit: 5,
        ));

        expect($resultASales->matches)->not->toBeEmpty();

        // Company B also gets vendor for sales
        $resultBSales = $resolver->resolve(new ResolverRequest(
            targetParty: $companyB->party,
            purpose: 'sales',
            channel: 'email',
            scopeParty: $companyB->party,
            limit: 5,
        ));

        expect($resultBSales->matches)->not->toBeEmpty();
    });

    it('supports multiple locations per organization with separate contacts', function () {
        // Create parent organization
        $parentOrg = Company::create(['legal_name' => 'National Corp']);

        // Create multiple locations as parties
        $eastLocation = Party::factory()->location()->create([
            'display_name_cached' => 'East Coast Warehouse',
        ]);

        $westLocation = Party::factory()->location()->create([
            'display_name_cached' => 'West Coast Warehouse',
        ]);

        // Link locations to parent org
        PartyRelationship::create([
            'from_party_id' => $eastLocation->id,
            'to_party_id' => $parentOrg->party->id,
            'relationship_type' => 'location_of',
        ]);

        PartyRelationship::create([
            'from_party_id' => $westLocation->id,
            'to_party_id' => $parentOrg->party->id,
            'relationship_type' => 'location_of',
        ]);

        // Create local receiving managers
        $eastManager = User::create(['name' => 'East Manager', 'email' => 'east@national.com']);
        $westManager = User::create(['name' => 'West Manager', 'email' => 'west@national.com']);

        $eastEmail = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($eastManager->party)
            ->create(['value_raw' => 'receiving.east@national.com']);

        $westEmail = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($westManager->party)
            ->create(['value_raw' => 'receiving.west@national.com']);

        // Assign receiving contact roles scoped to their locations
        // (The resolver looks for {purpose}_contact roles)
        RoleAssignment::create([
            'party_id' => $eastManager->party->id,
            'scope_party_id' => $eastLocation->id,
            'role' => 'receiving_contact',
            'priority' => 1,
        ]);

        RoleAssignment::create([
            'party_id' => $westManager->party->id,
            'scope_party_id' => $westLocation->id,
            'role' => 'receiving_contact',
            'priority' => 1,
        ]);

        $resolver = app(ContactResolver::class);

        // Query receiving at East location
        $resultEast = $resolver->resolve(new ResolverRequest(
            targetParty: $eastLocation,
            purpose: 'receiving',
            channel: 'email',
            scopeParty: $eastLocation,
            limit: 5,
        ));

        expect($resultEast->best()->contactPoint->value_normalized)->toBe('receiving.east@national.com');

        // Query receiving at West location
        $resultWest = $resolver->resolve(new ResolverRequest(
            targetParty: $westLocation,
            purpose: 'receiving',
            channel: 'email',
            scopeParty: $westLocation,
            limit: 5,
        ));

        expect($resultWest->best()->contactPoint->value_normalized)->toBe('receiving.west@national.com');
    });

    it('allows each organization to have separate addresses', function () {
        $companyA = Company::create(['legal_name' => 'Company A']);
        $companyB = Company::create(['legal_name' => 'Company B']);

        // Company A addresses
        $billingA = Address::factory()
            ->billing()
            ->primary()
            ->forParty($companyA->party)
            ->create([
                'line1' => '100 A Street',
                'city' => 'Alpha City',
            ]);

        $shippingA = Address::factory()
            ->shipping()
            ->forParty($companyA->party)
            ->create([
                'line1' => '200 A Warehouse',
                'city' => 'Alpha City',
            ]);

        // Company B addresses
        $billingB = Address::factory()
            ->billing()
            ->primary()
            ->forParty($companyB->party)
            ->create([
                'line1' => '100 B Street',
                'city' => 'Beta City',
            ]);

        // Query addresses per company
        $addressesA = $companyA->party->addresses()->get();
        $addressesB = $companyB->party->addresses()->get();

        expect($addressesA)->toHaveCount(2);
        expect($addressesB)->toHaveCount(1);

        // Addresses are properly isolated
        expect($addressesA->pluck('city')->unique()->toArray())->toBe(['Alpha City']);
        expect($addressesB->pluck('city')->unique()->toArray())->toBe(['Beta City']);
    });

    it('tracks consent separately for each party regardless of organization', function () {
        // Create multiple users across organizations
        $userA = User::create(['name' => 'User A', 'email' => 'usera@example.com']);
        $userB = User::create(['name' => 'User B', 'email' => 'userb@example.com']);

        // User A opts into marketing
        RobinsonRyan\HeyYou\Models\PartyConsent::create([
            'party_id' => $userA->party->id,
            'channel' => 'email',
            'purpose_category' => 'marketing',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        // User B opts out of marketing
        RobinsonRyan\HeyYou\Models\PartyConsent::create([
            'party_id' => $userB->party->id,
            'channel' => 'email',
            'purpose_category' => 'marketing',
            'status' => 'opted_out',
            'captured_at' => now(),
            'source' => 'unsubscribe',
        ]);

        $consentChecker = app(RobinsonRyan\HeyYou\Contracts\ConsentChecker::class);

        // Add contact points
        $emailA = ContactPoint::factory()->email()->forParty($userA->party)->create();
        $emailB = ContactPoint::factory()->email()->forParty($userB->party)->create();

        // Check consent
        $resultA = $consentChecker->hasConsent($emailA, 'marketing');
        $resultB = $consentChecker->hasConsent($emailB, 'marketing');

        expect($resultA->allowed)->toBeTrue();
        expect($resultB->allowed)->toBeFalse();
    });
});

describe('Data Integrity in Multi-Tenant Context', function () {
    it('maintains referential integrity when parties are deleted', function () {
        $company = Company::create(['legal_name' => 'Test Corp']);
        $employee = User::create(['name' => 'Test Employee', 'email' => 'emp@example.com']);

        // Create relationships
        PartyRelationship::create([
            'from_party_id' => $employee->party->id,
            'to_party_id' => $company->party->id,
            'relationship_type' => 'employment',
        ]);

        // Create contact points
        ContactPoint::factory()
            ->email()
            ->forParty($employee->party)
            ->count(3)
            ->create();

        // Create role assignment
        RoleAssignment::create([
            'party_id' => $employee->party->id,
            'scope_party_id' => $company->party->id,
            'role' => 'sales_contact',
        ]);

        // Soft delete the employee model
        $employee->delete();

        // The party should also be soft deleted (via Contactable trait)
        $employee->party->refresh();
        expect($employee->party->trashed())->toBeTrue();

        // Contact points still exist but belong to soft-deleted party
        $contactPoints = ContactPoint::query()
            ->where('party_id', $employee->party->id)
            ->get();
        expect($contactPoints)->toHaveCount(3);

        // Relationships still exist (for audit purposes)
        $relationships = PartyRelationship::query()
            ->where('from_party_id', $employee->party->id)
            ->get();
        expect($relationships)->toHaveCount(1);
    });

    it('supports querying across organization boundaries when needed', function () {
        // Create multiple organizations and their contacts
        $orgs = collect();
        for ($i = 1; $i <= 3; $i++) {
            $org = Company::create(['legal_name' => "Company $i"]);
            $orgs->push($org);

            // Add a verified email to each company's party
            ContactPoint::factory()
                ->email()
                ->verified()
                ->primary()
                ->forParty($org->party)
                ->create(['value_raw' => "main@company$i.com"]);
        }

        // Query all verified primary emails across all parties
        // This is something a super-admin might need
        $allVerifiedPrimaryEmails = ContactPoint::query()
            ->where('channel', 'email')
            ->where('is_verified', true)
            ->where('is_primary', true)
            ->get();

        expect($allVerifiedPrimaryEmails)->toHaveCount(3);
    });
});
