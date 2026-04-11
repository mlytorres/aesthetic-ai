import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
	webhook_url: string;
}

interface Props {
	clinic: ClinicFormData & { slug: string; logo_url: string | null };
	availableProcedures: AvailableProcedure[];
}

export default function ClinicSettings({ clinic, availableProcedures }: Props) {
	const { data, setData, patch, processing, errors, reset } =
		useForm<ClinicFormData>({
			name: clinic.name,
			theme: clinic.theme,
			procedures_enabled: clinic.procedures_enabled,
			coordinator_emails: clinic.coordinator_emails,
			webhook_url: clinic.webhook_url,
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
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
						<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
							General
						</h3>

						<div className="space-y-4">
							<div className="grid gap-2">
								<Label htmlFor="name" className="text-[#F5F0E8]">
									Clinic Name
								</Label>
								<Input
									id="name"
									value={data.name}
									onChange={(e) =>
										setData('name', e.target.value)
									}
									placeholder="Your clinic name"
									className="bg-[#0A0A0F] text-[#F5F0E8]"
								/>
								<InputError message={errors.name} />
							</div>

							<div className="grid gap-2">
								<Label className="text-[#F5F0E8]">Theme</Label>
								<div className="flex gap-3">
									<button
										type="button"
										onClick={() =>
											setData('theme', 'luxury-dark')
										}
										className={cn(
											'flex-1 rounded-lg border-2 px-4 py-3 font-medium transition-all',
											data.theme === 'luxury-dark'
												? 'border-[#C9A84C] bg-[#C9A84C]/10 text-[#C9A84C]'
												: 'border-sidebar-border/50 bg-transparent text-[#9B9B8E] hover:border-[#C9A84C]/50'
										)}
									>
										Luxury Dark
									</button>
									<button
										type="button"
										onClick={() =>
											setData('theme', 'clean-light')
										}
										className={cn(
											'flex-1 rounded-lg border-2 px-4 py-3 font-medium transition-all',
											data.theme === 'clean-light'
												? 'border-[#C9A84C] bg-[#C9A84C]/10 text-[#C9A84C]'
												: 'border-sidebar-border/50 bg-transparent text-[#9B9B8E] hover:border-[#C9A84C]/50'
										)}
									>
										Clean Light
									</button>
								</div>
								<InputError message={errors.theme} />
							</div>
						</div>
					</div>

					{/* Procedures Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
						<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
							Procedures
						</h3>

						<div className="space-y-3">
							{Object.entries(proceduresByCategory).map(
								([category, procedures]) => (
									<div key={category}>
										<p className="mb-2 text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">
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
													<span className="text-sm text-[#F5F0E8]">
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
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
						<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
							Notifications
						</h3>

						<div className="space-y-4">
							<div>
								<Label className="mb-3 block text-[#F5F0E8]">
									Coordinator Emails
								</Label>

								{data.coordinator_emails.length > 0 && (
									<div className="mb-4 space-y-2">
										{data.coordinator_emails.map(
											(email) => (
												<div
													key={email}
													className="flex items-center justify-between rounded-md bg-[#0A0A0F] px-3 py-2"
												>
													<span className="text-sm text-[#F5F0E8]">
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
										className="bg-[#0A0A0F] text-[#F5F0E8]"
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
										className="bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90"
									>
										Add
									</Button>
								</div>
							</div>
						</div>
						<InputError message={errors.coordinator_emails} />
					</div>

					{/* Integrations Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
						<h3 className="mb-6 text-base font-semibold text-[#F5F0E8]">
							Integrations
						</h3>

						<div className="space-y-4">
							<div className="grid gap-2">
								<Label htmlFor="webhook" className="text-[#F5F0E8]">
									Webhook URL
								</Label>
								<Input
									id="webhook"
									type="url"
									value={data.webhook_url}
									onChange={(e) =>
										setData('webhook_url', e.target.value)
									}
									placeholder="https://api.example.com/webhook"
									className="bg-[#0A0A0F] text-[#F5F0E8]"
								/>
								<p className="text-xs text-[#9B9B8E]">
									Webhooks send evaluation tokens to your CRM
									when analysis completes.
								</p>
							</div>
						</div>
						<InputError message={errors.webhook_url} />
					</div>

					{/* Save Button */}
					<div className="flex justify-end">
						<Button
							type="submit"
							disabled={processing}
							className="bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90"
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
