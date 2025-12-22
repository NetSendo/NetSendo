<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA settings page
     */
    public function index()
    {
        $user = Auth::user();
        
        return Inertia::render('Profile/TwoFactor', [
            'twoFactorEnabled' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Enable 2FA - generate secret and show QR code
     */
    public function enable()
    {
        $user = Auth::user();
        
        // Generate secret if not exists
        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->two_factor_secret = encrypt($secret);
            $user->save();
        } else {
            $secret = decrypt($user->two_factor_secret);
        }

        // Generate QR code
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'NetSendo'),
            $user->email,
            $secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return Inertia::render('Profile/TwoFactorSetup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $secret,
        ]);
    }

    /**
     * Confirm 2FA setup with verification code
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $secret = decrypt($user->two_factor_secret);

        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Nieprawidłowy kod weryfikacyjny.']);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        session(['2fa_verified' => true]);

        return redirect()->route('profile.2fa.index')
            ->with('success', 'Uwierzytelnianie dwuskładnikowe zostało włączone!');
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('profile.2fa.index')
            ->with('success', 'Uwierzytelnianie dwuskładnikowe zostało wyłączone.');
    }

    /**
     * Verify 2FA code during login
     */
    public function verify()
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify the 2FA code
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        
        if (!$user || !$user->two_factor_secret) {
            return redirect()->route('login');
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Nieprawidłowy kod weryfikacyjny.']);
        }

        // Mark 2FA as verified for this session
        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard'));
    }
}
