<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use App\DTOs\Transaction\ParsedTransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\BulkTransactionRequest;
use App\Http\Requests\Api\V1\Transaction\ParseTransactionRequest;
use App\Http\Requests\Api\V1\Transaction\TransactionFilterRequest;
use App\Http\Requests\Api\V1\Transaction\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Activity\ActivityService;
use App\Services\Transaction\TransactionParser;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Transactions of the active family (docs/api-contracts/transactions.md).
 */
class TransactionController extends Controller
{
    private const array RELATIONS = ['wallet', 'toWallet', 'category', 'creator', 'tags'];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly WalletService $wallets,
    ) {}

    /**
     * meta.totals covers the whole filter; meta.daily the dates on this page (INV-06).
     */
    public function index(TransactionFilterRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Transaction::class);

        $filter = $request->toFilter();
        $page = $this->transactions->paginate($filter, $request->perPage());
        $dates = array_values(array_unique(array_map(fn (Transaction $t): string => $t->date->toDateString(), $page->items())));

        return TransactionResource::collection($page)->additional(['meta' => [
            'totals' => $this->transactions->totals($filter),
            'daily' => (object) $this->transactions->dailyTotals($filter, $dates),
        ]]);
    }

    /**
     * Tags used by the family, for suggestions while typing.
     */
    public function tags(): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        $tags = $this->transactions->tagNames();
        sort($tags);

        return response()->json(['data' => $tags]);
    }

    /**
     * Free text → transaction preview, without saving (docs/13-rest-api.md §3).
     */
    public function parse(ParseTransactionRequest $request, TransactionParser $parser): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        // Same keys as every other endpoint (snake_case), unlike the camelCase DTO.
        $previews = array_map(
            fn (ParsedTransactionData $preview): array => collect($preview->toArray())->mapWithKeys(fn ($value, string $key) => [Str::snake($key) => $value])->all(),
            $parser->parse($request->text()),
        );

        return response()->json(['data' => $previews]);
    }

    public function store(TransactionRequest $request): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        /** @var User $user */
        $user = $request->user();
        $transaction = $this->transactions->create($request->toData(), $user);

        return $this->withWarnings($transaction)->response()->setStatusCode(201);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function update(TransactionRequest $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('update', $transaction);

        return $this->withWarnings($this->transactions->update($transaction, $request->toData()));
    }

    public function history(Transaction $transaction, ActivityService $activity): JsonResponse
    {
        Gate::authorize('view', $transaction);

        $entries = array_map(
            fn (array $entry): array => array_diff_key($entry, ['when' => true]),
            $activity->transactionHistory($transaction),
        );

        return response()->json(['data' => $entries]);
    }

    /**
     * All or nothing: every transaction is authorized before anything changes (BR-B09).
     */
    public function bulk(BulkTransactionRequest $request): JsonResponse
    {
        $transactions = $this->transactions->findMany($request->ids());
        $delete = $request->validated('action') === 'delete';

        foreach ($transactions as $transaction) {
            Gate::authorize($delete ? 'delete' : 'update', $transaction);
        }

        $count = $delete
            ? $this->transactions->deleteMany($transactions)
            : $this->transactions->changeCategory($transactions->load('tags'), (int) $request->validated('category_id'));

        return response()->json([
            'message' => $delete ? "{$count} transaksi dihapus." : "Kategori {$count} transaksi diubah.",
            'meta' => ['affected' => $count],
        ]);
    }

    public function destroy(Transaction $transaction): Response
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction);

        return response()->noContent();
    }

    /**
     * BR-C03: a transaction may push a wallet below zero; it is saved, with a warning.
     */
    private function withWarnings(Transaction $transaction): TransactionResource
    {
        return (new TransactionResource($transaction->load(self::RELATIONS)))
            ->additional(['meta' => ['warnings' => $this->wallets->negativeBalanceWarnings([$transaction->wallet_id, $transaction->to_wallet_id])]]);
    }
}
