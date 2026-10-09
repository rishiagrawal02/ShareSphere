<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Services\MatchService;
use App\Support\Paginator;

class MatchController
{
    private MatchService $matchService;

    public function __construct(?MatchService $matchService = null)
    {
        $this->matchService = $matchService ?? new MatchService();
    }

    /**
     * GET /api/matches?requirement_id=... OR ?donation_id=...
     */
    public function index(Request $request): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = (string) $request->getAttribute('auth_role');

        $reqId = $request->getQuery('requirement_id');
        $donId = $request->getQuery('donation_id');

        if (($reqId === null && $donId === null) || ($reqId !== null && $donId !== null)) {
            throw new ValidationFailedException(['query' => 'Provide exactly one of requirement_id or donation_id']);
        }

        $pagination = Paginator::fromRequest($request);

        if ($reqId !== null) {
            $rId = (int) $reqId;
            if ($rId <= 0) {
                throw new ValidationFailedException(['requirement_id' => 'Invalid requirement_id']);
            }

            $result = $this->matchService->matchesForRequirement(
                $userId,
                $userRole,
                $rId,
                $pagination['limit'],
                $pagination['offset']
            );

            $meta = Paginator::buildMeta($result['total'], $pagination['page'], $pagination['per_page']);
            if ($result['total'] === 0) {
                $meta['hint'] = 'No matching active donations found within your service radius. Try expanding requirement radius or adjusting category.';
            }

            return Response::json([
                'status' => 'ok',
                'data'   => $result['matches'],
                'meta'   => $meta,
            ]);
        }

        // Donor matching view
        $dId = (int) $donId;
        if ($dId <= 0) {
            throw new ValidationFailedException(['donation_id' => 'Invalid donation_id']);
        }

        $result = $this->matchService->matchesForDonation(
            $userId,
            $userRole,
            $dId,
            $pagination['limit'],
            $pagination['offset']
        );

        $meta = Paginator::buildMeta($result['total'], $pagination['page'], $pagination['per_page']);
        if ($result['total'] === 0) {
            $meta['hint'] = 'No nearby NGO requirements found for this item category at this time.';
        }

        return Response::json([
            'status' => 'ok',
            'data'   => $result['matches'],
            'meta'   => $meta,
        ]);
    }
}
