<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => \App\Services\CurrencyExchangeService::CURRENCIES,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Handle regular fields
        $user->fill($request->only(['name', 'email', 'timezone']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Handle settings (default_currency, time_format)
        $settings = $user->settings ?? [];

        if ($request->has('default_currency')) {
            $settings['default_currency'] = $request->input('default_currency');
        }

        if ($request->has('time_format')) {
            $settings['time_format'] = $request->input('time_format');
        }

        $user->settings = $settings;

        $user->save();

        return Redirect::route('profile.edit');
    }


    /**
     * Delete the user's account.
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

        return Redirect::to('/');
    }
}
