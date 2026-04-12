import { Head, useForm } from '@inertiajs/react';
import { RefreshCcw, Copy, Check } from 'lucide-react';
import { useState } from 'react';
import { updateWebhook, rotateSecret } from '@/actions/App/Http/Controllers/Clinic/IntegrationController';
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

interface Props {
    tenant: {
        id: string;
        webhook_url: string | null;
        webhook_secret: string;
    };
    tenantDomain: string;
    widgetUrl: string;
    availableProcedures: { slug: string; label: string }[];
}

export default function Integrations({ tenant, tenantDomain, widgetUrl, availableProcedures }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        webhook_url: tenant.webhook_url || '',
    });

    const { post: rotatePost, processing: rotating } = useForm();

    const [copiedContent, setCopiedContent] = useState<'script' | 'secret' | null>(null);

    // Widget Generator State
    const [widgetTheme, setWidgetTheme] = useState('luxury-dark');
    const [widgetLanguage, setWidgetLanguage] = useState('en');
    const [widgetProcedure, setWidgetProcedure] = useState<string>('none');
    const [widgetRenderMode, setWidgetRenderMode] = useState('inline');
    const [widgetButtonLabel, setWidgetButtonLabel] = useState('Start Free Evaluation');
    const [widgetHideHeader, setWidgetHideHeader] = useState('false');
    const [widgetPrimaryColor, setWidgetPrimaryColor] = useState('#0E9E8E');
    const [widgetFontFamily, setWidgetFontFamily] = useState('system-ui, sans-serif');

    const handleCopy = (text: string, type: 'script' | 'secret') => {
        navigator.clipboard.writeText(text);
        setCopiedContent(type);
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
                <div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
                    <h3 className="mb-4 text-lg font-semibold text-[#F5F0E8]">Embed SDK Widget</h3>
                    <p className="mb-6 text-sm text-[#9B9B8E]">
                        Configure the settings below to generate your personalized widget code.
                        Paste this code directly into your website's HTML just before the closing <code>&lt;/body&gt;</code> tag.
                    </p>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <div className="space-y-5">
                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Display Mode</Label>
                                <Select value={widgetRenderMode} onValueChange={setWidgetRenderMode}>
                                    <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
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
                                    <Label className="text-[#F5F0E8]">Button Label</Label>
                                    <Input 
                                        type="text" 
                                        value={widgetButtonLabel}
                                        onChange={(e) => setWidgetButtonLabel(e.target.value)}
                                        placeholder="Start Free Evaluation"
                                        className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50"
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Theme</Label>
                                <Select value={widgetTheme} onValueChange={setWidgetTheme}>
                                    <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="luxury-dark">Luxury Dark</SelectItem>
                                        <SelectItem value="luxury-light">Luxury Light</SelectItem>
                                        <SelectItem value="clinical">Clinical</SelectItem>
                                        <SelectItem value="bare">Bare (No CSS)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Hide Widget Header?</Label>
                                <Select value={widgetHideHeader} onValueChange={setWidgetHideHeader}>
                                    <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="false">No (Show Logo)</SelectItem>
                                        <SelectItem value="true">Yes (Hide Logo)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Primary Brand Color</Label>
                                <div className="flex gap-2">
                                    <Input 
                                        type="color" 
                                        value={widgetPrimaryColor}
                                        onChange={(e) => setWidgetPrimaryColor(e.target.value.toUpperCase())}
                                        className="h-10 w-14 cursor-pointer p-0.5 bg-[#0A0A0F] border-sidebar-border/50"
                                    />
                                    <Input 
                                        type="text" 
                                        value={widgetPrimaryColor}
                                        onChange={(e) => setWidgetPrimaryColor(e.target.value.toUpperCase())}
                                        className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50 uppercase font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Custom Font Family</Label>
                                <Input 
                                    type="text" 
                                    value={widgetFontFamily}
                                    onChange={(e) => setWidgetFontFamily(e.target.value)}
                                    placeholder="e.g. 'Proxima Nova', sans-serif"
                                    className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50"
                                />
                            </div>
                            
                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Language</Label>
                                <Select value={widgetLanguage} onValueChange={setWidgetLanguage}>
                                    <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="en">English</SelectItem>
                                        <SelectItem value="es">Spanish</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-[#F5F0E8]">Default Procedure (Optional)</Label>
                                <Select value={widgetProcedure} onValueChange={setWidgetProcedure}>
                                    <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
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
                            <Label className="text-[#F5F0E8]">Generated Script</Label>
                            <div className="relative rounded-md border border-[#2A2A3A] bg-[#0A0A0F] p-4 font-mono text-xs sm:text-sm text-[#D4D4D4] overflow-x-auto whitespace-pre">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => handleCopy(generatedScript, 'script')}
                                    className="absolute right-2 top-2 h-8 w-8 text-[#9B9B8E] hover:bg-[#1E1E28] hover:text-[#0E9E8E]"
                                >
                                    {copiedContent === 'script' ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                </Button>
                                {generatedScript}
                            </div>
                            <p className="text-xs text-[#9B9B8E] mt-3">
                                <strong>Requirement:</strong> Ensure your site sends the 
                                <code> &lt;meta http-equiv="Permissions-Policy" content="camera=(self 'https://app.aestheticai.com')"&gt; </code> header so the widget can access the camera for photos.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="border-t border-[#2A2A3A] w-full mt-8 mb-8"></div>

                {/* Outbound Webhook Settings */}
                <form onSubmit={handleWebhookSave} className="space-y-6">
                    <div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
                        <div className="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-[#F5F0E8]">CRM Webhook & Zapier</h3>
                                <p className="mt-1 text-sm text-[#9B9B8E]">
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
                                    <Label htmlFor="webhook" className="text-[#F5F0E8]">Target Webhook URL</Label>
                                    <Input
                                        id="webhook"
                                        type="url"
                                        value={data.webhook_url}
                                        onChange={(e) => setData('webhook_url', e.target.value)}
                                        placeholder="https://hooks.zapier.com/hooks/catch/..."
                                        className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50"
                                        disabled={processing}
                                    />
                                    <InputError message={errors.webhook_url} />
                                </div>
                            </div>
                            
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label className="text-[#F5F0E8]">HMAC Signing Secret</Label>
                                    <div className="relative">
                                        <Input
                                            type="text"
                                            readOnly
                                            value={tenant.webhook_secret}
                                            className="bg-[#0A0A0F] text-[#9B9B8E] pr-20 font-mono text-xs border-sidebar-border/50"
                                        />
                                        <div className="absolute inset-y-0 right-1 flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleCopy(tenant.webhook_secret, 'secret')}
                                                className="h-7 w-7 text-[#9B9B8E] hover:text-[#0E9E8E]"
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
                                    <p className="text-xs text-[#9B9B8E]">
                                        Use this secret to independently verify inbound <code>X-SymetriHealth-Signature</code> headers. Give it only to trusted developers.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div className="border-t border-[#2A2A3A] w-full mt-8 mb-8"></div>

                {/* Developer Documentation */}
                <div className="rounded-lg border border-sidebar-border/50 bg-[#111118] p-6">
                    <h3 className="mb-4 text-lg font-semibold text-[#F5F0E8]">Developer Documentation & Payload Specs</h3>
                    <p className="mb-6 text-sm text-[#9B9B8E]">
                        When an evaluation is completed, we will POST a JSON payload to your webhook. Note that the payload intentionally contains no Protected Health Information (PHI) to maintain HIPAA standards in transit. Use the provided <code>evaluation_token</code> to fetch the full patient details securely via our REST API.
                    </p>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <div className="space-y-3">
                            <Label className="text-[#F5F0E8]">Webhook Payload Example (POST)</Label>
                            <div className="rounded-md border border-[#2A2A3A] bg-[#0A0A0F] p-4 font-mono text-xs text-[#D4D4D4] overflow-x-auto whitespace-pre">
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
                            <Label className="text-[#F5F0E8]">Fetch Patient API (GET)</Label>
                            <p className="text-xs text-[#9B9B8E] mb-2 leading-relaxed">
                                Call this endpoint using the token from the webhook to retrieve the full patient profile, including name, email, phone, and securely signed photo URLs.
                            </p>
                            <div className="rounded-md border border-[#2A2A3A] bg-[#0A0A0F] px-4 py-3 font-mono text-xs text-[#0E9E8E] overflow-x-auto whitespace-pre">
                                GET /api/v1/evaluations/{"{"}evaluation_token{"}"}
                            </div>
                            <div className="rounded-md border border-[#2A2A3A] bg-[#0A0A0F] p-4 font-mono text-xs text-[#D4D4D4] overflow-x-auto whitespace-pre mt-3">
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
