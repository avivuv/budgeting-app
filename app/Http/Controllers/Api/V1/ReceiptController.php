<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmReceiptRequest;
use App\Http\Requests\Api\V1\ReceiptDraftRequest;
use App\Http\Requests\Api\V1\UploadReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Receipt\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * POST /receipts → GET /receipts/{id} (draft) → PATCH (corrections) →
 * POST /receipts/{id}/confirm (docs/13 §3).
 */
class ReceiptController extends Controller
{
    private const array RELATIONS = ['items', 'transactions.wallet', 'transactions.category', 'transactions.creator', 'transactions.tags'];

    public function __construct(
        private readonly ReceiptService $receipts,
    ) {}

    public function store(UploadReceiptRequest $request): JsonResponse
    {
        Gate::authorize('create', Receipt::class);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $receipt = $this->receipts->store((string) $file->getRealPath(), $file->getClientOriginalName(), $user);

        return (new ReceiptResource($receipt->refresh()->load(self::RELATIONS)))
            ->additional(['meta' => ['quota' => $this->receipts->quota()]])
            ->response()
            ->setStatusCode(202);
    }

    public function show(Receipt $receipt): ReceiptResource
    {
        Gate::authorize('view', $receipt);

        return new ReceiptResource($receipt->load(self::RELATIONS));
    }

    public function update(ReceiptDraftRequest $request, Receipt $receipt): ReceiptResource
    {
        Gate::authorize('update', $receipt);

        return new ReceiptResource($this->receipts->update($receipt, $request->toDraft())->load(self::RELATIONS));
    }

    public function confirm(ConfirmReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        Gate::authorize('update', $receipt);

        /** @var User $user */
        $user = $request->user();
        $receipt = $this->receipts->confirm($receipt, $request->mode(), $user);

        return (new ReceiptResource($receipt->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function destroy(Receipt $receipt): ReceiptResource
    {
        Gate::authorize('update', $receipt);

        return new ReceiptResource($this->receipts->reject($receipt)->load(self::RELATIONS));
    }
}
