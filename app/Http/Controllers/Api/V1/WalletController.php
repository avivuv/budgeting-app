<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Wallet\WalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Wallet::class);

        $wallets = $this->wallets->list($request->boolean('include_archived'));
        $balances = $this->wallets->balances($wallets);

        return response()->json([
            'data' => $wallets->map(fn (Wallet $wallet) => (new WalletResource($wallet, $balances))->resolve($request))->values(),
            'meta' => ['total_balance' => $this->wallets->totalBalance()],
        ]);
    }

    public function store(WalletRequest $request): JsonResponse
    {
        Gate::authorize('create', Wallet::class);

        $wallet = $this->wallets->create($request->toData());

        return (new WalletResource($wallet, $this->wallets->balances([$wallet])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(WalletRequest $request, Wallet $wallet): WalletResource
    {
        Gate::authorize('update', $wallet);

        $wallet = $this->wallets->update($wallet, $request->toData());

        return new WalletResource($wallet, $this->wallets->balances([$wallet]));
    }

    public function destroy(Wallet $wallet): Response
    {
        Gate::authorize('delete', $wallet);

        $this->wallets->delete($wallet);

        return response()->noContent();
    }
}
