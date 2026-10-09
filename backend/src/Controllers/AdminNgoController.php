<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\NgoRepository;
use App\Services\NgoVerificationService;
use App\Support\Paginator;

class AdminNgoController
{
    private NgoRepository $ngoRepo;
    private NgoVerificationService $verificationService;

    public function __construct(
        ?NgoRepository $ngoRepo = null,
        ?NgoVerificationService $verificationService = null
    ) {
        $this->ngoRepo = $ngoRepo ?? new NgoRepository();
        $this->verificationService = $verificationService ?? new NgoVerificationService($this->ngoRepo);
    }

    /**
     * GET /api/admin/ngos
     */
    public function index(Request $request): Response
    {
        $status = $request->getQuery('status');
        $q      = $request->getQuery('q');

        $pagination = Paginator::fromRequest($request);
        $total = $this->ngoRepo->countForAdmin($status, $q);
        $ngos = $this->ngoRepo->listForAdmin($status, $q, $pagination['limit'], $pagination['offset']);

        $meta = Paginator::buildMeta($total, $pagination['page'], $pagination['per_page']);

        return Response::json([
            'status' => 'ok',
            'data'   => $ngos,
            'meta'   => $meta,
        ]);
    }

    /**
     * GET /api/admin/ngos/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $ngo = $this->ngoRepo->findNgoById($id);
        if ($ngo === null) {
            throw new NotFoundException('NGO organisation not found');
        }

        $docs = $this->ngoRepo->listDocuments($id);
        foreach ($docs as &$doc) {
            $doc['media_url'] = "/api/media/ngo-documents/{$doc['id']}";
        }
        $ngo['documents'] = $docs;

        return Response::json([
            'status' => 'ok',
            'ngo'    => $ngo,
        ]);
    }

    /**
     * POST /api/admin/ngos/{id}/verify
     */
    public function verify(Request $request, int $id): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $body = $request->getBody();

        $decision = (string) ($body['decision'] ?? '');
        $note     = isset($body['note']) ? (string) $body['note'] : null;

        $updatedNgo = $this->verificationService->processDecision($adminUserId, $id, $decision, $note);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Verification decision applied successfully',
            'ngo'     => $updatedNgo,
        ]);
    }
}
