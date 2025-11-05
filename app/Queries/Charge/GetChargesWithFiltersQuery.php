<?php

namespace App\Queries\Charge;

use App\Models\Charge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetChargesWithFiltersQuery
{
    /**
     * Busca cobranças com filtros avançados
     */
    public function execute(
        ?array $statuses = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $customerId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Charge::query()
            ->with(['customer', 'paymentGateway'])
            ->when($statuses, fn($q) => $q->whereIn('status', $statuses))
            ->when($dateFrom, fn($q) => $q->whereDate('due_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('due_date', '<=', $dateTo))
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->latest()
            ->paginate($perPage);
    }
}
