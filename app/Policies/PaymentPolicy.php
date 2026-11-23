<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class PaymentPolicy
{
    use ChecksProjectAccess;

    /** Melihat bukti bayar: admin dan klien pemilik. */
    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $payment->invoice?->project);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->isAdmin() && $payment->status === PaymentStatus::Pending;
    }
}
