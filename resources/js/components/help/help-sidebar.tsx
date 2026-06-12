import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type HelpChapter = {
    slug: string;
    title: string;
    description: string;
    last_verified: string | null;
};

export function HelpSidebar({
    chapters,
    activeSlug,
}: {
    chapters: HelpChapter[];
    activeSlug?: string;
}) {
    return (
        <nav className="space-y-1" aria-label="Help chapters">
            <Link
                href="/help"
                className={cn(
                    'block rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    !activeSlug
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                )}
            >
                All guides
            </Link>

            <div className="border-t pt-2">
                {chapters.map((chapter) => (
                    <Link
                        key={chapter.slug}
                        href={`/help/${chapter.slug}`}
                        className={cn(
                            'block rounded-lg px-3 py-2 text-sm transition-colors',
                            chapter.slug === activeSlug
                                ? 'bg-primary/10 font-medium text-primary'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        {chapter.title}
                    </Link>
                ))}
            </div>
        </nav>
    );
}
