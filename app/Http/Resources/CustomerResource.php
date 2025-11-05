<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status->value,
            'is_active' => $this->isActive(),
            'charges_count' => $this->whenLoaded('charges',
                fn() => $this->charges->count()
            ),
            'recent_charges' => ChargeResource::collection(
                $this->whenLoaded('charges')
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
