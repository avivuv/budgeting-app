<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Transaction;

use App\DTOs\Category\CategoryItemData;
use App\DTOs\Transaction\TransactionItemData;
use App\Enums\CategoryType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Transaction\BulkTransactionRequest;
use App\Http\Requests\Web\Transaction\ParseTransactionRequest;
use App\Http\Requests\Web\Transaction\TransactionFilterRequest;
use App\Http\Requests\Web\Transaction\TransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Activity\ActivityService;
use App\Services\Category\CategoryService;
use App\Services\Family\MembershipService;
use App\Services\Tenancy\TenantContext;
use App\Services\Transaction\TransactionParser;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use App\Support\Money\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
        private readonly MembershipService $members,
    ) {}

    /**
     * Filtered list (docs/07 §4). Totals and daily subtotals come from the
     * server for the whole filter (INV-06); pages are appended on phones
     * (infinite scroll) and replaced on desktop.
     */
    public function index(TransactionFilterRequest $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        /** @var User $user */
        $user = $request->user();
        $filter = $request->toFilter();
        $paginator = $this->transactions->paginate($filter, $request->perPage());
        $dates = array_values(array_unique($paginator->getCollection()->map(fn (Transaction $t): string => $t->date->toDateString())->all()));
        $totals = $this->transactions->totals($filter);

        return Inertia::render('transactions/Index', [
            'transactions' => Inertia::scroll(fn () => $paginator->through(fn (Transaction $transaction) => TransactionItemData::fromTransaction(
                $transaction,
                $user->can('update', $transaction),
            ))),
            'daily' => Inertia::merge(fn () => array_map(
                fn (array $day): array => [
                    'income' => $day['income'],
                    'expense' => $day['expense'],
                    'incomeFormatted' => MoneyFormatter::format($day['income']),
                    'expenseFormatted' => MoneyFormatter::format($day['expense']),
                ],
                $this->transactions->dailyTotals($filter, $dates),
            )),
            'aggregates' => [
                'count' => $paginator->total(),
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'incomeFormatted' => MoneyFormatter::format($totals['income']),
                'expenseFormatted' => MoneyFormatter::format($totals['expense']),
                'netFormatted' => MoneyFormatter::format($totals['income'] - $totals['expense']),
            ],
            'filters' => (object) $request->filterQuery(),
            'perPage' => $request->perPage(),
            'options' => fn () => $this->filterOptions(),
            'canCreate' => $user->can('create', Transaction::class),
        ]);
    }

    /**
     * Quick entry: read "bensin 50rb" into form fields. Nothing is saved; the
     * user reviews the form and submits it as usual.
     */
    public function parse(ParseTransactionRequest $request, TransactionParser $parser): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        return response()->json(['data' => $parser->parse($request->text())]);
    }

    /**
     * Delete or re-categorise the selected transactions. Every item is
     * authorised on its own (BR-B09); ids of another family give 404 (BR-I06).
     */
    public function bulk(BulkTransactionRequest $request): RedirectResponse
    {
        $transactions = $this->transactions->findMany($request->ids());
        $ability = $request->validated('action') === 'delete' ? 'delete' : 'update';

        foreach ($transactions as $transaction) {
            Gate::authorize($ability, $transaction);
        }

        if ($ability === 'delete') {
            $count = $this->transactions->deleteMany($transactions);
            $message = "{$count} transaksi dihapus.";
        } else {
            $count = $this->transactions->changeCategory($transactions->load('tags'), (int) $request->validated('category_id'));
            $message = "Kategori {$count} transaksi diubah.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
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

    /**
     * Choices for the list filter; archived wallets and categories included so
     * old transactions stay findable.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $tree = fn (CategoryType $type) => $this->categories->tree($type, includeArchived: true)
            ->map(fn (Category $category) => CategoryItemData::fromCategory($category))
            ->values();

        return [
            'wallets' => $this->wallets->list(includeArchived: true)->map(fn (Wallet $wallet) => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'archived' => $wallet->is_archived,
            ])->values(),
            'categories' => [
                TransactionType::Expense->value => $tree(CategoryType::Expense),
                TransactionType::Income->value => $tree(CategoryType::Income),
            ],
            'members' => $this->members->members()->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
            ])->values(),
            'tags' => $this->transactions->tagNames(),
            'sources' => collect(TransactionSource::cases())->map(fn (TransactionSource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ])->values(),
        ];
    }
}
