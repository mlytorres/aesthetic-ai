import type {FC, ChangeEvent} from 'react';
import type {WizardState, WizardAction, ContactFormData} from '@/types/intake';

interface Props {
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    onNext: () => void;
    onBack: () => void;
}

const ContactInfo: FC<Props> = ({ state, dispatch, onNext, onBack }) => {
    const { contact } = state;

    const set = (field: keyof ContactFormData) =>
        (e: ChangeEvent<HTMLInputElement>): void => {
            dispatch({ type: 'SET_CONTACT', field, value: e.target.value });
        };

    const isValid =
        contact.name.trim().length > 0 &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email);

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[#F5F0E8]">Your contact details</h2>
            <p className="mt-2 text-sm text-[#9B9B8E]">
                We will use these details to send you your evaluation report and to schedule
                a consultation. Your information is never sold or shared.
            </p>

            <div className="mt-8 space-y-4">
                {/* Name */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold text-[#9B9B8E] uppercase tracking-widest">
                        Full Name <span className="text-[#0E9E8E]">*</span>
                    </label>
                    <input
                        type="text"
                        autoComplete="name"
                        placeholder="Jane Smith"
                        value={contact.name}
                        onChange={set('name')}
                        className="w-full rounded-xl border border-white/10 bg-[#111118] px-4 py-3 text-sm text-[#F5F0E8] placeholder-white/25 focus:border-[#0E9E8E]/60 focus:outline-none focus:ring-1 focus:ring-[#0E9E8E]/30 transition-colors"
                    />
                </div>

                {/* Email */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold text-[#9B9B8E] uppercase tracking-widest">
                        Email Address <span className="text-[#0E9E8E]">*</span>
                    </label>
                    <input
                        type="email"
                        autoComplete="email"
                        placeholder="jane@example.com"
                        value={contact.email}
                        onChange={set('email')}
                        className="w-full rounded-xl border border-white/10 bg-[#111118] px-4 py-3 text-sm text-[#F5F0E8] placeholder-white/25 focus:border-[#0E9E8E]/60 focus:outline-none focus:ring-1 focus:ring-[#0E9E8E]/30 transition-colors"
                    />
                </div>

                {/* Phone */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold text-[#9B9B8E] uppercase tracking-widest">
                        Phone Number{' '}
                        <span className="ml-1 text-white/30 normal-case font-normal tracking-normal">
                            (optional)
                        </span>
                    </label>
                    <input
                        type="tel"
                        autoComplete="tel"
                        placeholder="+1 (305) 555-0100"
                        value={contact.phone}
                        onChange={set('phone')}
                        className="w-full rounded-xl border border-white/10 bg-[#111118] px-4 py-3 text-sm text-[#F5F0E8] placeholder-white/25 focus:border-[#0E9E8E]/60 focus:outline-none focus:ring-1 focus:ring-[#0E9E8E]/30 transition-colors"
                    />
                </div>
            </div>

            {/* Privacy note */}
            <div className="mt-5 flex items-start gap-3 rounded-xl border border-white/5 bg-white/[0.02] px-4 py-3">
                <svg className="mt-0.5 h-4 w-4 shrink-0 text-[#0E9E8E]" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinejoin="round"
                    />
                </svg>
                <p className="text-xs text-[#9B9B8E] leading-relaxed">
                    Your data is encrypted and stored in compliance with HIPAA. We will never
                    share your information without your explicit consent.
                </p>
            </div>

            {state.error && (
                <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                    {state.error}
                </p>
            )}

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex-1 rounded-xl border border-white/10 bg-transparent px-6 py-3.5 text-sm font-medium text-[#9B9B8E] hover:border-white/20 hover:text-[#F5F0E8] transition-colors"
                >
                    ← Back
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    disabled={!isValid}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        isValid
                            ? 'bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    Review & Submit →
                </button>
            </div>
        </div>
    );
};

export default ContactInfo;
