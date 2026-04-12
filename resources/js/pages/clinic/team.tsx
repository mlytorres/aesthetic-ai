import { Head, useForm, usePage, router } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';
import { index, store, destroy } from '@/routes/clinic/team';

interface Member {
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

interface InviteFormData {
	name: string;
	email: string;
	role: string;
}

interface Props {
	members: Member[];
	availableRoles: RoleOption[];
}

const roleColorMap: Record<string, { text: string; bg: string }> = {
	owner: { text: 'text-[#0E9E8E]', bg: 'bg-[#0E9E8E]/10' },
	admin: { text: 'text-blue-400', bg: 'bg-blue-400/10' },
	coordinator: { text: 'text-emerald-400', bg: 'bg-emerald-400/10' },
	surgeon: { text: 'text-purple-400', bg: 'bg-purple-400/10' },
	viewer: { text: 'text-[#9B9B8E]', bg: 'bg-white/5' },
};

export default function Team({ members, availableRoles }: Props) {
	const page = usePage<{
		auth: { user: { id: number; name: string; email: string } };
	}>();
	const currentUser = page.props.auth.user;

	const { data, setData, post, processing, errors, reset } =
		useForm<InviteFormData>({
			name: '',
			email: '',
			role: availableRoles[0]?.value || '',
		});

	const [selectedRole, setSelectedRole] = useState(availableRoles[0]?.value || '');
	const [deletingId, setDeletingId] = useState<number | null>(null);

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		post(store.url(), {
			onSuccess: () => {
				reset();
				setSelectedRole(availableRoles[0]?.value || '');
			},
		});
	};

	const handleDelete = (member: Member) => {
		if (confirm(`Remove ${member.name} from the team?`)) {
			setDeletingId(member.id);
			router.delete(destroy.url({ user: member.id }));
		}
	};

	const getRoleColors = (role: string) => {
		return roleColorMap[role] || roleColorMap.viewer;
	};

	const formatDate = (dateString: string) => {
		return new Date(dateString).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		});
	};

	return (
		<>
			<Head title="Team" />

			<div className="space-y-8">
				<Heading
					title="Team"
					description="Manage your clinic's staff accounts"
				/>

				{/* Invite Member Card */}
				<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
					<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
						Invite Member
					</h3>

					<form onSubmit={handleSubmit} className="space-y-4">
						<div className="grid gap-2">
							<Label htmlFor="name" className="text-[#F5F0E8]">
								Name
							</Label>
							<Input
								id="name"
								value={data.name}
								onChange={(e) =>
									setData('name', e.target.value)
								}
								placeholder="Full name"
								className="bg-[#0A0A0F] text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.name} />
						</div>

						<div className="grid gap-2">
							<Label htmlFor="email" className="text-[#F5F0E8]">
								Email
							</Label>
							<Input
								id="email"
								type="email"
								value={data.email}
								onChange={(e) =>
									setData('email', e.target.value)
								}
								placeholder="email@example.com"
								className="bg-[#0A0A0F] text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.email} />
						</div>

						<div className="grid gap-2">
							<Label htmlFor="role" className="text-[#F5F0E8]">
								Role
							</Label>
							<Select
								value={selectedRole}
								onValueChange={(value) => {
									setSelectedRole(value);
									setData('role', value);
								}}
							>
								<SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8]">
									<SelectValue placeholder="Select a role" />
								</SelectTrigger>
								<SelectContent>
									{availableRoles.map((role) => (
										<SelectItem
											key={role.value}
											value={role.value}
										>
											{role.label}
										</SelectItem>
									))}
								</SelectContent>
							</Select>
							<InputError message={errors.role} />
						</div>

						<Button
							type="submit"
							disabled={processing}
							className="w-full bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
						>
							{processing ? 'Adding...' : 'Add Member'}
						</Button>
					</form>
				</div>

				{/* Team Members Table */}
				<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
					<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
						Team Members
					</h3>

					{members.length === 0 ? (
						<p className="text-center text-[#9B9B8E] py-8">
							No team members yet. Invite someone to get started.
						</p>
					) : (
						<div className="overflow-x-auto">
							<table className="w-full">
								<thead>
									<tr className="border-b border-sidebar-border/50">
										<th className="px-4 py-3 text-left text-sm font-semibold text-[#F5F0E8]">
											Name
										</th>
										<th className="px-4 py-3 text-left text-sm font-semibold text-[#F5F0E8]">
											Role
										</th>
										<th className="px-4 py-3 text-left text-sm font-semibold text-[#F5F0E8]">
											Joined
										</th>
										<th className="px-4 py-3 text-right text-sm font-semibold text-[#F5F0E8]">
											Action
										</th>
									</tr>
								</thead>
								<tbody>
									{members.map((member) => {
										const roleColors =
											getRoleColors(member.role);
										const isCurrentUser =
											currentUser.id === member.id;

										return (
											<tr
												key={member.id}
												className="border-b border-sidebar-border/30 hover:bg-[#0A0A0F]/50 transition-colors"
											>
												<td className="px-4 py-3 text-sm">
													<div>
														<p className="text-[#F5F0E8] font-medium">
															{member.name}
														</p>
														<p className="text-[#9B9B8E] text-xs">
															{member.email}
														</p>
													</div>
												</td>
												<td className="px-4 py-3 text-sm">
													<Badge
														className={cn(
															roleColors.text,
															roleColors.bg,
															'border-0'
														)}
														variant="outline"
													>
														{member.role}
													</Badge>
												</td>
												<td className="px-4 py-3 text-sm text-[#9B9B8E]">
													{formatDate(
														member.created_at
													)}
												</td>
												<td className="px-4 py-3 text-right text-sm">
													{!isCurrentUser && (
														<button
															type="button"
															onClick={() =>
																handleDelete(
																	member
																)
															}
															disabled={
																deletingId ===
																member.id
															}
															className="text-red-400 hover:text-red-300 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
														>
															{deletingId ===
															member.id
																? 'Removing...'
																: 'Remove'}
														</button>
													)}
												</td>
											</tr>
										);
									})}
								</tbody>
							</table>
						</div>
					)}
				</div>
			</div>
		</>
	);
}

Team.layout = {
	breadcrumbs: [
		{
			title: 'Team',
			href: index.url(),
		},
	],
};
