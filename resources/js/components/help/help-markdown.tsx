import { Link } from '@inertiajs/react';
import {
    Children,
    isValidElement
    
    
} from 'react';
import type {ComponentPropsWithoutRef, ReactNode} from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { slugifyHeading } from '@/components/help/help-toc';
import { MermaidBlock } from '@/components/help/mermaid-block';

function textFromChildren(children: ReactNode): string {
    return Children.toArray(children)
        .map((child) => {
            if (typeof child === 'string' || typeof child === 'number') {
                return String(child);
            }

            if (isValidElement<{ children?: ReactNode }>(child)) {
                return textFromChildren(child.props.children);
            }

            return '';
        })
        .join('');
}

function MarkdownLink({ href, children }: ComponentPropsWithoutRef<'a'>) {
    if (!href) {
        return <span>{children}</span>;
    }

    if (href.startsWith('/help')) {
        return (
            <Link
                href={href}
                className="font-medium text-primary hover:underline"
            >
                {children}
            </Link>
        );
    }

    if (href.startsWith('http')) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                className="font-medium text-primary hover:underline"
            >
                {children}
            </a>
        );
    }

    return (
        <a href={href} className="font-medium text-primary hover:underline">
            {children}
        </a>
    );
}

export function HelpMarkdown({ content }: { content: string }) {
    return (
        <div className="prose prose-sm max-w-none dark:prose-invert prose-headings:tracking-tight prose-a:no-underline prose-table:text-sm">
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    a: MarkdownLink,
                    h2: ({ children }) => (
                        <h2
                            id={slugifyHeading(textFromChildren(children))}
                            className="scroll-mt-24"
                        >
                            {children}
                        </h2>
                    ),
                    h3: ({ children }) => (
                        <h3
                            id={slugifyHeading(textFromChildren(children))}
                            className="scroll-mt-24"
                        >
                            {children}
                        </h3>
                    ),
                    code: ({ className, children }) => {
                        const isBlock = className?.includes('language-');

                        if (isBlock) {
                            return (
                                <code className={`text-xs ${className}`}>
                                    {children}
                                </code>
                            );
                        }

                        return (
                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => {
                        const child = Children.toArray(children)[0];

                        if (
                            isValidElement<{
                                className?: string;
                                children?: ReactNode;
                            }>(child) &&
                            typeof child.props.className === 'string' &&
                            child.props.className.includes('language-mermaid')
                        ) {
                            return (
                                <MermaidBlock
                                    code={String(child.props.children)}
                                />
                            );
                        }

                        return (
                            <pre className="overflow-x-auto rounded-lg border bg-muted p-4 text-xs">
                                {children}
                            </pre>
                        );
                    },
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
