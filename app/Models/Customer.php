<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'document',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'address' => 'array',
        'status' => CustomerStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::ACTIVE);
    }

    public function scopeByDocument(Builder $query, string $document): void
    {
        $query->where('document', $document);
    }

    // Accessors
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }
}
