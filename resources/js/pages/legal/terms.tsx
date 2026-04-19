import { Head, Link } from '@inertiajs/react';

const LAST_UPDATED = 'April 18, 2026';

export default function TermsOfService() {
    return (
        <div className="min-h-screen bg-background text-foreground selection:bg-[#0E9E8E] selection:text-white">
            <Head title="Terms of Service" />

            <header className="border-b border-border bg-card">
                <div className="container mx-auto flex items-center justify-between px-6 py-4">
                    <Link href="/" className="flex items-center gap-3">
                        <div className="shrink-0 rounded-xl bg-white p-1 shadow-sm">
                            <img src="/logo.png" alt="SymetriHealth" className="h-7 w-auto" />
                        </div>
                        <span className="hidden text-lg font-bold tracking-widest text-[#0E9E8E] uppercase sm:block">
                            SymetriHealth
                        </span>
                    </Link>
                    <Link href="/" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                        ← Back to Home
                    </Link>
                </div>
            </header>

            <main className="container mx-auto max-w-3xl px-6 py-12">
                <div className="max-w-none space-y-8">
                    <div>
                        <h1 className="mb-2 text-3xl font-bold tracking-tight">
                            Terms of Service
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Last Updated: {LAST_UPDATED}
                        </p>
                        <p className="mt-3 text-sm text-muted-foreground">
                            These Terms of Service apply to all users of the SymetriHealth platform —
                            including patients submitting aesthetic evaluations and clinic staff accessing the dashboard.
                        </p>
                    </div>

                    <div className="space-y-6 text-sm leading-relaxed md:text-base">
                        <section>
                            <h2 className="mb-3 text-xl font-semibold">1. Acceptance of Terms</h2>
                            <p>
                                By accessing or using the SymetriHealth platform — whether as a patient
                                submitting an evaluation or as a clinic staff member managing your practice —
                                you agree to be bound by these Terms of Service. If you do not agree with
                                any part of these terms, you must not use our service.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">2. Description of Service</h2>
                            <p>
                                SymetriHealth provides a software platform that allows medical clinics to
                                receive, organize, and evaluate patient intake requests. We are a software
                                provider, <strong>not a healthcare provider</strong>. Our AI-generated
                                "Beauty Roadmap" and all associated analyses are for informational purposes only.
                            </p>
                            <div className="mt-4 rounded-md border border-red-500/20 bg-red-500/5 p-4">
                                <p className="font-semibold text-red-500 dark:text-red-400">
                                    Important Medical Disclaimer:
                                </p>
                                <p className="mt-1">
                                    The analyses, scores, and simulations provided by our AI are NOT medical
                                    diagnoses, guarantees of candidacy, or treatment recommendations. All
                                    decisions regarding your health, eligibility for surgery, and treatment
                                    plans must be made directly with a qualified, licensed surgeon during a
                                    formal medical consultation.
                                </p>
                            </div>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">3. SMS Communications</h2>
                            <p>
                                When you provide your phone number and opt-in during the intake process,
                                you consent to receive SMS notifications (such as status updates and secure
                                report links) on behalf of your clinic.
                            </p>
                            <p className="mt-2">
                                <strong>Message Frequency:</strong> Varies based on your evaluation status.<br />
                                <strong>Opt-Out:</strong> Reply <strong>STOP</strong> at any time to cancel.<br />
                                <strong>Help:</strong> Reply <strong>HELP</strong> for support.<br />
                                <strong>Rates:</strong> Standard message and data rates may apply depending
                                on your mobile carrier.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">4. Your Responsibilities</h2>
                            <p>When using our platform, you agree to:</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>Provide accurate, current, and complete medical and personal information.</li>
                                <li>
                                    Upload photographs that only feature yourself and meet the quality
                                    guidelines provided.
                                </li>
                                <li>
                                    Not submit malicious content, false information, or attempt to compromise
                                    the security of the platform.
                                </li>
                                <li>
                                    Keep your account credentials confidential and notify us immediately of
                                    any unauthorized access.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">5. Intellectual Property</h2>
                            <p>
                                While you retain ownership of the photos and personal data you submit, you
                                grant SymetriHealth a limited, secure license to process this data strictly
                                for the purpose of generating your evaluation for your clinic and improving
                                patient outcomes in accordance with HIPAA regulations.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">6. Limitation of Liability</h2>
                            <p>
                                To the fullest extent permitted by law, SymetriHealth and its affiliates
                                shall not be liable for any medical decisions, outcomes, or actions taken
                                by your medical provider. Our software simply facilitates the secure
                                transmission and initial analysis of your data. In no event shall our
                                aggregate liability exceed the amounts paid by you (if any) in the twelve
                                months preceding the claim.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">7. Governing Law</h2>
                            <p>
                                These Terms of Service shall be governed by and construed in accordance
                                with the laws of the State of Florida, United States, without regard to
                                its conflict of law provisions. Any disputes arising under these terms
                                shall be subject to the exclusive jurisdiction of the state and federal
                                courts located in Miami-Dade County, Florida.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">8. Changes to These Terms</h2>
                            <p>
                                We reserve the right to update these Terms of Service at any time. We
                                will notify registered clinic accounts of material changes via email.
                                Continued use of the platform after changes are posted constitutes your
                                acceptance of the revised terms.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">9. Contact Us</h2>
                            <p>
                                If you have any questions about these Terms of Service, please contact us at:
                            </p>
                            <div className="mt-3 rounded-md border border-border bg-muted/30 p-4 text-sm">
                                <p><strong>SymetriHealth</strong></p>
                                <p className="mt-1">
                                    Email:{' '}
                                    <a
                                        href="mailto:legal@symetrihealth.com"
                                        className="text-[#0E9E8E] hover:underline"
                                    >
                                        legal@symetrihealth.com
                                    </a>
                                </p>
                            </div>
                        </section>
                    </div>

                    <div className="border-t border-border pt-6 text-xs text-muted-foreground">
                        <Link href="/legal/privacy" className="text-[#0E9E8E] hover:underline">
                            Privacy Policy
                        </Link>
                        {' · '}
                        <span>© 2026 SymetriHealth. All rights reserved.</span>
                    </div>
                </div>
            </main>
        </div>
    );
}
