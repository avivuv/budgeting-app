<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

/**
 * Members see the family's receipts; anyone who can add transactions may
 * scan, correct, confirm or cancel them (BR-B09).
 */
class ReceiptPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return $this->canWrite($user);
    }
}
