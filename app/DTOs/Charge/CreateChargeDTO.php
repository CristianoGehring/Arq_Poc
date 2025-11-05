<?php

namespace App\DTOs\Charge;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;

readonly class CreateChargeDTO
{
    public function __construct(
        public int $customerId,
        public float $amount,
        public string $description,
        public PaymentMethod $paymentMethod,
        public string $dueDate,
        public ?int $paymentGatewayId = null,
        public ?array $metadata = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            customerId: $data['customer_id'],
            amount: $data['amount'],
            description: $data['description'],
            paymentMethod: PaymentMethod::from($data['payment_method']),
            dueDate: $data['due_date'],
            paymentGatewayId: $data['payment_gateway_id'] ?? null,
            metadata: $data['metadata'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'payment_gateway_id' => $this->paymentGatewayId,
            'amount' => $this->amount,
            'description' => $this->description,
            'payment_method' => $this->paymentMethod,
            'status' => ChargeStatus::PENDING,
            'due_date' => $this->dueDate,
            'metadata' => $this->metadata,
        ];
    }
}
