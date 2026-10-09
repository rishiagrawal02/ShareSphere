<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\PickupResource;
use PHPUnit\Framework\TestCase;

class PickupResourceTest extends TestCase
{
    private array $samplePickup;

    protected function setUp(): void
    {
        $this->samplePickup = [
            'id'                  => 10,
            'allocation_id'       => 5,
            'proposed_by'         => 1,
            'scheduled_at'        => '2026-10-15 14:00:00',
            'location_details'    => 'Side entrance',
            'contact_note'        => 'Buzz 4B',
            'state'               => 'proposed',
            'otp_attempts'        => 0,
            'otp_locked'          => false,
            'otp_issue_count'     => 0,
            'otp_expires_at'      => null,
            'collected_at'        => null,
            'completed_at'        => null,
            'created_at'          => '2026-10-10 10:00:00',
            'updated_at'          => '2026-10-10 10:00:00',
            'donation_id'         => 20,
            'donation_title'      => 'Winter Blankets',
            'total_quantity'      => 10,
            'available_quantity'  => 5,
            'donation_status'     => 'active',
            'address_text'        => '742 Evergreen Terrace',
            'latitude_exact'      => 40.7128,
            'longitude_exact'     => -74.0060,
            'latitude_public'     => 40.7100,
            'longitude_public'    => -74.0100,
            'allocated_quantity'  => 5,
            'allocation_status'   => 'confirmed',
            'ngo_id'              => 3,
            'organization_name'   => 'Helping Hands',
        ];
    }

    public function testNgoProposedPickupHidesExactLocation(): void
    {
        $this->samplePickup['state'] = 'proposed';
        $formatted = PickupResource::format($this->samplePickup, 'ngo');

        $this->assertSame(10, $formatted['id']);
        $this->assertSame('Winter Blankets', $formatted['donation']['title']);
        $this->assertSame(40.7100, $formatted['donation']['latitude_public']);
        $this->assertSame(-74.0100, $formatted['donation']['longitude_public']);
        $this->assertArrayNotHasKey('address_text', $formatted['donation']);
        $this->assertArrayNotHasKey('latitude_exact', $formatted['donation']);
        $this->assertArrayNotHasKey('longitude_exact', $formatted['donation']);
    }

    public function testNgoScheduledPickupExposesExactLocation(): void
    {
        $this->samplePickup['state'] = 'scheduled';
        $formatted = PickupResource::format($this->samplePickup, 'ngo');

        $this->assertArrayHasKey('address_text', $formatted['donation']);
        $this->assertSame('742 Evergreen Terrace', $formatted['donation']['address_text']);
        $this->assertSame(40.7128, $formatted['donation']['latitude_exact']);
        $this->assertSame(-74.0060, $formatted['donation']['longitude_exact']);
    }

    public function testDonorAlwaysSeesExactLocation(): void
    {
        $this->samplePickup['state'] = 'proposed';
        $formatted = PickupResource::format($this->samplePickup, 'donor');

        $this->assertArrayHasKey('address_text', $formatted['donation']);
        $this->assertSame('742 Evergreen Terrace', $formatted['donation']['address_text']);
        $this->assertSame(40.7128, $formatted['donation']['latitude_exact']);
    }

    public function testAdminAlwaysSeesExactLocation(): void
    {
        $this->samplePickup['state'] = 'proposed';
        $formatted = PickupResource::format($this->samplePickup, 'admin');

        $this->assertArrayHasKey('address_text', $formatted['donation']);
        $this->assertSame('742 Evergreen Terrace', $formatted['donation']['address_text']);
    }

    public function testFormatCollection(): void
    {
        $collection = [$this->samplePickup, $this->samplePickup];
        $formatted = PickupResource::formatCollection($collection, 'ngo');

        $this->assertCount(2, $formatted);
        $this->assertArrayNotHasKey('address_text', $formatted[0]['donation']);
        $this->assertArrayNotHasKey('address_text', $formatted[1]['donation']);
    }
}
