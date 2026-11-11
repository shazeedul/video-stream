import { Links, PaginationProps, SimplePaginationProps } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import React from 'react';

interface PaginationComponentProps {
    data: PaginationProps | SimplePaginationProps;
    showEntriesInfo?: boolean;
    doNotRenderIfLessThanThreeLinks?: boolean;
    type?: 'regular' | 'simple' | 'auto';
}

// Type guard to check if it's regular pagination
function isRegularPagination(data: PaginationProps | SimplePaginationProps): data is PaginationProps {
    return 'links' in data && 'total' in data && 'last_page' in data;
}

// Type guard to check if it's simple pagination
function isSimplePagination(data: PaginationProps | SimplePaginationProps): data is SimplePaginationProps {
    return 'next_page_url' in data && 'prev_page_url' in data && !('links' in data) && !('total' in data) && !('last_page' in data);
}

const Pagination: React.FC<PaginationComponentProps> = ({ data, showEntriesInfo = true, doNotRenderIfLessThanThreeLinks = false, type = 'auto' }) => {
    const { __ } = useLocalizer();
    // Auto-detect pagination type if not specified
    const paginationType = type === 'auto' ? (isRegularPagination(data) ? 'regular' : 'simple') : type;

    const { from, to } = data;

    // For simple pagination, we don't have total or links, so check prev/next URLs
    if (doNotRenderIfLessThanThreeLinks) {
        if (paginationType === 'simple' && isSimplePagination(data)) {
            if (!data.prev_page_url && !data.next_page_url) return null;
        } else if (paginationType === 'regular' && isRegularPagination(data)) {
            if (data.links.length <= 3) return null;
        }
    }

    // Render simple pagination
    if (paginationType === 'simple' && isSimplePagination(data)) {
        return (
            <div className="flex flex-wrap items-center justify-center gap-4 lg:justify-between xl:justify-between 2xl:justify-between">
                {showEntriesInfo && (
                    <div className="mb-2 text-sm text-muted-foreground sm:mb-0">
                        {__('Showing :from to :to entries', { from: from ?? 0, to: to ?? 0 })}
                    </div>
                )}

                <div className={`flex gap-1 ${!showEntriesInfo ? 'w-full justify-center' : ''}`}>
                    {/* Previous Button */}
                    {data.prev_page_url ? (
                        <Link
                            href={data.prev_page_url}
                            className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium whitespace-nowrap text-foreground transition-all duration-200 hover:border-primary/30 hover:bg-primary/10 hover:text-primary hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            preserveScroll
                        >
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            {__('Previous')}
                        </Link>
                    ) : (
                        <span className="inline-flex h-9 cursor-not-allowed items-center justify-center rounded-md bg-muted px-3 text-sm font-medium whitespace-nowrap text-muted-foreground opacity-50">
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            {__('Previous')}
                        </span>
                    )}

                    {/* Next Button */}
                    {data.next_page_url ? (
                        <Link
                            href={data.next_page_url}
                            className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium whitespace-nowrap text-foreground transition-all duration-200 hover:border-primary/30 hover:bg-primary/10 hover:text-primary hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            preserveScroll
                        >
                            {__('Next')}
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </Link>
                    ) : (
                        <span className="inline-flex h-9 cursor-not-allowed items-center justify-center rounded-md bg-muted px-3 text-sm font-medium whitespace-nowrap text-muted-foreground opacity-50">
                            {__('Next')}
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </span>
                    )}
                </div>
            </div>
        );
    }

    // Render regular pagination
    if (paginationType === 'regular' && isRegularPagination(data)) {
        const { links, total } = data;

        return (
            <div className="flex flex-wrap items-center justify-center gap-4 lg:justify-between xl:justify-between 2xl:justify-between">
                {showEntriesInfo && (
                    <div className="mb-2 text-sm text-muted-foreground sm:mb-0">
                        {__('Showing :from to :to of :total entries', { from: from ?? 0, to: to ?? 0, total: total ?? 0 })}
                    </div>
                )}

                <div className={`flex flex-wrap gap-1 ${!showEntriesInfo ? 'w-full justify-center' : ''}`}>
                    {links.map((link: Links, index: number) => (
                        <div key={index}>
                            {link.url === null ? (
                                <span
                                    className="inline-flex h-9 cursor-not-allowed items-center justify-center rounded-md bg-muted px-3 text-sm font-medium whitespace-nowrap text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <Link
                                    href={link.url}
                                    className={`inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium whitespace-nowrap transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:shadow-md'
                                            : 'border border-input bg-background text-foreground hover:border-primary/30 hover:bg-primary/10 hover:text-primary hover:shadow-sm'
                                    }`}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return null;
};

export default Pagination;
