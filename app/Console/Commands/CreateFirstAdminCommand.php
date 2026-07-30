<?php

namespace App\Console\Commands;

use App\Domain\Auth\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps the very first AdminUser on a fresh environment. Every other
 * admin gets created through the Team UI (App\Domain\Auth\Actions\
 * CreateAdminUserAction, audited, emailed a temp password) — but that flow
 * needs an existing logged-in admin to invoke it, so the first one has no
 * such path. The admin realm also has no self-service password reset (that
 * module is customer-portal-only), so this deliberately takes a real
 * password up front rather than generating a temp one there'd be no way to
 * recover afterward.
 *
 * Refuses if any AdminUser already exists — this is a one-time bootstrap
 * step, not a way to mint additional admins, and that guard also makes it
 * safe to leave wired into a platform's post-deploy/release hook
 * permanently: after the first successful run it's a no-op forever after.
 *
 * Usage (non-interactive, e.g. a deploy hook or a platform's one-off
 * command runner):
 *   php artisan admin:create-first --name="Jane Doe" --email=jane@hetkin.app --password="…"
 *
 * Usage (interactive, e.g. an actual shell/SSH session — prompts and masks
 * the password rather than taking it as a plain CLI argument):
 *   php artisan admin:create-first
 */
class CreateFirstAdminCommand extends Command
{
    protected $signature = 'admin:create-first
        {--name= : Full name}
        {--email= : Sign-in email}
        {--password= : Sign-in password (prompted + masked if omitted)}';

    protected $description = 'Create the first admin account on a fresh environment (refuses if one already exists)';

    public function handle(): int
    {
        if (AdminUser::query()->exists()) {
            $this->info('An admin account already exists — nothing to do.');

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = strtolower(trim($this->option('email') ?: $this->ask('Email')));
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => AdminRole::Admin,
            'active' => true,
        ]);

        $this->info("Admin account created: {$email}");

        return self::SUCCESS;
    }
}
