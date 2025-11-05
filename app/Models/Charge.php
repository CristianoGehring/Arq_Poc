<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'payment_gateway_id',
        'gateway_charge_id',
        'amount',
        'description',
        'payment_method',
        'status',
        'due_date',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status' => ChargeStatus::class,
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    // Scopes
    public function scopePaid(Builder $query): void
    {
        $query->where('status', ChargeStatus::PAID);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', ChargeStatus::PENDING);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', ChargeStatus::PENDING)
            ->where('due_date', '<', now());
    }

    public function scopeDueToday(Builder $query): void
    {
        $query->where('status', ChargeStatus::PENDING)
            ->whereDate('due_date', today());
    }

    public function scopeByCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
    }

    // Accessors
    public function isPaid(): bool
    {
        return $this->status === ChargeStatus::PAID;
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && !$this->isPaid();
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, [
            ChargeStatus::PAID,
            ChargeStatus::CANCELLED,
            ChargeStatus::REFUNDED
        ]);
    }

    public function canBeUpdated(): bool
    {
        return !in_array($this->status, [
            ChargeStatus::PAID,
            ChargeStatus::CANCELLED,
            ChargeStatus::REFUNDED
        ]);
    }
}