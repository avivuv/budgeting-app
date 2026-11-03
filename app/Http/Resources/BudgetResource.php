<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Budget\BudgetItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A budget with its realisation (docs/api-contracts/budgets.md).
 */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BudgetItemData $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'period' => $item->period,
            'category' => [
                'id' => $item->categoryId,
                'name' => $item->categoryName,
                'icon' => $item->categoryIcon,
                'color' => $item->categoryColor,
                'is_sub_category' => $item->isSubCategory,
                'is_archived' => $item->isCategoryArchived,
            ],
            'amount' => $item->amount,
            'amount_formatted' => $item->amountFormatted,
            'spent' => $item->spent,
            'spent_formatted' => $item->spentFormatted,
            'remaining' => $item->remaining,
            'remaining_formatted' => $item->remainingFormatted,
            'percentage' => $item->percentage,
            'status' => $item->status->value,
            'status_label' => $item->statusLabel,
            'daily_allowance' => $item->dailyAllowance,
            'daily_allowance_formatted' => $item->dailyAllowanceFormatted,
        ];
    }
}
