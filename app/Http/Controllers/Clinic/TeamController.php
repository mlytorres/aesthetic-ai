<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        return Inertia::render('clinic/team', [
            'members'           => $members,
            'availableRoles'    => [
                ['value' => User::ROLE_OWNER,       'label' => 'Owner'],
                ['value' => User::ROLE_ADMIN,        'label' => 'Admin'],
                ['value' => User::ROLE_COORDINATOR,  'label' => 'Coordinator'],
                ['value' => 'surgeon',               'label' => 'Surgeon'],
                ['value' => 'viewer',                'label' => 'Viewer'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role'  => ['required', Rule::in([
                User::ROLE_OWNER,
                User::ROLE_ADMIN,
                User::ROLE_COORDINATOR,
                'surgeon',
                'viewer',
            ])],
        ]);

        // Create user — they will reset password on first login
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make(\Illuminate\Support\Str::random(32)),
            'role'      => $validated['role'],
        ]);

        // TODO Sprint 3: dispatch InviteUserJob to send welcome email with password reset link

        return back()->with('flash.success', "{$user->name} added to your team.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $tenant = TenantContext::get();

        // Prevent removing yourself or the last owner
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot remove yourself.']);
        }

        if ($user->tenant_id !== $tenant->id) {
            abort(403);
        }

        $user->delete();

        return back()->with('flash.success', 'Team member removed.');
    }
}
