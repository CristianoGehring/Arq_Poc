<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Charge\CancelChargeAction;
use App\Actions\Charge\CreateChargeAction;
use App\Actions\Charge\UpdateChargeAction;
use App\DTOs\Charge\CreateChargeDTO;
use App\DTOs\Charge\UpdateChargeDTO;
use App\Exceptions\ChargeNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Charge\CancelChargeRequest;
use App\Http\Requests\Charge\ListChargesRequest;
use App\Http\Requests\Charge\StoreChargeRequest;
use App\Http\Requests\Charge\UpdateChargeRequest;
use App\Http\Resources\ChargeResource;
use App\Jobs\SyncChargeStatusJob;
use App\Models\Charge;
use App\Queries\Charge\GetChargeByIdQuery;
use App\Queries\Charge\GetChargesWithFiltersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChargeController extends Controller
{
    /**
     * Lista todas as cobranças
     */
    public function index(
        ListChargesRequest $request,
        GetChargesWithFiltersQuery $query
    ): AnonymousResourceCollection {
        $charges = $query->execute(
            statuses: $request->input('status'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            customerId: $request->input('customer_id'),
            perPage: $request->input('per_page', 15)
        );

        return ChargeResource::collection($charges);
    }

    /**
     * Exibe cobrança específica
     */
    public function show(int $id, GetChargeByIdQuery $query): ChargeResource
    {
        $charge = $query->execute($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        return new ChargeResource($charge);
    }

    /**
     * Cria nova cobrança
     */
    public function store(
        StoreChargeRequest $request,
        CreateChargeAction $action
    ): JsonResponse {
        $dto = CreateChargeDTO::fromRequest($request->validated());

        $charge = $action->execute($dto);

        return (new ChargeResource($charge))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza cobrança
     */
    public function update(
        int $id,
        UpdateChargeRequest $request,
        UpdateChargeAction $action
    ): ChargeResource {
        $dto = UpdateChargeDTO::fromRequest($request->validated());

        $charge = $action->execute($id, $dto);

        return new ChargeResource($charge);
    }

    /**
     * Remove cobrança (soft delete)
     */
    public function destroy(
        int $id,
        CancelChargeAction $action
    ): JsonResponse {
        $action->execute($id, 'Deleted via API');

        return response()->json(null, 204);
    }

    /**
     * Cancela cobrança
     */
    public function cancel(
        int $id,
        CancelChargeRequest $request,
        CancelChargeAction $action
    ): ChargeResource {
        $charge = $action->execute($id, $request->input('reason', ''));

        return new ChargeResource($charge);
    }

    /**
     * Sincroniza status com gateway
     */
    public function syncWithGateway(int $id): JsonResponse
    {
        $charge = Charge::find($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        SyncChargeStatusJob::dispatch($charge->id);

        return response()->json([
            'message' => 'Charge sync queued successfully',
            'charge_id' => $charge->id,
        ], 202);
    }
}
