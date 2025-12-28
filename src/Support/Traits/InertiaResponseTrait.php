<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Inertia\Inertia;
use Inertia\Response;

trait InertiaResponseTrait
{
    /**
     * Render an Inertia page.
     */
    protected function render(string $component, array $props = []): Response
    {
        return Inertia::render($component, $props);
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
