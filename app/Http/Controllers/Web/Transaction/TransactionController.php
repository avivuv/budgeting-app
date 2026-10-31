<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Transaction;

use App\DTOs\Category\CategoryItemData;
use App\DTOs\Transaction\TransactionItemData;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Transaction\TransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Activity\ActivityService;
use App\Services\Category\CategoryService;
use App\Services\Tenancy\TenantContext;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly WalletService $wallets,
        private readonly CategoryService $categories,
        private readonly TenantContext $tenant,
        private readonly ActivityService $activity,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        /** @var User $user */
        $user = $request->user();

        $page = $this->transactions->paginate()
            ->through(fn (Transaction $transaction) => TransactionItemData::fromTransaction(
                $transaction,
                $user->can('update', $transaction),
            ));

        return Inertia::render('transactions/Index', [
            'transactions' => $page,
            'canCreate' => $user->can('create', Transaction::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Transaction::class);

        return Inertia::render('transactions/Form', [
            'transaction' => null,
            'history' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Transaction::class);

        /** @var User $user */
        $user = $request->user();
        $transaction = $this->transactions->create($request->toData(), $user);

        $this->flashResult('Transaksi tersimpan.', $transaction);

        return $request->boolean('add_another')
            ? redirect()->route('transactions.create')
            : redirect()->route('transactions.index');
    }

    public function edit(Request $request, Transaction $transaction): Response
    {
        Gate::authorize('update', $transaction);

        $transaction->load(['wallet', 'toWallet', 'category', 'creator', 'tags']);

        return Inertia::render('transactions/Form', [
            'transaction' => TransactionItemData::fromTransaction($transaction, true),
            'history' => $this->activity->transactionHistory($transaction),
            ...$this->formOptions(),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $transaction = $this->transactions->update($transaction, $request->toData());

        $this->flashResult('Transaksi diperbarui.', $transaction);

        return redirect()->route('transactions.index');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaksi dihapus.']);

        return redirect()->route('transactions.index');
    }

    /**
     * Success toast, or a warning when a wallet went below zero (BR-C03).
     */
    private function flashResult(string $message, Transaction $transaction): void
    {
        $warnings = $this->wallets->negativeBalanceWarnings([$transaction->wallet_id, $transaction->to_wallet_id]);

        Inertia::flash('toast', $warnings === []
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'warning', 'message' => $message.' '.implode(' ', $warnings)]);
    }

    /**
     * Wallets and categories for the form (active only; archived ones stay
     * selectable on existing transactions through their current value).
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $tree = fn (CategoryType $type) => $this->categories->tree($type)
            ->map(fn (Category $category) => CategoryItemData::fromCategory($category))
            ->values();

        return [
            'wallets' => $this->wallets->list()->map(fn (Wallet $wallet) => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'type' => $wallet->type->value,
            ])->values(),
            'categories' => [
                TransactionType::Expense->value => $tree(CategoryType::Expense),
                TransactionType::Income->value => $tree(CategoryType::Income),
            ],
            'today' => CarbonImmutable::now($this->tenant->get()->timezone)->toDateString(),
        ];
    }
}
