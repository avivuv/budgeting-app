<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ParseTransactionRequest;
use App\Http\Requests\Api\V1\TransactionFilterRequest;
use App\Http\Requests\Api\V1\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionParser;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    private const array RELATIONS = ['wallet', 'toWallet', 'category', 'creator', 'tags'];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly WalletService $wallets,
    ) {}

    /**
     * Same filter as the web list (docs/13-rest-api.md §3); totals of the whole
     * filter are returned in meta.totals.
     */
    public function index(TransactionFilterRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Transaction::class);

        $filter = $request->toFilter();

        return TransactionResource::collection($this->transactions->paginate($filter, $request->perPage()))
            ->additional(['meta' => ['totals' => $this->transactions->totals($filter)]]);
    }

    /**
     * Free text → transaction preview, without saving (docs/13-rest-api.md §3).
     */
    public function parse(ParseTransactionRequest $request, TransactionParser $parser): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        return response()->json(['data' => $parser->parse($request->text())]);
    }

    public function store(TransactionRequest $request): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        /** @var User $user */
        $user = $request->user();
        $transaction = $this->transactions->create($request->toData(), $user);

        return (new TransactionResource($transaction->load(self::RELATIONS)))
            ->additional(['meta' => ['warnings' => $this->wallets->negativeBalanceWarnings([$transaction->wallet_id, $transaction->to_wallet_id])]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function update(TransactionRequest $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('update', $transaction);

        $transaction = $this->transactions->update($transaction, $request->toData());

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function destroy(Transaction $transaction): Response
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction);

        return response()->noContent();
    }
}
