<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Savings;

use App\DTOs\Savings\SavingGoalItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Savings\ContributeRequest;
use App\Http\Requests\Api\V1\Savings\SavingGoalRequest;
use App\Http\Responses\SnakeCase;
use App\Models\SavingGoal;
use App\Models\User;
use App\Services\Savings\SavingGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Savings goals (docs/api-contracts/savings.md).
 */
class SavingGoalController extends Controller
{
    public function __construct(private readonly SavingGoalService $goals) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SavingGoal::class);

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => SnakeCase::keys($this->goals->overview()),
            'meta' => [
                'can_manage' => $user->can('create', SavingGoal::class),
                'can_contribute' => $user->can('contribute', new SavingGoal),
            ],
        ]);
    }

    public function store(SavingGoalRequest $request): JsonResponse
    {
        Gate::authorize('create', SavingGoal::class);

        $goal = $this->goals->create($request->toData());

        return $this->goal($this->goals->item($goal), 201);
    }

    public function update(SavingGoalRequest $request, SavingGoal $goal): JsonResponse
    {
        Gate::authorize('update', $goal);

        $goal = $this->goals->update($goal, $request->toData());

        return $this->goal($this->goals->item($goal));
    }

    /**
     * BR-K02: deposit into or withdraw from a goal without a linked wallet.
     */
    public function contribute(ContributeRequest $request, SavingGoal $goal): JsonResponse
    {
        Gate::authorize('contribute', $goal);

        $goal = $this->goals->contribute($goal, $request->signedAmount());

        return $this->goal($this->goals->item($goal), 200, [
            'message' => $request->signedAmount() > 0 ? 'Tabungan ditambahkan.' : 'Tabungan diambil.',
        ]);
    }

    public function destroy(SavingGoal $goal): Response
    {
        Gate::authorize('delete', $goal);

        $this->goals->delete($goal);

        return response()->noContent();
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function goal(SavingGoalItemData $item, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json([...$extra, 'data' => SnakeCase::keys($item)], $status);
    }
}
