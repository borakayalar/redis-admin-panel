<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserPreferences
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $localeConfig = config('preferences.locale');
        $themeConfig = config('preferences.theme');
        $layoutWidthConfig = config('preferences.layout_width');

        $locale = $request->cookie($localeConfig['cookie'])
            ?? $request->session()->get($localeConfig['cookie'])
            ?? $localeConfig['default'];

        if (! in_array($locale, $localeConfig['supported'], true)) {
            $locale = $localeConfig['default'];
        }

        $theme = $request->cookie($themeConfig['cookie'])
            ?? $request->session()->get($themeConfig['cookie'])
            ?? $themeConfig['default'];

        if (! in_array($theme, $themeConfig['supported'], true)) {
            $theme = $themeConfig['default'];
        }

        $layoutWidth = $request->cookie($layoutWidthConfig['cookie'])
            ?? $request->session()->get($layoutWidthConfig['cookie'])
            ?? $layoutWidthConfig['default'];

        if (! in_array($layoutWidth, $layoutWidthConfig['supported'], true)) {
            $layoutWidth = $layoutWidthConfig['default'];
        }

        app()->setLocale($locale);
        $request->session()->put($localeConfig['cookie'], $locale);
        $request->session()->put($themeConfig['cookie'], $theme);
        $request->session()->put($layoutWidthConfig['cookie'], $layoutWidth);

        return $next($request);
    }
}
