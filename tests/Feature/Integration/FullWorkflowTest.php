<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

describe('Full Workflow Integration', function () {
    it('completes a full B2B contact management workflow', function () {
        // Step 1: Create a company with the Contactable trait
        $company = Company::create(['legal_name' => 'Acme Corp']);

        // Verify party was auto-created
        expect($company->party)->toBeInstanceOf(Party::class);
        expect($company->party->display_name_cached)->toBe('Acme Corp');

        // Step 2: Add contact points to the company
        $mainEmail = $company->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'Info@Acme.COM',
            'label' => 'Main Office',
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
            'verification_method' => 'manual',
        ]);

        // Verify normalization occurred
        expect($mainEmail->value_normalized)->toBe('info@acme.com');

        // Step 3: Create an employee
        $employee = User::create(['name' => 'Jane Smith', 'email' => 'jane@acme.com']);
        expect($employee->party)->toBeInstanceOf(Party::class);

        // Step 4: Establish employment relationship
        $employment = PartyRelationship::create([
            'from_party_id' => $employee->party->id,
            'to_party_id' => $company->party->id,
            'relationship_type' => 'employment',
            'valid_from' => now(),
        ]);

        expect($employment->fromParty->id)->toBe($employee->party->id);
        expect($employment->toParty->id)->toBe($company->party->id);

        // Step 5: Add employee contact points
        $employeeEmail = $employee->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'jane.smith@acme.com',
            'label' => 'Work Email',
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $employeePhone = $employee->party->contactPoints()->create([
            'channel' => 'phone',
            'value_raw' => '+15551234567',
            'label' => 'Work Mobile',
        ]);

        // Step 6: Assign AP role to employee
        $roleAssignment = RoleAssignment::create([
            'party_id' => $employee->party->id,
            'scope_party_id' => $company->party->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        // Step 7: Attach purpose to employee's email
        ContactPointPurpose::create([
            'contact_point_id' => $employeeEmail->id,
            'purpose' => 'accounts_payable',
            'priority' => 1,
            'is_preferred' => true,
        ]);

        // Step 8: Add company address
        $address = $company->party->addresses()->create([
            'purpose' => 'billing',
            'is_primary' => true,
            'line1' => '123 Business St',
            'city' => 'Commerce City',
            'region' => 'CO',
            'postal_code' => '80022',
            'country_code' => 'US',
        ]);

        expect($address->party_id)->toBe($company->party->id);

        // Step 9: Grant consent for transactional communications
        PartyConsent::create([
            'party_id' => $employee->party->id,
            'channel' => null,
            'purpose_category' => 'transactional',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'employment_agreement',
        ]);

        // Step 10: Resolve contacts
        $resolver = app(ContactResolver::class);

        $result = $resolver->resolve(new ResolverRequest(
            targetParty: $company->party,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $company->party,
            constraints: new ResolverConstraints(
                requireVerified: true,
            ),
            limit: 5,
        ));

        // Should find Jane's email as the AP contact
        expect($result->isEmpty())->toBeFalse();
        expect($result->best()->contactPoint->id)->toBe($employeeEmail->id);
        expect($result->best()->owningParty->id)->toBe($employee->party->id);
    });

    it('handles the full consent and DNC workflow', function () {
        // Create a party with contact points
        $party = Party::factory()->create();

        $emailForMarketing = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($party)
            ->create(['value_raw' => 'marketing@example.com']);

        $emailForTransactional = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($party)
            ->create(['value_raw' => 'billing@example.com']);

        // Grant party-level marketing consent
        PartyConsent::create([
            'party_id' => $party->id,
            'channel' => 'email',
            'purpose_category' => 'marketing',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        // Revoke consent at contact-point level for transactional email
        ContactPointConsent::create([
            'contact_point_id' => $emailForTransactional->id,
            'purpose_category' => 'marketing',
            'status' => 'opted_out',
            'captured_at' => now(),
            'source' => 'unsubscribe_link',
        ]);

        // Verify consent state
        $consentChecker = app(RobinsonRyan\HeyYou\Contracts\ConsentChecker::class);

        // Marketing email should have consent (party-level)
        $marketingResult = $consentChecker->hasConsent($emailForMarketing, 'marketing');
        expect($marketingResult->allowed)->toBeTrue();
        expect($marketingResult->level)->toBe('party');

        // Transactional email should NOT have consent (contact-point-level overrides)
        $transactionalResult = $consentChecker->hasConsent($emailForTransactional, 'marketing');
        expect($transactionalResult->allowed)->toBeFalse();
        expect($transactionalResult->level)->toBe('contact_point');

        // Now add a DNC rule for a specific purpose
        DoNotContact::create([
            'party_id' => $party->id,
            'purpose' => 'collections',
            'reason' => 'Legal dispute',
            'source' => 'legal',
            'effective_at' => now(),
        ]);

        // Verify DNC is checked
        $dncChecker = app(RobinsonRyan\HeyYou\Contracts\DncChecker::class);
        $dncResult = $dncChecker->isBlocked($emailForMarketing, 'collections');
        expect($dncResult->blocked)->toBeTrue();
        expect($dncResult->scope)->toBe('purpose');
    });

    it('handles contact point verification lifecycle', function () {
        $party = Party::factory()->create();

        // Create unverified contact point
        $contactPoint = ContactPoint::factory()
            ->email()
            ->forParty($party)
            ->create();

        expect($contactPoint->is_verified)->toBeFalse();
        expect($contactPoint->isCurrentlyVerified())->toBeFalse();

        // Verify it
        $contactPoint->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_method' => 'code',
        ]);

        expect($contactPoint->fresh()->isCurrentlyVerified())->toBeTrue();

        // Create contact point with expiring verification
        $expiringContact = ContactPoint::factory()
            ->email()
            ->verifiedUntil(now()->subDay())
            ->forParty($party)
            ->create();

        // Verification has expired
        expect($expiringContact->isCurrentlyVerified())->toBeFalse();
    });

    it('handles role assignment temporal validity', function () {
        $person = Party::factory()->person()->create();
        $company = Party::factory()->organization()->create();

        // Create a past role assignment
        $pastRole = RoleAssignment::factory()
            ->forParty($person)
            ->scopedTo($company)
            ->accountsPayable()
            ->expired()
            ->create();

        // Create a current role assignment
        $currentRole = RoleAssignment::factory()
            ->forParty($person)
            ->scopedTo($company)
            ->hr()
            ->create();

        // Create a future role assignment
        $futureRole = RoleAssignment::factory()
            ->forParty($person)
            ->scopedTo($company)
            ->executiveContact()
            ->future()
            ->create();

        // Query current roles
        $currentRoles = RoleAssignment::query()
            ->where('party_id', $person->id)
            ->where('scope_party_id', $company->id)
            ->current()
            ->get();

        expect($currentRoles)->toHaveCount(1);
        expect($currentRoles->first()->role)->toBe('hr_contact');
    });

    it('handles address management with purposes', function () {
        $party = Party::factory()->organization()->create();

        // Create multiple addresses for different purposes
        $billingAddress = Address::factory()
            ->billing()
            ->primary()
            ->forParty($party)
            ->create();

        $shippingAddress = Address::factory()
            ->shipping()
            ->forParty($party)
            ->create();

        $receivingAddress = Address::factory()
            ->receiving()
            ->forParty($party)
            ->create();

        // Query addresses by purpose
        $billingAddresses = Address::query()
            ->where('party_id', $party->id)
            ->where('purpose', 'billing')
            ->where('is_primary', true)
            ->get();

        expect($billingAddresses)->toHaveCount(1);
        expect($billingAddresses->first()->id)->toBe($billingAddress->id);

        // Test current scope with effective dating
        $expiredAddress = Address::factory()
            ->forParty($party)
            ->forPurpose('hr')
            ->validBetween(now()->subYear(), now()->subMonth())
            ->create();

        $currentAddresses = Address::query()
            ->where('party_id', $party->id)
            ->current()
            ->get();

        // Should not include expired address
        expect($currentAddresses->pluck('id'))->not->toContain($expiredAddress->id);
    });
});

describe('Contact Resolution End-to-End', function () {
    it('resolves contacts through organizational hierarchy', function () {
        // Create organizational structure:
        // Parent Corp
        //   └── Regional Office
        //         └── Local Branch

        $parentCorp = Party::factory()->organization()->create([
            'display_name_cached' => 'Parent Corp',
        ]);

        $regionalOffice = Party::factory()->organization()->create([
            'display_name_cached' => 'Regional Office',
        ]);

        $localBranch = Party::factory()->location()->create([
            'display_name_cached' => 'Local Branch',
        ]);

        // Establish relationships
        PartyRelationship::create([
            'from_party_id' => $regionalOffice->id,
            'to_party_id' => $parentCorp->id,
            'relationship_type' => 'member_of',
            'valid_from' => now(),
        ]);

        PartyRelationship::create([
            'from_party_id' => $localBranch->id,
            'to_party_id' => $regionalOffice->id,
            'relationship_type' => 'location_of',
            'valid_from' => now(),
        ]);

        // Create corporate AP contact
        $corpApPerson = Party::factory()->person()->create();
        $corpApEmail = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($corpApPerson)
            ->create(['value_raw' => 'ap@parentcorp.com']);

        ContactPointPurpose::create([
            'contact_point_id' => $corpApEmail->id,
            'purpose' => 'accounts_payable',
            'priority' => 1,
            'is_preferred' => true,
        ]);

        RoleAssignment::create([
            'party_id' => $corpApPerson->id,
            'scope_party_id' => $parentCorp->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        // Try to resolve AP contact at local branch level
        $resolver = app(ContactResolver::class);

        $result = $resolver->resolve(new ResolverRequest(
            targetParty: $localBranch,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $localBranch,
            constraints: new ResolverConstraints(
                requireVerified: true,
                allowFallback: true,
            ),
            limit: 5,
        ));

        // Should find parent corp's AP contact via scope hierarchy
        expect($result->isEmpty())->toBeFalse();
        expect($result->explanation->fallbackUsed)->toBeTrue();
    });

    it('excludes DNC-blocked contacts from resolution', function () {
        $company = Party::factory()->organization()->create();
        $person = Party::factory()->person()->create();

        $email = ContactPoint::factory()
            ->email()
            ->verified()
            ->primary()
            ->forParty($person)
            ->create();

        RoleAssignment::create([
            'party_id' => $person->id,
            'scope_party_id' => $company->id,
            'role' => 'accounts_payable_contact',
            'priority' => 1,
        ]);

        // Add DNC for this person's email
        DoNotContact::create([
            'party_id' => $person->id,
            'contact_point_id' => $email->id,
            'reason' => 'Requested no contact',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        // Resolve
        $resolver = app(ContactResolver::class);
        $result = $resolver->resolve(new ResolverRequest(
            targetParty: $company,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $company,
            limit: 5,
        ));

        // Should be empty - contact is DNC'd
        expect($result->isEmpty())->toBeTrue();
        expect($result->explanation->exclusionSummary['dnc'])->toBe(1);
    });

    it('ranks contacts correctly based on verification and primary status', function () {
        $company = Party::factory()->organization()->create();

        // Create multiple employees with different contact qualities
        $employee1 = Party::factory()->person()->create();
        $email1 = ContactPoint::factory()
            ->email()
            ->verified()
            ->primary()
            ->forParty($employee1)
            ->create(['value_raw' => 'primary.verified@example.com']);

        $employee2 = Party::factory()->person()->create();
        $email2 = ContactPoint::factory()
            ->email()
            ->verified()
            ->forParty($employee2)
            ->create(['value_raw' => 'verified@example.com']);

        $employee3 = Party::factory()->person()->create();
        $email3 = ContactPoint::factory()
            ->email()
            ->primary()
            ->forParty($employee3)
            ->create(['value_raw' => 'primary.unverified@example.com']);

        // All are AP contacts
        foreach ([$employee1, $employee2, $employee3] as $employee) {
            RoleAssignment::create([
                'party_id' => $employee->id,
                'scope_party_id' => $company->id,
                'role' => 'accounts_payable_contact',
                'priority' => 1,
            ]);
        }

        $resolver = app(ContactResolver::class);
        $result = $resolver->resolve(new ResolverRequest(
            targetParty: $company,
            purpose: 'accounts_payable',
            channel: 'email',
            scopeParty: $company,
            limit: 10,
        ));

        expect($result->matches)->not->toBeEmpty();

        // The verified+primary should rank highest
        $matchedEmails = $result->matches->pluck('contactPoint.value_normalized')->toArray();
        expect($matchedEmails[0])->toBe('primary.verified@example.com');
    });
});
