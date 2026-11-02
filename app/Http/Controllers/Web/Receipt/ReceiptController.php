<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Receipt;

use App\DTOs\Category\CategoryItemData;
use App\DTOs\Receipt\ReceiptData;
use App\Enums\CategoryType;
use App\Enums\ReceiptConfirmMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Receipt\ConfirmReceiptRequest;
use App\Http\Requests\Web\Receipt\ReceiptDraftRequest;
use App\Http\Requests\Web\Receipt\UploadReceiptRequest;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Category\CategoryService;
use App\Services\Receipt\ReceiptService;
use App\Services\Storage\FileStorageService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scan → review form → Simpan (docs/06 §8). No transaction exists before
 * the user confirms (BR-H08).
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly CategoryService $categories,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Receipt::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('receipts/Index', [
            'receipts' => $this->receipts->recent(20)->map(fn (Receipt $receipt): ReceiptData => ReceiptData::fromReceipt($receipt))->values(),
            'quota' => $this->receipts->quota(),
            'canScan' => $user->can('create', Receipt::class),
            'maxFileMb' => (int) config('receipt.max_file_mb'),
        ]);
    }

    public function store(UploadReceiptRequest $request): RedirectResponse
    {
        Gate::authorize('create', Receipt::class);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $receipt = $this->receipts->store((string) $file->getRealPath(), $file->getClientOriginalName(), $user);

        return redirect()->route('receipts.show', $receipt);
    }

    public function show(Request $request, Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('receipts/Show', [
            'receipt' => ReceiptData::fromReceipt($receipt->load(['items', 'transactions'])),
            'categories' => $this->categories->tree(CategoryType::Expense)
                ->map(fn (Category $category): CategoryItemData => CategoryItemData::fromCategory($category))
                ->values(),
            'wallets' => $this->wallets->list()->map(fn (Wallet $wallet): array => ['id' => $wallet->id, 'name' => $wallet->name])->values(),
            'canEdit' => $user->can('update', $receipt),
        ]);
    }

    public function update(ReceiptDraftRequest $request, Receipt $receipt): RedirectResponse
    {
        Gate::authorize('update', $receipt);

        $this->receipts->update($receipt, $request->toDraft());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan disimpan. Belum jadi transaksi sampai kamu menekan Simpan.']);

        return redirect()->route('receipts.show', $receipt);
    }

    public function confirm(ConfirmReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        Gate::authorize('update', $receipt);

        /** @var User $user */
        $user = $request->user();
        $receipt = $this->receipts->confirm($receipt, $request->mode(), $user);
        $count = $receipt->load('transactions')->transactions->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->mode() === ReceiptConfirmMode::Split && $count > 1
            ? "Struk tersimpan sebagai {$count} transaksi."
            : 'Struk tersimpan sebagai transaksi.']);

        return redirect()->route('transactions.index');
    }

    public function destroy(Receipt $receipt): RedirectResponse
    {
        Gate::authorize('update', $receipt);

        $this->receipts->reject($receipt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Struk dibatalkan, tidak ada transaksi yang dibuat.']);

        return redirect()->route('receipts.index');
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
}
