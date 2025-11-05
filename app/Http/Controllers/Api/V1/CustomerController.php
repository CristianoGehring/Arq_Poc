<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customer\CreateCustomerAction;
use App\Actions\Customer\DeleteCustomerAction;
use App\Actions\Customer\UpdateCustomerAction;
use App\Actions\Customer\ActivateCustomerAction;
use App\Actions\Customer\DeactivateCustomerAction;
use App\Actions\Customer\BlockCustomerAction;
use App\DTOs\Customer\CreateCustomerDTO;
use App\DTOs\Customer\UpdateCustomerDTO;
use App\Exceptions\CustomerNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Queries\Customer\GetAllCustomersQuery;
use App\Queries\Customer\GetCustomerByIdQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * Lista todos clientes
     */
    public function index(GetAllCustomersQuery $query): AnonymousResourceCollection
    {
        $customers = $query->execute(
            perPage: request('per_page', 15)
        );

        return CustomerResource::collection($customers);
    }

    /**
     * Exibe cliente específico
     */
    public function show(int $id, GetCustomerByIdQuery $query): CustomerResource
    {
        $customer = $query->execute($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        return new CustomerResource($customer);
    }

    /**
     * Cria novo cliente
     */
    public function store(
        StoreCustomerRequest $request,
        CreateCustomerAction $action
    ): JsonResponse {
        $dto = CreateCustomerDTO::fromRequest($request->validated());

        $customer = $action->execute($dto);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza cliente
     */
    public function update(
        int $id,
        UpdateCustomerRequest $request,
        UpdateCustomerAction $action
    ): CustomerResource {
        $dto = UpdateCustomerDTO::fromRequest($request->validated());

        $customer = $action->execute($id, $dto);

        return new CustomerResource($customer);
    }

    /**
     * Remove cliente (soft delete)
     */
    public function destroy(
        int $id,
        DeleteCustomerAction $action
    ): JsonResponse {
        $action->execute($id);

        return response()->json(null, 204);
    }

    /**
     * Ativa cliente
     */
    public function activate(int $id, ActivateCustomerAction $action): CustomerResource
    {
        $customer = $action->execute($id);

        return new CustomerResource($customer);
    }

    /**
     * Desativa cliente
     */
    public function deactivate(int $id, DeactivateCustomerAction $action): CustomerResource
    {
        $customer = $action->execute($id);

        return new CustomerResource($customer);
    }

    /**
     * Bloqueia cliente
     */
    public function block(int $id, BlockCustomerAction $action): CustomerResource
    {
        $customer = $action->execute($id);

        return new CustomerResource($customer);
    }
}
