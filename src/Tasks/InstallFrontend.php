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

        $cssPath = base_path('app/Support/Frontend/app.css');
        File::ensureDirectoryExists(dirname($cssPath));
        
        $directives = "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n\n@layer base {\n  :root {\n    --background: 0 0% 100%;\n    --foreground: 222.2 84% 4.9%;\n    --card: 0 0% 100%;\n    --card-foreground: 222.2 84% 4.9%;\n    --popover: 0 0% 100%;\n    --popover-foreground: 222.2 84% 4.9%;\n    --primary: 222.2 47.4% 11.2%;\n    --primary-foreground: 210 40% 98%;\n    --secondary: 210 40% 96.1%;\n    --secondary-foreground: 222.2 47.4% 11.2%;\n    --muted: 210 40% 96.1%;\n    --muted-foreground: 215.4 16.3% 46.9%;\n    --accent: 210 40% 96.1%;\n    --accent-foreground: 222.2 47.4% 11.2%;\n    --destructive: 0 84.2% 60.2%;\n    --destructive-foreground: 210 40% 98%;\n    --border: 214.3 31.8% 91.4%;\n    --input: 214.3 31.8% 91.4%;\n    --ring: 222.2 84% 4.9%;\n    --radius: 0.5rem;\n  }\n\n  .dark {\n    --background: 222.2 84% 4.9%;\n    --foreground: 210 40% 98%;\n    /* ... other dark vars if needed ... */\n  }\n}\n\n@layer base {\n  * {\n    @apply border-border;\n  }\n  body {\n    @apply bg-background text-foreground;\n  }\n}";
        
        if (!File::exists($cssPath) || !str_contains(File::get($cssPath), '@tailwind base')) {
            File::put($cssPath, $directives . "\n" . (File::exists($cssPath) ? File::get($cssPath) : ''));
        }

        // 1.1 Create lib/utils.ts if missing (critical for shadcn)
        $utilsPath = base_path('app/Support/Frontend/lib/utils.ts');
        if (!File::exists($utilsPath)) {
            File::ensureDirectoryExists(dirname($utilsPath));
            File::put($utilsPath, "import { type ClassValue, clsx } from \"clsx\"\nimport { twMerge } from \"tailwind-merge\"\n\nexport function cn(...inputs: ClassValue[]) {\n  return twMerge(clsx(inputs))\n}\n");
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
        exec('npx -y shadcn@latest init -y --cwd .', $output, $returnVar);

        // 4. Add core components used by the Starter Kit
        $this->command->info('Adding Shadcn UI components...');
        exec('npx -y shadcn@latest add button card badge input label checkbox alert avatar dropdown-menu -y --cwd .', $output, $returnVar);
    }

    public function configureVite(string $mode): void
    {
        $path = base_path('vite.config.js');
        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        // 1. Remove Tailwind v4 remnants (Laravel 12 defaults)
        $content = preg_replace("/import tailwindcss from '@tailwindcss\/vite';\n?/", "", $content);
        $content = preg_replace("/tailwindcss\(\),\s*/", "", $content);

        // 2. Add required imports at the top
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
            $this->command->info('Creating Tailwind CSS configuration...');
            $ext = $mode === 'vue' ? 'vue' : 'js,ts,jsx,tsx';
            
            $content = "import tailwindAnimate from 'tailwindcss-animate';\n\n" .
                "/** @type {import('tailwindcss').Config} */\n" .
                "export default {\n" .
                "    darkMode: ['class'],\n" .
                "    content: [\n" .
                "        './app/Domain/**/*.{" . $ext . "}',\n" .
                "        './app/Support/**/*.{" . $ext . "}',\n" .
                "        './resources/views/**/*.blade.php',\n" .
                "    ],\n" .
                "    theme: {\n" .
                "        container: {\n" .
                "            center: true,\n" .
                "            padding: '2rem',\n" .
                "            screens: {\n" .
                "                '2xl': '1400px',\n" .
                "            },\n" .
                "        },\n" .
                "        extend: {\n" .
                "            colors: {\n" .
                "                border: 'hsl(var(--border))',\n" .
                "                input: 'hsl(var(--input))',\n" .
                "                ring: 'hsl(var(--ring))',\n" .
                "                background: 'hsl(var(--background))',\n" .
                "                foreground: 'hsl(var(--foreground))',\n" .
                "                primary: {\n" .
                "                    DEFAULT: 'hsl(var(--primary))',\n" .
                "                    foreground: 'hsl(var(--primary-foreground))',\n" .
                "                },\n" .
                "                secondary: {\n" .
                "                    DEFAULT: 'hsl(var(--secondary))',\n" .
                "                    foreground: 'hsl(var(--secondary-foreground))',\n" .
                "                },\n" .
                "                destructive: {\n" .
                "                    DEFAULT: 'hsl(var(--destructive))',\n" .
                "                    foreground: 'hsl(var(--destructive-foreground))',\n" .
                "                },\n" .
                "                muted: {\n" .
                "                    DEFAULT: 'hsl(var(--muted))',\n" .
                "                    foreground: 'hsl(var(--muted-foreground))',\n" .
                "                },\n" .
                "                accent: {\n" .
                "                    DEFAULT: 'hsl(var(--accent))',\n" .
                "                    foreground: 'hsl(var(--accent-foreground))',\n" .
                "                },\n" .
                "                popover: {\n" .
                "                    DEFAULT: 'hsl(var(--popover))',\n" .
                "                    foreground: 'hsl(var(--popover-foreground))',\n" .
                "                },\n" .
                "                card: {\n" .
                "                    DEFAULT: 'hsl(var(--card))',\n" .
                "                    foreground: 'hsl(var(--card-foreground))',\n" .
                "                },\n" .
                "            },\n" .
                "            borderRadius: {\n" .
                "                lg: 'var(--radius)',\n" .
                "                md: 'calc(var(--radius) - 2px)',\n" .
                "                sm: 'calc(var(--radius) - 4px)',\n" .
                "            },\n" .
                "        },\n" .
                "    },\n" .
                "    plugins: [tailwindAnimate],\n" .
                "};\n";
            
            File::put($path, $content);
        } else {
            // Ensure DDD paths are present in existing config
            $content = File::get($path);
            if (!str_contains($content, 'app/Domain')) {
                $ext = $mode === 'vue' ? 'vue' : 'js,ts,jsx,tsx';
                $newPaths = "'./app/Domain/**/*.{" . $ext . "}', './app/Support/**/*.{" . $ext . "}',";
                $content = preg_replace('/(content:\s*\[)/', "$1\n        $newPaths", $content);
                File::put($path, $content);
            }
        }

        $this->setupPostCss();
    }

    protected function setupPostCss(): void
    {
        $path = base_path('postcss.config.js');
        
        $content = "import tailwindcssNesting from 'tailwindcss/nesting/index.js';\n" .
            "import tailwindcss from 'tailwindcss';\n" .
            "import autoprefixer from 'autoprefixer';\n\n" .
            "export default {\n" .
            "    plugins: [\n" .
            "        tailwindcssNesting,\n" .
            "        tailwindcss,\n" .
            "        autoprefixer,\n" .
            "    ],\n" .
            "};\n";

        File::put($path, $content);
    }

    public function configureTsConfig(): void
    {
        $path = base_path('tsconfig.json');
        
        // If it's a JS project but we want TS support for Shadcn, we prefer tsconfig.json
        if (!File::exists($path) && File::exists(base_path('jsconfig.json'))) {
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

        $commonUtils = 'class-variance-authority clsx tailwind-merge lucide-react ziggy-js concurrently tailwindcss-animate';

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

        // Update package.json dev script
        $pkgPath = base_path('package.json');
        if (File::exists($pkgPath)) {
            $pkg = json_decode(File::get($pkgPath), true);
            $pkg['scripts']['dev'] = 'concurrently "php artisan serve" "vite"';
            File::put($pkgPath, json_encode($pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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