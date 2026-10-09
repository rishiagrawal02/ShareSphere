<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Services\MatchService;

class MapController
{
    private MatchService $matchService;

    public function __construct(?MatchService $matchService = null)
    {
        $this->matchService = $matchService ?? new MatchService();
    }

    /**
     * GET /api/map/donations?requirement_id=... OR ?bbox=minLng,minLat,maxLng,maxLat
     *
     * Returns up to 200 GeoJSON-like donation markers using only snapped public coordinates.
     * Never includes exact address or precise location.
     */
    public function donations(Request $request): Response
    {
        $requirementId = $request->getQuery('requirement_id');
        $bboxParam     = $request->getQuery('bbox');

        $reqId = null;
        $bbox  = null;

        if ($requirementId !== null) {
            $reqId = (int) $requirementId;
            if ($reqId <= 0) {
                throw new ValidationFailedException(['requirement_id' => 'Invalid requirement_id']);
            }
        } elseif ($bboxParam !== null) {
            $parts = array_map('trim', explode(',', $bboxParam));
            if (count($parts) !== 4) {
                throw new ValidationFailedException(['bbox' => 'bbox must be minLng,minLat,maxLng,maxLat']);
            }
            $bbox = array_map('floatval', $parts);
            // Basic range validation
            [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
            if ($minLng < -180 || $maxLng > 180 || $minLat < -90 || $maxLat > 90
                || $minLng >= $maxLng || $minLat >= $maxLat) {
                throw new ValidationFailedException(['bbox' => 'bbox values are out of range or inverted']);
            }
        }

        $markers = $this->matchService->mapDonations($reqId, $bbox);

        return Response::json([
            'status' => 'ok',
            'data'   => $markers,
            'meta'   => ['count' => count($markers), 'cap' => 200],
        ]);
    }
}
