<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Wallet;

use App\DTOs\Wallet\WalletItemData;
use App\Enums\WalletType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Wallet\WalletRequest;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        $wallets = $this->wallets->list(includeArchived: true);
        $balances = $this->wallets->balances($wallets);
        $total = $this->wallets->totalBalance();

        return Inertia::render('wallets/Index', [
            'wallets' => $wallets->map(fn (Wallet $wallet) => WalletItemData::fromWallet($wallet, $balances[$wallet->id] ?? 0))->values(),
            'totalBalance' => $total,
            'totalBalanceFormatted' => MoneyFormatter::format($total),
            'walletTypes' => collect(WalletType::cases())->map(fn (WalletType $type) => ['value' => $type->value, 'label' => $type->label()])->values(),
            'canManage' => Gate::allows('create', Wallet::class),
        ]);
    }

    public function store(WalletRequest $request): RedirectResponse
    {
        Gate::authorize('create', Wallet::class);

        $wallet = $this->wallets->create($request->toData());

        return $this->done("Dompet {$wallet->name} ditambahkan.");
    }

    public function update(WalletRequest $request, Wallet $wallet): RedirectResponse
    {
        Gate::authorize('update', $wallet);

        $this->wallets->update($wallet, $request->toData());

        return $this->done('Dompet diperbarui.');
    }

    public function archive(Request $request, Wallet $wallet): RedirectResponse
    {
        Gate::authorize('update', $wallet);

        $archived = $request->boolean('archived', true);
        $this->wallets->setArchived($wallet, $archived);

        return $this->done($archived ? 'Dompet diarsipkan.' : 'Dompet diaktifkan kembali.');
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        Gate::authorize('delete', $wallet);

        $this->wallets->delete($wallet);

        return $this->done('Dompet dihapus.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('wallets.index');
    }
}
