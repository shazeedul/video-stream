/**
 * Optimized navigation utility for checking if URL matches pattern
 * Supports wildcards (*), AND (&&), OR (||), and parentheses
 */

// Cache for compiled regex patterns to avoid recompilation
const regexCache = new Map<string, RegExp>();

/**
 * Check if current URL matches the given pattern
 * Supports query parameters matching
 */
export function isActiveUrl(currentUrl: string, pattern: string): boolean {
    if (!pattern || !currentUrl) return false;

    // Get current URL with query parameters
    const fullUrl = getCurrentFullUrl();
    const url = currentUrl.replace(/\/$/, '') || '/';

    // Handle logical operators with parentheses
    if (pattern.includes('||') || pattern.includes('&&') || pattern.includes('(')) {
        return evaluateLogicalPattern(fullUrl, pattern);
    }

    // Check if pattern has query parameters
    if (pattern.includes('?')) {
        return matchPatternWithQuery(fullUrl, pattern.trim());
    }

    // Simple pattern matching with caching (path only)
    return matchPattern(url, pattern.trim());
}

/**
 * Match a single pattern against URL with regex caching
 */
function matchPattern(url: string, pattern: string): boolean {
    const cleanPattern = pattern.replace(/\/$/, '') || '/';

    // Exact match - fastest check
    if (cleanPattern === url) return true;

    // Wildcard pattern with caching
    if (cleanPattern.includes('*')) {
        // Check cache first
        let regex = regexCache.get(cleanPattern);

        if (!regex) {
            // Compile and cache regex
            const escapedPattern = cleanPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\\\*/g, '.*');
            regex = new RegExp(`^${escapedPattern}$`);
            regexCache.set(cleanPattern, regex);
        }

        return regex.test(url);
    }

    return false;
}

/**
 * Match pattern with query parameters
 */
function matchPatternWithQuery(fullUrl: string, pattern: string): boolean {
    const cleanPattern = pattern.replace(/\/$/, '') || '/';

    // Exact match with query parameters
    if (cleanPattern === fullUrl) return true;

    // Parse URLs
    const currentUrlObj = parseUrl(fullUrl);
    const patternUrlObj = parseUrl(cleanPattern);

    // Check if paths match (with wildcard support)
    const pathMatches = matchPattern(currentUrlObj.pathname, patternUrlObj.pathname);
    if (!pathMatches) return false;

    // If pattern has no query parameters, just check path
    if (!patternUrlObj.search) return true;

    // Check if all pattern query parameters match current URL
    const currentParams = new URLSearchParams(currentUrlObj.search);
    const patternParams = new URLSearchParams(patternUrlObj.search);

    for (const [key, value] of patternParams.entries()) {
        if (currentParams.get(key) !== value) {
            return false;
        }
    }

    return true;
}

/**
 * Parse URL string into pathname and search parts
 */
function parseUrl(url: string): { pathname: string; search: string } {
    const [pathname, search = ''] = url.split('?');
    return {
        pathname: pathname.replace(/\/$/, '') || '/',
        search: search ? `?${search}` : '',
    };
}

/**
 * Evaluate logical patterns with &&, ||, and parentheses
 */
function evaluateLogicalPattern(url: string, pattern: string): boolean {
    try {
        // Replace patterns with boolean results
        let expression = pattern;

        // Find all pattern parts (not operators)
        const patterns = pattern.match(/[^&|()]+/g) || [];

        patterns.forEach((p) => {
            const trimmed = p.trim();
            if (trimmed && !['&&', '||'].includes(trimmed)) {
                // Use appropriate matching function based on whether pattern has query params
                const result = trimmed.includes('?') ? matchPatternWithQuery(url, trimmed) : matchPattern(url.split('?')[0], trimmed);
                expression = expression.replace(trimmed, result.toString());
            }
        });

        // Replace operators with JavaScript operators
        expression = expression.replace(/&&/g, ' && ').replace(/\|\|/g, ' || ');

        // Evaluate the expression safely
        return Function('"use strict"; return (' + expression + ')')();
    } catch {
        console.warn('Invalid pattern:', pattern);
        return false;
    }
}

/**
 * Get current URL path
 */
export function getCurrentUrl(): string {
    return typeof window !== 'undefined' ? window.location.pathname : '';
}

/**
 * Get current full URL with query parameters
 */
export function getCurrentFullUrl(): string {
    if (typeof window === 'undefined') return '';
    return window.location.pathname + window.location.search;
}

/**
 * Clear regex cache (useful for testing or memory management)
 */
export function clearPatternCache(): void {
    regexCache.clear();
}
