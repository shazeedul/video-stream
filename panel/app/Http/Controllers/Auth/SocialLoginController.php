<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UnlinkSocialAccountRequest;
use App\Models\SocialAccount;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

final class SocialLoginController extends Controller
{
    /**
     * Supported social providers.
     */
    private const array SUPPORTED_PROVIDERS = ['google', 'github', 'facebook', 'twitter', 'linkedin'];

    /**
     * Redirect the user to the social provider authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle social provider callback.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            return to_route('login')->withErrors([
                'social' => 'Invalid state parameter. Please try again.',
            ]);
        } catch (Exception) {
            return to_route('login')->withErrors([
                'social' => 'Authentication failed. Please try again.',
            ]);
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        Auth::login($user, true);

        return redirect()->intended(homeRoute());
    }

    /**
     * Unlink social provider from user account.
     */
    public function unlink(string $provider, UnlinkSocialAccountRequest $request): RedirectResponse
    {
        $this->validateProvider($provider);

        $user = Auth::user();
        $socialAccount = $user->getProvider($provider);

        if (! $socialAccount) {
            return back()->withErrors([
                'social' => 'No linked account found for this provider.',
            ]);
        }

        // If user is social only and this is their last account, set password first
        if ($user->isSocialOnly() && $user->socialAccounts()->count() === 1) {
            $validated = $request->validated();
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        // Delete the social account record
        $socialAccount->delete();

        return back()->with('status', 'Social account unlinked successfully.');
    }

    /**
     * Find existing user or create new one.
     */
    private function findOrCreateUser(\Laravel\Socialite\Contracts\User $socialUser, string $provider): User
    {
        // First, try to find user by provider ID
        $socialAccount = SocialAccount::query()->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->with('user')
            ->first();

        if ($socialAccount) {
            // Update auth provider info in case it changed
            $socialAccount->update([
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'metadata' => [
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'username' => $socialUser->getNickname(),
                ],
            ]);

            // Update user info if needed
            $user = $socialAccount->user;
            $user->update([
                'name' => $socialUser->getName() ?? $user->name,
            ]);
            $user->assignRole('user');

            return $user;
        }

        // Try to find user by email
        $existingUserByEmail = User::query()->where('email', $socialUser->getEmail())->first();

        if ($existingUserByEmail) {
            // Link social account to existing user
            $existingUserByEmail->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'metadata' => [
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'username' => $socialUser->getNickname(),
                ],
            ]);

            // If user has a password but now has social accounts,
            // remove password to indicate preference for social login
            if ($existingUserByEmail->password && $existingUserByEmail->socialAccounts()->count() > 0) {
                $existingUserByEmail->update(['password' => null]);
            }

            return $existingUserByEmail;
        }

        // Create new user
        $user = User::query()->create([
            'name' => $socialUser->getName() ?? 'Unknown User',
            'email' => $socialUser->getEmail(),
            'password' => null, // No password for social-only users
        ]);

        // Mark email as verified for newly created social users
        // Use the framework method to set the verified flag and dispatch any events
        $user->markEmailAsVerified();

        // Assign default role
        $user->assignRole('user');

        // Create social account record
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            'metadata' => [
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'username' => $socialUser->getNickname(),
            ],
        ]);

        return $user;
    }

    /**
     * Validate that the provider is supported.
     */
    private function validateProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 404, 'Unsupported social provider.');
    }
}
