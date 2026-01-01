<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

abstract class WebControllers extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Render a Blade view.
     * Automatically supports 'domain::path' syntax.
     *
     * @param string $view
     * @param array $data
     * @return View
     */
    protected function render(string $view, array $data = []): View
    {
        if (str_contains($view, '::')) {
            [$domain, $path] = explode('::', $view, 2);
            $view = strtolower($domain) . '::' . $path;
        }
        
        return view($view, $data);
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
