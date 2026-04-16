// SymetriHealth brand icon — replaces default Laravel SVG logo.
// Used in auth layouts (login, password reset, etc.)
// Logo PNG has transparent background — works on any dark or light surface.
export default function AppLogoIcon({ className }: { className?: string }) {
    return <img src="/logo.png" alt="SymetriHealth" className={className} />;
}
