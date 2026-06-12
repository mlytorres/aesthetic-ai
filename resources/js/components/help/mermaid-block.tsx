import { useEffect, useId, useRef, useState } from 'react';

type MermaidBlockProps = {
    code: string;
};

export function MermaidBlock({ code }: MermaidBlockProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const id = useId().replace(/:/g, '');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        const render = async () => {
            try {
                const mermaid = (await import('mermaid')).default;
                mermaid.initialize({
                    startOnLoad: false,
                    theme: 'neutral',
                    securityLevel: 'strict',
                });

                const { svg } = await mermaid.render(
                    `help-mermaid-${id}`,
                    code.trim(),
                );

                if (!cancelled && containerRef.current) {
                    containerRef.current.innerHTML = svg;
                    setError(null);
                }
            } catch {
                if (!cancelled) {
                    setError('Could not render diagram.');
                }
            }
        };

        void render();

        return () => {
            cancelled = true;
        };
    }, [code, id]);

    if (error) {
        return (
            <pre className="overflow-x-auto rounded-lg border bg-muted p-4 text-xs text-muted-foreground">
                {code}
            </pre>
        );
    }

    return (
        <div
            ref={containerRef}
            className="my-6 overflow-x-auto rounded-lg border bg-muted/30 p-4 [&_svg]:mx-auto [&_svg]:max-h-72 [&_svg]:max-w-full"
        />
    );
}
