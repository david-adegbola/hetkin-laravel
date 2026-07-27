<?php

namespace App\Models;

use App\Domain\Auth\Enums\AdminRole;
use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Internal (Hetkin staff) realm — deliberately separate model/table/guard
 * from the customer realm's User (MIGRATION_PLAN.md ADR-4): a bug in the
 * customer-facing app should never be able to reach an internal admin
 * session, and vice versa.
 *
 * `active` is a soft-disable, not a delete — see the migration's comment.
 * Enforcing that a deactivated admin can't authenticate belongs to the
 * login flow (App\Http\Requests\Admin\Auth\LoginRequest), not this model.
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class AdminUser extends Authenticatable
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => AdminRole::class,
            'active' => 'boolean',
        ];
    }
}
