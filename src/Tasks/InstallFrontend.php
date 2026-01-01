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

        $this->configureVite($mode);
        $this->configureTailwind($mode);
        $this->configureTsConfig();
        $this->installNpmDependencies($mode);
        $this->setupShadcn($mode);

        $this->command->info("✓ Frontend configured successfully.");
    }

    public function setupShadcn(string $mode): void
    {
        if (!in_array($mode, ['react', 'vue'])) {
            return;
        }

        $this->command->info('Initializing Shadcn UI...');

        // 1. Create app.css if missing
        $cssPath = base_path('app/Support/Frontend/app.css');
        if (!File::exists($cssPath)) {
            File::ensureDirectoryExists(dirname($cssPath));
            File::put($cssPath, "@tailwind base;\n@tailwind components;\n@tailwind utilities;");
        }

        // 2. Pre-create components.json to make it non-interactive
        $componentsJson = [
            '$schema' => 'https://ui.shadcn.com/schema.json',
            'style' => 'new-york',
            'rsc' => false,
            'tsx' => true,
            'tailwind' => [
                'config' => 'tailwind.config.js',
                'css' => 'app/Support/Frontend/app.css',
                'baseColor' => 'slate',
                'cssVariables' => true,
                'prefix' => '',
            ],
            'aliases' => [
                'components' => '@/components',
                'utils' => '@/lib/utils',
                'ui' => '@/components/ui',
                'lib' => '@/lib',
                'hooks' => '@/hooks',
            ],
        ];

        File::put(base_path('components.json'), json_encode($componentsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3. Run shadcn init (pick up components.json)
        exec('npx -y shadcn@latest init -y', $output, $returnVar);
    }

    public function configureVite(string $mode): void
    {
        $path = base_path('vite.config.js');
        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        // 1. Add required imports at the top
        if (!str_contains($content, "import path from 'path'")) {
            $content = "import path from 'path';\n" . $content;
        }
        if ($mode === 'react' && !str_contains($content, "@vitejs/plugin-react")) {
            $content = "import react from '@vitejs/plugin-react';\n" . $content;
        }
        if ($mode === 'vue' && !str_contains($content, "@vitejs/plugin-vue")) {
            $content = "import vue from '@vitejs/plugin-vue';\n" . $content;
        }

        // 2. Update entry point
        $content = preg_replace(
            "/'resources\/js\/app\.(js|jsx|ts|tsx|vue)'/",
            "'app/Support/Frontend/app.$1'",
            $content
        );

        // 3. Add plugins to defineConfig
        if ($mode === 'react' && !str_contains($content, "react()")) {
            $content = preg_replace('/(plugins:\s*\[)/', "$1\n        react(),", $content);
        }
        if ($mode === 'vue' && !str_contains($content, "vue()")) {
            $content = preg_replace('/(plugins:\s*\[)/', "$1\n        vue(),", $content);
        }

        // 4. Add aliases with path.resolve
        if (!str_contains($content, 'resolve: {')) {
            $aliasConfig = "\n" .
                "    resolve: {\n" .
                "        alias: {\n" .
                "            '@': path.resolve(__dirname, 'app/Support/Frontend'),\n" .
                "            '@domain': path.resolve(__dirname, 'app/Domain'),\n" .
                "            '@support': path.resolve(__dirname, 'app/Support'),\n" .
                "            '@ui': path.resolve(__dirname, 'app/Support/Frontend/components/ui'),\n" .
                "        },\n" .
                "    },";

            $pattern = '/(defineConfig\s*\(\s*\{)/';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "$1" . $aliasConfig, $content);
            }
        }

        File::put($path, $content);
    }

    public function configureTailwind(string $mode): void
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

    public function configureTsConfig(): void
    {
        $path = base_path('tsconfig.json');
        if (!File::exists($path)) {
            $path = base_path('jsconfig.json');
        }

        // Initialize empty config if missing
        $json = File::exists($path) ? json_decode(File::get($path), true) : [
            'compilerOptions' => [
                'target' => 'esnext',
                'module' => 'esnext',
                'moduleResolution' => 'bundler',
                'strict' => true,
                'jsx' => 'react-jsx',
                'baseUrl' => '.',
                'allowJs' => true,
                'skipLibCheck' => true,
                'esModuleInterop' => true,
                'allowSyntheticDefaultImports' => true,
                'forceConsistentCasingInFileNames' => true,
                'noEmit' => true,
                'isolatedModules' => true,
            ],
            'include' => [
                'app/Domain/**/*',
                'app/Support/**/*',
                'vite.config.js'
            ]
        ];

        if (!isset($json['compilerOptions'])) {
            $json['compilerOptions'] = [];
        }

        $json['compilerOptions']['baseUrl'] = '.';
        $json['compilerOptions']['jsx'] = 'react-jsx';
        $json['compilerOptions']['moduleResolution'] = 'bundler';

        if (!isset($json['compilerOptions']['paths'])) {
            $json['compilerOptions']['paths'] = [];
        }

        // Add vite/client types
        if (!isset($json['compilerOptions']['types'])) {
            $json['compilerOptions']['types'] = [];
        }
        if (!in_array('vite/client', $json['compilerOptions']['types'])) {
            $json['compilerOptions']['types'][] = 'vite/client';
        }

        // Merge DDD aliases
        $json['compilerOptions']['paths'] = array_merge($json['compilerOptions']['paths'], [
            '@/*' => ['app/Support/Frontend/*'],
            '@domain/*' => ['app/Domain/*'],
            '@support/*' => ['app/Support/*'],
            '@ui/*' => ['app/Support/Frontend/components/ui/*'],
        ]);

        // Ensure include exists and has our folders
        if (!isset($json['include'])) {
            $json['include'] = [];
        }
        $requiredInclude = ['app/Domain/**/*', 'app/Support/**/*'];
        foreach ($requiredInclude as $inc) {
            if (!in_array($inc, $json['include'])) {
                $json['include'][] = $inc;
            }
        }

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function installNpmDependencies(string $mode): void
    {
        $this->command->info('Installing NPM dependencies...');

        $commonUtils = 'class-variance-authority clsx tailwind-merge lucide-react ziggy-js';

        $packages = match ($mode) {
            'react' => "@inertiajs/react react react-dom @vitejs/plugin-react @types/react @types/react-dom {$commonUtils}",
            'vue' => "@inertiajs/vue3 vue @vitejs/plugin-vue lucide-vue-next {$commonUtils}",
            default => '',
        };

        if ($packages) {
            // Optimized npm install
            exec("npm install {$packages} --save-dev --no-audit --no-fund --quiet", $output, $returnVar);

            if ($returnVar !== 0) {
                $this->command->warn("NPM install might have failed. Please run: npm install {$packages} --save-dev");
            }
        }

        if (in_array($mode, ['react', 'vue'])) {
            $this->command->info('Setting up Inertia & Ziggy PHP side...');
            exec('composer require inertiajs/inertia-laravel tightenco/ziggy --quiet');
        }

        if ($mode === 'livewire') {
            $this->command->info('Setting up Livewire Volt...');
            exec('composer require livewire/livewire livewire/volt --quiet');
            exec('php artisan volt:install');
        }
    }
}