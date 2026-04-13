import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { update } from '@/routes/clinic/settings';

interface AvailableProcedure {
	slug: string;
	label: string;
	category: string;
}

interface ClinicFormData {
	name: string;
	theme: string;
	procedures_enabled: string[];
	coordinator_emails: string[];
}

interface Props {
	clinic: ClinicFormData & { slug: string; logo_url: string | null };
	availableProcedures: AvailableProcedure[];
}

export default function ClinicSettings({ clinic, availableProcedures }: Props) {
	const { data, setData, patch, processing, errors } =
		useForm<ClinicFormData>({
			name: clinic.name,
			theme: clinic.theme,
			procedures_enabled: clinic.procedures_enabled,
			coordinator_emails: clinic.coordinator_emails,
		});

	const [newEmail, setNewEmail] = useState('');

	const handleAddEmail = () => {
		if (newEmail.trim() && !data.coordinator_emails.includes(newEmail)) {
			setData('coordinator_emails', [...data.coordinator_emails, newEmail]);
			setNewEmail('');
		}
	};

	const handleRemoveEmail = (email: string) => {
		setData(
			'coordinator_emails',
			data.coordinator_emails.filter((e) => e !== email)
		);
	};

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		patch(update.url());
	};

	const handleProcedureToggle = (slug: string) => {
		setData(
			'procedures_enabled',
			data.procedures_enabled.includes(slug)
				? data.procedures_enabled.filter((s) => s !== slug)
				: [...data.procedures_enabled, slug]
		);
	};

	const proceduresByCategory = availableProcedures.reduce(
		(acc, proc) => {
			if (!acc[proc.category]) {
				acc[proc.category] = [];
			}

			acc[proc.category].push(proc);

			return acc;
		},
		{} as Record<string, AvailableProcedure[]>
	);

	return (
		<>
			<Head title="Clinic Settings" />

			<div className="space-y-8">
				<Heading
					title="Clinic Settings"
					description="Configure your clinic's branding and preferences"
				/>

				<form onSubmit={handleSubmit} className="space-y-6">
					{/* General Settings Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
						<h3 className="mb-6 text-base font-semibold text-foreground">
							General
						</h3>

						<div className="space-y-4">
							<div className="grid gap-2">
								<Label htmlFor="name" className="text-foreground">
									Clinic Name
								</Label>
								<Input
									id="name"
									value={data.name}
									onChange={(e) =>
										setData('name', e.target.value)
									}
									placeholder="Your clinic name"
									className="bg-background text-foreground"
								/>
								<InputError message={errors.name} />
							</div>

							<div className="grid gap-2">
								<Label className="text-foreground">Intake Page Theme</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									Applies to your hosted intake form. The embedded widget uses this by default but can be overridden per embed in Integrations.
								</p>
								<div className="flex gap-3">
									{[
										{ value: 'luxury-dark', label: 'Luxury Dark' },
										{ value: 'luxury-light', label: 'Luxury Light' },
										{ value: 'clinical', label: 'Clinical' },
									].map(({ value, label }) => (
										<button
											key={value}
											type="button"
											onClick={() => setData('theme', value)}
											className={cn(
												'flex-1 rounded-lg border-2 px-4 py-3 font-medium transition-all',
												data.theme === value
													? 'border-[#0E9E8E] bg-[#0E9E8E]/10 text-[#0E9E8E]'
													: 'border-sidebar-border/50 bg-transparent text-muted-foreground hover:border-[#0E9E8E]/50'
											)}
										>
											{label}
										</button>
									))}
								</div>
								<InputError message={errors.theme} />
							</div>
						</div>
					</div>

					{/* Procedures Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
						<h3 className="mb-6 text-base font-semibold text-foreground">
							Procedures
						</h3>

						<div className="space-y-3">
							{Object.entries(proceduresByCategory).map(
								([category, procedures]) => (
									<div key={category}>
										<p className="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
											{category}
										</p>
										<div className="ml-2 space-y-2">
											{procedures.map((proc) => (
												<label
													key={proc.slug}
													className="flex items-center gap-3 cursor-pointer"
												>
													<Checkbox
														checked={data.procedures_enabled.includes(
															proc.slug
														)}
														onCheckedChange={() =>
															handleProcedureToggle(
																proc.slug
															)
														}
													/>
													<span className="text-sm text-foreground">
														{proc.label}
													</span>
													<Badge
														variant="outline"
														className="ml-auto text-xs"
													>
														{proc.category}
													</Badge>
												</label>
											))}
										</div>
									</div>
								)
							)}
						</div>
						<InputError message={errors.procedures_enabled} />
					</div>

					{/* Notifications Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
						<h3 className="mb-6 text-base font-semibold text-foreground">
							Notifications
						</h3>

						<div className="space-y-4">
							<div>
								<Label className="mb-3 block text-foreground">
									Coordinator Emails
								</Label>

								{data.coordinator_emails.length > 0 && (
									<div className="mb-4 space-y-2">
										{data.coordinator_emails.map(
											(email) => (
												<div
													key={email}
													className="flex items-center justify-between rounded-md bg-background px-3 py-2"
												>
													<span className="text-sm text-foreground">
														{email}
													</span>
													<button
														type="button"
														onClick={() =>
															handleRemoveEmail(
																email
															)
														}
														className="text-xs font-medium text-red-400 hover:text-red-300"
													>
														Remove
													</button>
												</div>
											)
										)}
									</div>
								)}

								<div className="flex gap-2">
									<Input
										type="email"
										value={newEmail}
										onChange={(e) =>
											setNewEmail(e.target.value)
										}
										placeholder="email@example.com"
										className="bg-background text-foreground"
										onKeyDown={(e) => {
											if (e.key === 'Enter') {
												e.preventDefault();
												handleAddEmail();
											}
										}}
									/>
									<Button
										type="button"
										onClick={handleAddEmail}
										disabled={!newEmail.trim()}
										className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
									>
										Add
									</Button>
								</div>
							</div>
						</div>
						<InputError message={errors.coordinator_emails} />
					</div>

					{/* Save Button */}
					<div className="flex justify-end">
						<Button
							type="submit"
							disabled={processing}
							className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
						>
							{processing ? 'Saving...' : 'Save Changes'}
						</Button>
					</div>
				</form>
			</div>
		</>
	);
}

ClinicSettings.layout = {
	breadcrumbs: [
		{
			title: 'Clinic Settings',
			href: update.url(),
		},
	],
};
