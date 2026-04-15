import { Head } from '@inertiajs/react';

export default function ConsultCancelled() {
    return (
        <>
            <Head title="Consultation Cancelled" />

            <div className="flex min-h-screen flex-col items-center justify-center bg-[#0A0A0F] px-4 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-8 w-8 text-red-400">
                        <path fillRule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clipRule="evenodd" />
                    </svg>
                </div>

                <h1 className="mt-6 text-2xl font-light tracking-tight text-[#F5F0E8]">
                    Consultation Cancelled
                </h1>
                <p className="mt-2 max-w-sm text-sm text-[#9B9B8E]">
                    This video consultation has been cancelled. Please contact your clinic directly to reschedule.
                </p>
            </div>
        </>
    );
}
