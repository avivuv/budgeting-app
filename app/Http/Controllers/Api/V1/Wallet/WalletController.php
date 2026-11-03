<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\ArchiveWalletRequest;
use App\Http\Requests\Api\V1\Wallet\WalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Wallets of the active family (docs/api-contracts/wallets.md).
 */
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
        $total = $this->wallets->totalBalance();

        return response()->json([
            'data' => $wallets->map(fn (Wallet $wallet) => (new WalletResource($wallet, $balances))->resolve($request))->values(),
            'meta' => [
                'total_balance' => $total,
                'total_balance_formatted' => MoneyFormatter::format($total),
            ],
        ]);
    }

    public function store(WalletRequest $request): JsonResponse
    {
        Gate::authorize('create', Wallet::class);

        $wallet = $this->wallets->create($request->toData());

        return $this->resource($wallet)->response()->setStatusCode(201);
    }

    public function update(WalletRequest $request, Wallet $wallet): WalletResource
    {
        Gate::authorize('update', $wallet);

        return $this->resource($this->wallets->update($wallet, $request->toData()));
    }

    public function archive(ArchiveWalletRequest $request, Wallet $wallet): WalletResource
    {
        Gate::authorize('update', $wallet);

        return $this->resource($this->wallets->setArchived($wallet, $request->boolean('archived')));
    }

    public function destroy(Wallet $wallet): Response
    {
        Gate::authorize('delete', $wallet);

        $this->wallets->delete($wallet);

        return response()->noContent();
    }

    private function resource(Wallet $wallet): WalletResource
    {
        return new WalletResource($wallet, $this->wallets->balances([$wallet]));
    }
}
