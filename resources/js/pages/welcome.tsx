import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import { FormEvent } from 'react';

export default function Welcome() {
    const { auth } = usePage().props;

    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({
        name: '',
        clinic_name: '',
        email: '',
        website_url: '',
    });

    const submitRequest = (e: FormEvent) => {
        e.preventDefault();
        post('/access-requests', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Welcome | SymetriHealth" />
            
            <div className="min-h-screen bg-[#0A0A0F] text-[#F5F0E8] font-sans selection:bg-[#0E9E8E]/30 relative overflow-hidden">
                {/* Background aesthetic glow */}
                <div className="absolute top-0 inset-x-0 h-[500px] bg-gradient-to-b from-[#0E9E8E]/15 via-[#0E9E8E]/5 to-transparent pointer-events-none" />
                <div className="absolute -top-[500px] left-1/2 -translate-x-1/2 w-[1000px] h-[1000px] rounded-full bg-gradient-radial from-[#0E9E8E]/10 to-transparent blur-3xl pointer-events-none" />
                
                {/* Navigation */}
                <nav className="relative z-10 max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
                    <div className="flex items-center">
                        <div className="rounded-xl bg-white px-3 py-1.5 shadow-sm">
                            <img
                                src="/logo.png"
                                alt="SymetriHealth"
                                className="h-10 w-auto"
                            />
                        </div>
                    </div>
                    
                    <div className="flex gap-6 items-center">
                        {auth.user ? (
                            <Link href={dashboard()} className="text-sm font-medium hover:text-[#0E9E8E] transition-colors">
                                Go to Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link href={login()} className="text-sm font-medium text-[#9B9B8E] hover:text-[#F5F0E8] transition-colors">
                                    Clinic Login
                                </Link>
                                <a href="#request-access" className="text-sm font-semibold px-5 py-2.5 rounded-lg bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#2DD4BF] transition-colors shadow-[0_0_15px_rgba(14,158,142,0.2)]">
                                    Partner With Us
                                </a>
                            </>
                        )}
                    </div>
                </nav>

                {/* Hero */}
                <main className="relative z-10 max-w-7xl mx-auto px-6 pt-24 pb-32 text-center lg:pt-36">
                    <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-semibold tracking-wide text-[#0E9E8E] mb-8 backdrop-blur-md">
                        <span className="w-2 h-2 rounded-full bg-[#0E9E8E] animate-pulse" />
                        HIPAA-Compliant Platform
                    </div>
                    
                    <h1 className="text-5xl lg:text-7xl font-bold tracking-tight mb-8">
                        The future of <br className="hidden lg:block"/>
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#0E9E8E] via-[#2DD4BF] to-[#0E9E8E]">
                            aesthetic consultations.
                        </span>
                    </h1>
                    
                    <p className="text-[#9B9B8E] max-w-2xl mx-auto text-lg mb-12 leading-relaxed">
                        Automate your patient intake pipeline with real-time AI facial analysis, instant photo quality validation, and advanced clinical lead scoring. Built securely for modern plastic surgery clinics.
                    </p>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="#request-access" className="px-8 py-4 rounded-xl bg-gradient-to-r from-[#0E9E8E] to-[#2DD4BF] text-[#0A0A0F] font-bold hover:opacity-90 hover:scale-[1.02] transition-all shadow-[0_0_30px_rgba(14,158,142,0.3)] w-full sm:w-auto text-center">
                            Request Platform Access
                        </a>
                        <Link href={login()} className="px-8 py-4 rounded-xl bg-[#111118] border border-white/10 font-semibold hover:border-[#0E9E8E]/50 hover:bg-white/[0.04] transition-all w-full sm:w-auto text-center">
                            Clinic Login
                        </Link>
                    </div>
                </main>

                {/* Features Grid */}
                <section className="relative z-10 max-w-7xl mx-auto px-6 pb-24">
                    <div className="grid md:grid-cols-3 gap-6">
                        {[
                            {
                                title: "AI Vision Pipeline",
                                description: "Real-time AWS Rekognition validation. Instantly detects facial landmarks, calculates proportions, and rejects blurry photos before they reach your coordinators."
                            },
                            {
                                title: "Zero-Friction Intake",
                                description: "WebRTC and native smartphone camera integrations allow patients to seamlessly snap high-def clinical photos directly via their browser."
                            },
                            {
                                title: "HIPAA-Grade Security",
                                description: "Strict end-to-end encryption, multi-tenant isolation, automated audit logging, and 15-minute expiring S3 signed URLs for maximum PHI security."
                            }
                        ].map((feature, i) => (
                            <div key={i} className="p-8 rounded-2xl bg-[#111118] border border-white/5 hover:border-[#0E9E8E]/40 hover:bg-[#15151D] hover:-translate-y-1 transition-all group">
                                <div className="w-14 h-14 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center mb-6 group-hover:bg-[#0E9E8E]/10 group-hover:border-[#0E9E8E]/30 transition-all">
                                    <svg className="w-7 h-7 text-[#0E9E8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold mb-3 text-[#F5F0E8]">{feature.title}</h3>
                                <p className="text-[#9B9B8E] leading-relaxed text-sm">{feature.description}</p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Request Access Form Section */}
                <section id="request-access" className="relative z-10 max-w-3xl mx-auto px-6 pb-32">
                    <div className="p-10 rounded-3xl bg-[#111118] border border-[#0E9E8E]/20 shadow-[0_0_50px_rgba(14,158,142,0.05)] relative overflow-hidden">
                        
                        {/* Decorative background element inside card */}
                        <div className="absolute top-0 right-0 w-64 h-64 bg-[#0E9E8E]/5 rounded-full blur-3xl" />

                        {recentlySuccessful ? (
                            <div className="text-center py-16">
                                <div className="mx-auto w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mb-6">
                                    <svg className="w-8 h-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h3 className="text-2xl font-bold mb-3">Request Received!</h3>
                                <p className="text-[#9B9B8E]">Thank you for your interest. Our partnership team will review your clinic's details and reach out to you shortly to get you set up.</p>
                            </div>
                        ) : (
                            <>
                                <div className="mb-8 text-center relative z-10">
                                    <h2 className="text-3xl font-bold mb-3">Request Platform Access</h2>
                                    <p className="text-[#9B9B8E] text-sm md:text-base">SymetriHealth is currently an invite-only platform. Submit your clinic's info below to request an onboarding session.</p>
                                </div>

                                <form onSubmit={submitRequest} className="space-y-5 relative z-10">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-[#9B9B8E]">Your Name *</label>
                                            <input 
                                                type="text" 
                                                required
                                                value={data.name}
                                                onChange={e => setData('name', e.target.value)}
                                                className="w-full bg-[#1A1A24] border border-white/10 rounded-xl px-4 py-3 text-[#F5F0E8] focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E] outline-none transition"
                                                placeholder="e.g. Dr. Sarah Smith"
                                            />
                                            {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-[#9B9B8E]">Clinic Name *</label>
                                            <input 
                                                type="text" 
                                                required
                                                value={data.clinic_name}
                                                onChange={e => setData('clinic_name', e.target.value)}
                                                className="w-full bg-[#1A1A24] border border-white/10 rounded-xl px-4 py-3 text-[#F5F0E8] focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E] outline-none transition"
                                                placeholder="e.g. Beverly Hills Aesthetics"
                                            />
                                            {errors.clinic_name && <p className="text-red-400 text-xs mt-1">{errors.clinic_name}</p>}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-[#9B9B8E]">Work Email *</label>
                                        <input 
                                            type="email" 
                                            required
                                            value={data.email}
                                            onChange={e => setData('email', e.target.value)}
                                            className="w-full bg-[#1A1A24] border border-white/10 rounded-xl px-4 py-3 text-[#F5F0E8] focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E] outline-none transition"
                                            placeholder="sarah@clinic.com"
                                        />
                                        {errors.email && <p className="text-red-400 text-xs mt-1">{errors.email}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-[#9B9B8E]">Clinic Website URL</label>
                                        <input 
                                            type="url" 
                                            value={data.website_url}
                                            onChange={e => setData('website_url', e.target.value)}
                                            className="w-full bg-[#1A1A24] border border-white/10 rounded-xl px-4 py-3 text-[#F5F0E8] focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E] outline-none transition"
                                            placeholder="https://www.clinic.com"
                                        />
                                        {errors.website_url && <p className="text-red-400 text-xs mt-1">{errors.website_url}</p>}
                                    </div>

                                    <button 
                                        type="submit" 
                                        disabled={processing}
                                        className="w-full px-8 py-4 mt-4 rounded-xl bg-[#0E9E8E] text-[#0A0A0F] font-bold hover:bg-[#2DD4BF] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {processing ? 'Submitting Request...' : 'Submit Access Request'}
                                    </button>
                                </form>
                            </>
                        )}
                    </div>
                </section>
                
                {/* Footer */}
                <footer className="border-t border-white/10 border-dashed py-8">
                    <div className="max-w-7xl mx-auto px-6 text-center text-xs text-[#9B9B8E]">
                        &copy; 2026 SymetriHealth Platform. All rights reserved. Strictly confidential.
                    </div>
                </footer>
            </div>
        </>
    );
}
