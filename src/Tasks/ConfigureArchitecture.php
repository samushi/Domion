<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureArchitecture
{
    public function __construct(protected Command $command) {}

    public function run(bool $tenancy, string $mode = 'api'): void
    {
        $this->createFolders($tenancy);
        $this->updateComposer();
        $this->cleanup($mode);
        $this->setupBootstrap();
        $this->cleanDefaultRoutes();
        $this->setupInertiaResolver($mode);
    }

    protected function setupInertiaResolver(string $mode): void
    {
        if ($mode === 'api' || $mode === 'blade' || $mode === 'livewire') {
            return;
        }

        $ext = $mode === 'react' ? 'tsx' : 'vue';
        $possiblePaths = [
            base_path('resources/js/app.js'),
            base_path('resources/js/app.tsx'),
            base_path('resources/js/app.jsx'),
            base_path('resources/js/app.vue'),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                $content = File::get($path);

                // If already configured, skip
                if (str_contains($content, 'domain')) {
                    continue;
                }

                $dddResolver = "resolve: (name) => {\n" .
                    "        if (name.includes('/')) {\n" .
                    "            const parts = name.split('/');\n" .
                    "            const domain = parts[0];\n" .
                    "            const page = parts.slice(1).join('/');\n" .
                    "            return resolvePageComponent(`../../app/Domain/\${domain}/Frontend/Pages/\${page}.{$ext}`, import.meta.glob('../../app/Domain/*/Frontend/Pages/**/*.{$ext}'));\n" .
                    "        }\n" .
                    "        return resolvePageComponent(`./Pages/\${name}.{$ext}`, import.meta.glob('./Pages/**/*.{$ext}'));\n" .
                    "    },";

                $content = preg_replace('/resolve:\s*\(name\)\s*=>\s*resolvePageComponent\([^)]+\),?/', $dddResolver, $content);

                File::put($path, $content);
                break;
            }
        }
    }

    protected function createFolders(bool $tenancy): void
    {
        $dirs = ['app/App/Providers', 'app/Domain', 'app/Support/Frontend/Views'];
        if ($tenancy) array_push($dirs, 'app/Domain/Central', 'app/Domain/Tenant');

        foreach ($dirs as $dir) File::ensureDirectoryExists(base_path($dir));
    }

    protected function updateComposer(): void
    {
        $path = base_path('composer.json');
        if (!File::exists($path)) return;

        $json = json_decode(File::get($path), true);

        $json['autoload']['psr-4'] = array_merge($json['autoload']['psr-4'], [
            "App\\" => "app/App/",
            "Domain\\" => "app/Domain/",
            "Support\\" => "app/Support/"
        ]);

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function cleanup(string $mode): void
    {
        if (File::isDirectory(base_path('app/Providers'))) {
            foreach (File::files(base_path('app/Providers')) as $file) {
                $content = File::get($file);
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
                
                // If it's AppServiceProvider, inject DomainLoader and Volt if needed
                if ($file->getFilename() === 'AppServiceProvider.php') {
                    if (!str_contains($content, 'DomainLoader::registerDomainProviders')) {
                        $content = str_replace(
                            'public function register(): void',
                            "public function register(): void\n    {\n        \\Samushi\\Domion\\Support\\DomainLoader::registerDomainProviders(\$this->app);",
                            $content
                        );
                        $content = preg_replace('/register\(\): void\n    \{\s*\}/', "register(): void\n    {", $content);
                    }

                    if ($mode === 'livewire' && !str_contains($content, 'Volt::mount')) {
                        $content = str_replace(
                            'public function boot(): void',
                            "public function boot(): void\n    {\n        \\Livewire\\Volt\\Volt::mount([realpath(__DIR__.'/../../../app/Domain')]);",
                            $content
                        );
                        $content = preg_replace('/boot\(\): void\n    \{\s*\}/', "boot(): void\n    {", $content);
                    }
                }

                File::put(base_path('app/App/Providers/' . $file->getFilename()), $content);
            }
            File::deleteDirectory(base_path('app/Providers'));
        }
        File::deleteDirectory(base_path('app/Models'));
    }

    protected function setupBootstrap(): void
    {
        $path = base_path('bootstrap/app.php');
        if (File::exists($path)) {
            $content = File::get($path);
            if (!str_contains($content, 'useAppPath')) {
                $content = str_replace(
                    '->create();',
                    "->create()\n        ->useAppPath(realpath(__DIR__.'/../app/App'));",
                    $content
                );
                File::put($path, $content);
            }
        }
    }

    /**
     * Remove the old Helper loader from global routes.
     * Service Providers now handle routing.
     */
    protected function cleanDefaultRoutes(): void
    {
        $webPath = base_path('routes/web.php');
        if (File::exists($webPath)) {
            File::put($webPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n// Global routes (e.g. health check)\n// Domain routes are loaded via their ServiceProviders\n");
        }

        $apiPath = base_path('routes/api.php');
        if (File::exists($apiPath)) {
            File::put($apiPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n// Global API routes\n");
        }
    }
}