<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureArchitecture
{
    public function __construct(protected Command $command) {}

    public function run(bool $tenancy): void
    {
        $this->createFolders($tenancy);
        $this->updateComposer();
        $this->cleanup();
        $this->setupBootstrap();
        $this->cleanDefaultRoutes(); // Crucial step
    }

    protected function createFolders(bool $tenancy): void
    {
        $dirs = ['app/App/Providers', 'app/Domain', 'app/Support/Resources/views'];
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

    protected function cleanup(): void
    {
        if (File::isDirectory(base_path('app/Providers'))) {
            foreach (File::files(base_path('app/Providers')) as $file) {
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', File::get($file));
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