<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
use RobinsonRyan\HeyYou\Resolver\ResolverExplanation;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Resolver\ResolverResult;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('ContactResolved', function () {
    it('contains the resolver request and result', function () {
        $request = new ResolverRequest(
            targetParty: $this->party,
            purpose: 'billing',
            channel: 'email',
        );

        $result = new ResolverResult(
            matches: new Collection,
            explanation: new ResolverExplanation(
                candidatesConsidered: 5,
                exclusionSummary: ['dnc' => 1, 'no_consent' => 1],
                fallbackUsed: false,
                fallbackPath: null,
            ),
        );

        $event = new ContactResolved($request, $result);

        expect($event->request)->toBeInstanceOf(ResolverRequest::class)
            ->and($event->result)->toBeInstanceOf(ResolverResult::class)
            ->and($event->request->purpose)->toBe('billing')
            ->and($event->request->channel)->toBe('email')
            ->and($event->result->explanation->candidatesConsidered)->toBe(5);
    });
});
