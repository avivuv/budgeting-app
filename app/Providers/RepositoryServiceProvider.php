<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\InvitationRepositoryInterface;
use App\Repositories\Contracts\SavingGoalRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Eloquent\ActivityRepository;
use App\Repositories\Eloquent\BudgetRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\FamilyRepository;
use App\Repositories\Eloquent\InvitationRepository;
use App\Repositories\Eloquent\SavingGoalRepository;
use App\Repositories\Eloquent\TagRepository;
use App\Repositories\Eloquent\TransactionRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WalletRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        FamilyRepositoryInterface::class => FamilyRepository::class,
        WalletRepositoryInterface::class => WalletRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        TransactionRepositoryInterface::class => TransactionRepository::class,
        TagRepositoryInterface::class => TagRepository::class,
        InvitationRepositoryInterface::class => InvitationRepository::class,
        ActivityRepositoryInterface::class => ActivityRepository::class,
        BudgetRepositoryInterface::class => BudgetRepository::class,
        SavingGoalRepositoryInterface::class => SavingGoalRepository::class,
    ];
}
