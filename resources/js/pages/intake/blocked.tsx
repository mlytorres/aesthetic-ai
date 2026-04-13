import { Head } from '@inertiajs/react';
import type { FC } from 'react';

interface Props {
	clinic: { name: string };
	message: string;
}

const BlockedPage: FC<Props> = ({ clinic, message }) => {
	return (
		<>
			<Head title={`Unavailable — ${clinic.name}`} />

			<div className="flex min-h-screen flex-col items-center justify-center bg-[#0A0A0F] px-6 py-12 text-center">
				<div className="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-amber-500/10 ring-1 ring-amber-500/30">
					<span className="text-3xl">⏸</span>
				</div>

				<h1 className="mb-3 text-2xl font-bold text-[#F5F0E8]">
					Evaluations Paused
				</h1>

				<p className="mb-2 max-w-sm text-sm text-[#9B9B8E]">{message}</p>

				<p className="text-xs text-[#9B9B8E]/60">
					Please contact <strong className="text-[#9B9B8E]">{clinic.name}</strong> directly
					for assistance booking a consultation.
				</p>
			</div>
		</>
	);
};

export default BlockedPage;
