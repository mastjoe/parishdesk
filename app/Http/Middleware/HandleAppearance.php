<?php

namespace App\Http\Middleware;

use App\Models\ParishSetting;
use App\Support\Theme\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $parish = ParishSetting::current();

        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('theme', Theme::active($parish));
        View::share('themeCss', Theme::inlineCss($parish));

        return $next($request);
    }
}
