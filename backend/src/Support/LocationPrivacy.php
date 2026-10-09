<?php

declare(strict_types=1);

namespace App\Support;

class LocationPrivacy
{
    public const DEFAULT_GRID_DEGREES = 0.005; // ~550 meters at equator

    /**
     * Snap exact coordinates to a coarse grid to protect donor location privacy.
     *
     * @param float $lat Latitude in degrees
     * @param float $lng Longitude in degrees
     * @param float $grid Grid interval in degrees
     * @return array{latitude_public: float, longitude_public: float}
     */
    public static function snap(float $lat, float $lng, float $grid = self::DEFAULT_GRID_DEGREES): array
    {
        $snappedLat = round($lat / $grid) * $grid;
        $snappedLng = round($lng / $grid) * $grid;

        return [
            'latitude_public'  => round($snappedLat, 6),
            'longitude_public' => round($snappedLng, 6),
        ];
    }
}
