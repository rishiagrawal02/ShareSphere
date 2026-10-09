<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\DonationRepository;
use App\Services\DonationService;
use App\Support\Paginator;

class DonationController
{
    private DonationService $donationService;
    private DonationRepository $donationRepo;

    public function __construct(
        ?DonationService $donationService = null,
        ?DonationRepository $donationRepo = null
    ) {
        $this->donationService = $donationService ?? new DonationService();
        $this->donationRepo = $donationRepo ?? new DonationRepository();
    }

    /**
     * POST /api/donations
     */
    public function store(Request $request): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = (string) $request->getAttribute('auth_role');
        $body     = $request->getBody();

        $donation = $this->donationService->createDonation($userId, $userRole, $body);

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Donation published successfully',
            'donation' => $donation,
        ], 201);
    }

    /**
     * GET /api/donations
     */
    public function index(Request $request): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = (string) $request->getAttribute('auth_role');
        $scope    = $request->getQuery('scope');

        $pagination = Paginator::fromRequest($request);

        if ($scope === 'mine') {
            if ($userRole !== 'donor') {
                throw new ForbiddenException('Scope mine is only available to donors', 'FORBIDDEN_SCOPE');
            }

            $status     = $request->getQuery('status');
            $categoryId = $request->getQuery('category_id') ? (int) $request->getQuery('category_id') : null;

            $total = $this->donationRepo->countForOwner($userId, $status, $categoryId);
            $items = $this->donationService->listOwnerDonations(
                $userId,
                $status,
                $categoryId,
                $pagination['limit'],
                $pagination['offset']
            );

            $meta = Paginator::buildMeta($total, $pagination['page'], $pagination['per_page']);

            return Response::json([
                'status' => 'ok',
                'data'   => $items,
                'meta'   => $meta,
            ]);
        }

        // Public Discovery view (NGO or Admin)
        $filters = [
            'category_id' => $request->getQuery('category_id'),
            'condition'   => $request->getQuery('condition'),
            'q'           => $request->getQuery('q'),
            'latitude'    => $request->getQuery('latitude'),
            'longitude'   => $request->getQuery('longitude'),
            'radius_km'   => $request->getQuery('radius_km'),
            'sort'        => $request->getQuery('sort'),
        ];

        $total = $this->donationRepo->countForNgo($filters);
        $items = $this->donationService->listNgoDonations($filters, $pagination['limit'], $pagination['offset']);

        $meta = Paginator::buildMeta($total, $pagination['page'], $pagination['per_page']);

        return Response::json([
            'status' => 'ok',
            'data'   => $items,
            'meta'   => $meta,
        ]);
    }

    /**
     * GET /api/donations/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = (string) $request->getAttribute('auth_role');

        $donation = $this->donationService->getDonation($id, $userId, $userRole);

        return Response::json([
            'status'   => 'ok',
            'donation' => $donation,
        ]);
    }

    /**
     * PATCH /api/donations/{id}
     */
    public function update(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $body   = $request->getBody();

        $donation = $this->donationService->updateDonation($userId, $id, $body);

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Donation updated successfully',
            'donation' => $donation,
        ]);
    }

    /**
     * DELETE /api/donations/{id}
     */
    public function destroy(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $donation = $this->donationService->closeDonation($userId, $id);

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Donation closed successfully',
            'donation' => $donation,
        ]);
    }

    /**
     * POST /api/donations/{id}/images
     */
    public function uploadImages(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $files  = $request->getFiles();

        $images = $this->donationService->uploadImages($userId, $id, $files);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Images uploaded successfully',
            'images'  => $images,
        ], 201);
    }

    /**
     * DELETE /api/donations/{id}/images/{imageId}
     */
    public function deleteImage(Request $request, int $id, int $imageId): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $this->donationService->deleteImage($userId, $id, $imageId);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Image deleted successfully',
        ]);
    }
}
