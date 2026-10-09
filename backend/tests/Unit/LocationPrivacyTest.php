<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\LocationPrivacy;
use PHPUnit\Framework\TestCase;

class LocationPrivacyTest extends TestCase
{
    public function testSnapSnapsCoordinatesToGrid(): void
    {
        // 18.520432, 73.856743 with 0.005 grid
        $snapped = LocationPrivacy::snap(18.520432, 73.856743);

        $this->assertSame(18.52, $snapped['latitude_public']);
        $this->assertSame(73.855, $snapped['longitude_public']);
    }

    public function testSnapEquatorAndMeridian(): void
    {
        $snapped = LocationPrivacy::snap(0.0024, 0.0031);
        $this->assertSame(0.0, $snapped['latitude_public']);
        $this->assertSame(0.005, $snapped['longitude_public']);
    }
}
