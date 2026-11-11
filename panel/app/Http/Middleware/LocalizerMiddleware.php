<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

use function array_keys;

final class LocalizerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        // Set the application locale
        App::setLocale($locale);

        // Store in session for backward compatibility
        Session::put('locale', $locale);

        // Get current locale config
        $availableLocales = config('localizer.available', []);
        $currentLocaleConfig = $availableLocales[$locale] ?? ['dir' => 'ltr'];

        // Share the locale with Inertia props (if Inertia is available)
        if (class_exists(Inertia::class)) {
            Inertia::share('locale', [
                'current' => $locale,
                'dir' => $currentLocaleConfig['dir'] ?? 'ltr',
                'available' => $availableLocales,
            ]);
        }

        $response = $next($request);

        // Store locale in a persistent cookie (expires in 1 year)
        Cookie::queue('locale', $locale, 525600); // 525600 minutes = 1 year

        return $response;
    }

    /**
     * Determine the appropriate locale for the request.
     */
    private function determineLocale(Request $request): string
    {

        $user = $request->user();
        // 1. If user is authenticated, use their preference
        if ($user && method_exists($user, 'getPreference')) {
            $userLocale = $user->getPreference('locale');
            if ($userLocale && $this->isValidLocale($userLocale)) {
                return $userLocale;
            }
        }

        // 2. Check URL query parameter (explicit user action to change locale)
        $queryLocale = $request->query('locale');
        if ($queryLocale && $this->isValidLocale($queryLocale)) {
            return $queryLocale;
        }

        // 3. Check request header (e.g., X-Locale: fr)
        $headerLocale = $request->header('X-Locale');
        if ($headerLocale && $this->isValidLocale($headerLocale)) {
            return $headerLocale;
        }

        // 4. Check cookie (persists across logout)
        $cookieLocale = $request->cookie('locale');
        if ($cookieLocale && $this->isValidLocale($cookieLocale)) {
            return $cookieLocale;
        }

        // 5. Check session (fallback for backward compatibility)
        $sessionLocale = Session::get('locale');
        if ($sessionLocale && $this->isValidLocale($sessionLocale)) {
            return $sessionLocale;
        }

        // 6. Check browser accept-language header
        $preferredLocale = $request->getPreferredLanguage(
            array_keys(config('localizer.available', []))
        );
        if ($preferredLocale && $this->isValidLocale($preferredLocale)) {
            return $preferredLocale;
        }

        // 7. Fall back to default locale
        return config('localizer.default', config('app.locale', 'en'));
    }

    /**
     * Check if the locale is valid.
     */
    private function isValidLocale(string $locale): bool
    {
        $availableLocales = array_keys(config('localizer.available', []));

        return in_array($locale, $availableLocales, true);
    }
}
