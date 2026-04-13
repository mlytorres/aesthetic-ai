import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

interface RegisterFields {
    clinic_name: string;
    slug: string;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

function generateSlug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 50);
}

export default function Register() {
    const { data, setData, post, processing, errors } = useForm<RegisterFields>({
        clinic_name: '',
        slug: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    // Once the user manually edits the slug, stop auto-generating from clinic name
    const [slugEdited, setSlugEdited] = useState(false);

    const appDomain = window.location.hostname.replace(/^[^.]+\./, '');

    function handleClinicNameChange(value: string) {
        setData('clinic_name', value);
        if (!slugEdited) {
            setData('slug', generateSlug(value));
        }
    }

    function handleSlugChange(value: string) {
        const cleaned = value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50);
        setData('slug', cleaned);
        setSlugEdited(cleaned !== '' && cleaned !== generateSlug(data.clinic_name));
    }

    function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="Start your free trial" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                {/* ── Clinic details ── */}
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="clinic_name">Clinic name</Label>
                        <Input
                            id="clinic_name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="organization"
                            placeholder="Miami Aesthetic Center"
                            value={data.clinic_name}
                            onChange={(e) => handleClinicNameChange(e.target.value)}
                        />
                        <InputError message={errors.clinic_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">Your subdomain</Label>
                        <div className="flex items-center gap-0 overflow-hidden rounded-md border bg-background focus-within:ring-1 focus-within:ring-ring">
                            <input
                                id="slug"
                                type="text"
                                required
                                tabIndex={2}
                                autoComplete="off"
                                spellCheck={false}
                                placeholder="miami-aesthetic"
                                value={data.slug}
                                onChange={(e) => handleSlugChange(e.target.value)}
                                className="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                            />
                            <span className="shrink-0 border-l bg-muted px-3 py-2 text-sm text-muted-foreground">
                                .{appDomain}
                            </span>
                        </div>
                        <InputError message={errors.slug} />
                    </div>
                </div>

                {/* ── Account details ── */}
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Your name</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            tabIndex={3}
                            autoComplete="name"
                            placeholder="Dr. Jane Smith"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Work email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={4}
                            autoComplete="email"
                            placeholder="jane@clinic.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            required
                            tabIndex={5}
                            autoComplete="new-password"
                            placeholder="Min. 8 characters"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <PasswordInput
                            id="password_confirmation"
                            required
                            tabIndex={6}
                            autoComplete="new-password"
                            placeholder="Repeat password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                <Button
                    type="submit"
                    className="w-full"
                    tabIndex={7}
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    Start 14-day free trial
                </Button>

                <p className="text-center text-xs text-muted-foreground">
                    By signing up you agree to our{' '}
                    <TextLink href="/terms" tabIndex={8} className="text-xs">
                        Terms of Service
                    </TextLink>{' '}
                    and{' '}
                    <TextLink href="/privacy" tabIndex={9} className="text-xs">
                        Privacy Policy
                    </TextLink>
                    .
                </p>

                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink href={login()} tabIndex={10}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </>
    );
}

Register.layout = {
    title: 'Start your free trial',
    description: '14 days free — no credit card required',
};
