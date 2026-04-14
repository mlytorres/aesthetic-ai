import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import settings, { update } from '@/routes/clinic/settings';

interface AvailableProcedure {
	slug: string;
	label: string;
	category: string;
}

interface ClinicFormData {
	name: string;
	theme: string;
	brand_primary: string;
	from_name: string;
	custom_domain: string;
	locale: string;
	procedures_enabled: string[];
	coordinator_emails: string[];
	phone: string;
	booking_url: string;
}

interface Props {
	clinic: ClinicFormData & {
		slug: string;
		logo_url: string | null;
		phone: string | null;
		booking_url: string | null;
	};
	availableProcedures: AvailableProcedure[];
}

export default function ClinicSettings({ clinic, availableProcedures }: Props) {
	const { data, setData, patch, processing, errors } =
		useForm<ClinicFormData>({
			name: clinic.name,
			theme: clinic.theme,
			brand_primary: clinic.brand_primary ?? '',
			from_name: clinic.from_name ?? '',
			custom_domain: clinic.custom_domain ?? '',
			locale: clinic.locale ?? 'en',
			procedures_enabled: clinic.procedures_enabled,
			coordinator_emails: clinic.coordinator_emails,
			phone: clinic.phone ?? '',
			booking_url: clinic.booking_url ?? '',
		});

	const [newEmail, setNewEmail] = useState('');
	const [logoUrl, setLogoUrl] = useState<string | null>(clinic.logo_url);
	const [logoUploading, setLogoUploading] = useState(false);
	const logoInputRef = useRef<HTMLInputElement>(null);

	const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const file = e.target.files?.[0];
		if (!file) return;
		setLogoUploading(true);
		router.post(
			settings.logo.upload.url(),
			{ logo: file },
			{
				forceFormData: true,
				preserveScroll: true,
				onSuccess: () => setLogoUploading(false),
				onError: () => setLogoUploading(false),
			}
		);
	};

	const handleLogoDelete = () => {
		router.delete(settings.logo.delete.url(), { preserveScroll: true });
	};

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

							{/* Intake language */}
							<div className="grid gap-2">
								<Label className="text-foreground">Intake Language</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									Language shown to patients in the intake wizard.
								</p>
								<div className="flex gap-3">
									{[
										{ value: 'en', label: '🇺🇸 English' },
										{ value: 'es', label: '🇪🇸 Español' },
									].map(({ value, label }) => (
										<button
											key={value}
											type="button"
											onClick={() => setData('locale', value)}
											className={cn(
												'flex-1 rounded-lg border-2 px-4 py-3 font-medium transition-all max-w-[140px]',
												data.locale === value
													? 'border-[#0E9E8E] bg-[#0E9E8E]/10 text-[#0E9E8E]'
													: 'border-sidebar-border/50 bg-transparent text-muted-foreground hover:border-[#0E9E8E]/50'
											)}
										>
											{label}
										</button>
									))}
								</div>
								<InputError message={errors.locale} />
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

					{/* Branding Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
						<h3 className="mb-6 text-base font-semibold text-foreground">
							Branding
						</h3>

						<div className="space-y-6">
							{/* Logo upload */}
							<div className="grid gap-2">
								<Label className="text-foreground">Clinic Logo</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									Shown in the intake wizard header. PNG, JPG, or SVG — max 2 MB.
								</p>
								<div className="flex items-center gap-4">
									{logoUrl ? (
										<img
											src={logoUrl}
											alt="Clinic logo"
											className="h-12 w-auto max-w-[160px] rounded-md border border-border object-contain p-1"
										/>
									) : (
										<div className="flex h-12 w-24 items-center justify-center rounded-md border border-dashed border-border bg-muted/30 text-xs text-muted-foreground">
											No logo
										</div>
									)}
									<div className="flex flex-col gap-2">
										<input
											ref={logoInputRef}
											type="file"
											accept="image/jpeg,image/png,image/webp,image/svg+xml"
											className="hidden"
											onChange={handleLogoChange}
										/>
										<Button
											type="button"
											variant="outline"
											size="sm"
											disabled={logoUploading}
											onClick={() => logoInputRef.current?.click()}
										>
											{logoUploading ? 'Uploading…' : 'Upload Logo'}
										</Button>
										{logoUrl && (
											<Button
												type="button"
												variant="ghost"
												size="sm"
												className="text-red-400 hover:text-red-300"
												onClick={handleLogoDelete}
											>
												Remove
											</Button>
										)}
									</div>
								</div>
							</div>

							{/* Brand primary color */}
							<div className="grid gap-2">
								<Label htmlFor="brand_primary" className="text-foreground">
									Brand Color
								</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									Replaces the default teal (#0E9E8E) on buttons and accents in the intake wizard.
								</p>
								<div className="flex items-center gap-3">
									<input
										type="color"
										id="brand_primary_picker"
										value={data.brand_primary || '#0E9E8E'}
										onChange={(e) => setData('brand_primary', e.target.value)}
										className="h-9 w-12 cursor-pointer rounded border border-border bg-transparent p-0.5"
									/>
									<Input
										id="brand_primary"
										value={data.brand_primary}
										onChange={(e) => setData('brand_primary', e.target.value)}
										placeholder="#0E9E8E"
										className="w-32 bg-background font-mono text-foreground"
										maxLength={7}
									/>
									{data.brand_primary && (
										<Button
											type="button"
											variant="ghost"
											size="sm"
											className="text-muted-foreground"
											onClick={() => setData('brand_primary', '')}
										>
											Reset
										</Button>
									)}
								</div>
								<InputError message={errors.brand_primary} />
							</div>

							{/* Email sender name */}
							<div className="grid gap-2">
								<Label htmlFor="from_name" className="text-foreground">
									Email Sender Name
								</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									The "From" name shown in patient emails. Defaults to your clinic name.
								</p>
								<Input
									id="from_name"
									value={data.from_name}
									onChange={(e) => setData('from_name', e.target.value)}
									placeholder={clinic.name}
									className="max-w-sm bg-background text-foreground"
								/>
								<InputError message={errors.from_name} />
							</div>

							{/* Custom domain */}
							<div className="grid gap-2">
								<Label htmlFor="custom_domain" className="text-foreground">
									Custom Domain
									<span className="ml-2 rounded-full bg-[#0E9E8E]/10 px-2 py-0.5 text-[10px] font-semibold text-[#0E9E8E] uppercase tracking-wide">
										Pro
									</span>
								</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									Point your own domain (e.g. <code className="font-mono">intake.yourclinic.com</code>) to your intake page.
								</p>
								<Input
									id="custom_domain"
									value={data.custom_domain}
									onChange={(e) => setData('custom_domain', e.target.value)}
									placeholder="intake.yourclinic.com"
									className="max-w-sm bg-background font-mono text-foreground"
								/>
								<InputError message={errors.custom_domain} />
								{data.custom_domain && (
									<div className="rounded-md border border-border/50 bg-muted/30 p-4 text-xs text-muted-foreground space-y-2 max-w-sm mt-1">
										<p className="font-semibold text-foreground">DNS Setup Instructions</p>
										<p>Add a <code className="font-mono">CNAME</code> record at your DNS provider:</p>
										<div className="rounded bg-background px-3 py-2 font-mono text-xs border border-border/30">
											<span className="text-[#0E9E8E]">{data.custom_domain}</span>
											{' → '}
											<span className="text-foreground">{clinic.slug}.aesthetic-ai.com</span>
										</div>
										<p>DNS changes may take up to 24 hours to propagate. Contact support once your record is live to activate SSL.</p>
									</div>
								)}
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

					{/* Contact & Booking Card */}
					<div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
						<h3 className="mb-1 text-base font-semibold text-foreground">
							Contact &amp; Booking
						</h3>
						<p className="mb-6 text-xs text-muted-foreground">
							Shown to patients on their portal so they can reach out to schedule a consultation.
						</p>

						<div className="space-y-4">
							<div className="grid gap-2">
								<Label htmlFor="phone" className="text-foreground">
									Clinic Phone
								</Label>
								<Input
									id="phone"
									type="tel"
									value={data.phone}
									onChange={(e) => setData('phone', e.target.value)}
									placeholder="+1 (305) 555-0100"
									className="max-w-sm bg-background text-foreground"
								/>
								<InputError message={errors.phone} />
							</div>

							<div className="grid gap-2">
								<Label htmlFor="booking_url" className="text-foreground">
									Online Booking URL
								</Label>
								<p className="text-xs text-muted-foreground -mt-1">
									If set, the "Book Consultation" button on the patient portal will link here instead of showing the phone number.
								</p>
								<Input
									id="booking_url"
									type="url"
									value={data.booking_url}
									onChange={(e) => setData('booking_url', e.target.value)}
									placeholder="https://calendly.com/yourclinic"
									className="max-w-sm bg-background text-foreground"
								/>
								<InputError message={errors.booking_url} />
							</div>
						</div>
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
