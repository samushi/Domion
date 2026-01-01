<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallFrontend
{
    public function __construct(protected Command $command) {}

    public function run(string $mode): void
    {
        if ($mode === 'api') {
            return;
        }

        $this->command->info("Configuring Frontend Stack: {$mode}...");

        $this->configureVite();
        $this->configureTailwind($mode);
        $this->configureTsConfig();
        $this->installNpmDependencies($mode);

        $this->command->info("✓ Frontend configured successfully.");
    }

    protected function configureVite(): void
    {
        $path = base_path('vite.config.js');
        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        // Check if alias is already configured to avoid duplication
        if (str_contains($content, '@domain')) {
            return;
        }

        // Configuration block to inject
        // We use standard string format to avoid IDE confusion with Heredoc labels
        $aliasConfig = "\n" .
            "    resolve: {\n" .
            "        alias: {\n" .
            "            '@': '/resources/js',\n" .
            "            '@domain': '/app/Domain',\n" .
            "            '@support': '/app/Support',\n" .
            "        },\n" .
            "    },";

        // Strategy 1: Inject safely inside defineConfig({
        // This regex looks for 'defineConfig(' followed by optional spaces, then '{'
        $pattern = '/(defineConfig\s*\(\s*\{)/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                $pattern,
                "$1" . $aliasConfig, // Appends alias config right after opening brace
                $content
            );

            File::put($path, $content);
        } else {
            $this->command->warn('Could not locate defineConfig({ in vite.config.js. Please add aliases manually.');
        }
    }

    protected function configureTailwind(string $mode): void
    {
        $path = base_path('tailwind.config.js');
        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        if (!str_contains($content, 'app/Domain')) {
            $ext = $mode === 'vue' ? 'vue' : 'js,ts,jsx,tsx';

            // Add paths for Domain and Support folders
            $newPaths = "'./app/Domain/**/*.{{$ext}}', './app/Support/**/*.{{$ext}}',";

            // Find the 'content: [' array start and inject paths
            $content = preg_replace('/(content:\s*\[)/', "$1\n        $newPaths", $content);

            File::put($path, $content);
        }
    }

    protected function configureTsConfig(): void
    {
        $path = base_path('tsconfig.json');
        if (!File::exists($path)) {
            $path = base_path('jsconfig.json');
        }

        // Initialize empty config if missing
        $json = File::exists($path) ? json_decode(File::get($path), true) : ['compilerOptions' => []];

        if (!isset($json['compilerOptions'])) {
            $json['compilerOptions'] = [];
        }

        $json['compilerOptions']['baseUrl'] = '.';

        if (!isset($json['compilerOptions']['paths'])) {
            $json['compilerOptions']['paths'] = [];
        }

        // Merge new aliases
        $json['compilerOptions']['paths'] = array_merge($json['compilerOptions']['paths'], [
            '@domain/*' => ['app/Domain/*'],
            '@support/*' => ['app/Support/*'],
            '@/*' => ['resources/js/*'],
        ]);

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function installNpmDependencies(string $mode): void
    {
        $this->command->info('Installing NPM dependencies...');

        $commonUtils = 'class-variance-authority clsx tailwind-merge lucide-react';

        $packages = match ($mode) {
            'react' => "@inertiajs/react react react-dom @vitejs/plugin-react @types/react @types/react-dom {$commonUtils}",
            'vue' => "@inertiajs/vue3 vue @vitejs/plugin-vue lucide-vue-next {$commonUtils}",
            default => '',
        };

        if ($packages) {
            // Using shell_exec/exec. Ensure you are in the project root.
            exec("npm install {$packages} --save-dev", $output, $returnVar);

            if ($returnVar !== 0) {
                $this->command->warn("NPM install might have failed. Please run: npm install {$packages} --save-dev");
            }
        }

        if ($mode === 'livewire') {
            $this->command->info('Setting up Livewire Volt...');
            exec('composer require livewire/livewire livewire/volt');
            exec('php artisan volt:install');
        }
    }
}