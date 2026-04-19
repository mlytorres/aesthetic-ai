<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInviteMail;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantAdminController extends Controller
{
    // ─── List ─────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $tenants = Tenant::withTrashed()
            ->with('plan')
            ->withCount('users')
            ->orderByDesc('created_at')
            ->get(['id', 'slug', 'name', 'plan_id', 'settings', 'created_at', 'deleted_at']);

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants->map(fn (Tenant $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'plan' => $t->plan?->name,
                'users_count' => $t->users_count,
                'active' => $t->deleted_at === null,
                'created_at' => $t->created_at?->toDateString(),
            ]),
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(string $id): Response
    {
        $tenant = Tenant::withTrashed()->with('plan')->findOrFail($id);

        $users = User::where('tenant_id', $tenant->id)
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        $plans = Plan::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('admin/tenants/show', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'plan_id' => $tenant->plan_id,
                'plan' => $tenant->plan?->name,
                'plan_slug' => $tenant->plan?->slug,
                'settings' => $tenant->settings,
                'has_video_consultations' => $tenant->hasVideoConsultations(),
                'has_affiliate_program' => $tenant->hasAffiliateProgram(),
                'active' => $tenant->deleted_at === null,
                'created_at' => $tenant->created_at?->toDateString(),
            ],
            'users' => $users,
            'plans' => $plans,
            'availableRoles' => [
                ['value' => User::ROLE_OWNER,       'label' => 'Owner'],
                ['value' => User::ROLE_ADMIN,        'label' => 'Admin'],
                ['value' => User::ROLE_COORDINATOR,  'label' => 'Coordinator'],
                ['value' => User::ROLE_SURGEON,      'label' => 'Surgeon'],
                ['value' => User::ROLE_VIEWER,       'label' => 'Viewer'],
            ],
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $plans = Plan::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('admin/tenants/create', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:tenants,slug'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'unique:users,email'],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['string'],
        ]);

        // Create tenant
        $tenant = Tenant::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'plan_id' => $validated['plan_id'],
            'settings' => [
                'procedures_enabled' => $validated['procedures'] ?? ['rhinoplasty'],
            ],
        ]);

        // Create initial owner with a temporary password
        $temporaryPassword = Str::password(12, symbols: false);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['owner_name'],
            'email' => $validated['owner_email'],
            'password' => Hash::make($temporaryPassword),
            'role' => User::ROLE_OWNER,
        ]);

        // Send invitation email with credentials
        Mail::to($owner->email)->send(new UserInviteMail(
            user: $owner,
            tenant: $tenant,
            temporaryPassword: $temporaryPassword,
        ));

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('flash.success', "Clinic \"{$tenant->name}\" created. Invitation sent to {$owner->email}.");
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
        ]);

        $tenant->update($validated);

        return back()->with('flash.success', 'Clinic details updated.');
    }

    // ─── Deactivate / Restore ─────────────────────────────────────────────────

    public function deactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return back()->with('flash.success', "\"{$tenant->name}\" has been deactivated.");
    }

    public function restore(string $id): RedirectResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);
        $tenant->restore();

        return back()->with('flash.success', "\"{$tenant->name}\" has been reactivated.");
    }

    // ─── Feature Flags ────────────────────────────────────────────────────────

    public function updateFeatures(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'video_consultations_enabled' => ['required', 'boolean'],
            'affiliate_program_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings = $tenant->settings ?? [];
        $settings['video_consultations_enabled'] = $validated['video_consultations_enabled'];

        if (array_key_exists('affiliate_program_enabled', $validated)) {
            $settings['affiliate_program_enabled'] = $validated['affiliate_program_enabled'];
        }

        $tenant->update(['settings' => $settings]);

        return back()->with('flash.success', 'Feature flags updated.');
    }

    // ─── Add User to Tenant ───────────────────────────────────────────────────

    public function addUser(Request $request, Tenant $tenant): RedirectResponse
    {
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

        $temporaryPassword = Str::password(12, symbols: false);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $validated['role'],
        ]);

        Mail::to($user->email)->send(new UserInviteMail(
            user: $user,
            tenant: $tenant,
            temporaryPassword: $temporaryPassword,
        ));

        return back()->with('flash.success', "{$user->name} added and invitation sent.");
    }

    // ─── Resend Invite ────────────────────────────────────────────────────────

    public function resendInvite(Tenant $tenant, User $user): RedirectResponse
    {
        if ($user->tenant_id !== $tenant->id) {
            abort(403);
        }

        $temporaryPassword = Str::password(12, symbols: false);
        $user->update(['password' => Hash::make($temporaryPassword)]);

        Mail::to($user->email)->send(new UserInviteMail(
            user: $user,
            tenant: $tenant,
            temporaryPassword: $temporaryPassword,
        ));

        return back()->with('flash.success', "Invitation resent to {$user->email}.");
    }
}
