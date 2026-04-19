import { Head, Link } from '@inertiajs/react';

const LAST_UPDATED = 'April 18, 2026';

export default function PrivacyPolicy() {
    return (
        <div className="min-h-screen bg-background text-foreground selection:bg-[#0E9E8E] selection:text-white">
            <Head title="Privacy Policy" />

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
                            Privacy Policy
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Last Updated: {LAST_UPDATED}
                        </p>
                    </div>

                    <div className="space-y-6 text-sm leading-relaxed md:text-base">
                        <section>
                            <h2 className="mb-3 text-xl font-semibold">1. Introduction</h2>
                            <p>
                                SymetriHealth ("we", "our", or "us") provides a HIPAA-compliant platform
                                for medical clinics to collect, process, and evaluate aesthetic patient
                                intakes. This Privacy Policy explains how we handle your Protected Health
                                Information (PHI) and personal data{' '}
                                <strong>on behalf of your selected medical provider</strong>.
                            </p>
                            <p className="mt-2">
                                When you submit an evaluation through our platform, your primary
                                relationship remains with your clinic. We act solely as a secure
                                technology processor for them.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">2. Information We Collect</h2>
                            <p>
                                We collect information strictly necessary for your clinic to evaluate
                                you for aesthetic procedures, which may include:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>
                                    <strong>Identifiers:</strong> Name, email address, phone number,
                                    and date of birth.
                                </li>
                                <li>
                                    <strong>Health Information (PHI):</strong> Medical history, physical
                                    characteristics, and procedural goals.
                                </li>
                                <li>
                                    <strong>Media:</strong> Photographs securely uploaded for AI
                                    analysis and clinical review.
                                </li>
                                <li>
                                    <strong>Technical Data:</strong> IP address, browser type, and
                                    device information collected automatically for security and
                                    audit logging purposes.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">3. How We Use Your Information</h2>
                            <p>
                                Your data is used exclusively to facilitate your evaluation with your
                                chosen healthcare provider and to operate our platform securely. This includes:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>Securely transmitting your data to your clinic.</li>
                                <li>
                                    Performing automated AI analysis on your photos to assist your provider.
                                </li>
                                <li>
                                    Sending you transactional notifications about your evaluation
                                    (e.g., SMS or email).
                                </li>
                                <li>
                                    Maintaining immutable audit logs of all PHI access for HIPAA compliance.
                                </li>
                            </ul>
                            <p className="mt-3">
                                We do <strong>not</strong> use your data for advertising, sell it to third
                                parties, or use it to train AI models beyond the scope of your evaluation.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold text-[#0E9E8E]">
                                4. SMS &amp; Mobile Number Privacy (Twilio A2P 10DLC)
                            </h2>
                            <p>
                                We highly value the privacy of your mobile number. By opting into SMS
                                notifications during your intake, you consent to receive transactional
                                updates regarding your evaluation status and scheduling.
                            </p>
                            <div className="mt-4 rounded-r-md border-l-4 border-[#0E9E8E] bg-muted/30 p-4">
                                <p className="font-medium">
                                    We do not share, sell, or trade your mobile phone number, personal
                                    information, or SMS consent with any third parties or affiliates for
                                    marketing purposes.
                                </p>
                            </div>
                            <p className="mt-4">
                                You may opt-out of SMS communications at any time by replying{' '}
                                <strong>STOP</strong>. Standard message and data rates may apply.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">5. Data Security (HIPAA)</h2>
                            <p>
                                Our platform is designed with rigorous security standards to comply with
                                the Health Insurance Portability and Accountability Act (HIPAA). Safeguards include:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>All data encrypted in transit (TLS 1.3) and at rest (AES-256).</li>
                                <li>
                                    Patient photos are stored in private cloud storage and only accessible
                                    via signed URLs that expire within 15 minutes.
                                </li>
                                <li>
                                    Automatic session timeout after 30 minutes of inactivity.
                                </li>
                                <li>
                                    Role-based access control ensuring staff only access data relevant
                                    to their role.
                                </li>
                                <li>
                                    Immutable audit logs recording every PHI access with timestamp, user,
                                    and IP address.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">6. Data Retention</h2>
                            <p>
                                We retain your personal data and PHI for as long as your clinic maintains
                                an active account on the SymetriHealth platform, or as required by
                                applicable law and HIPAA regulations (generally a minimum of six years
                                from the date of creation or last effective date, whichever is later).
                            </p>
                            <p className="mt-2">
                                Upon a clinic's account termination, patient data is securely deleted
                                from our systems within 90 days, unless a longer retention period is
                                required by law. Patient photos stored in cloud storage are deleted
                                on the same schedule.
                            </p>
                            <p className="mt-2">
                                You may request deletion of your personal data by contacting your clinic
                                directly. Requests that conflict with HIPAA retention requirements may
                                not be fulfilled.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">7. Third-Party Service Providers</h2>
                            <p>
                                We use a limited number of trusted third-party providers to operate our
                                platform. All providers are bound by HIPAA Business Associate Agreements
                                (BAAs) where applicable:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li><strong>Amazon Web Services (AWS):</strong> Cloud infrastructure, photo storage, and AI image analysis.</li>
                                <li><strong>Twilio:</strong> SMS transactional notifications.</li>
                                <li><strong>Stripe:</strong> Payment processing for clinic subscriptions (no patient payment data is processed).</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">8. Your Rights</h2>
                            <p>
                                Depending on your location, you may have the right to access, correct,
                                or request deletion of your personal data. To exercise any of these
                                rights, please contact your clinic directly, as they are the covered
                                entity responsible for your PHI. For platform-level inquiries, contact
                                us at the address below.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">9. Changes to This Policy</h2>
                            <p>
                                We may update this Privacy Policy from time to time. We will notify
                                registered clinic accounts of material changes via email. The "Last
                                Updated" date at the top of this page reflects the most recent revision.
                                Continued use of the platform after changes are posted constitutes your
                                acceptance of the revised policy.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">10. Contact Us</h2>
                            <p>
                                For questions about how your data is handled at the platform level,
                                or to submit a data request, contact our Privacy team:
                            </p>
                            <div className="mt-3 rounded-md border border-border bg-muted/30 p-4 text-sm">
                                <p><strong>SymetriHealth — Privacy Team</strong></p>
                                <p className="mt-1">
                                    Email:{' '}
                                    <a
                                        href="mailto:privacy@symetrihealth.com"
                                        className="text-[#0E9E8E] hover:underline"
                                    >
                                        privacy@symetrihealth.com
                                    </a>
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    For questions about your medical data specifically, please contact
                                    your healthcare provider directly.
                                </p>
                            </div>
                        </section>
                    </div>

                    <div className="border-t border-border pt-6 text-xs text-muted-foreground">
                        <Link href="/legal/terms" className="text-[#0E9E8E] hover:underline">
                            Terms of Service
                        </Link>
                        {' · '}
                        <span>© 2026 SymetriHealth. All rights reserved.</span>
                    </div>
                </div>
            </main>
        </div>
    );
}
