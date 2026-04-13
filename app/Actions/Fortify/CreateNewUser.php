<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Handles self-registration for new clinic owners.
 *
 * Creates the Tenant record (with 14-day trial) and the first User (owner)
 * in a single transaction. Fortify fires the Registered event afterwards,
 * which triggers the email-verification notification.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /** Slugs that cannot be used as tenant subdomains. */
    private const RESERVED_SLUGS = [
        'admin', 'api', 'app', 'www', 'mail', 'ftp', 'smtp',
        'portal', 'static', 'assets', 'cdn', 'blog', 'store',
        'ns1', 'ns2', 'support', 'help', 'status', 'health',
    ];

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'clinic_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique(Tenant::class, 'slug'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ], [
            'slug.regex' => 'Subdomain may only contain lowercase letters, numbers, and hyphens, and must start and end with a letter or number.',
            'slug.not_in' => 'This subdomain is reserved. Please choose a different one.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $starterPlan = Plan::where('slug', 'starter')->first();

            $tenant = Tenant::create([
                'name' => $input['clinic_name'],
                'slug' => $input['slug'],
                'plan_id' => $starterPlan?->id,
                'trial_ends_at' => now()->addDays(14),
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role' => User::ROLE_OWNER,
            ]);
        });
    }
}
