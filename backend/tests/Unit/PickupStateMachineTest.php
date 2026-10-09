<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\ConflictException;
use App\Support\PickupStateMachine;
use PHPUnit\Framework\TestCase;

class PickupStateMachineTest extends TestCase
{
    public function testProposedTransitions(): void
    {
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_SCHEDULED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_PROPOSED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_CANCELLED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_COLLECTED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_COMPLETED));
    }

    public function testScheduledTransitions(): void
    {
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_SCHEDULED, PickupStateMachine::STATE_OTP_ISSUED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_SCHEDULED, PickupStateMachine::STATE_PROPOSED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_SCHEDULED, PickupStateMachine::STATE_CANCELLED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_SCHEDULED, PickupStateMachine::STATE_COLLECTED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_SCHEDULED, PickupStateMachine::STATE_COMPLETED));
    }

    public function testOtpIssuedTransitions(): void
    {
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_OTP_ISSUED, PickupStateMachine::STATE_OTP_ISSUED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_OTP_ISSUED, PickupStateMachine::STATE_COLLECTED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_OTP_ISSUED, PickupStateMachine::STATE_PROPOSED));
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_OTP_ISSUED, PickupStateMachine::STATE_CANCELLED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_OTP_ISSUED, PickupStateMachine::STATE_COMPLETED));
    }

    public function testCollectedTransitions(): void
    {
        $this->assertTrue(PickupStateMachine::canTransition(PickupStateMachine::STATE_COLLECTED, PickupStateMachine::STATE_COMPLETED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_COLLECTED, PickupStateMachine::STATE_CANCELLED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_COLLECTED, PickupStateMachine::STATE_PROPOSED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_COLLECTED, PickupStateMachine::STATE_SCHEDULED));
    }

    public function testTerminalStates(): void
    {
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_COMPLETED, PickupStateMachine::STATE_PROPOSED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_COMPLETED, PickupStateMachine::STATE_CANCELLED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_CANCELLED, PickupStateMachine::STATE_PROPOSED));
        $this->assertFalse(PickupStateMachine::canTransition(PickupStateMachine::STATE_CANCELLED, PickupStateMachine::STATE_SCHEDULED));
    }

    public function testAssertCanTransitionThrowsConflict(): void
    {
        $this->expectException(ConflictException::class);
        PickupStateMachine::assertCanTransition(PickupStateMachine::STATE_PROPOSED, PickupStateMachine::STATE_COMPLETED);
    }
}
