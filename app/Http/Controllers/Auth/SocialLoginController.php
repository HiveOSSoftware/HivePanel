<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function redirect(string $provider)
    {
        $config = $this->providerConfig($provider);

        abort_unless($config?->enabled, 404);

        return Socialite::driver($provider)
            ->setConfig(new \SocialiteProviders\Manager\Config(
                $config->client_id,
                $config->client_secret,
                $config->redirect_url
            ))
            ->redirect();
    }

    public function callback(string $provider)
    {
        $config = $this->providerConfig($provider);

        abort_unless($config?->enabled, 404);

        $socialUser = Socialite::driver($provider)
            ->setConfig(new \SocialiteProviders\Manager\Config(
                $config->client_id,
                $config->client_secret,
                $config->redirect_url
            ))
            ->user();

        $email = $socialUser->getEmail();

        abort_unless($email, 422, 'No email address was returned by the provider.');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: $email,
                'password' => bcrypt(Str::random(64)),
            ]
        );

        Auth::login($user, true);

        return redirect()->route('dashboard');
    }

    private function providerConfig(string $provider): ?OAuthProvider
    {
        abort_unless(in_array($provider, ['discord', 'google', 'github'], true), 404);

        return OAuthProvider::where('provider', $provider)->first();
    }
}