import { Head, Link } from '@inertiajs/react';

interface Props {
    status: number;
}

export default function ErrorPage({ status }: Props) {
    const title = {
        503: '503: Service Unavailable',
        500: '500: Server Error',
        404: '404: Page Not Found',
        403: '403: Access Denied',
    }[status] || 'Error';

    const description = {
        503: 'Sorry, we are doing some maintenance. Please check back soon.',
        500: 'Whoops, something went wrong on our servers. Our team has been notified.',
        404: 'Sorry, the page you are looking for could not be found.',
        403: 'You do not have permission to access this page.',
    }[status] || 'An unexpected error occurred.';

    return (
        <div className="min-h-screen bg-[#0A0A0F] text-foreground font-sans selection:bg-[#0E9E8E]/30 relative overflow-hidden flex items-center justify-center p-6">
            <Head title={title} />

            {/* Background glow effects */}
            <div className="absolute top-0 inset-x-0 h-[500px] bg-gradient-to-b from-[#0E9E8E]/10 via-[#0E9E8E]/5 to-transparent pointer-events-none" />
            <div className="absolute -top-[500px] left-1/2 -translate-x-1/2 w-[1000px] h-[1000px] rounded-full bg-gradient-radial from-[#0E9E8E]/10 to-transparent blur-3xl pointer-events-none" />
            <div className="absolute bottom-0 inset-x-0 h-[300px] bg-gradient-to-t from-red-500/5 to-transparent pointer-events-none" />

            {/* Content Card */}
            <div className="relative z-10 max-w-lg w-full text-center space-y-8">
                {/* Logo or Icon */}
                <div className="flex justify-center mb-12">
                     <div className="rounded-2xl bg-white p-2 shadow-xl ring-1 ring-white/20">
                        <img src="/logo.png" alt="SymetriHealth Logo" className="h-10 w-auto" />
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] font-bold tracking-widest uppercase mb-4">
                        Secure Access Alert
                    </div>
                    
                    <h1 className="text-4xl md:text-5xl font-bold tracking-tight text-white leading-tight">
                        {status === 403 ? 'Access Restricted' : status === 404 ? 'Page Not Found' : 'Something went wrong'}
                    </h1>
                    
                    <p className="text-muted-foreground text-base md:text-lg leading-relaxed max-w-md mx-auto">
                        {description}
                    </p>
                </div>

                {/* Error status hint - subtle */}
                <div className="text-[10px] font-mono text-muted-foreground/30 py-4 uppercase tracking-[0.2em]">
                    Internal Code: {status}
                </div>

                <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                    <Link 
                        href="/" 
                        className="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-[#0E9E8E] text-[#0A0A0F] font-bold hover:bg-[#2DD4BF] transition-all shadow-[0_0_20px_rgba(14,158,142,0.2)]"
                    >
                        Return Home
                    </Link>
                    <button 
                        onClick={() => window.history.back()}
                        className="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-[#1A1A25] border border-sidebar-border/50 text-foreground font-semibold hover:border-[#0E9E8E]/50 hover:bg-[#20202C] transition-all"
                    >
                        Go Back
                    </button>
                </div>

                <p className="pt-12 text-[10px] text-muted-foreground/40 leading-relaxed max-w-xs mx-auto">
                    If you believe this is an error, please contact your clinic administrator or the SymetriHealth support team.
                </p>
            </div>
        </div>
    );
}
