import { Head, Link } from '@inertiajs/react';
import { HelpMarkdown } from '@/components/help/help-markdown';
import { HelpSidebar  } from '@/components/help/help-sidebar';
import type {HelpChapter} from '@/components/help/help-sidebar';
import { HelpToc } from '@/components/help/help-toc';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type HelpArticle = {
    slug: string;
    title: string;
    description: string;
    content: string;
    last_verified: string | null;
};

export default function HelpShow({
    article,
    chapters,
}: {
    article: HelpArticle;
    chapters: HelpChapter[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Help', href: '/help' },
        { title: article.title, href: `/help/${article.slug}` },
    ];

    const currentIndex = chapters.findIndex((c) => c.slug === article.slug);
    const previous = currentIndex > 0 ? chapters[currentIndex - 1] : null;
    const next =
        currentIndex >= 0 && currentIndex < chapters.length - 1
            ? chapters[currentIndex + 1]
            : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Help — ${article.title}`} />

            <div className="flex flex-col gap-8 p-4 md:p-6 lg:flex-row lg:items-start">
                <aside className="lg:w-60 lg:shrink-0">
                    <div className="space-y-4 lg:sticky lg:top-6">
                        <HelpSidebar
                            chapters={chapters}
                            activeSlug={article.slug}
                        />
                        <HelpToc content={article.content} />
                    </div>
                </aside>

                <article className="min-w-0 flex-1 lg:max-w-3xl">
                    <header className="border-b pb-6">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {article.title}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {article.description}
                        </p>
                        {article.last_verified && (
                            <Badge
                                variant="outline"
                                className="mt-3 text-xs font-normal"
                            >
                                Last verified {article.last_verified}
                            </Badge>
                        )}
                    </header>

                    <div className="pt-8">
                        <HelpMarkdown content={article.content} />
                    </div>

                    {(previous || next) && (
                        <footer className="mt-12 flex flex-wrap gap-3 border-t pt-6">
                            {previous && (
                                <Button variant="outline" asChild>
                                    <Link href={`/help/${previous.slug}`}>
                                        ← {previous.title}
                                    </Link>
                                </Button>
                            )}
                            {next && (
                                <Button
                                    variant="outline"
                                    className="ml-auto"
                                    asChild
                                >
                                    <Link href={`/help/${next.slug}`}>
                                        {next.title} →
                                    </Link>
                                </Button>
                            )}
                        </footer>
                    )}
                </article>
            </div>
        </AppLayout>
    );
}
