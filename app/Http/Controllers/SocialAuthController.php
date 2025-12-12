<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to the provider's authentication page.
     */
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider's callback.
     */
    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['email' => 'Der opstod en fejl ved login med ' . ucfirst($provider) . '. Prøv venligst igen.']);
        }

        // Check if user exists by provider ID
        $user = User::where($provider . '_id', $socialUser->id)->first();

        // If not, check if user exists by email
        if (! $user) {
            $user = User::where('email', $socialUser->email)->first();

            if ($user) {
                // If user exists by email, update provider ID and avatar
                $user->update([
                    $provider . '_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                ]);
            } else {
                // If user does not exist, create new user
                $user = User::create([
                    'name' => $socialUser->name,
                    'email' => $socialUser->email,
                    'email_verified_at' => now(),
                    'password' => bcrypt(Str::random(16)), // Random secure password
                    $provider . '_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                ]);
            }
        }

        Auth::login($user, true);

        return redirect()->intended('/');
    }
}
