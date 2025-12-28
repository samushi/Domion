<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\GeneratorCommand;
use Samushi\Domion\Helpers\DomainHelpers;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Facades\File;

#[AsCommand(
    name: 'domion:make:dto',
    description: 'Create a new DTO (Data Transfer Object) in a domain',
)]
class MakeDto extends GeneratorCommand
{
    protected $type = 'DTO';

    public function handle(): int
    {
        $name = $this->getNameInput();

        // Ensure name ends with Dto
        if (!str_ends_with($name, 'Dto')) {
            $name .= 'Dto';
        }

        \Laravel\Prompts\intro("Creating DTO: {$name}");

        $domains = DomainHelpers::getDomains();

        if (empty($domains)) {
            \Laravel\Prompts\error('No domains found. Please create a domain first.');
            return self::FAILURE;
        }

        // Map domain paths to names for selection
        $options = [];
        foreach ($domains as $path) {
            $options[$path] = basename($path);
        }

        $domainPath = \Laravel\Prompts\select(
            label: 'In which domain?',
            options: $options
        );

        $domain = basename($domainPath);

        // Determine scope (Central/Tenant) for namespace
        $scope = '';
        if (str_contains($domainPath, 'Central')) {
            $scope = 'Central';
        }
        if (str_contains($domainPath, 'Tenant')) {
            $scope = 'Tenant';
        }

        $path = $domainPath . "/Dto/{$name}.php";
        if (File::exists($path)) {
            \Laravel\Prompts\error("DTO already exists!");
            return self::FAILURE;
        }

        $namespace = DomainHelpers::baseNamespace($domain, $scope) . "\\Dto";

        $stubPath = __DIR__ . '/../stubs/Dto.stub';
        $content = File::get($stubPath);
        $content = str_replace(['{{namespace}}', '{{class}}'], [$namespace, $name], $content);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);

        \Laravel\Prompts\info("DTO created successfully: {$path}");

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        return __DIR__ . '/../stubs/Dto.stub';
    }
}
