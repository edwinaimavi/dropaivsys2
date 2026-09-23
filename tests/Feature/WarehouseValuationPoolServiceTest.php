<?php

namespace Tests\Feature;

use App\Models\WarehouseValuationPool;
use App\Services\WarehouseValuationPoolService;
use Closure;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseValuationPoolServiceTest extends TestCase
{
    private TestableWarehouseValuationPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TestableWarehouseValuationPoolService;
    }

    public function test_calculates_weighted_average_for_an_entry(): void
    {
        $this->service->lockedPool = $this->pool('10.0000', '20.000000', '200.00');

        $pool = $this->service->addQuantityAtCost(
            $this->service->lockedPool,
            '10',
            '300'
        );

        $this->assertSame('20.0000', $pool->current_quantity);
        $this->assertSame('500.00', $pool->total_cost);
        $this->assertSame('25.000000', $pool->average_unit_cost);
    }

    public function test_removes_quantity_using_current_average_cost(): void
    {
        $this->service->lockedPool = $this->pool('20.0000', '25.000000', '500.00');

        $result = $this->service->removeQuantityAtAverage($this->service->lockedPool, '4');

        $this->assertSame('25.000000', $result['unit_cost']);
        $this->assertSame('100.00', $result['total_cost']);
        $this->assertSame('16.0000', $result['pool']->current_quantity);
        $this->assertSame('400.00', $result['pool']->total_cost);
        $this->assertSame('25.000000', $result['pool']->average_unit_cost);
    }

    public function test_reenters_quantity_at_historical_cost(): void
    {
        $this->service->lockedPool = $this->pool('16.0000', '25.000000', '400.00');

        $pool = $this->service->addQuantityAtCost($this->service->lockedPool, '2', '40');

        $this->assertSame('18.0000', $pool->current_quantity);
        $this->assertSame('440.00', $pool->total_cost);
        $this->assertSame('24.444444', $pool->average_unit_cost);
    }

    public function test_clears_all_amounts_when_final_quantity_is_removed(): void
    {
        $this->service->lockedPool = $this->pool('18.0000', '24.444444', '440.00');

        $result = $this->service->removeQuantityAtAverage($this->service->lockedPool, '18');

        $this->assertSame('0.0000', $result['pool']->current_quantity);
        $this->assertSame('0.000000', $result['pool']->average_unit_cost);
        $this->assertSame('0.00', $result['pool']->total_cost);
    }

    public function test_rejects_output_greater_than_pool_quantity(): void
    {
        $this->service->lockedPool = $this->pool('20.0000', '25.000000', '500.00');

        $this->expectException(ValidationException::class);

        $this->service->removeQuantityAtAverage($this->service->lockedPool, '20.0001');
    }

    public function test_rejects_zero_pool_creation_when_previous_stock_has_balance(): void
    {
        $this->service->lockedPool = null;
        $this->service->balance = [
            'quantity' => '1.0000',
            'total_cost' => '0.00',
        ];

        $this->expectException(ValidationException::class);

        $this->service->lockPool(1, 1, 1);
    }

    private function pool(string $quantity, string $averageUnitCost, string $totalCost): WarehouseValuationPool
    {
        return new WarehouseValuationPool([
            'company_id' => 1,
            'warehouse_id' => 1,
            'article_id' => 1,
            'current_quantity' => $quantity,
            'average_unit_cost' => $averageUnitCost,
            'total_cost' => $totalCost,
        ]);
    }
}

class TestableWarehouseValuationPoolService extends WarehouseValuationPoolService
{
    public ?WarehouseValuationPool $lockedPool = null;

    public array $balance = [
        'quantity' => '0.0000',
        'total_cost' => '0.00',
    ];

    protected function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    protected function findPoolForUpdate(
        int $companyId,
        int $warehouseId,
        int $articleId
    ): ?WarehouseValuationPool {
        return $this->lockedPool;
    }

    protected function stockBalance(int $companyId, int $warehouseId, int $articleId): array
    {
        return $this->balance;
    }

    protected function createZeroPool(
        int $companyId,
        int $warehouseId,
        int $articleId,
        ?int $userId
    ): void {
        $this->lockedPool = new WarehouseValuationPool([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'current_quantity' => '0.0000',
            'average_unit_cost' => '0.000000',
            'total_cost' => '0.00',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    protected function persistPool(WarehouseValuationPool $pool): void
    {
        $this->lockedPool = $pool;
    }
}
