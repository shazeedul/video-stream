<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    /**
     * Show the user's profile overview page.
     */
    public function overview(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('portal/settings/overview', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'company' => $user->company,
                'designation' => $user->designation,
                'address' => $user->address,
                'city' => $user->city,
                'state' => $user->state,
                'zip_code' => $user->zip_code,
                'social_links' => $user->social_links ?? [],
                'profile_photo_path' => $user->profile_photo_path,
                'profile_photo_url' => $user->profile_photo_url,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at->format('F j, Y'),
                'is_social_only' => $user->isSocialOnly(),
                'social_accounts_count' => $user->socialAccounts->count(),
            ],
        ]);
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('portal/settings/edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'company' => $user->company,
                'designation' => $user->designation,
                'address' => $user->address,
                'city' => $user->city,
                'state' => $user->state,
                'zip_code' => $user->zip_code,
                'social_links' => $user->social_links ?? [],
                'profile_photo_path' => $user->profile_photo_path,
                'profile_photo_url' => $user->profile_photo_url,
                'is_active' => $user->is_active,
                'is_social_only' => $user->isSocialOnly(),
                'social_accounts' => $user->socialAccounts->map(fn ($socialAccount): array => [
                    'id' => $socialAccount->id,
                    'provider' => $socialAccount->provider,
                    'name' => $socialAccount->name,
                    'email' => $socialAccount->email,
                    'avatar' => $socialAccount->avatar,
                    'username' => $socialAccount->username,
                ]),
            ],
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Handle cropped profile photo from form
        if ($request->filled('cropped_image')) {
            // Decode base64 image
            $imageData = $request->cropped_image;
            $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
            $imageData = str_replace('data:image/png;base64,', '', $imageData);
            $imageData = str_replace(' ', '+', $imageData);

            $decodedImage = base64_decode($imageData);

            // Generate unique filename
            $filename = 'profile-photos/'.uniqid().'_'.time().'.jpg';

            // Store the cropped image
            Storage::put($filename, $decodedImage);

            // Delete old profile photo if exists
            if ($user->profile_photo_path) {
                Storage::delete($user->profile_photo_path);
            }

            // Update user profile photo path
            $user->profile_photo_path = $filename;
            unset($data['cropped_image']);  // Remove from data array
        }

        // Clean up empty social links
        if (isset($data['social_links'])) {
            $data['social_links'] = array_filter($data['social_links'], fn ($value): bool => ! empty($value));

            if (empty($data['social_links'])) {
                $data['social_links'] = null;
            }
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Profile updated successfully.');
    }

    /**
     * Show the account deletion page.
     */
    public function delete(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('portal/settings/delete', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'scheduled_deletion_at' => $user->scheduled_deletion_at,
                'days_until_deletion' => $user->scheduled_deletion_at
                    ? now()->diffInDays($user->scheduled_deletion_at)
                    : null,
            ],
        ]);
    }

    /**
     * Schedule account for deletion.
     */
    public function scheduleForDeletion(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->scheduled_deletion_at = now()->addDays(30);
        $user->save();

        return back()->with('status', 'Your account has been scheduled for deletion in 30 days.');
    }

    /**
     * Cancel scheduled account deletion.
     */
    public function cancelDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->scheduled_deletion_at = null;
        $user->save();

        return back()->with('status', 'Account deletion has been cancelled.');
    }

    /**
     * Delete the user's account immediately (for emergency use).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
