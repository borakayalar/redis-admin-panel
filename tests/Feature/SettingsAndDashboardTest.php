<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_dashboard_request_renders_the_dashboard_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('settings.locale', 'en')
            ->where('settings.theme', 'system')
            ->where('settings.layoutWidth', 'normal')
            ->where('settings.availableLocales', ['tr', 'en'])
            ->where('settings.availableThemes', ['light', 'dark', 'system'])
            ->where('settings.availableLayoutWidths', ['normal', 'wide'])
        );
    }

    public function test_locale_cookie_sets_the_app_locale_and_shared_settings(): void
    {
        $response = $this
            ->withCookie('locale', 'tr')
            ->withCookie('theme', 'dark')
            ->get('/login');

        $response->assertOk();
        $response->assertSee('lang="tr"', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('settings.locale', 'tr')
            ->where('settings.theme', 'dark')
            ->where('settings.layoutWidth', 'normal')
        );

        $this->assertSame('tr', app()->getLocale());
    }

    public function test_default_settings_are_shared_on_guest_auth_pages(): void
    {
        foreach ([
            '/login' => 'Auth/Login',
            '/forgot-password' => 'Auth/ForgotPassword',
        ] as $uri => $component) {
            $response = $this->get($uri);

            $response->assertOk();
            $response->assertSee('lang="en"', false);
            $response->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('settings.locale', 'en')
                ->where('settings.theme', 'system')
                ->where('settings.layoutWidth', 'normal')
            );
        }
    }

    public function test_default_settings_are_shared_on_authenticated_pages(): void
    {
        $unverifiedUser = User::factory()->unverified()->create();
        $profileUser = User::factory()->create();

        $verifyEmailResponse = $this->actingAs($unverifiedUser)->get('/verify-email');
        $verifyEmailResponse->assertOk();
        $verifyEmailResponse->assertSee('lang="en"', false);
        $verifyEmailResponse->assertInertia(fn (Assert $page) => $page
            ->component('Auth/VerifyEmail')
            ->where('settings.locale', 'en')
            ->where('settings.theme', 'system')
            ->where('settings.layoutWidth', 'normal')
        );

        $profileResponse = $this->actingAs($profileUser)->get('/profile');
        $profileResponse->assertOk();
        $profileResponse->assertSee('lang="en"', false);
        $profileResponse->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('settings.locale', 'en')
            ->where('settings.theme', 'system')
            ->where('settings.layoutWidth', 'normal')
        );
    }
}
