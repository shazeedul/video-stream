import { search, searchInCategory } from '@/actions/App/Http/Controllers/Admin/GlobalSearchController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowRight,
    Briefcase,
    Cog,
    CreditCard,
    FileText,
    LayoutGrid,
    Loader2,
    Menu,
    Search,
    Shield,
    User,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface SearchResult {
    id: string;
    title: string;
    subtitle: string;
    url: string;
    type: string;
    category: string;
    icon: string;
}

interface CategoryInfo {
    name: string;
    icon: string;
    count: number;
}

interface SearchResponse {
    results: SearchResult[];
    categories: CategoryInfo[];
}

interface GlobalSearchProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

// Map icon names to Lucide icons
const iconMap: Record<string, LucideIcon> = {
    user: User,
    users: Users,
    menu: Menu,
    briefcase: Briefcase,
    'credit-card': CreditCard,
    file: FileText,
    'layout-grid': LayoutGrid,
    shield: Shield,
    cog: Cog,
};

export function GlobalSearch({ open, onOpenChange }: GlobalSearchProps) {
    const { __ } = useLocalizer();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [categories, setCategories] = useState<CategoryInfo[]>([]);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const resultsRef = useRef<HTMLDivElement>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    // Focus input when dialog opens
    useEffect(() => {
        if (open) {
            setTimeout(() => {
                inputRef.current?.focus();
            }, 100);
        } else {
            // Reset state when closing
            setQuery('');
            setResults([]);
            setCategories([]);
            setSelectedIndex(0);
            setSelectedCategory(null);
        }
    }, [open]);

    const performSearch = useCallback(
        async (searchQuery: string) => {
            // Cancel previous request
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }

            abortControllerRef.current = new AbortController();
            setIsLoading(true);

            try {
                const endpoint = selectedCategory ? searchInCategory({ category: selectedCategory }).url : search().url;

                const response = await axios.get<SearchResponse>(endpoint, {
                    params: { query: searchQuery, limit: 10 },
                    signal: abortControllerRef.current.signal,
                });

                setResults(response.data.results || []);
                setCategories(response.data.categories || []);
                setSelectedIndex(0);
            } catch (error) {
                if (!axios.isCancel(error)) {
                    console.error('Search error:', error);
                    setResults([]);
                    setCategories([]);
                }
            } finally {
                setIsLoading(false);
            }
        },
        [selectedCategory],
    );

    // Debounced search
    useEffect(() => {
        if (!query.trim()) {
            setResults([]);
            setCategories([]);
            return;
        }

        const timeoutId = setTimeout(() => {
            performSearch(query);
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            // Cancel any pending requests
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [query, selectedCategory, performSearch]);

    const handleResultClick = useCallback(
        (result: SearchResult) => {
            if (result.url && result.url !== '#') {
                router.visit(result.url);
                onOpenChange(false);
            }
        },
        [onOpenChange],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev < results.length - 1 ? prev + 1 : prev));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev > 0 ? prev - 1 : prev));
            } else if (e.key === 'Enter' && results[selectedIndex]) {
                e.preventDefault();
                handleResultClick(results[selectedIndex]);
            } else if (e.key === 'Escape') {
                onOpenChange(false);
            }
        },
        [results, selectedIndex, handleResultClick, onOpenChange],
    );

    // Scroll selected item into view
    useEffect(() => {
        if (resultsRef.current) {
            const selectedElement = resultsRef.current.children[selectedIndex] as HTMLElement;
            if (selectedElement) {
                selectedElement.scrollIntoView({
                    block: 'nearest',
                    behavior: 'smooth',
                });
            }
        }
    }, [selectedIndex]);

    const handleCategoryFilter = (categoryName: string) => {
        if (selectedCategory === categoryName) {
            setSelectedCategory(null);
        } else {
            setSelectedCategory(categoryName);
        }
    };

    const clearSearch = () => {
        setQuery('');
        setResults([]);
        setCategories([]);
        setSelectedCategory(null);
        inputRef.current?.focus();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl p-0">
                <DialogHeader className="sr-only">
                    <DialogTitle>{__('Global Search')}</DialogTitle>
                </DialogHeader>
                <div className="flex flex-col">
                    {/* Search Input */}
                    <div className="flex items-center border-b px-4 py-3">
                        <Search className="mr-2 h-5 w-5 shrink-0 opacity-50" />
                        <Input
                            ref={inputRef}
                            placeholder={__('Search for anything...')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                            className="border-0 shadow-none focus-visible:ring-0"
                        />
                        {isLoading && <Loader2 className="mr-2 h-4 w-4 shrink-0 animate-spin opacity-50" />}
                        {query && !isLoading && (
                            <Button variant="ghost" size="icon" onClick={clearSearch} className="h-6 w-6 shrink-0">
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    {/* Category Filters */}
                    {categories.length > 0 && (
                        <div className="flex flex-wrap gap-2 border-b px-4 py-3">
                            {categories.map((category) => (
                                <Badge
                                    key={category.name}
                                    variant={selectedCategory === category.name ? 'default' : 'outline'}
                                    className="cursor-pointer"
                                    onClick={() => handleCategoryFilter(category.name)}
                                >
                                    {category.name} ({category.count})
                                </Badge>
                            ))}
                            {selectedCategory && (
                                <Button variant="ghost" size="sm" onClick={() => setSelectedCategory(null)} className="h-6 px-2 text-xs">
                                    Clear filter
                                </Button>
                            )}
                        </div>
                    )}

                    {/* Results */}
                    <div className="max-h-[400px] overflow-y-auto p-2" ref={resultsRef}>
                        {isLoading && !results.length ? (
                            <div className="space-y-2 p-2">
                                {[1, 2, 3, 4, 5].map((i) => (
                                    <div key={i} className="flex items-center space-x-3 rounded-lg p-3">
                                        <Skeleton className="h-10 w-10 rounded" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-4 w-3/4" />
                                            <Skeleton className="h-3 w-1/2" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : results.length > 0 ? (
                            <div className="space-y-1">
                                {results.map((result, index) => (
                                    <button
                                        key={result.id}
                                        onClick={() => handleResultClick(result)}
                                        onMouseEnter={() => setSelectedIndex(index)}
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-lg p-3 text-left transition-colors',
                                            'hover:bg-accent',
                                            index === selectedIndex && 'bg-accent',
                                        )}
                                    >
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10">
                                            {(() => {
                                                const IconComponent = iconMap[result.icon] || FileText;
                                                return <IconComponent className="h-5 w-5 text-primary" />;
                                            })()}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-medium">{result.title}</p>
                                                <Badge variant="secondary" className="shrink-0 text-xs">
                                                    {result.category}
                                                </Badge>
                                            </div>
                                            <p className="truncate text-xs text-muted-foreground">{result.subtitle}</p>
                                        </div>
                                        <ArrowRight className="h-4 w-4 shrink-0 opacity-50" />
                                    </button>
                                ))}
                            </div>
                        ) : query.trim() ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Search className="mb-4 h-12 w-12 opacity-20" />
                                <p className="text-sm font-medium">{__('No results found')}</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {__('Try searching with different keywords')}
                                    {selectedCategory && __(' or clear the category filter')}
                                </p>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Search className="mb-4 h-12 w-12 opacity-20" />
                                <p className="text-sm font-medium">{__('Start typing to search')}</p>
                                <p className="mt-1 text-xs text-muted-foreground">{__('Search across users, menu items, services, and more')}</p>
                            </div>
                        )}
                    </div>

                    {/* Footer with keyboard hints */}
                    {results.length > 0 && (
                        <div className="flex items-center justify-between border-t px-4 py-2 text-xs text-muted-foreground">
                            <div className="flex items-center gap-4">
                                <span className="flex items-center gap-1">
                                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">↑</kbd>
                                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">↓</kbd>
                                    to navigate
                                </span>
                                <span className="flex items-center gap-1">
                                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">↵</kbd>
                                    to select
                                </span>
                            </div>
                            <span className="flex items-center gap-1">
                                <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">Esc</kbd>
                                to close
                            </span>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
