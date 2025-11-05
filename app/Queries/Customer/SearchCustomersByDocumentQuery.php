<?php

namespace App\Queries\Customer;

use App\Models\Customer;

class SearchCustomersByDocumentQuery
{
    /**
     * Busca cliente por documento
     */
    public function execute(string $document): ?Customer
    {
        return Customer::where('document', $document)->first();
    }
}
