<?php

namespace Samushi\Domion\Support\Enums;

use Samushi\Domion\Support\Enums\Concern\Arrayable;

enum OrderQuery: string
{
    use Arrayable;
    case ASC = 'asc';
    case DESC = 'desc';
}
