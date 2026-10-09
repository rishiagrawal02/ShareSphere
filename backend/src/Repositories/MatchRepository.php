<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class MatchRepository
{
    private PDO $pdo;

    private const CONDITION_RANKS = [
        'fair'     => 1,
        'good'     => 2,
        'like_new' => 3,
        'new'      => 4,
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve eligible donation candidates for a specific NGO requirement.
     * Uses snapped public location to preserve donor privacy.
     */
    public function candidatesForRequirement(int $requirementId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                d.id as donation_id,
                d.donor_id,
                d.category_id as donation_category_id,
                dc.name as donation_category_name,
                d.title as donation_title,
                d.description as donation_description,
                d.condition as donation_condition,
                d.total_quantity as donation_total_quantity,
                d.available_quantity as donation_available_quantity,
                d.status as donation_status,
                ST_Y(d.location_public::geometry) as latitude_public,
                ST_X(d.location_public::geometry) as longitude_public,
                d.expires_at as donation_expires_at,
                d.created_at as donation_created_at,
                
                r.id as requirement_id,
                r.ngo_id,
                r.category_id as requirement_category_id,
                rc.name as requirement_category_name,
                r.title as requirement_title,
                r.quantity_needed as requirement_quantity_needed,
                r.quantity_allocated as requirement_quantity_allocated,
                (r.quantity_needed - r.quantity_allocated) as requirement_outstanding,
                r.urgency as requirement_urgency,
                r.min_condition as requirement_min_condition,
                r.radius_km as requirement_radius_km,
                r.needed_by as requirement_needed_by,
                
                ST_Distance(d.location_public, r.location) as distance_meters,
                
                CASE 
                    WHEN d.category_id = r.category_id THEN 100.0
                    ELSE COALESCE(cc.score_factor, 60.0)
                END as item_compatibility_factor,
                
                CASE 
                    WHEN d.category_id = r.category_id THEN 'exact'
                    ELSE 'compatible'
                END as category_match_type

            FROM ngo_requirements r
            JOIN ngos n ON r.ngo_id = n.id
            JOIN users nu ON n.user_id = nu.id
            JOIN categories rc ON r.category_id = rc.id
            
            CROSS JOIN donations d
            JOIN users du ON d.donor_id = du.id
            JOIN categories dc ON d.category_id = dc.id
            LEFT JOIN category_compatibility cc 
                ON (cc.category_id = r.category_id AND cc.compatible_category_id = d.category_id)
                OR (cc.category_id = d.category_id AND cc.compatible_category_id = r.category_id)

            WHERE r.id = :req_id
              -- NGO & User Active Checks
              AND n.verification_status = 'verified'
              AND nu.account_status = 'active'
              AND du.account_status = 'active'
              
              -- State & Quantity Checks
              AND r.status IN ('active', 'partially_fulfilled')
              AND (r.quantity_needed - r.quantity_allocated) > 0
              AND d.status IN ('active', 'partially_allocated')
              AND d.available_quantity > 0
              AND (d.expires_at IS NULL OR d.expires_at > NOW())
              
              -- Category Eligibility Check
              AND (d.category_id = r.category_id OR cc.score_factor IS NOT NULL)
              
              -- Spatial Radius Check (using GiST index with ST_DWithin)
              AND ST_DWithin(d.location_public, r.location, (r.radius_km * 1000.0))
              
              -- Condition Check
              AND (
                  r.min_condition IS NULL
                  OR (
                      CASE d.condition
                          WHEN 'new' THEN 4
                          WHEN 'like_new' THEN 3
                          WHEN 'good' THEN 2
                          WHEN 'fair' THEN 1
                          ELSE 0
                      END >= 
                      CASE r.min_condition
                          WHEN 'new' THEN 4
                          WHEN 'like_new' THEN 3
                          WHEN 'good' THEN 2
                          WHEN 'fair' THEN 1
                          ELSE 0
                      END
                  )
              )
              
              -- Exclude donations that already have open/pending requests from this NGO
              AND NOT EXISTS (
                  SELECT 1 FROM donation_requests dr
                  WHERE dr.donation_id = d.id
                    AND dr.ngo_id = r.ngo_id
                    AND dr.requirement_id = r.id
                    AND dr.status IN ('pending', 'accepted')
              )
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':req_id', $requirementId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve eligible requirement candidates for a specific donation (donor side view).
     */
    public function candidatesForDonation(int $donationId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                r.id as requirement_id,
                r.ngo_id,
                n.organization_name,
                r.category_id as requirement_category_id,
                rc.name as requirement_category_name,
                r.title as requirement_title,
                r.description as requirement_description,
                r.quantity_needed as requirement_quantity_needed,
                r.quantity_allocated as requirement_quantity_allocated,
                (r.quantity_needed - r.quantity_allocated) as requirement_outstanding,
                r.urgency as requirement_urgency,
                r.min_condition as requirement_min_condition,
                r.radius_km as requirement_radius_km,
                r.needed_by as requirement_needed_by,
                
                d.id as donation_id,
                d.donor_id,
                d.category_id as donation_category_id,
                dc.name as donation_category_name,
                d.title as donation_title,
                d.total_quantity as donation_total_quantity,
                d.available_quantity as donation_available_quantity,
                d.condition as donation_condition,
                
                ST_Distance(d.location_public, r.location) as distance_meters,
                
                CASE 
                    WHEN d.category_id = r.category_id THEN 100.0
                    ELSE COALESCE(cc.score_factor, 60.0)
                END as item_compatibility_factor,
                
                CASE 
                    WHEN d.category_id = r.category_id THEN 'exact'
                    ELSE 'compatible'
                END as category_match_type

            FROM donations d
            JOIN users du ON d.donor_id = du.id
            JOIN categories dc ON d.category_id = dc.id
            
            CROSS JOIN ngo_requirements r
            JOIN ngos n ON r.ngo_id = n.id
            JOIN users nu ON n.user_id = nu.id
            JOIN categories rc ON r.category_id = rc.id
            LEFT JOIN category_compatibility cc 
                ON (cc.category_id = r.category_id AND cc.compatible_category_id = d.category_id)
                OR (cc.category_id = d.category_id AND cc.compatible_category_id = r.category_id)

            WHERE d.id = :donation_id
              -- Active & Verified Checks
              AND n.verification_status = 'verified'
              AND nu.account_status = 'active'
              AND du.account_status = 'active'
              
              -- State Checks
              AND d.status IN ('active', 'partially_allocated')
              AND d.available_quantity > 0
              AND (d.expires_at IS NULL OR d.expires_at > NOW())
              AND r.status IN ('active', 'partially_fulfilled')
              AND (r.quantity_needed - r.quantity_allocated) > 0
              
              -- Category & Spatial Filters
              AND (d.category_id = r.category_id OR cc.score_factor IS NOT NULL)
              AND ST_DWithin(d.location_public, r.location, (r.radius_km * 1000.0))
              
              -- Condition Match
              AND (
                  r.min_condition IS NULL
                  OR (
                      CASE d.condition
                          WHEN 'new' THEN 4
                          WHEN 'like_new' THEN 3
                          WHEN 'good' THEN 2
                          WHEN 'fair' THEN 1
                          ELSE 0
                      END >= 
                      CASE r.min_condition
                          WHEN 'new' THEN 4
                          WHEN 'like_new' THEN 3
                          WHEN 'good' THEN 2
                          WHEN 'fair' THEN 1
                          ELSE 0
                      END
                  )
              )
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':donation_id', $donationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
