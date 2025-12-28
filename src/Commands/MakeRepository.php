<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\GeneratorCommand;
use Samushi\Domion\Helpers\DomainHelpers;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Facades\File;

#[AsCommand(
    name: 'domion:make:repository',
    description: 'Create a new repository in a domain',
)]
class MakeRepository extends GeneratorCommand
{
    protected $type = 'Repository';

    public function handle(): int
    {
        $name = $this->getNameInput();

        // Ensure name ends with Repository
        if (!str_ends_with($name, 'Repository')) {
            $name .= 'Repository';
        }

        \Laravel\Prompts\intro("Creating repository: {$name}");

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

        $path = $domainPath . "/Repository/{$name}.php";
        if (File::exists($path)) {
            \Laravel\Prompts\error("Repository already exists!");
            return self::FAILURE;
        }

        // Get model name from repository name
        $modelName = str_replace('Repository', '', $name);

        $namespace = DomainHelpers::baseNamespace($domain, $scope) . "\\Repository";
        $modelNamespace = DomainHelpers::baseNamespace($domain, $scope) . "\\Models";

        $stubPath = __DIR__ . '/../stubs/Repository.stub';
        $content = File::get($stubPath);
        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{modelNamespace}}', '{{model}}'],
            [$namespace, $name, $modelNamespace, $modelName],
            $content
        );

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);

        \Laravel\Prompts\info("Repository created successfully: {$path}");

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        return __DIR__ . '/../stubs/Repository.stub';
    }
}
