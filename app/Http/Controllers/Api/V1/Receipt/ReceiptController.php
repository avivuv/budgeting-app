<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Receipt;

use App\Enums\ReceiptConfirmMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Receipt\ConfirmReceiptRequest;
use App\Http\Requests\Api\V1\Receipt\ReceiptDraftRequest;
use App\Http\Requests\Api\V1\Receipt\UploadReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Receipt\ReceiptService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * POST /receipts → GET /receipts/{id} (draft) → PATCH (corrections) →
 * POST /receipts/{id}/confirm (docs/api-contracts/receipts.md). No
 * transaction exists before confirm (BR-H08).
 */
class ReceiptController extends Controller
{
    private const array RELATIONS = ['items', 'transactions.wallet', 'transactions.category', 'transactions.creator', 'transactions.tags'];

    public function __construct(
        private readonly ReceiptService $receipts,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Receipt::class);

        return ReceiptResource::collection($this->receipts->recent(20))->additional(['meta' => [
            'quota' => $this->receipts->quota(),
            'max_file_mb' => (int) config('receipt.max_file_mb'),
        ]]);
    }

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

    /**
     * The file is never public: members of the family only (docs/06 §7).
     */
    public function image(Receipt $receipt, FileStorageService $files): StreamedResponse
    {
        Gate::authorize('view', $receipt);

        abort_unless($receipt->file_path !== null && $files->exists($receipt->file_path), 404);

        return $files->response($receipt->file_path, $receipt->mime_type);
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
        $receipt = $this->receipts->confirm($receipt, $request->mode(), $user)->load(self::RELATIONS);
        $count = $receipt->transactions->count();

        return (new ReceiptResource($receipt))
            ->additional(['message' => $request->mode() === ReceiptConfirmMode::Split && $count > 1
                ? "Struk tersimpan sebagai {$count} transaksi."
                : 'Struk tersimpan sebagai transaksi.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Receipt $receipt): JsonResponse
    {
        Gate::authorize('update', $receipt);

        return (new ReceiptResource($this->receipts->reject($receipt)->load(self::RELATIONS)))
            ->additional(['message' => 'Struk dibatalkan, tidak ada transaksi yang dibuat.'])
            ->response();
    }
}
