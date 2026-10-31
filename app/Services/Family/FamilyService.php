<?php

declare(strict_types=1);

namespace App\Services\Family;

use App\DTOs\Family\FamilyData;
use App\Enums\CategoryType;
use App\Enums\FamilyRole;
use App\Enums\WalletType;
use App\Models\Family;
use App\Models\User;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FamilyService
{
    public function __construct(
        private readonly FamilyRepositoryInterface $families,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Create a family owned by $owner, with default wallet and categories,
     * and make it the owner's active family.
     */
    public function create(FamilyData $data, User $owner): Family
    {
        return DB::transaction(function () use ($data, $owner): Family {
            $family = $this->families->create([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->name),
                'period_start_day' => $data->periodStartDay,
                'timezone' => $data->timezone,
            ]);

            $this->families->addMember($family, $owner, FamilyRole::Owner);
            $this->families->setCurrentFamily($owner, $family);

            $this->tenant->run($family, fn () => $this->seedDefaults());

            return $family;
        });
    }

    /**
     * @return Collection<int, Family>
     */
    public function familiesOf(User $user): Collection
    {
        return $this->families->forUser($user);
    }

    /**
     * The user's active family, falling back to their first family when the
     * stored one is gone or no longer theirs. Null when they have none.
     */
    public function resolveActive(User $user): ?Family
    {
        if ($user->current_family_id !== null) {
            $family = $this->families->findForMember($user->current_family_id, $user);

            if ($family !== null) {
                return $family;
            }
        }

        $family = $this->families->forUser($user)->first();
        $this->families->setCurrentFamily($user, $family);

        return $family;
    }

    public function roleOf(Family $family, User $user): ?FamilyRole
    {
        return $this->families->roleOf($family, $user);
    }

    /**
     * Switch the active family; returns null when the user is not a member (BR-I06).
     */
    public function switchTo(User $user, int $familyId): ?Family
    {
        $family = $this->families->findForMember($familyId, $user);

        if ($family !== null) {
            $this->families->setCurrentFamily($user, $family);
        }

        return $family;
    }

    private function seedDefaults(): void
    {
        foreach (config('budgeting.default_wallets', []) as $index => $wallet) {
            $this->wallets->create([
                'name' => $wallet['name'],
                'type' => WalletType::from($wallet['type']),
                'icon' => $wallet['icon'] ?? null,
                'color' => $wallet['color'] ?? null,
                'sort_order' => $index,
            ]);
        }

        foreach (config('budgeting.default_categories', []) as $type => $items) {
            foreach ($items as $index => $category) {
                $this->categories->create([
                    'type' => CategoryType::from($type),
                    'name' => $category['name'],
                    'icon' => $category['icon'] ?? null,
                    'color' => $category['color'] ?? null,
                    'keywords' => $category['keywords'] ?? [],
                    'is_default' => true,
                    'sort_order' => $index,
                ]);
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'keluarga';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while ($this->families->slugExists($slug));

        return $slug;
    }
}
