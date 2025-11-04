<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Customer\CreateCustomerDTO;
use App\DTOs\Customer\UpdateCustomerDTO;
use App\Exceptions\CustomerAlreadyExistsException;
use App\Exceptions\CustomerException;
use App\Exceptions\CustomerNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerCollection;
use App\Http\Resources\CustomerResource;
use App\Services\Customer\CustomerQueryService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
        private readonly CustomerQueryService $queryService
    ) {
    }

    public function index(Request $request): CustomerCollection
    {
        $perPage = (int) $request->query('per_page', '15');
        $search = $request->query('search');

        $customers = $search
            ? $this->queryService->search($search, $perPage)
            : $this->queryService->getAll($perPage);

        return new CustomerCollection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $dto = CreateCustomerDTO::fromRequest($request->validated());
            $customer = $this->service->create($dto);

            return (new CustomerResource($customer))
                ->response()
                ->setStatusCode(JsonResponse::HTTP_CREATED);
        } catch (CustomerAlreadyExistsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_CONFLICT);
        }
    }

    public function show(int $id): JsonResponse
    {
        $customer = $this->queryService->findById($id);

        if ($customer === null) {
            return response()->json([
                'message' => 'Cliente não encontrado',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return (new CustomerResource($customer))->response();
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateCustomerDTO::fromRequest($request->validated());
            $customer = $this->service->update($id, $dto);

            return (new CustomerResource($customer))->response();
        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_NOT_FOUND);
        } catch (CustomerAlreadyExistsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_CONFLICT);
        } catch (CustomerException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);

            return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_NOT_FOUND);
        } catch (CustomerException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
