<?php

namespace Tests\Unit\Actions\Charge;

use App\Actions\Charge\CancelChargeAction;
use App\Enums\ChargeStatus;
use App\Exceptions\ChargeCannotBeCancelledException;
use App\Exceptions\ChargeNotFoundException;
use App\Models\Charge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelChargeActionTest extends TestCase
{
    use RefreshDatabase;

    private CancelChargeAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CancelChargeAction();
    }

    /** @test */
    public function it_cancels_pending_charge_successfully(): void
    {
        $charge = Charge::factory()->create(['status' => ChargeStatus::PENDING]);

        $result = $this->action->execute($charge->id, 'Test reason');

        $this->assertEquals(ChargeStatus::CANCELLED, $result->status);
        $this->assertArrayHasKey('cancellation_reason', $result->metadata);
    }

    /** @test */
    public function it_throws_exception_when_cancelling_paid_charge(): void
    {
        $charge = Charge::factory()->create(['status' => ChargeStatus::PAID]);

        $this->expectException(ChargeCannotBeCancelledException::class);

        $this->action->execute($charge->id, 'Test reason');
    }

    /** @test */
    public function it_throws_exception_for_nonexistent_charge(): void
    {
        $this->expectException(ChargeNotFoundException::class);

        $this->action->execute(999, 'Test reason');
    }
}
