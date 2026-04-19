import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings/layout';

type Props = {
    canUseEmailFallback: boolean;
    codeExpiresInMinutes: number;
};

export default function CoordinatorEmailOtp({
    canUseEmailFallback,
    codeExpiresInMinutes,
}: Props) {
    const sendForm = useForm({});
    const verifyForm = useForm({
        code: '',
    });

    if (!canUseEmailFallback) {
        return (
            <SettingsLayout>
                <Head title="Security verification" />
                <p className="text-sm text-muted-foreground">
                    Email verification fallback is not available for this
                    account.
                </p>
            </SettingsLayout>
        );
    }

    return (
        <SettingsLayout>
            <Head title="Coordinator security verification" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Email verification code"
                    description="If you do not use an authenticator app yet, you can quickly verify with a code sent to your email."
                />

                <p className="text-sm text-muted-foreground">
                    This is a quick security check to protect patient data. It
                    usually takes less than a minute.
                </p>

                <div className="rounded-md border border-border/60 bg-muted/30 p-3 text-sm text-muted-foreground">
                    <p className="font-medium text-foreground">How it works</p>
                    <p>
                        1) Click <strong>Send verification code</strong>. 2)
                        Open your email inbox. 3) Enter the 6-digit code here.
                        The code expires in about {codeExpiresInMinutes} minutes.
                    </p>
                    <p className="mt-2">
                        If you do not see the email, check spam/junk and click
                        send again.
                    </p>
                </div>

                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        sendForm.post('/settings/security/coordinator-otp/send', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <Button type="submit" disabled={sendForm.processing}>
                        {sendForm.processing
                            ? 'Sending code...'
                            : 'Send verification code'}
                    </Button>
                </form>

                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        verifyForm.post(
                            '/settings/security/coordinator-otp/verify',
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="code">6-digit code</Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            maxLength={6}
                            placeholder="123456"
                            value={verifyForm.data.code}
                            onChange={(event) =>
                                verifyForm.setData(
                                    'code',
                                    event.target.value.replace(/\D/g, ''),
                                )
                            }
                        />
                        <InputError message={verifyForm.errors.code} />
                    </div>

                    <Button type="submit" disabled={verifyForm.processing}>
                        {verifyForm.processing
                            ? 'Verifying...'
                            : 'Verify and continue'}
                    </Button>
                </form>

                <p className="text-xs text-muted-foreground">
                    Need help? Ask your clinic owner/admin or contact support.
                </p>
            </div>
        </SettingsLayout>
    );
}
