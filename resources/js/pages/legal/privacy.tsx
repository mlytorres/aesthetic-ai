import { Head, Link } from '@inertiajs/react';

export default function PrivacyPolicy() {
    return (
        <div className="min-h-screen bg-background text-foreground selection:bg-[#0E9E8E] selection:text-white">
            <Head title="Patient Privacy Policy" />

            <header className="border-b border-border bg-card">
                <div className="container mx-auto flex items-center justify-between px-6 py-4">
                    <span className="text-xl font-bold tracking-widest text-[#0E9E8E] uppercase">
                        SymetriHealth
                    </span>
                    <Link
                        href="/"
                        className="text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        Back to Home
                    </Link>
                </div>
            </header>

            <main className="container mx-auto max-w-3xl px-6 py-12">
                <div className="max-w-none space-y-8">
                    <div>
                        <h1 className="mb-2 text-3xl font-bold tracking-tight">
                            Patient Privacy Policy
                        </h1>
                        <p className="text-muted-foreground">
                            Last Updated: {new Date().toLocaleDateString()}
                        </p>
                    </div>

                    <div className="space-y-6 text-sm leading-relaxed md:text-base">
                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                1. Introduction
                            </h2>
                            <p>
                                SymetriHealth ("we", "our", or "us") provides a
                                HIPAA-compliant platform for medical clinics to
                                collect, process, and evaluate aesthetic patient
                                intakes. This Privacy Policy explains how we
                                handle your Protected Health Information (PHI)
                                and personal data{' '}
                                <strong>
                                    on behalf of your selected medical provider
                                </strong>
                                .
                            </p>
                            <p className="mt-2">
                                When you submit an evaluation through our
                                platform, your primary relationship remains with
                                your clinic. We act solely as a secure
                                technology processor for them.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                2. Information We Collect
                            </h2>
                            <p>
                                We collect information strictly necessary for
                                your clinic to evaluate you for aesthetic
                                procedures, which may include:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>
                                    <strong>Identifiers:</strong> Name, email
                                    address, phone number, and date of birth.
                                </li>
                                <li>
                                    <strong>Health Information (PHI):</strong>{' '}
                                    Medical history, physical characteristics,
                                    and procedural goals.
                                </li>
                                <li>
                                    <strong>Media:</strong> Photographs securely
                                    uploaded for AI analysis and clinical
                                    review.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                3. How We Use Your Information
                            </h2>
                            <p>
                                Your data is used exclusively to facilitate your
                                evaluation with your chosen healthcare provider
                                and to operate our platform securely. This
                                includes:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                <li>
                                    Securely transmitting your data to your
                                    clinic.
                                </li>
                                <li>
                                    Performing automated AI analysis on your
                                    photos to assist your provider.
                                </li>
                                <li>
                                    Sending you transactional notifications
                                    about your evaluation (e.g., SMS or Email).
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold text-[#0A9E7E]">
                                4. SMS &amp; Mobile Number Privacy (Twilio A2P
                                10DLC)
                            </h2>
                            <p>
                                We highly value the privacy of your mobile
                                number. By opting into SMS notifications during
                                your intake, you consent to receive
                                transactional updates regarding your evaluation
                                status and scheduling.
                            </p>
                            <div className="mt-4 rounded-r-md border-l-4 border-[#0E9E8E] bg-muted/30 p-4">
                                <p className="font-medium">
                                    We do not share, sell, or trade your mobile
                                    phone number, personal information, or SMS
                                    consent with any third parties or affiliates
                                    for marketing purposes.
                                </p>
                            </div>
                            <p className="mt-4">
                                You may opt-out of SMS communications at any
                                time by replying <strong>STOP</strong>. Standard
                                message and data rates may apply.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                5. Data Security (HIPAA)
                            </h2>
                            <p>
                                Our platform is designed with rigorous security
                                standards to comply with the Health Insurance
                                Portability and Accountability Act (HIPAA). Your
                                data is encrypted both in transit and at rest.
                                Access to your PHI is strictly limited to
                                authorized personnel at your clinic and
                                necessary system processes.
                            </p>
                        </section>

                        <section>
                            <h2 className="mb-3 text-xl font-semibold">
                                6. Contact Information
                            </h2>
                            <p>
                                If you have questions about how your medical
                                data is used, please contact your healthcare
                                provider directly. For technical questions
                                regarding the SymetriHealth platform, you may
                                contact us via our website.
                            </p>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    );
}
