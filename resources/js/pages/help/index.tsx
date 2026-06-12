import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { HelpSidebar  } from '@/components/help/help-sidebar';
import type {HelpChapter} from '@/components/help/help-sidebar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Help', href: '/help' }];

export default function HelpIndex({ chapters }: { chapters: HelpChapter[] }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (!term) {
            return chapters;
        }

        return chapters.filter(
            (chapter) =>
                chapter.title.toLowerCase().includes(term) ||
                chapter.description.toLowerCase().includes(term) ||
                chapter.slug.toLowerCase().includes(term),
        );
    }, [chapters, query]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Help" />

            <div className="flex flex-col gap-8 p-4 md:p-6 lg:flex-row lg:items-start">
                <aside className="lg:w-60 lg:shrink-0">
                    <div className="lg:sticky lg:top-6">
                        <HelpSidebar chapters={chapters} />
                    </div>
                </aside>

                <div className="min-w-0 flex-1 space-y-6">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Help center
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Guides for working evaluations, simulations,
                            affiliates, and clinic setup.
                        </p>
                    </div>

                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search guides..."
                        aria-label="Search help guides"
                        className="max-w-md"
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        {filtered.map((chapter) => (
                            <Link
                                key={chapter.slug}
                                href={`/help/${chapter.slug}`}
                                className="group block h-full"
                            >
                                <Card className="h-full transition-colors hover:border-primary/30">
                                    <CardHeader>
                                        <CardTitle className="text-base transition-colors group-hover:text-primary">
                                            {chapter.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {chapter.description}
                                        </CardDescription>
                                    </CardHeader>
                                    {chapter.last_verified && (
                                        <CardContent className="pt-0">
                                            <p className="text-xs text-muted-foreground">
                                                Last verified{' '}
                                                {chapter.last_verified}
                                            </p>
                                        </CardContent>
                                    )}
                                </Card>
                            </Link>
                        ))}
                    </div>

                    {filtered.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No guides match your search. Try a different
                            keyword.
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
