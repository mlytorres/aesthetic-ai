import { useMemo } from 'react';

export type HelpHeading = {
    id: string;
    title: string;
};

export function slugifyHeading(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-');
}

export function extractHeadings(content: string): HelpHeading[] {
    const headings: HelpHeading[] = [];
    let inCodeFence = false;

    for (const line of content.split('\n')) {
        if (line.trimStart().startsWith('```')) {
            inCodeFence = !inCodeFence;
            continue;
        }

        if (inCodeFence) {
            continue;
        }

        const match = /^##\s+(.+)$/.exec(line);

        if (match) {
            const title = match[1].trim();
            headings.push({ id: slugifyHeading(title), title });
        }
    }

    return headings;
}

export function HelpToc({ content }: { content: string }) {
    const headings = useMemo(() => extractHeadings(content), [content]);

    if (headings.length < 2) {
        return null;
    }

    return (
        <nav aria-label="On this page" className="space-y-1 border-t pt-4">
            <p className="px-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                On this page
            </p>
            <ul>
                {headings.map((heading) => (
                    <li key={heading.id}>
                        <a
                            href={`#${heading.id}`}
                            className="block rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            {heading.title}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
