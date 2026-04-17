<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $localeConfig = config('preferences.locale');
        $themeConfig = config('preferences.theme');
        $layoutWidthConfig = config('preferences.layout_width');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'settings' => [
                'locale' => app()->getLocale(),
                'theme' => $request->session()->get($themeConfig['cookie'], $themeConfig['default']),
                'layoutWidth' => $request->session()->get($layoutWidthConfig['cookie'], $layoutWidthConfig['default']),
                'availableLocales' => $localeConfig['supported'],
                'availableThemes' => $themeConfig['supported'],
                'availableLayoutWidths' => $layoutWidthConfig['supported'],
            ],
        ];
    }
}
