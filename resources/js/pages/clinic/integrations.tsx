import { Head, useForm, router } from '@inertiajs/react';
import { RefreshCcw, Copy, Check, Send, Key, Plus, Trash2, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { updateWebhook, rotateSecret, sendTest, createToken, revokeToken } from '@/actions/App/Http/Controllers/Clinic/IntegrationController';
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

interface ApiTokenData {
    id: string;
    name: string;
    scopes: string[];
    last_used_at: string | null;
    created_at: string;
}

interface Props {
    tenant: {
        id: string;
        webhook_url: string | null;
        webhook_secret: string;
    };
    tenantDomain: string;
    widgetUrl: string;
    availableProcedures: { slug: string; label: string }[];
    apiTokens: ApiTokenData[];
}

export default function Integrations({ tenant, tenantDomain, widgetUrl, availableProcedures, apiTokens }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        webhook_url: tenant.webhook_url || '',
    });

    const { post: rotatePost, processing: rotating } = useForm();

    const [copiedContent, setCopiedContent] = useState<'script' | 'secret' | 'clinic-id' | string | null>(null);

    const [testResult, setTestResult] = useState<{
        ok: boolean;
        status_code: number | null;
        latency_ms: number;
        body: string;
    } | null>(null);
    const [testSending, setTestSending] = useState(false);

    // ─── API Token state ───────────────────────────────────────────────────────
    const [newTokenName, setNewTokenName] = useState('');
    const [creatingToken, setCreatingToken] = useState(false);
    const [newTokenNameError, setNewTokenNameError] = useState('');
    const [revealedToken, setRevealedToken] = useState<{ id: string; name: string; raw: string } | null>(null);
    const [tokenVisible, setTokenVisible] = useState(false);
    const [showNewTokenForm, setShowNewTokenForm] = useState(false);

    const handleSendTest = async () => {
        setTestResult(null);
        setTestSending(true);
        try {
            const res = await fetch(sendTest.url(), { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '', 'Accept': 'application/json' } });
            const json = await res.json();
            setTestResult(json);
        } catch (e) {
            setTestResult({ ok: false, status_code: null, latency_ms: 0, body: String(e) });
        } finally {
            setTestSending(false);
        }
    };

    // Widget Generator State
    const [widgetTheme, setWidgetTheme] = useState('luxury-dark');
    const [widgetLanguage, setWidgetLanguage] = useState('en');
    const [widgetProcedure, setWidgetProcedure] = useState<string>('none');
    const [widgetRenderMode, setWidgetRenderMode] = useState('inline');
    const [widgetButtonLabel, setWidgetButtonLabel] = useState('Start Free Evaluation');
    const [widgetHideHeader, setWidgetHideHeader] = useState('false');
    const [widgetPrimaryColor, setWidgetPrimaryColor] = useState('#0E9E8E');
    const [widgetFontFamily, setWidgetFontFamily] = useState('system-ui, sans-serif');

    const handleCopy = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopiedContent(key);
        setTimeout(() => setCopiedContent(null), 2000);
    };

    const handleWebhookSave = (e: React.FormEvent) => {
        e.preventDefault();
        patch(updateWebhook.url());
    };

    const handleRotateSecret = () => {
        if (confirm('Are you certain? This will immediately invalidate existing integrations using the current secret.')) {
            rotatePost(rotateSecret.url());
        }
    };

    const handleCreateToken = async () => {
        if (!newTokenName.trim()) {
            setNewTokenNameError('Token name is required.');
            return;
        }
        setNewTokenNameError('');
        setCreatingToken(true);
        try {
            const res = await fetch(createToken.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ name: newTokenName.trim() }),
            });
            const json = await res.json();
            if (res.ok) {
                setRevealedToken({ id: json.data.id, name: json.data.name, raw: json.data.raw_token });
                setTokenVisible(true);
                setNewTokenName('');
                setShowNewTokenForm(false);
                // Reload page data so token list updates
                router.reload({ only: ['apiTokens'] });
            } else {
                setNewTokenNameError(json.errors?.name?.[0] ?? json.message ?? 'Failed to create token.');
            }
        } finally {
            setCreatingToken(false);
        }
    };

    const handleRevokeToken = (token: ApiTokenData) => {
        if (!confirm(`Revoke "${token.name}"? Any integrations using this token will stop working immediately.`)) {
            return;
        }
        router.delete(revokeToken.url({ apiToken: token.id }), { preserveScroll: true });
    };

    const generatedScript = `<!-- SymetriHealth Widget -->
<script
  src="${widgetUrl}"
  data-clinic-id="${tenant.id}"
  data-domain="${tenantDomain}"${widgetProcedure !== 'none' ? `\n  data-procedure="${widgetProcedure}"` : ''}
  data-render-mode="${widgetRenderMode}"${widgetRenderMode !== 'inline' ? `\n  data-button-label="${widgetButtonLabel.replace(/"/g, "'")}"` : ''}
  data-hide-header="${widgetHideHeader}"
  data-theme="${widgetTheme}"
  data-primary-color="${widgetPrimaryColor}"
  data-font-family="${widgetFontFamily.replace(/"/g, "'")}"
  data-language="${widgetLanguage}"
  async
></script>`;

    return (
        <>
            <Head title="Integrations" />

            <div className="space-y-8 pb-12">
                <Heading
                    title="Integrations & Embeds"
                    description="Connect SymetriHealth to your existing systems and embed the intake widget."
                />

                {/* Widget SDK Generator */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-lg font-semibold text-foreground">Embed SDK Widget</h3>
                    <p className="mb-6 text-sm text-muted-foreground">
                        Configure the settings below to generate your personalized widget code.
                        Paste this code directly into your website's HTML just before the closing <code>&lt;/body&gt;</code> tag.
                    </p>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <div className="space-y-5">
                            <div className="grid gap-2">
                                <Label className="text-foreground">Display Mode</Label>
                                <Select value={widgetRenderMode} onValueChange={setWidgetRenderMode}>
                                    <SelectTrigger className="bg-background text-foreground border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="inline">Inline (Direct Form)</SelectItem>
                                        <SelectItem value="button-modal">Button Modal</SelectItem>
                                        <SelectItem value="fab">Floating Action Button (Sticky)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {widgetRenderMode !== 'inline' && (
                                <div className="grid gap-2">
                                    <Label className="text-foreground">Button Label</Label>
                                    <Input
                                        type="text"
                                        value={widgetButtonLabel}
                                        onChange={(e) => setWidgetButtonLabel(e.target.value)}
                                        placeholder="Start Free Evaluation"
                                        className="bg-background text-foreground border-sidebar-border/50"
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label className="text-foreground">Widget Theme</Label>
                                <Select value={widgetTheme} onValueChange={setWidgetTheme}>
                                    <SelectTrigger className="bg-background text-foreground border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="luxury-dark">Luxury Dark</SelectItem>
                                        <SelectItem value="luxury-light">Luxury Light</SelectItem>
                                        <SelectItem value="clinical">Clinical</SelectItem>
                                        <SelectItem value="bare">Bare (No CSS)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Override for this embed only. Your global default is set in{' '}
                                    <a href="/clinic/settings" className="underline underline-offset-2 hover:text-foreground transition-colors">
                                        Clinic Settings
                                    </a>.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-foreground">Hide Widget Header?</Label>
                                <Select value={widgetHideHeader} onValueChange={setWidgetHideHeader}>
                                    <SelectTrigger className="bg-background text-foreground border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="false">No (Show Logo)</SelectItem>
                                        <SelectItem value="true">Yes (Hide Logo)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-foreground">Primary Brand Color</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="color"
                                        value={widgetPrimaryColor}
                                        onChange={(e) => setWidgetPrimaryColor(e.target.value.toUpperCase())}
                                        className="h-10 w-14 cursor-pointer p-0.5 bg-background border-sidebar-border/50"
                                    />
                                    <Input
                                        type="text"
                                        value={widgetPrimaryColor}
                                        onChange={(e) => setWidgetPrimaryColor(e.target.value.toUpperCase())}
                                        className="bg-background text-foreground border-sidebar-border/50 uppercase font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-foreground">Custom Font Family</Label>
                                <Input
                                    type="text"
                                    value={widgetFontFamily}
                                    onChange={(e) => setWidgetFontFamily(e.target.value)}
                                    placeholder="e.g. 'Proxima Nova', sans-serif"
                                    className="bg-background text-foreground border-sidebar-border/50"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-foreground">Language</Label>
                                <Select value={widgetLanguage} onValueChange={setWidgetLanguage}>
                                    <SelectTrigger className="bg-background text-foreground border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="en">English</SelectItem>
                                        <SelectItem value="es">Spanish</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-foreground">Default Procedure (Optional)</Label>
                                <Select value={widgetProcedure} onValueChange={setWidgetProcedure}>
                                    <SelectTrigger className="bg-background text-foreground border-sidebar-border/50">
                                        <SelectValue placeholder="Let patient choose" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Let patient choose</SelectItem>
                                        {availableProcedures.map((proc) => (
                                            <SelectItem key={proc.slug} value={proc.slug}>{proc.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <Label className="text-foreground">Generated Script</Label>
                            <div className="relative rounded-md border border-border bg-background p-4 font-mono text-xs sm:text-sm text-foreground overflow-x-auto whitespace-pre">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => handleCopy(generatedScript, 'script')}
                                    className="absolute right-2 top-2 h-8 w-8 text-muted-foreground hover:bg-muted hover:text-[#0E9E8E]"
                                >
                                    {copiedContent === 'script' ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                </Button>
                                {generatedScript}
                            </div>
                            <p className="text-xs text-muted-foreground mt-3">
                                <strong>Requirement:</strong> Ensure your site sends the
                                <code> &lt;meta http-equiv="Permissions-Policy" content="camera=(self 'https://app.aestheticai.com')"&gt; </code> header so the widget can access the camera for photos.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="border-t border-border w-full mt-8 mb-8"></div>

                {/* Outbound Webhook Settings */}
                <form onSubmit={handleWebhookSave} className="space-y-6">
                    <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                        <div className="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">CRM Webhook & Zapier</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Automatically trigger workflow actions in HubSpot, PatientNow, or Zapier when an evaluation is complete.
                                </p>
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90 whitespace-nowrap"
                            >
                                {processing ? 'Saving...' : 'Save Configuration'}
                            </Button>
                        </div>

                        <div className="grid gap-8 lg:grid-cols-2">
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="webhook" className="text-foreground">Target Webhook URL</Label>
                                    <Input
                                        id="webhook"
                                        type="url"
                                        value={data.webhook_url}
                                        onChange={(e) => setData('webhook_url', e.target.value)}
                                        placeholder="https://hooks.zapier.com/hooks/catch/..."
                                        className="bg-background text-foreground border-sidebar-border/50"
                                        disabled={processing}
                                    />
                                    <InputError message={errors.webhook_url} />
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label className="text-foreground">HMAC Signing Secret</Label>
                                    <div className="relative">
                                        <Input
                                            type="text"
                                            readOnly
                                            value={tenant.webhook_secret}
                                            className="bg-background text-muted-foreground pr-20 font-mono text-xs border-sidebar-border/50"
                                        />
                                        <div className="absolute inset-y-0 right-1 flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleCopy(tenant.webhook_secret, 'secret')}
                                                className="h-7 w-7 text-muted-foreground hover:text-[#0E9E8E]"
                                                title="Copy secret"
                                            >
                                                {copiedContent === 'secret' ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={handleRotateSecret}
                                                disabled={rotating}
                                                className="h-7 w-7 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                                title="Rotate secret"
                                            >
                                                <RefreshCcw className={`h-3.5 w-3.5 ${rotating ? 'animate-spin' : ''}`} />
                                            </Button>
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Use this secret to independently verify inbound <code>X-SymetriHealth-Signature</code> headers. Give it only to trusted developers.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                {/* Test Webhook */}
                {data.webhook_url && (
                    <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h3 className="text-sm font-semibold text-foreground">Test Your Endpoint</h3>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Send a live <code>evaluation.test</code> payload to <span className="text-foreground">{data.webhook_url}</span> and see the response immediately.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleSendTest}
                                disabled={testSending}
                                className="shrink-0 gap-1.5 bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#2DD4BF]"
                            >
                                <Send className={`h-3.5 w-3.5 ${testSending ? 'animate-pulse' : ''}`} />
                                {testSending ? 'Sending…' : 'Send Test'}
                            </Button>
                        </div>

                        {testResult && (
                            <div className={`mt-4 rounded-md border p-4 ${testResult.ok ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-red-500/30 bg-red-500/5'}`}>
                                <div className="flex items-center gap-3 mb-2">
                                    <span className={`text-sm font-semibold ${testResult.ok ? 'text-emerald-400' : 'text-red-400'}`}>
                                        {testResult.ok ? '✓ Delivered' : '✗ Failed'}
                                    </span>
                                    {testResult.status_code && (
                                        <span className="font-mono text-xs text-muted-foreground">
                                            HTTP {testResult.status_code}
                                        </span>
                                    )}
                                    <span className="font-mono text-xs text-muted-foreground">{testResult.latency_ms}ms</span>
                                </div>
                                {testResult.body && (
                                    <pre className="text-xs text-muted-foreground overflow-x-auto whitespace-pre-wrap break-all">{testResult.body}</pre>
                                )}
                            </div>
                        )}
                    </div>
                )}

                <div className="border-t border-border w-full mt-8 mb-8"></div>

                {/* REST API Access */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <div className="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <Key className="mt-0.5 h-5 w-5 shrink-0 text-[#0E9E8E]" />
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">REST API Access</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Use these credentials when configuring a Zapier step or any external system that needs to call{' '}
                                    <code>GET /api/v1/evaluations/{'{'}token{'}'}</code> to fetch patient details.
                                </p>
                            </div>
                        </div>
                        {!showNewTokenForm && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setShowNewTokenForm(true)}
                                className="shrink-0 gap-1.5 bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Generate Token
                            </Button>
                        )}
                    </div>

                    {/* Clinic ID row */}
                    <div className="mb-6 grid gap-2">
                        <Label className="text-foreground">
                            Clinic ID <span className="ml-1 text-xs font-normal text-muted-foreground">(X-Clinic-ID header)</span>
                        </Label>
                        <div className="relative">
                            <Input
                                type="text"
                                readOnly
                                value={tenant.id}
                                className="bg-background text-muted-foreground pr-10 font-mono text-xs border-sidebar-border/50"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => handleCopy(tenant.id, 'clinic-id')}
                                className="absolute inset-y-0 right-1 h-auto w-8 text-muted-foreground hover:text-[#0E9E8E]"
                                title="Copy Clinic ID"
                            >
                                {copiedContent === 'clinic-id' ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Send this as the <code>X-Clinic-ID</code> header on every API request alongside your Bearer token.
                        </p>
                    </div>

                    {/* New token revealed banner */}
                    {revealedToken && (
                        <div className="mb-6 rounded-md border border-emerald-500/40 bg-emerald-500/5 p-4">
                            <p className="mb-2 text-sm font-semibold text-emerald-400">
                                ✓ Token "{revealedToken.name}" created — copy it now, it won't be shown again.
                            </p>
                            <div className="relative">
                                <Input
                                    type={tokenVisible ? 'text' : 'password'}
                                    readOnly
                                    value={revealedToken.raw}
                                    className="bg-background pr-20 font-mono text-xs border-emerald-500/30 text-foreground"
                                />
                                <div className="absolute inset-y-0 right-1 flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setTokenVisible((v) => !v)}
                                        className="h-7 w-7 text-muted-foreground hover:text-foreground"
                                        title={tokenVisible ? 'Hide token' : 'Show token'}
                                    >
                                        {tokenVisible ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => handleCopy(revealedToken.raw, `token-${revealedToken.id}`)}
                                        className="h-7 w-7 text-muted-foreground hover:text-[#0E9E8E]"
                                        title="Copy token"
                                    >
                                        {copiedContent === `token-${revealedToken.id}` ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                                    </Button>
                                </div>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Use as: <code>Authorization: Bearer {revealedToken.raw.slice(0, 16)}…</code>
                            </p>
                        </div>
                    )}

                    {/* New token form */}
                    {showNewTokenForm && (
                        <div className="mb-6 rounded-md border border-sidebar-border/50 bg-background p-4">
                            <p className="mb-3 text-sm font-medium text-foreground">Name this token</p>
                            <p className="mb-3 text-xs text-muted-foreground">
                                Give it a descriptive name so you know which integration uses it (e.g. "Zapier Production").
                            </p>
                            <div className="flex gap-2">
                                <div className="flex-1">
                                    <Input
                                        type="text"
                                        value={newTokenName}
                                        onChange={(e) => { setNewTokenName(e.target.value); setNewTokenNameError(''); }}
                                        placeholder="e.g. Zapier Production"
                                        className="bg-background text-foreground border-sidebar-border/50"
                                        onKeyDown={(e) => e.key === 'Enter' && handleCreateToken()}
                                        autoFocus
                                    />
                                    {newTokenNameError && (
                                        <p className="mt-1 text-xs text-red-400">{newTokenNameError}</p>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    onClick={handleCreateToken}
                                    disabled={creatingToken}
                                    className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                                >
                                    {creatingToken ? 'Creating…' : 'Create'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => { setShowNewTokenForm(false); setNewTokenName(''); setNewTokenNameError(''); }}
                                    className="text-muted-foreground"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Active tokens list */}
                    {apiTokens.length > 0 ? (
                        <div className="space-y-2">
                            <Label className="text-foreground">Active Tokens</Label>
                            <div className="rounded-md border border-sidebar-border/50 divide-y divide-sidebar-border/30">
                                {apiTokens.map((token) => (
                                    <div key={token.id} className="flex items-center justify-between gap-4 px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-foreground truncate">{token.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Created {new Date(token.created_at).toLocaleDateString()}
                                                {token.last_used_at && (
                                                    <> · Last used {new Date(token.last_used_at).toLocaleDateString()}</>
                                                )}
                                                {!token.last_used_at && ' · Never used'}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRevokeToken(token)}
                                            className="shrink-0 gap-1.5 text-red-400 hover:text-red-300 hover:bg-red-950/30"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Revoke
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        !showNewTokenForm && !revealedToken && (
                            <p className="text-sm text-muted-foreground">
                                No active API tokens. Generate one above to connect Zapier or another external service.
                            </p>
                        )
                    )}
                </div>

                <div className="border-t border-border w-full mt-8 mb-8"></div>

                {/* Developer Documentation */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-lg font-semibold text-foreground">Developer Documentation & Payload Specs</h3>
                    <p className="mb-6 text-sm text-muted-foreground">
                        When an evaluation is completed, we will POST a JSON payload to your webhook. Note that the payload intentionally contains no Protected Health Information (PHI) to maintain HIPAA standards in transit. Use the provided <code>evaluation_token</code> to fetch the full patient details securely via our REST API.
                    </p>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <div className="space-y-3">
                            <Label className="text-foreground">Webhook Payload Example (POST)</Label>
                            <div className="rounded-md border border-border bg-background p-4 font-mono text-xs text-foreground overflow-x-auto whitespace-pre">
{`{
  "event": "evaluation.completed",
  "api_version": "2025-01",
  "idempotency_key": "eval_01HXYZ...",
  "timestamp": "2025-06-15T14:32:00Z",
  "data": {
    "evaluation_token": "eyJhbGciOi...",
    "procedure_interest": "rhinoplasty",
    "lead_score": 87,
    "priority": "high",
    "ready_for_call": true,
    "timeline": "within_3_months",
    "budget_range": "15000_25000",
    "photos_available": true,
    "ai_analysis_complete": true
  }
}`}
                            </div>
                        </div>

                        <div className="space-y-3">
                            <Label className="text-foreground">Fetch Patient API (GET)</Label>
                            <p className="text-xs text-muted-foreground mb-2 leading-relaxed">
                                Call this endpoint using the token from the webhook to retrieve the full patient profile, including name, email, phone, and securely signed photo URLs.
                            </p>
                            <div className="rounded-md border border-border bg-background px-4 py-3 font-mono text-xs text-[#0E9E8E] overflow-x-auto whitespace-pre">
                                GET /api/v1/evaluations/{"{"}evaluation_token{"}"}
                            </div>
                            <div className="rounded-md border border-border bg-background p-4 font-mono text-xs text-foreground overflow-x-auto whitespace-pre mt-3">
{`{
  "data": {
    "id": "eval_01HXYZ...",
    "patient": {
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+13055550123"
    },
    "quiz_summary": { ... },
    "ai_analysis": { ... },
    "photos": {
      "front": { "url": "https://...", "expires_at": "..." }
    }
  }
}`}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </>
    );
}

Integrations.layout = {
    breadcrumbs: [
        {
            title: 'Integrations',
            href: updateWebhook.url(),
        },
    ],
};
