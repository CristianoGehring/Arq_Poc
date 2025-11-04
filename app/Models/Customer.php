<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $document
 * @property string|null $phone
 * @property array|null $address
 * @property CustomerStatus $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static Builder active()
 * @method static Builder byDocument(string $document)
 */
class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

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

    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::ACTIVE);
    }

    public function scopeByDocument(Builder $query, string $document): void
    {
        $query->where('document', $document);
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === CustomerStatus::BLOCKED;
    }

    public function canBeModified(): bool
    {
        return !$this->isBlocked();
    }
}
