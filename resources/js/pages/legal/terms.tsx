import { Head, Link } from '@inertiajs/react';

export default function TermsOfService() {
    return (
        <div className="min-h-screen bg-background text-foreground selection:bg-[#0E9E8E] selection:text-white">
            <Head title="Patient Terms of Service" />
            
            <header className="border-b border-border bg-card">
                <div className="container mx-auto px-6 py-4 flex items-center justify-between">
                    <span className="text-xl font-bold tracking-widest text-[#0E9E8E] uppercase">SymetriHealth</span>
                    <Link href="/" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                        Back to Home
                    </Link>
                </div>
            </header>

            <main className="container mx-auto px-6 py-12 max-w-3xl">
                <div className="space-y-8 max-w-none">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight mb-2">Patient Terms of Service</h1>
                        <p className="text-muted-foreground">Last Updated: {new Date().toLocaleDateString()}</p>
                    </div>

                    <div className="space-y-6 text-sm md:text-base leading-relaxed">
                        <section>
                            <h2 className="text-xl font-semibold mb-3">1. Acceptance of Terms</h2>
                            <p>
                                By submitting an aesthetic evaluation request through the SymetriHealth platform, you agree to these Patient Terms of Service. If you do not agree with any part of these terms, you must not use our service.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-semibold mb-3">2. Description of Service</h2>
                            <p>
                                SymetriHealth provides a software platform that allows medical clinics to receive, organize, and evaluate patient intake requests. We are a software provider, <strong>not a healthcare provider</strong>. Our AI-generated "Beauty Roadmap" and all associated analyses are for informational purposes only.
                            </p>
                            <div className="mt-4 p-4 border border-red-500/20 bg-red-500/5 rounded-md">
                                <p className="font-semibold text-red-500 dark:text-red-400">Important Medical Disclaimer:</p>
                                <p className="mt-1">
                                    The analyses, scores, and simulations provided by our AI are NOT medical diagnoses, guarantees of candidacy, or treatment recommendations. All decisions regarding your health, eligibility for surgery, and treatment plans must be made directly with a qualified, licensed surgeon during a formal medical consultation.
                                </p>
                            </div>
                        </section>

                        <section>
                            <h2 className="text-xl font-semibold mb-3">3. SMS Communications</h2>
                            <p>
                                When you provide your phone number and opt-in during the intake process, you consent to receive SMS notifications (such as status updates and secure report links) on behalf of your clinic. 
                            </p>
                            <p className="mt-2">
                                <strong>Message Frequency:</strong> Varies based on your evaluation status.<br/>
                                <strong>Opt-Out:</strong> Reply <strong>STOP</strong> at any time to cancel.<br/>
                                <strong>Help:</strong> Reply <strong>HELP</strong> for support.<br/>
                                <strong>Rates:</strong> Standard message and data rates may apply depending on your mobile carrier.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-semibold mb-3">4. Your Responsibilities</h2>
                            <p>When using our platform, you agree to:</p>
                            <ul className="list-disc pl-5 mt-2 space-y-1">
                                <li>Provide accurate, current, and complete medical and personal information.</li>
                                <li>Upload photographs that only feature yourself and meet the quality guidelines provided.</li>
                                <li>Not submit malicious content, false information, or attempt to compromise the security of the platform.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="text-xl font-semibold mb-3">5. Intellectual Property</h2>
                            <p>
                                While you retain ownership of the photos and personal data you submit, you grant SymetriHealth a limited, secure license to process this data strictly for the purpose of generating your evaluation for your clinic and improving patient outcomes in accordance with HIPAA regulations.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-semibold mb-3">6. Limitation of Liability</h2>
                            <p>
                                To the fullest extent permitted by law, SymetriHealth and its affiliates shall not be liable for any medical decisions, outcomes, or actions taken by your medical provider. Our software simply facilitates the secure transmission and initial analysis of your data.
                            </p>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    );
}
