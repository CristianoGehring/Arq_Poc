<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'payment_gateway_id' => $this->payment_gateway_id,
            'gateway_charge_id' => $this->gateway_charge_id,
            'amount' => $this->amount,
            'description' => $this->description,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'due_date' => $this->due_date->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'is_paid' => $this->isPaid(),
            'is_overdue' => $this->isOverdue(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_updated' => $this->canBeUpdated(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}