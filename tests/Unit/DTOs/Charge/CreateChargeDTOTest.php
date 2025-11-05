<?php

namespace Tests\Unit\DTOs\Charge;

use App\DTOs\Charge\CreateChargeDTO;
use App\Enums\PaymentMethod;
use Tests\TestCase;

class CreateChargeDTOTest extends TestCase
{
    /** @test */
    public function it_can_be_created_from_request_array(): void
    {
        $data = [
            'customer_id' => 1,
            'amount' => 100.50,
            'description' => 'Test Description',
            'payment_method' => 'pix',
            'due_date' => '2025-12-31',
            'payment_gateway_id' => 2,
            'metadata' => ['key' => 'value'],
        ];

        $dto = CreateChargeDTO::fromRequest($data);

        $this->assertInstanceOf(CreateChargeDTO::class, $dto);
        $this->assertEquals(1, $dto->customerId);
        $this->assertEquals(100.50, $dto->amount);
        $this->assertEquals('Test Description', $dto->description);
        $this->assertEquals(PaymentMethod::PIX, $dto->paymentMethod);
        $this->assertEquals('2025-12-31', $dto->dueDate);
        $this->assertEquals(2, $dto->paymentGatewayId);
        $this->assertEquals(['key' => 'value'], $dto->metadata);
    }

    /** @test */
    public function it_can_be_converted_to_array(): void
    {
        $dto = new CreateChargeDTO(
            customerId: 1,
            amount: 100.50,
            description: 'Test Description',
            paymentMethod: PaymentMethod::PIX,
            dueDate: '2025-12-31',
            paymentGatewayId: 2,
            metadata: ['key' => 'value']
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('customer_id', $array);
        $this->assertEquals(1, $array['customer_id']);
    }
}
