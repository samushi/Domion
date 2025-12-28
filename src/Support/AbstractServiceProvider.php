<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\ServiceProvider;

abstract class AbstractServiceProvider extends ServiceProvider
{
    /**
     * Set Domain name
     */
    abstract public function setDomain(): string;

    /**
     * Service Provider when boot
     */
    public function boot(): void
    {
        //
    }

    /**
     * Service Provider when register
     */
    public function register(): void
    {
    }

    /**
     * Get domain name
     */
    protected function getDomain(): ?string
    {
        return $this->setDomain();
    }
}
