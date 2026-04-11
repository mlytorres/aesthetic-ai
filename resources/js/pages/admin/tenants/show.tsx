import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, UserPlus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

interface TenantData {
	id: string;
	slug: string;
	name: string;
	plan_id: string;
	plan: string | null;
	settings: Record<string, unknown>;
	active: boolean;
	created_at: string;
}

interface User {
	id: number;
	name: string;
	email: string;
	role: string;
	created_at: string;
}

interface RoleOption {
	value: string;
	label: string;
}

interface Plan {
	id: string;
	name: string;
	slug: string;
}

interface Props {
	tenant: TenantData;
	users: User[];
	plans: Plan[];
	availableRoles: RoleOption[];
}

const roleColorMap: Record<string, { text: string; bg: string }> = {
	owner:       { text: 'text-[#C9A84C]',   bg: 'bg-[#C9A84C]/10' },
	admin:       { text: 'text-blue-400',     bg: 'bg-blue-400/10' },
	coordinator: { text: 'text-emerald-400',  bg: 'bg-emerald-400/10' },
	surgeon:     { text: 'text-purple-400',   bg: 'bg-purple-400/10' },
	viewer:      { text: 'text-[#9B9B8E]',   bg: 'bg-white/5' },
};

interface AddUserForm {
	name: string;
	email: string;
	role: string;
}

export default function TenantShow({ tenant, users, plans, availableRoles }: Props) {
	const [resendingId, setResendingId] = useState<number | null>(null);

	// Edit tenant form
	const editForm = useForm({
		name:    tenant.name,
		slug:    tenant.slug,
		plan_id: tenant.plan_id,
	});

	// Add user form
	const addUserForm = useForm<AddUserForm>({
		name:  '',
		email: '',
		role:  availableRoles[0]?.value ?? 'owner',
	});

	const [selectedRole, setSelectedRole] = useState(availableRoles[0]?.value ?? 'owner');

	const handleEditSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		editForm.patch(`/admin/tenants/${tenant.id}`);
	};

	const handleAddUser = (e: React.FormEvent) => {
		e.preventDefault();
		addUserForm.post(`/admin/tenants/${tenant.id}/users`, {
			onSuccess: () => {
				addUserForm.reset();
				setSelectedRole(availableRoles[0]?.value ?? 'owner');
			},
		});
	};

	const handleResend = (user: User) => {
		setResendingId(user.id);
		router.post(
			`/admin/tenants/${tenant.id}/users/${user.id}/resend-invite`,
			{},
			{ onFinish: () => setResendingId(null) },
		);
	};

	const getRoleColors = (role: string) => roleColorMap[role] ?? roleColorMap.viewer;

	return (
		<>
			<Head title={`${tenant.name} — Admin`} />

			<div className="space-y-8">
				<div className="flex items-center gap-4">
					<Link href="/admin/tenants">
						<Button
							variant="ghost"
							size="sm"
							className="gap-1 text-[#9B9B8E] hover:text-[#F5F0E8]"
						>
							<ArrowLeft className="h-4 w-4" />
							Tenants
						</Button>
					</Link>

					<div className="flex-1">
						<Heading
							title={tenant.name}
							description={`${tenant.slug}.aesthetic-ai.com — ${tenant.plan ?? 'No plan'}`}
						/>
					</div>

					<Badge
						className={
							tenant.active
								? 'border-0 bg-emerald-400/10 text-emerald-400'
								: 'border-0 bg-red-400/10 text-red-400'
						}
						variant="outline"
					>
						{tenant.active ? 'Active' : 'Inactive'}
					</Badge>
				</div>

				<div className="grid gap-8 lg:grid-cols-2">
					{/* Left column: Edit tenant + Add user */}
					<div className="space-y-6">
						{/* Edit Clinic */}
						<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
							<h3 className="mb-4 text-base font-semibold text-[#F5F0E8]">
								Clinic Details
							</h3>

							<form onSubmit={handleEditSubmit} className="space-y-4">
								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Name</Label>
									<Input
										value={editForm.data.name}
										onChange={(e) => editForm.setData('name', e.target.value)}
										className="bg-[#0A0A0F] text-[#F5F0E8]"
									/>
									<InputError message={editForm.errors.name} />
								</div>

								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Slug</Label>
									<Input
										value={editForm.data.slug}
										onChange={(e) => editForm.setData('slug', e.target.value)}
										className="bg-[#0A0A0F] font-mono text-[#F5F0E8]"
									/>
									<InputError message={editForm.errors.slug} />
								</div>

								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Plan</Label>
									<Select
										value={editForm.data.plan_id}
										onValueChange={(v) => editForm.setData('plan_id', v)}
									>
										<SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8]">
											<SelectValue />
										</SelectTrigger>
										<SelectContent>
											{plans.map((plan) => (
												<SelectItem key={plan.id} value={plan.id}>
													{plan.name}
												</SelectItem>
											))}
										</SelectContent>
									</Select>
									<InputError message={editForm.errors.plan_id} />
								</div>

								<Button
									type="submit"
									disabled={editForm.processing}
									className="w-full bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90"
								>
									{editForm.processing ? 'Saving...' : 'Save Changes'}
								</Button>
							</form>
						</div>

						{/* Add User */}
						<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
							<h3 className="mb-1 flex items-center gap-2 text-base font-semibold text-[#F5F0E8]">
								<UserPlus className="h-4 w-4 text-[#C9A84C]" />
								Add Team Member
							</h3>
							<p className="mb-4 text-sm text-[#9B9B8E]">
								A temporary password and invitation email will be sent automatically.
							</p>

							<form onSubmit={handleAddUser} className="space-y-4">
								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Name</Label>
									<Input
										value={addUserForm.data.name}
										onChange={(e) => addUserForm.setData('name', e.target.value)}
										placeholder="Full name"
										className="bg-[#0A0A0F] text-[#F5F0E8]"
										required
									/>
									<InputError message={addUserForm.errors.name} />
								</div>

								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Email</Label>
									<Input
										type="email"
										value={addUserForm.data.email}
										onChange={(e) => addUserForm.setData('email', e.target.value)}
										placeholder="staff@clinic.com"
										className="bg-[#0A0A0F] text-[#F5F0E8]"
										required
									/>
									<InputError message={addUserForm.errors.email} />
								</div>

								<div className="grid gap-2">
									<Label className="text-[#F5F0E8]">Role</Label>
									<Select
										value={selectedRole}
										onValueChange={(v) => {
											setSelectedRole(v);
											addUserForm.setData('role', v);
										}}
									>
										<SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8]">
											<SelectValue />
										</SelectTrigger>
										<SelectContent>
											{availableRoles.map((r) => (
												<SelectItem key={r.value} value={r.value}>
													{r.label}
												</SelectItem>
											))}
										</SelectContent>
									</Select>
									<InputError message={addUserForm.errors.role} />
								</div>

								<Button
									type="submit"
									disabled={addUserForm.processing}
									variant="outline"
									className="w-full border-[#C9A84C]/30 text-[#C9A84C] hover:bg-[#C9A84C]/10"
								>
									{addUserForm.processing ? 'Adding...' : 'Add & Send Invite'}
								</Button>
							</form>
						</div>
					</div>

					{/* Right column: Staff list */}
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
						<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
							Staff ({users.length})
						</h3>

						{users.length === 0 ? (
							<p className="py-8 text-center text-sm text-[#9B9B8E]">
								No staff yet.
							</p>
						) : (
							<div className="space-y-3">
								{users.map((user) => {
									const colors = getRoleColors(user.role);

									return (
										<div
											key={user.id}
											className="flex items-center justify-between rounded-lg bg-[#0A0A0F]/50 px-4 py-3"
										>
											<div className="min-w-0 flex-1">
												<p className="truncate text-sm font-medium text-[#F5F0E8]">
													{user.name}
												</p>
												<p className="truncate text-xs text-[#9B9B8E]">
													{user.email}
												</p>
											</div>

											<div className="ml-4 flex items-center gap-3">
												<Badge
													className={cn(
														colors.text,
														colors.bg,
														'border-0 shrink-0',
													)}
													variant="outline"
												>
													{user.role}
												</Badge>

												<button
													type="button"
													onClick={() => handleResend(user)}
													disabled={resendingId === user.id}
													title="Resend invite"
													className="text-[#9B9B8E] hover:text-[#C9A84C] disabled:opacity-40 transition-colors"
												>
													<Mail className="h-4 w-4" />
												</button>
											</div>
										</div>
									);
								})}
							</div>
						)}
					</div>
				</div>
			</div>
		</>
	);
}

TenantShow.layout = (page: React.ReactNode) => (
	<AppLayout
		breadcrumbs={[
			{ title: 'Admin', href: '/admin' },
			{ title: 'Tenants', href: '/admin/tenants' },
			{ title: 'Manage', href: '#' },
		]}
	>
		{page}
	</AppLayout>
);
