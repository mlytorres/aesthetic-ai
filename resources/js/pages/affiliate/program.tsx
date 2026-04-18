import { Head, Link } from '@inertiajs/react';
import React from 'react';
import { 
    ShieldCheck, 
    Zap, 
    Gift, 
    TrendingUp, 
    Lock, 
    ChevronRight, 
    ExternalLink, 
    Award,
    CheckCircle2
} from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function AffiliateProgram() {
    return (
        <div className="min-h-screen bg-[#0A0A0F] text-[#F5F0E8] selection:bg-[#C9A84C] selection:text-[#0A0A0F] font-sans scroll-smooth">
            <Head title="HIPAA-Compliant Affiliate Program | AestheticAI" />

            {/* Navigation */}
            <nav className="sticky top-0 z-50 border-b border-[#C9A84C]/10 bg-[#0A0A0F]/80 backdrop-blur-xl">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                    <Link href="/" className="flex items-center gap-2 group">
                        <div className="h-8 w-8 rounded bg-[#C9A84C] flex items-center justify-center transition-transform group-hover:scale-105">
                            <span className="text-[#0A0A0F] font-black text-xs">AI</span>
                        </div>
                        <span className="text-xl font-bold tracking-widest text-[#C9A84C] uppercase">
                            AestheticAI
                        </span>
                    </Link>
                    <div className="hidden md:flex items-center gap-8 text-sm font-medium">
                        <a href="#hipaa" className="hover:text-[#C9A84C] transition-colors">HIPAA Compliance</a>
                        <a href="#benefits" className="hover:text-[#C9A84C] transition-colors">Benefits</a>
                        <a href="/affiliate-program/guide" className="hover:text-[#C9A84C] transition-colors">Partner Guide</a>
                    </div>
                </div>
            </nav>

            {/* Hero Section */}
            <section className="relative overflow-hidden pt-20 pb-32">
                <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[1000px] bg-[#C9A84C]/5 rounded-full blur-[120px] pointer-events-none" />
                
                <div className="relative z-10 mx-auto max-w-7xl px-6 text-center">
                    <div className="inline-flex items-center gap-2 rounded-full border border-[#C9A84C]/20 bg-[#C9A84C]/10 px-4 py-1.5 text-xs font-bold tracking-widest text-[#C9A84C] uppercase mb-8">
                        <Award className="h-3.5 w-3.5" />
                        Invitation-Only Elite Network
                    </div>
                    
                    <h1 className="text-5xl md:text-7xl font-bold tracking-tight mb-8 leading-[1.1]">
                        The Standard for <br />
                        <span className="bg-gradient-to-r from-[#C9A84C] via-[#E6D291] to-[#C9A84C] bg-clip-text text-transparent">
                            Aesthetic Partners.
                        </span>
                    </h1>
                    
                    <p className="mx-auto max-w-2xl text-lg md:text-xl text-[#F5F0E8]/70 mb-12 leading-relaxed">
                        Join the most exclusive circle in medical aesthetics. We connect world-class influencers with the planet's premier plastic surgery clinics through a secure, medically-vetted ecosystem.
                    </p>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="#cta">
                            <Button className="h-14 px-8 rounded-full bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90 font-bold text-lg shadow-[0_0_30px_rgba(201,168,76,0.25)]">
                                Request Invitation
                                <ChevronRight className="ml-2 h-5 w-5" />
                            </Button>
                        </a>
                    </div>
                </div>
            </section>

            {/* Reputation Section (HIPAA Shield Reframed) */}
            <section id="hipaa" className="py-24 bg-[#12121A]">
                <div className="mx-auto max-w-7xl px-6">
                    <div className="grid lg:grid-cols-2 gap-16 items-center">
                        <div>
                            <div className="inline-flex items-center gap-2 text-[#C9A84C] font-bold text-sm uppercase tracking-widest mb-6">
                                <ShieldCheck className="h-4 w-4" />
                                Professional Integrity
                            </div>
                            <h2 className="text-4xl font-bold mb-6">
                                Your Reputation, <br />
                                <span className="text-[#C9A84C]">Obsessively Protected.</span>
                            </h2>
                            <p className="text-[#F5F0E8]/70 text-lg mb-8 leading-relaxed">
                                In medical aesthetics, trust is your most valuable asset. AestheticAI is the only platform built on a 100% HIPAA-compliant foundation, ensuring that while you earn, your followers' most sensitive data remains locked in a vault. No leaks, no scandals, just pure professional growth.
                            </p>
                            
                            <div className="grid sm:grid-cols-2 gap-4">
                                {[
                                    "Total Patient Anonymity",
                                    "Bank-Grade Data Encryption",
                                    "Surgeon-Approved Content",
                                    "Full Compliance Auditing"
                                ].map((item, i) => (
                                    <div key={i} className="flex items-center gap-3 bg-[#0A0A0F] p-4 rounded-2xl border border-white/5">
                                        <CheckCircle2 className="h-4 w-4 text-[#C9A84C] shrink-0" />
                                        <span className="text-sm font-semibold">{item}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        
                        <div className="relative group">
                            <div className="absolute -inset-4 bg-gradient-to-br from-[#C9A84C]/20 to-transparent blur-3xl rounded-3xl opacity-50 transition-opacity" />
                            <div className="relative aspect-square md:aspect-video rounded-3xl overflow-hidden border border-[#C9A84C]/20 shadow-2xl">
                                <img 
                                    src="/assets/marketing/exclusive-clinic.png" 
                                    alt="Luxury Medical" 
                                    className="w-full h-full object-cover grayscale brightness-50 group-hover:grayscale-0 group-hover:scale-105 transition-all duration-700"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-[#0A0A0F] via-transparent to-transparent" />
                                <div className="absolute bottom-8 left-8 right-8">
                                    <div className="text-sm font-bold tracking-widest uppercase text-[#C9A84C] mb-2">The Standard</div>
                                    <div className="text-2xl font-bold">Trusted by World-Class Surgeons</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Benefits Section */}
            <section id="benefits" className="py-32">
                <div className="mx-auto max-w-7xl px-6">
                    <div className="text-center mb-20 text-balance">
                        <h2 className="text-3xl md:text-5xl font-bold mb-6 italic tracking-tight uppercase">High-Altitude Partner Benefits</h2>
                        <p className="text-[#F5F0E8]/60 max-w-2xl mx-auto">
                            We don't just provide links. We provide a platform for clinical excellence and professional authority.
                        </p>
                    </div>
                    
                    <div className="grid md:grid-cols-3 gap-8">
                        {[
                            {
                                icon: <Zap className="h-8 w-8 text-[#C9A84C]" />,
                                title: "Surgeon-Vetted Assets",
                                desc: "Gain access to a private library of surgeon-approved clinical results, ultra-HD videos, and expert-level captions that build your authority."
                            },
                            {
                                icon: <Gift className="h-8 w-8 text-[#C9A84C]" />,
                                title: "Elite Clinic Access",
                                desc: "Connect with the top 1% of aesthetic practices. Our invitation-only model ensures you're only promoting the height of clinical safety."
                            },
                            {
                                icon: <TrendingUp className="h-8 w-8 text-[#C9A84C]" />,
                                title: "High-Yield Rewards",
                                desc: "Participate in a rewards structure designed for professional influencers. Track your achievements in a clean, high-performance vault."
                            }
                        ].map((feat, i) => (
                            <div key={i} className="group p-10 rounded-[2.5rem] border border-[#C9A84C]/10 bg-[#12121A] hover:bg-[#12121A]/50 transition-all">
                                <div className="mb-8">{feat.icon}</div>
                                <h3 className="text-xl font-bold mb-4 uppercase tracking-wider">{feat.title}</h3>
                                <p className="text-[#F5F0E8]/60 text-sm leading-relaxed">{feat.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section id="cta" className="py-24 px-6">
                <div className="mx-auto max-w-5xl rounded-[3rem] border border-[#C9A84C]/20 bg-[#12121A] p-12 text-center overflow-hidden relative shadow-2xl">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-[#C9A84C]/10 blur-[100px] pointer-events-none" />
                    <div className="relative z-10">
                        <h2 className="text-3xl md:text-5xl font-black mb-6 uppercase tracking-tight italic">By Invitation Only</h2>
                        <p className="text-[#F5F0E8]/70 text-lg md:text-xl font-medium mb-12 max-w-2xl mx-auto">
                            AestheticAI is a curated ecosystem. Membership in the Partner Program is only available via invitation from a participating clinic.
                        </p>
                        <div className="flex flex-wrap justify-center gap-4">
                            <a href="mailto:partners@aesthetic-ai.test?subject=Inquiry: Invitation-Only Partner Network">
                                <Button className="h-16 px-10 rounded-full bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90 font-bold text-lg">
                                    Inquire with your Clinic
                                </Button>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="py-12 border-t border-[#C9A84C]/10 bg-[#0A0A0F]">
                <div className="mx-auto max-w-7xl px-6 flex flex-col md:flex-row justify-between items-center gap-8">
                    <div className="flex items-center gap-2">
                        <div className="h-6 w-6 rounded bg-[#C9A84C] flex items-center justify-center">
                            <span className="text-[#0A0A0F] font-black text-[10px]">AI</span>
                        </div>
                        <span className="text-lg font-bold tracking-widest text-[#C9A84C] uppercase">
                            AestheticAI
                        </span>
                    </div>
                    
                    <div className="flex gap-8 text-[10px] text-[#F5F0E8]/40 uppercase tracking-[0.2em] font-bold">
                        <Link href="/legal/terms" className="hover:text-[#C9A84C]">Legal Terms</Link>
                        <Link href="/legal/privacy" className="hover:text-[#C9A84C]">Privacy Shield</Link>
                        <Link href="/affiliate-program/guide" className="hover:text-[#C9A84C]">Documentation</Link>
                    </div>
                    
                    <div className="text-[10px] text-[#F5F0E8]/30 font-medium uppercase tracking-widest">
                        &copy; 2026. HIPAA Protected Network.
                    </div>
                </div>
            </footer>
        </div>
    );
}

function Badge({ children, className }: { children: React.ReactNode, className?: string }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${className}`}>
            {children}
        </span>
    );
}
