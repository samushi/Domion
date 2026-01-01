<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Inertia\Inertia;
use Inertia\Response;

trait InertiaResponseTrait
{
    /**
     * Render an Inertia page.
     * Supports 'domain::Page' syntax which converts to 'Domain/Page' for Inertia.
     */
    protected function render(string $component, array $props = []): mixed
    {
        $original = $component;

        // Convert 'domain::Page' to 'Domain/Page' format for Inertia
        if (str_contains($component, '::')) {
            [$domain, $page] = explode('::', $component, 2);
            // Inertia expects 'Auth/Login'
            $component = \Illuminate\Support\Str::studly($domain) . '/' . \Illuminate\Support\Str::studly($page);
        }

        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render($component, $props);
        }

        // Fallback to standard Blade View
        if (str_contains($original, '::')) {
            [$domain, $page] = explode('::', $original, 2);
            // registered namespace is lowercase, filenames are usually studly or kebab but we use studly in stubs
            return view(\Illuminate\Support\Str::lower($domain) . '::' . $page, $props);
        }

        return view($component, $props);
    }

    /**
     * Redirect to a route with a flash message.
     */
    protected function redirectWithMessage(string $route, string $message, string $type = 'success'): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route($route)->with($type, $message);
    }

    /**
     * Back to previous page with a flash message.
     */
    protected function backWithMessage(string $message, string $type = 'success'): \Illuminate\Http\RedirectResponse
    {
        return back()->with($type, $message);
    }
}
