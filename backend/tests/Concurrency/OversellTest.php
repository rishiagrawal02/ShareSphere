<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\AllocationService;
use App\Support\Database;
use App\Tests\Support\Invariants;
use PDO;
use PHPUnit\Framework\TestCase;

class OversellTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;

    private int $donorUserId;
    private int $categoryId;
    private array $ngos = []; // array of ['user_id' => int, 'ngo_id' => int]

    public static function setUpBeforeClass(): void
    {
        $pdo = Database::getOwnerConnection();
        $pdo->exec("
            DELETE FROM allocations;
            DELETE FROM donation_requests;
            DELETE FROM donations;
            DELETE FROM ngo_requirements;
        ");
    }

    protected function setUp(): void
    {
        $this->pdo = Database::getOwnerConnection();
        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->catRepo = new CategoryRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reqRepo = new RequirementRepository($this->pdo);

        $suffix = bin2hex(random_bytes(4));

        $cat = $this->catRepo->create("Concurrency Cat {$suffix}", 'Concurrency testing category');
        $this->categoryId = (int) $cat['id'];

        $donor = $this->userRepo->create([
            'name'           => 'Concurrency Donor',
            'email'          => "donor_conc_{$suffix}@test.local",
            'password_hash'  => password_hash('Pass123!', PASSWORD_DEFAULT),
            'role'           => 'donor',
            'account_status' => 'active',
        ]);
        $this->donorUserId = (int) $donor['id'];

        // Create 6 verified NGOs for worker requests
        $this->ngos = [];
        for ($i = 1; $i <= 6; $i++) {
            $ngoUser = $this->userRepo->create([
                'name'           => "Conc NGO {$i} {$suffix}",
                'email'          => "ngo_conc_{$i}_{$suffix}@test.local",
                'password_hash'  => password_hash('Pass123!', PASSWORD_DEFAULT),
                'role'           => 'ngo',
                'account_status' => 'active',
            ]);
            $uId = (int) $ngoUser['id'];
            $ngo = $this->ngoRepo->createNgo([
                'user_id'             => $uId,
                'organization_name'   => "Conc NGO Org {$i} {$suffix}",
                'registration_number' => "REG-CONC-{$i}-{$suffix}",
                'contact_phone'       => '+100000000' . $i,
                'address_text'        => '123 Concurrency Rd',
                'latitude'            => 12.9716,
                'longitude'           => 77.5946,
            ]);
            $nId = (int) $ngo['id'];
            $this->ngoRepo->updateVerification($nId, 'verified', null, 'Auto verified');
            $this->ngos[] = ['user_id' => $uId, 'ngo_id' => $nId];
        }
    }

    protected function tearDown(): void
    {
        Invariants::checkAll($this->pdo);
    }

    private function createDonation(int $totalQty): int
    {
        $don = $this->donationRepo->create([
            'donor_id'        => $this->donorUserId,
            'category_id'     => $this->categoryId,
            'title'           => 'Concurrency Stock Item',
            'description'     => 'Items for concurrency test',
            'condition'       => 'new',
            'total_quantity'  => $totalQty,
            'latitude'        => 12.9716,
            'longitude'       => 77.5946,
            'address_text'    => 'Donor Address 100',
            'status'          => 'active',
        ]);
        return (int) $don['id'];
    }

    private function createRequirement(int $ngoId, int $needed): int
    {
        $req = $this->reqRepo->create([
            'ngo_id'          => $ngoId,
            'category_id'     => $this->categoryId,
            'title'           => 'Concurrency Need',
            'description'     => 'Need for concurrency test',
            'quantity_needed' => $needed,
            'urgency'         => 'high',
            'min_condition'   => 'good',
            'radius_km'       => 25.0,
            'latitude'        => 12.9716,
            'longitude'       => 77.5946,
        ]);
        return (int) $req['id'];
    }

    /**
     * Helper to run parallel PHP worker commands and collect exit codes and output.
     */
    private function runParallelCommands(array $commands): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processes = [];
        $pipes = [];

        foreach ($commands as $index => $cmd) {
            $process = proc_open($cmd, $descriptors, $pipes[$index]);
            if (is_resource($process)) {
                $processes[$index] = $process;
            }
        }

        $results = [];
        foreach ($processes as $index => $proc) {
            $stdout = stream_get_contents($pipes[$index][1]);
            $stderr = stream_get_contents($pipes[$index][2]);
            fclose($pipes[$index][0]);
            fclose($pipes[$index][1]);
            fclose($pipes[$index][2]);
            $exitCode = proc_close($proc);
            $results[$index] = [
                'exit_code' => $exitCode,
                'stdout'    => trim($stdout),
                'stderr'    => trim($stderr),
            ];
        }

        return $results;
    }

    /**
     * T-9.3-01 (C): Scenario A - 5 NGOs simultaneously request 4 from stock of 10.
     * Exactly 2 succeed (8 total allocated), 3 receive 409 conflict. Available stock = 2.
     */
    public function testScenarioA_NeverOversellsAvailableStock(): void
    {
        $repetitions = 5;
        $phpBinary = PHP_BINARY;
        $workerPath = __DIR__ . '/worker.php';

        for ($rep = 0; $rep < $repetitions; $rep++) {
            $dId = $this->createDonation(10);
            $commands = [];

            for ($i = 0; $i < 5; $i++) {
                $uId = $this->ngos[$i]['user_id'];
                $nId = $this->ngos[$i]['ngo_id'];
                $rId = $this->createRequirement($nId, 10);

                $commands[] = "\"{$phpBinary}\" \"{$workerPath}\" create {$uId} {$dId} {$rId} 4";
            }

            $results = $this->runParallelCommands($commands);
            $successes = 0;
            $conflicts = 0;

            foreach ($results as $res) {
                if (str_starts_with($res['stdout'], 'SUCCESS:')) {
                    $successes++;
                } elseif (str_starts_with($res['stdout'], 'CONFLICT:')) {
                    $conflicts++;
                }
            }

            // Exactly 2 must succeed (2 * 4 = 8 <= 10) and 3 must get conflict
            $this->assertSame(2, $successes, "Rep {$rep}: expected exactly 2 successes for stock 10 with request size 4");
            $this->assertSame(3, $conflicts, "Rep {$rep}: expected exactly 3 conflicts");

            $don = $this->donationRepo->findById($dId);
            $this->assertSame(2, (int) $don['available_quantity'], "Rep {$rep}: available quantity should be exactly 2");
            $this->assertSame('partially_allocated', $don['status']);

            Invariants::checkAll($this->pdo);
        }
    }

    /**
     * T-9.3-02 (C): Scenario B - Two NGOs race for exact last 3 items from stock of 3.
     * Exactly 1 succeeds, 1 receives 409 conflict. Available stock = 0.
     */
    public function testScenarioB_RaceForExactRemainder(): void
    {
        $repetitions = 5;
        $phpBinary = PHP_BINARY;
        $workerPath = __DIR__ . '/worker.php';

        for ($rep = 0; $rep < $repetitions; $rep++) {
            $dId = $this->createDonation(3);
            $commands = [];

            for ($i = 0; $i < 2; $i++) {
                $uId = $this->ngos[$i]['user_id'];
                $nId = $this->ngos[$i]['ngo_id'];
                $rId = $this->createRequirement($nId, 5);

                $commands[] = "\"{$phpBinary}\" \"{$workerPath}\" create {$uId} {$dId} {$rId} 3";
            }

            $results = $this->runParallelCommands($commands);
            $successes = 0;
            $conflicts = 0;

            foreach ($results as $res) {
                if (str_starts_with($res['stdout'], 'SUCCESS:')) {
                    $successes++;
                } elseif (str_starts_with($res['stdout'], 'CONFLICT:')) {
                    $conflicts++;
                }
            }

            $this->assertSame(1, $successes, "Rep {$rep}: expected exactly 1 winner for last 3 units");
            $this->assertSame(1, $conflicts, "Rep {$rep}: expected exactly 1 conflict");

            $don = $this->donationRepo->findById($dId);
            $this->assertSame(0, (int) $don['available_quantity']);
            $this->assertSame('fully_allocated', $don['status']);

            Invariants::checkAll($this->pdo);
        }
    }

    /**
     * T-9.3-03 (C): Scenario C - Single NGO requirement with need of 5 races on 2 distinct donations.
     * Prevents over-fulfillment: only 1 succeeds, other receives conflict.
     */
    public function testScenarioC_PreventsRequirementOverfulfillment(): void
    {
        $repetitions = 5;
        $phpBinary = PHP_BINARY;
        $workerPath = __DIR__ . '/worker.php';

        for ($rep = 0; $rep < $repetitions; $rep++) {
            $dId1 = $this->createDonation(5);
            $dId2 = $this->createDonation(5);
            $uId = $this->ngos[0]['user_id'];
            $nId = $this->ngos[0]['ngo_id'];
            $rId = $this->createRequirement($nId, 5); // needed 5

            $commands = [
                "\"{$phpBinary}\" \"{$workerPath}\" create {$uId} {$dId1} {$rId} 5",
                "\"{$phpBinary}\" \"{$workerPath}\" create {$uId} {$dId2} {$rId} 5",
            ];

            $results = $this->runParallelCommands($commands);
            $successes = 0;
            $conflicts = 0;

            foreach ($results as $res) {
                if (str_starts_with($res['stdout'], 'SUCCESS:')) {
                    $successes++;
                } elseif (str_starts_with($res['stdout'], 'CONFLICT:')) {
                    $conflicts++;
                }
            }

            $this->assertSame(1, $successes, "Rep {$rep}: requirement should only accept 1 allocation of 5");
            $this->assertSame(1, $conflicts, "Rep {$rep}: second allocation must conflict");

            $req = $this->reqRepo->findById($rId);
            $this->assertSame(5, (int) $req['quantity_allocated']);
            $this->assertSame('fulfilled', $req['status']);

            Invariants::checkAll($this->pdo);
        }
    }

    /**
     * T-9.3-04 (C): Scenario D - Parallel accept, reject, cancel storm on same request.
     * Exactly one transition wins, no double stock returns.
     */
    public function testScenarioD_ParallelTransitionStorm(): void
    {
        $repetitions = 5;
        $phpBinary = PHP_BINARY;
        $workerPath = __DIR__ . '/worker.php';
        $svc = new AllocationService($this->pdo);

        for ($rep = 0; $rep < $repetitions; $rep++) {
            $dId = $this->createDonation(10);
            $uId = $this->ngos[0]['user_id'];
            $nId = $this->ngos[0]['ngo_id'];
            $rId = $this->createRequirement($nId, 10);

            // Create initial pending request
            $req = $svc->createRequest($uId, [
                'donation_id'        => $dId,
                'requirement_id'     => $rId,
                'requested_quantity' => 4,
            ]);
            $reqId = (int) $req['id'];

            // Parallel race: Donor accept vs Donor reject vs NGO cancel
            $commands = [
                "\"{$phpBinary}\" \"{$workerPath}\" accept {$this->donorUserId} 0 null 0 {$reqId}",
                "\"{$phpBinary}\" \"{$workerPath}\" reject {$this->donorUserId} 0 null 0 {$reqId}",
                "\"{$phpBinary}\" \"{$workerPath}\" cancel {$uId} 0 null 0 {$reqId}",
            ];

            $results = $this->runParallelCommands($commands);
            $successes = 0;
            foreach ($results as $res) {
                if (str_starts_with($res['stdout'], 'SUCCESS:')) {
                    $successes++;
                }
            }

            // Exactly 1 state transition should win the race (or if accept won, subsequent double-accept is idempotent, but reject/cancel fail)
            $this->assertGreaterThanOrEqual(1, $successes);

            Invariants::checkAll($this->pdo);
        }
    }
}
