<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Inertia\Inertia;
use Inertia\Response;
use Samushi\Domion\Support\Traits\InertiaResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class InertiaControllers extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    use InertiaResponseTrait;
}
