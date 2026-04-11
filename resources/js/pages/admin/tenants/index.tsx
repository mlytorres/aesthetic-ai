import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Tenant {
	id: string;
	slug: string;
	name: string;
	plan: string | null;
	users_count: number;
	active: boolean;
	created_at: string;
}

interface Props {
	tenants: Tenant[];
}

export default function TenantsIndex({ tenants }: Props) {
	const handleToggle = (tenant: Tenant) => {
		if (tenant.active) {
			if (!confirm(`Deactivate "${tenant.name}"? Their staff will lose access.`)) {
return;
}

			router.delete(`/admin/tenants/${tenant.id}`);
		} else {
			router.post(`/admin/tenants/${tenant.id}/restore`);
		}
	};

	return (
		<>
			<Head title="Tenants — Admin" />

			<div className="space-y-8">
				<div className="flex items-center justify-between">
					<Heading
						title="Tenants"
						description="Manage all clinic accounts on the platform"
					/>
					<Link href="/admin/tenants/create">
						<Button className="bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90 gap-2">
							<Plus className="h-4 w-4" />
							New Clinic
						</Button>
					</Link>
				</div>

				{/* Stats row */}
				<div className="grid grid-cols-3 gap-4">
					{[
						{
							label: 'Total Clinics',
							value: tenants.length,
							color: 'text-[#F5F0E8]',
						},
						{
							label: 'Active',
							value: tenants.filter((t) => t.active).length,
							color: 'text-emerald-400',
						},
						{
							label: 'Inactive',
							value: tenants.filter((t) => !t.active).length,
							color: 'text-red-400',
						},
					].map((stat) => (
						<div
							key={stat.label}
							className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-4"
						>
							<p className="text-sm text-[#9B9B8E]">{stat.label}</p>
							<p className={`mt-1 text-3xl font-bold ${stat.color}`}>
								{stat.value}
							</p>
						</div>
					))}
				</div>

				{/* Tenants table */}
				<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
					{tenants.length === 0 ? (
						<div className="flex flex-col items-center justify-center py-16 text-center">
							<Building2 className="mb-4 h-12 w-12 text-[#9B9B8E]/40" />
							<p className="text-[#9B9B8E]">No clinics yet.</p>
							<Link href="/admin/tenants/create" className="mt-4">
								<Button
									variant="outline"
									className="border-[#C9A84C]/30 text-[#C9A84C] hover:bg-[#C9A84C]/10"
								>
									Create the first clinic
								</Button>
							</Link>
						</div>
					) : (
						<div className="overflow-x-auto">
							<table className="w-full">
								<thead>
									<tr className="border-b border-sidebar-border/50">
										{['Clinic', 'Slug', 'Plan', 'Staff', 'Created', 'Status', ''].map(
											(h) => (
												<th
													key={h}
													className="px-4 py-3 text-left text-sm font-semibold text-[#F5F0E8] last:text-right"
												>
													{h}
												</th>
											),
										)}
									</tr>
								</thead>
								<tbody>
									{tenants.map((tenant) => (
										<tr
											key={tenant.id}
											className="border-b border-sidebar-border/30 hover:bg-[#0A0A0F]/50 transition-colors"
										>
											<td className="px-4 py-3">
												<Link
													href={`/admin/tenants/${tenant.id}`}
													className="font-medium text-[#F5F0E8] hover:text-[#C9A84C] transition-colors"
												>
													{tenant.name}
												</Link>
											</td>
											<td className="px-4 py-3 font-mono text-sm text-[#9B9B8E]">
												{tenant.slug}
											</td>
											<td className="px-4 py-3 text-sm text-[#9B9B8E]">
												{tenant.plan ?? '—'}
											</td>
											<td className="px-4 py-3 text-sm text-[#9B9B8E]">
												{tenant.users_count}
											</td>
											<td className="px-4 py-3 text-sm text-[#9B9B8E]">
												{tenant.created_at}
											</td>
											<td className="px-4 py-3">
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
											</td>
											<td className="px-4 py-3 text-right">
												<div className="flex items-center justify-end gap-3">
													<Link
														href={`/admin/tenants/${tenant.id}`}
														className="text-sm text-[#C9A84C] hover:text-[#C9A84C]/80"
													>
														Manage
													</Link>
													<button
														type="button"
														onClick={() => handleToggle(tenant)}
														className={`text-sm font-medium ${
															tenant.active
																? 'text-red-400 hover:text-red-300'
																: 'text-emerald-400 hover:text-emerald-300'
														}`}
													>
														{tenant.active ? 'Deactivate' : 'Restore'}
													</button>
												</div>
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					)}
				</div>
			</div>
		</>
	);
}

TenantsIndex.layout = {
	breadcrumbs: [
		{ title: 'Admin', href: '/admin' },
		{ title: 'Tenants', href: '/admin/tenants' },
	],
};
