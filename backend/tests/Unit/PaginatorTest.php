<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Request;
use App\Support\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testDefaultPaginationValues(): void
    {
        $req = new Request('GET', '/api/notifications');
        $params = Paginator::fromRequest($req);

        $this->assertSame(1, $params['page']);
        $this->assertSame(20, $params['per_page']);
        $this->assertSame(20, $params['limit']);
        $this->assertSame(0, $params['offset']);
    }

    public function testClampingOutOfBounds(): void
    {
        // Negative page and excessive per_page
        $req = new Request('GET', '/api/notifications', ['page' => '-5', 'per_page' => '1000']);
        $params = Paginator::fromRequest($req);

        $this->assertSame(1, $params['page']);
        $this->assertSame(50, $params['per_page']); // Clamped to max 50
        $this->assertSame(0, $params['offset']);

        // High page offset calculation
        $req2 = new Request('GET', '/api/notifications', ['page' => '3', 'per_page' => '10']);
        $params2 = Paginator::fromRequest($req2);

        $this->assertSame(3, $params2['page']);
        $this->assertSame(10, $params2['per_page']);
        $this->assertSame(20, $params2['offset']);
    }

    public function testBuildMetaCalculations(): void
    {
        $meta = Paginator::buildMeta(45, 2, 20);

        $this->assertSame(2, $meta['current_page']);
        $this->assertSame(20, $meta['per_page']);
        $this->assertSame(45, $meta['total_records']);
        $this->assertSame(3, $meta['total_pages']);
        $this->assertTrue($meta['has_next']);
        $this->assertTrue($meta['has_prev']);
    }
}
