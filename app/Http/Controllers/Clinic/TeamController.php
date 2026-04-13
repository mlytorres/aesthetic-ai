<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $tenant = TenantContext::get();

        $members = User::where('tenant_id', $tenant->id)
            ->withoutGlobalScopes()
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        /** @var User $actor */
        $actor = auth()->user();

        return Inertia::render('clinic/team', [
            'members' => $members,
            // Admins see all roles except Owner in the invite form; Owners see all roles.
            'availableRoles' => collect([
                ['value' => User::ROLE_OWNER,       'label' => 'Owner'],
                ['value' => User::ROLE_ADMIN,        'label' => 'Admin'],
                ['value' => User::ROLE_COORDINATOR,  'label' => 'Coordinator'],
                ['value' => User::ROLE_SURGEON,      'label' => 'Surgeon'],
                ['value' => User::ROLE_VIEWER,       'label' => 'Viewer'],
            ])->when(! $actor->isOwner(), fn ($c) => $c->reject(fn ($r) => $r['value'] === User::ROLE_OWNER))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in([
                User::ROLE_OWNER,
                User::ROLE_ADMIN,
                User::ROLE_COORDINATOR,
                User::ROLE_SURGEON,
                User::ROLE_VIEWER,
            ])],
        ]);

        // UserPolicy::assignRole — only Owner can invite another Owner.
        Gate::authorize('assignRole', [User::class, $validated['role']]);

        $temporaryPassword = Str::password(12, symbols: false);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $validated['role'],
        ]);

        // email_verified_at is not in $fillable — call directly so invited staff
        // can log in immediately without going through email verification.
        $user->markEmailAsVerified();

        Mail::to($user->email)->send(new UserInviteMail(
            user: $user,
            tenant: $tenant,
            temporaryPassword: $temporaryPassword,
        ));

        return back()->with('flash.success', "{$user->name} added and invitation sent.");
    }

    public function destroy(User $user): RedirectResponse
    {
        // UserPolicy::remove — checks: same tenant, can't remove self, owner-only to remove owner.
        Gate::authorize('remove', $user);

        $user->delete();

        return back()->with('flash.success', 'Team member removed.');
    }
}
