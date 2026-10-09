<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Repositories\DonationRepository;
use App\Repositories\MatchRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Support\Database;
use PDO;

class MatchService
{
    private PDO $pdo;
    private MatchRepository $matchRepo;
    private RequirementRepository $reqRepo;
    private DonationRepository $donationRepo;
    private NgoRepository $ngoRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?MatchRepository $matchRepo = null,
        ?RequirementRepository $reqRepo = null,
        ?DonationRepository $donationRepo = null,
        ?NgoRepository $ngoRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->matchRepo = $matchRepo ?? new MatchRepository($this->pdo);
        $this->reqRepo = $reqRepo ?? new RequirementRepository($this->pdo);
        $this->donationRepo = $donationRepo ?? new DonationRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
    }

    /**
     * Paged, scored, and explained match candidates for an NGO requirement.
     */
    public function matchesForRequirement(
        int $userId,
        string $userRole,
        int $requirementId,
        int $limit = 20,
        int $offset = 0
    ): array {
        $req = $this->reqRepo->findById($requirementId);
        if ($req === null) {
            throw new NotFoundException('Requirement not found');
        }

        if ($userRole !== 'admin') {
            if ($userRole !== 'ngo' || (int) $req['ngo_owner_id'] !== $userId) {
                throw new NotFoundException('Requirement not found');
            }
        }

        $candidates = $this->matchRepo->candidatesForRequirement($requirementId, 100);

        $processed = [];
        foreach ($candidates as $cand) {
            $scoreData = MatchScorer::scoreCandidate($cand);
            $reasons   = ExplanationBuilder::buildReasons($cand);

            // Fetch thumbnail image if any
            $images = $this->donationRepo->listImages((int) $cand['donation_id']);
            $thumbUrl = !empty($images) ? "/api/media/donation-images/{$images[0]['id']}" : null;

            $processed[] = [
                'donation' => [
                    'id'                 => (int) $cand['donation_id'],
                    'title'              => $cand['donation_title'],
                    'category_id'        => (int) $cand['donation_category_id'],
                    'category_name'      => $cand['donation_category_name'],
                    'condition'          => $cand['donation_condition'],
                    'total_quantity'     => (int) $cand['donation_total_quantity'],
                    'available_quantity' => (int) $cand['donation_available_quantity'],
                    'status'             => $cand['donation_status'],
                    'latitude_public'    => (float) $cand['latitude_public'],
                    'longitude_public'   => (float) $cand['longitude_public'],
                    'distance_km'        => round(((float) $cand['distance_meters']) / 1000.0, 2),
                    'distance_meters'    => round((float) $cand['distance_meters'], 1),
                    'thumbnail_url'      => $thumbUrl,
                    'created_at'         => $cand['donation_created_at'],
                ],
                'match' => [
                    'score'      => $scoreData['score'],
                    'score_band' => $scoreData['score_band'],
                    'components' => $scoreData['components'],
                    'reasons'    => $reasons,
                ],
                // Internal fields for sorting
                'score'           => $scoreData['score'],
                'distance_meters' => (float) $cand['distance_meters'],
                'donation_id'     => (int) $cand['donation_id'],
            ];
        }

        MatchScorer::sortCandidates($processed);
        $total = count($processed);
        $slice = array_slice($processed, $offset, $limit);

        // Strip internal sort fields
        $cleaned = array_map(function ($item) {
            unset($item['score'], $item['distance_meters'], $item['donation_id']);
            return $item;
        }, $slice);

        return [
            'matches' => $cleaned,
            'total'   => $total,
        ];
    }

    /**
     * Matching NGO requirements for a donor's donation listing.
     */
    public function matchesForDonation(
        int $userId,
        string $userRole,
        int $donationId,
        int $limit = 20,
        int $offset = 0
    ): array {
        $donation = $this->donationRepo->findById($donationId);
        if ($donation === null) {
            throw new NotFoundException('Donation not found');
        }

        if ($userRole !== 'admin' && (int) $donation['donor_id'] !== $userId) {
            throw new NotFoundException('Donation not found');
        }

        $candidates = $this->matchRepo->candidatesForDonation($donationId, 100);

        $processed = [];
        foreach ($candidates as $cand) {
            $scoreData = MatchScorer::scoreCandidate($cand);
            $reasons   = ExplanationBuilder::buildReasons($cand);

            $processed[] = [
                'requirement' => [
                    'id'                 => (int) $cand['requirement_id'],
                    'organization_name'  => $cand['organization_name'],
                    'title'              => $cand['requirement_title'],
                    'category_id'        => (int) $cand['requirement_category_id'],
                    'category_name'      => $cand['requirement_category_name'],
                    'quantity_needed'    => (int) $cand['requirement_quantity_needed'],
                    'quantity_allocated' => (int) $cand['requirement_quantity_allocated'],
                    'outstanding'        => (int) $cand['requirement_outstanding'],
                    'urgency'            => $cand['requirement_urgency'],
                    'min_condition'      => $cand['requirement_min_condition'],
                    'distance_km'        => round(((float) $cand['distance_meters']) / 1000.0, 2),
                ],
                'match' => [
                    'score'      => $scoreData['score'],
                    'score_band' => $scoreData['score_band'],
                    'components' => $scoreData['components'],
                    'reasons'    => $reasons,
                ],
                // Internal fields for sorting
                'score'           => $scoreData['score'],
                'distance_meters' => (float) $cand['distance_meters'],
                'donation_id'     => (int) $cand['donation_id'],
            ];
        }

        MatchScorer::sortCandidates($processed);
        $total = count($processed);
        $slice = array_slice($processed, $offset, $limit);

        $cleaned = array_map(function ($item) {
            unset($item['score'], $item['distance_meters'], $item['donation_id']);
            return $item;
        }, $slice);

        return [
            'matches' => $cleaned,
            'total'   => $total,
        ];
    }

    /**
     * Map view markers (max 200) with snapped coordinates only.
     */
    public function mapDonations(?int $requirementId, ?array $bbox = null): array
    {
        if ($requirementId !== null) {
            $candidates = $this->matchRepo->candidatesForRequirement($requirementId, 200);
            return array_map(function ($c) {
                return [
                    'id'                 => (int) $c['donation_id'],
                    'title'              => $c['donation_title'],
                    'category_name'      => $c['donation_category_name'],
                    'condition'          => $c['donation_condition'],
                    'available_quantity' => (int) $c['donation_available_quantity'],
                    'latitude_public'    => (float) $c['latitude_public'],
                    'longitude_public'   => (float) $c['longitude_public'],
                    'distance_km'        => round(((float) $c['distance_meters']) / 1000.0, 2),
                ];
            }, $candidates);
        }

        // Bounding box query if provided
        $whereBbox = "";
        $params = [];
        if ($bbox !== null && count($bbox) === 4) {
            [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
            $whereBbox = " AND location_public && ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326)::geography";
            $params = [
                ':min_lng' => (float) $minLng,
                ':min_lat' => (float) $minLat,
                ':max_lng' => (float) $maxLng,
                ':max_lat' => (float) $maxLat,
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT d.id, d.title, c.name as category_name, d.condition, d.available_quantity,
                   ST_Y(d.location_public::geometry) as latitude_public,
                   ST_X(d.location_public::geometry) as longitude_public
            FROM donations d
            JOIN categories c ON d.category_id = c.id
            WHERE d.status IN ('active', 'partially_allocated')
              AND d.available_quantity > 0
              {$whereBbox}
            ORDER BY d.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);

        return array_map(function ($r) {
            return [
                'id'                 => (int) $r['id'],
                'title'              => $r['title'],
                'category_name'      => $r['category_name'],
                'condition'          => $r['condition'],
                'available_quantity' => (int) $r['available_quantity'],
                'latitude_public'    => (float) $r['latitude_public'],
                'longitude_public'   => (float) $r['longitude_public'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
