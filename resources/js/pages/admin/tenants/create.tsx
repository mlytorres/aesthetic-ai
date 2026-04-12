import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

interface Plan {
	id: string;
	name: string;
	slug: string;
}

interface Props {
	plans: Plan[];
}

const PROCEDURES = [
	{ value: 'rhinoplasty',        label: 'Rhinoplasty' },
	{ value: 'bbl',                label: 'BBL' },
	{ value: 'lipo_360',           label: 'Lipo 360°' },
	{ value: 'breast_augmentation', label: 'Breast Augmentation' },
	{ value: 'facelift',           label: 'Facelift' },
];

interface FormData {
	name: string;
	slug: string;
	plan_id: string;
	owner_name: string;
	owner_email: string;
	procedures: string[];
}

export default function CreateTenant({ plans }: Props) {
	const { data, setData, post, processing, errors } = useForm<FormData>({
		name: '',
		slug: '',
		plan_id: plans[0]?.id ?? '',
		owner_name: '',
		owner_email: '',
		procedures: ['rhinoplasty'],
	});

	// Auto-generate slug from name
	const handleNameChange = (value: string) => {
		setData((prev) => ({
			...prev,
			name: value,
			slug: prev.slug === '' || prev.slug === slugify(prev.name)
				? slugify(value)
				: prev.slug,
		}));
	};

	const handleProcedureToggle = (value: string) => {
		setData('procedures', data.procedures.includes(value)
			? data.procedures.filter((p) => p !== value)
			: [...data.procedures, value],
		);
	};

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		post('/admin/tenants');
	};

	return (
		<>
			<Head title="Create Clinic — Admin" />

			<div className="max-w-2xl space-y-8">
				<Heading
					title="Create Clinic"
					description="Set up a new clinic account and send the owner their credentials"
				/>

				<form onSubmit={handleSubmit} className="space-y-6">
					{/* Clinic Details */}
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6 space-y-4">
						<h3 className="text-base font-semibold text-[#F5F0E8]">
							Clinic Details
						</h3>

						<div className="grid gap-2">
							<Label htmlFor="name" className="text-[#F5F0E8]">Clinic Name</Label>
							<Input
								id="name"
								value={data.name}
								onChange={(e) => handleNameChange(e.target.value)}
								placeholder="Miami Life Cosmetic Center"
								className="bg-[#0A0A0F] text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.name} />
						</div>

						<div className="grid gap-2">
							<Label htmlFor="slug" className="text-[#F5F0E8]">
								Subdomain Slug
								<span className="ml-2 text-xs font-normal text-[#9B9B8E]">
									Used for {'{slug}'}.aesthetic-ai.com
								</span>
							</Label>
							<Input
								id="slug"
								value={data.slug}
								onChange={(e) => setData('slug', e.target.value.toLowerCase())}
								placeholder="miamilife"
								className="bg-[#0A0A0F] font-mono text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.slug} />
						</div>

						<div className="grid gap-2">
							<Label htmlFor="plan_id" className="text-[#F5F0E8]">Plan</Label>
							<Select
								value={data.plan_id}
								onValueChange={(v) => setData('plan_id', v)}
							>
								<SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8]">
									<SelectValue placeholder="Select a plan" />
								</SelectTrigger>
								<SelectContent>
									{plans.map((plan) => (
										<SelectItem key={plan.id} value={plan.id}>
											{plan.name}
										</SelectItem>
									))}
								</SelectContent>
							</Select>
							<InputError message={errors.plan_id} />
						</div>

						<div className="grid gap-2">
							<Label className="text-[#F5F0E8]">Enabled Procedures</Label>
							<div className="flex flex-wrap gap-2">
								{PROCEDURES.map((proc) => {
									const active = data.procedures.includes(proc.value);

									return (
										<button
											key={proc.value}
											type="button"
											onClick={() => handleProcedureToggle(proc.value)}
											className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${
												active
													? 'bg-[#0E9E8E] text-[#0A0A0F]'
													: 'bg-white/5 text-[#9B9B8E] hover:bg-white/10'
											}`}
										>
											{proc.label}
										</button>
									);
								})}
							</div>
							<InputError message={errors.procedures} />
						</div>
					</div>

					{/* Owner Account */}
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6 space-y-4">
						<h3 className="text-base font-semibold text-[#F5F0E8]">
							Clinic Owner Account
						</h3>
						<p className="text-sm text-[#9B9B8E]">
							An account will be created with a temporary password and an invitation
							email sent to this address.
						</p>

						<div className="grid gap-2">
							<Label htmlFor="owner_name" className="text-[#F5F0E8]">Full Name</Label>
							<Input
								id="owner_name"
								value={data.owner_name}
								onChange={(e) => setData('owner_name', e.target.value)}
								placeholder="Dr. Ana Rivera"
								className="bg-[#0A0A0F] text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.owner_name} />
						</div>

						<div className="grid gap-2">
							<Label htmlFor="owner_email" className="text-[#F5F0E8]">Email Address</Label>
							<Input
								id="owner_email"
								type="email"
								value={data.owner_email}
								onChange={(e) => setData('owner_email', e.target.value)}
								placeholder="owner@theirclinic.com"
								className="bg-[#0A0A0F] text-[#F5F0E8]"
								required
							/>
							<InputError message={errors.owner_email} />
						</div>
					</div>

					<Button
						type="submit"
						disabled={processing}
						className="w-full bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90 font-semibold"
					>
						{processing ? 'Creating Clinic...' : 'Create Clinic & Send Invite'}
					</Button>
				</form>
			</div>
		</>
	);
}

CreateTenant.layout = {
	breadcrumbs: [
		{ title: 'Admin', href: '/admin' },
		{ title: 'Tenants', href: '/admin/tenants' },
		{ title: 'Create', href: '/admin/tenants/create' },
	],
};

// ── Helpers ───────────────────────────────────────────────────────────────────

function slugify(value: string): string {
	return value
		.toLowerCase()
		.replace(/[^a-z0-9-]/g, '-')
		.replace(/-+/g, '-')
		.replace(/^-|-$/g, '');
}
