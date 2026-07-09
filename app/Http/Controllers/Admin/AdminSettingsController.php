<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\AppSetting;
use App\Support\AppSettings;
use App\Models\OAuthProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'general' => $this->setting('general', [
                    'company_name' => 'HivePanel',
                    'company_logo' => null,
                    'require_2fa' => 'not_required',
                    'default_language' => 'en',
                ]),

                'security' => $this->setting('security', [
                    'allow_registration' => false,
                    'require_email_verification' => false,
                    'session_lifetime' => 120,
                    'password_min_length' => 8,
                ]),

                'mail' => $this->safeMailSettings(),

                'captcha' => $this->safeCaptchaSettings(),
            ],

            'oauthProviders' => $this->oauthProviders(),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'company_logo' => ['nullable', 'string', 'max:2048'],
            'require_2fa' => ['required', 'in:not_required,admin_only,all_users'],
            'default_language' => ['required', 'string', 'max:10'],
        ]);

        $this->setSetting('general', $data);

        return back()->with('success', 'General settings updated.');
    }

    public function updateSecurity(Request $request)
    {
        $data = $request->validate([
            'allow_registration' => ['boolean'],
            'require_email_verification' => ['boolean'],
            'session_lifetime' => ['required', 'integer', 'min:5', 'max:10080'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:128'],
        ]);

        $this->setSetting('security', $data);

        return back()->with('success', 'Security settings updated.');
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:none,tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $this->setting('mail', []);

        if (! filled($data['password'] ?? null)) {
            $data['password'] = $existing['password'] ?? '';
        }

        $this->setSetting('mail', $data);

        return back()->with('success', 'Mail settings updated.');
    }

    public function testMail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $mail = $this->setting('mail', []);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $mail['host'] ?? '');
        Config::set('mail.mailers.smtp.port', $mail['port'] ?? 587);
        Config::set('mail.mailers.smtp.encryption', ($mail['encryption'] ?? 'tls') === 'none' ? null : $mail['encryption']);
        Config::set('mail.mailers.smtp.username', $mail['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $mail['password'] ?? null);
        Config::set('mail.from.address', $mail['from_address'] ?? config('mail.from.address'));
        Config::set('mail.from.name', $mail['from_name'] ?? 'HivePanel');

        Mail::to($data['email'])->send(new TestMail());

        return back()->with('success', 'Test email sent.');
    }

    public function updateCaptcha(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', 'in:turnstile,recaptcha,hcaptcha'],
            'site_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $this->setting('captcha', []);

        if (! filled($data['secret_key'] ?? null)) {
            $data['secret_key'] = $existing['secret_key'] ?? '';
        }

        $this->setSetting('captcha', $data);

        return back()->with('success', 'Captcha settings updated.');
    }

    public function updateOAuth(Request $request)
    {
        $providers = ['discord', 'google', 'github'];

        $data = $request->validate([
            'providers' => ['required', 'array'],
        ]);

        foreach ($providers as $provider) {
            $payload = $request->input("providers.{$provider}", []);

            $validated = validator($payload, [
                'enabled' => ['boolean'],
                'client_id' => ['nullable', 'string', 'max:255'],
                'client_secret' => ['nullable', 'string', 'max:255'],
                'redirect_url' => ['nullable', 'url', 'max:2048'],
            ])->validate();

            $existing = OAuthProvider::firstOrCreate([
                'provider' => $provider,
            ]);

            $existing->update([
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'client_id' => $validated['client_id'] ?? null,
                'client_secret' => filled($validated['client_secret'] ?? null)
                    ? $validated['client_secret']
                    : $existing->client_secret,
                'redirect_url' => $validated['redirect_url'] ?? null,
            ]);
        }

        return back()->with('success', 'OAuth settings updated.');
    }

    private function setting(string $key, array $default = []): array
    {
        return AppSetting::where('key', $key)->first()?->value ?? $default;
    }

    private function setSetting(string $key, array $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        AppSettings::clear($key);
    }

    private function safeMailSettings(): array
    {
        $mail = $this->setting('mail', [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => '',
        ]);

        $mail['password'] = '';

        return $mail;
    }

    private function safeCaptchaSettings(): array
    {
        $captcha = $this->setting('captcha', [
            'enabled' => false,
            'provider' => 'turnstile',
            'site_key' => '',
            'secret_key' => '',
        ]);

        $captcha['secret_key'] = '';

        return $captcha;
    }

    private function oauthProviders(): array
    {
        $providers = OAuthProvider::query()
            ->get()
            ->keyBy('provider');

        return collect(['discord', 'google', 'github'])
            ->mapWithKeys(fn ($provider) => [
                $provider => [
                    'provider' => $provider,
                    'enabled' => (bool) ($providers[$provider]->enabled ?? false),
                    'client_id' => $providers[$provider]->client_id ?? '',
                    'client_secret' => '',
                    'redirect_url' => $providers[$provider]->redirect_url
                        ?? url("/auth/{$provider}/callback"),
                ],
            ])
            ->all();
    }
}