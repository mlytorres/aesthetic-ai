export default function AppLogo() {
    return (
        <>
            {/* Gold "S" monogram */}
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-[#C9A84C] text-[#0A0A0F]">
                <span className="text-sm font-bold tracking-tight">S</span>
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold text-[#F5F0E8] tracking-wide">
                    SymetriHealth
                </span>
                <span className="truncate text-[10px] text-[#9B9B8E] leading-none tracking-wider uppercase">
                    Platform
                </span>
            </div>
        </>
    );
}
