<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\GeneratorCommand;
use Samushi\Domion\Helpers\DomainHelpers;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Facades\File;

#[AsCommand(
    name: 'domion:make:action',
    description: 'Create a new action in a domain',
)]
class MakeAction extends GeneratorCommand
{
    protected $type = 'Action';

    public function handle()
    {
        $name = $this->getNameInput();
        
        \Laravel\Prompts\intro("Creating action: {$name}");

        $domains = DomainHelpers::getDomains();
        
        if (empty($domains)) {
            \Laravel\Prompts\error('No domains found. Please create a domain first.');
            return false;
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
        $basePath = $domainPath;
        
        // Determine scope (Central/Tenant) for namespace
        $scope = '';
        if (str_contains($domainPath, 'Central')) $scope = 'Central';
        if (str_contains($domainPath, 'Tenant')) $scope = 'Tenant';

        $path = $basePath . "/Actions/{$name}.php";
        if (File::exists($path)) {
            \Laravel\Prompts\error("Action already exists!");
            return false;
        }

        $namespace = DomainHelpers::baseNamespace($domain, $scope) . "\\Actions";
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Samushi\Domion\Actions\ActionFactory;\n\nclass {$name} extends ActionFactory\n{\n    public function handle(...\$args): mixed\n    {\n        // Your business logic here\n        return null;\n    }\n}\n";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);

        $this->info("Action created successfully: {$path}");
    }

    protected function getStub()
    {
        return '';
    }
}
