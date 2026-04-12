// Sidebar brand area — logo displayed in a white rounded card so the
// original white-background logo looks intentional against the dark sidebar.
export default function AppLogo() {
    return (
        <div className="flex items-center">
            <div className="rounded-xl bg-white px-3 py-1.5 shadow-sm">
                <img
                    src="/logo.png"
                    alt="SymetriHealth"
                    className="h-8 w-auto object-contain"
                />
            </div>
        </div>
    );
}
