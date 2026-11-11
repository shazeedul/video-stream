<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateUserPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class UserPreferencesController extends Controller
{
    /**
     * Display user preferences page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Initialize preferences if empty
        if (empty($user->preferences)) {
            $user->initializePreferences();
            $user->save();
        }

        return Inertia::render('admin/settings/preferences', [
            'preferences' => $user->getPreferences(),
            'defaultPreferences' => $user->getDefaultPreferences(),
            'availableLocales' => config('localizer.available'),
        ]);
    }

    /**
     * Update user preferences (full or partial).
     */
    public function update(UpdateUserPreferencesRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        if (isset($validated['preferences'])) {
            // Merge with existing preferences
            $user->setPreferences($validated['preferences']);
            $user->save();
        }

        return back()
            ->with('success', __('Preferences updated successfully.'));
    }

    /**
     * Update a single preference using dot notation.
     */
    public function set(string $key, UpdateUserPreferencesRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $value = $request->input('value');

        $user->setPreference($key, $value);
        $user->save();

        return back()
            ->with('success', 'Preference updated successfully.');
    }
}
