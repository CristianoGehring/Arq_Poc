<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'credentials',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'array',
    ];
}
