import { Head, Link } from '@inertiajs/react';

interface Props {
    status: number;
}

export default function ErrorPage({ status }: Props) {
    const title =
        {
            503: '503: Service Unavailable',
            500: '500: Server Error',
            404: '404: Page Not Found',
            403: '403: Access Denied',
        }[status] || 'Error';

    const description =
        {
            503: 'Sorry, we are doing some maintenance. Please check back soon.',
            500: 'Whoops, something went wrong on our servers. Our team has been notified.',
            404: 'Sorry, the page you are looking for could not be found.',
            403: 'You do not have permission to access this page.',
        }[status] || 'An unexpected error occurred.';

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#0A0A0F] p-6 font-sans text-foreground selection:bg-[#0E9E8E]/30">
            <Head title={title} />

            {/* Background glow effects */}
            <div className="pointer-events-none absolute inset-x-0 top-0 h-[500px] bg-gradient-to-b from-[#0E9E8E]/10 via-[#0E9E8E]/5 to-transparent" />
            <div className="bg-gradient-radial pointer-events-none absolute -top-[500px] left-1/2 h-[1000px] w-[1000px] -translate-x-1/2 rounded-full from-[#0E9E8E]/10 to-transparent blur-3xl" />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-[300px] bg-gradient-to-t from-red-500/5 to-transparent" />

            {/* Content Card */}
            <div className="relative z-10 w-full max-w-lg space-y-8 text-center">
                {/* Logo or Icon */}
                <div className="mb-12 flex justify-center">
                    <div className="rounded-2xl bg-white p-2 shadow-xl ring-1 ring-white/20">
                        <img
                            src="/logo.png"
                            alt="SymetriHealth Logo"
                            className="h-10 w-auto"
                        />
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-red-500/20 bg-red-500/10 px-3 py-1 text-[10px] font-bold tracking-widest text-red-400 uppercase">
                        Secure Access Alert
                    </div>

                    <h1 className="text-4xl leading-tight font-bold tracking-tight text-white md:text-5xl">
                        {status === 403
                            ? 'Access Restricted'
                            : status === 404
                              ? 'Page Not Found'
                              : 'Something went wrong'}
                    </h1>

                    <p className="mx-auto max-w-md text-base leading-relaxed text-muted-foreground md:text-lg">
                        {description}
                    </p>
                </div>

                {/* Error status hint - subtle */}
                <div className="py-4 font-mono text-[10px] tracking-[0.2em] text-muted-foreground/30 uppercase">
                    Internal Code: {status}
                </div>

                <div className="flex flex-col items-center justify-center gap-4 pt-4 sm:flex-row">
                    <Link
                        href="/"
                        className="w-full rounded-xl bg-[#0E9E8E] px-8 py-3.5 font-bold text-[#0A0A0F] shadow-[0_0_20px_rgba(14,158,142,0.2)] transition-all hover:bg-[#2DD4BF] sm:w-auto"
                    >
                        Return Home
                    </Link>
                    <button
                        onClick={() => window.history.back()}
                        className="w-full rounded-xl border border-sidebar-border/50 bg-[#1A1A25] px-8 py-3.5 font-semibold text-foreground transition-all hover:border-[#0E9E8E]/50 hover:bg-[#20202C] sm:w-auto"
                    >
                        Go Back
                    </button>
                </div>

                <p className="mx-auto max-w-xs pt-12 text-[10px] leading-relaxed text-muted-foreground/40">
                    If you believe this is an error, please contact your clinic
                    administrator or the SymetriHealth support team.
                </p>
            </div>
        </div>
    );
}
