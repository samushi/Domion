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

        // Ensure tsconfig.json exists first (required by Shadcn)
        $this->configureTsConfig();

        $cssPath = base_path('app/Support/Frontend/app.css');
        File::ensureDirectoryExists(dirname($cssPath));
        
        // Tailwind v4 uses @import instead of @tailwind directives
        $directives = "@import \"tailwindcss\";\n\n" .
            "@theme {\n" .
            "  --color-background: hsl(0 0% 100%);\n" .
            "  --color-foreground: hsl(222.2 84% 4.9%);\n" .
            "  --color-card: hsl(0 0% 100%);\n" .
            "  --color-card-foreground: hsl(222.2 84% 4.9%);\n" .
            "  --color-popover: hsl(0 0% 100%);\n" .
            "  --color-popover-foreground: hsl(222.2 84% 4.9%);\n" .
            "  --color-primary: hsl(222.2 47.4% 11.2%);\n" .
            "  --color-primary-foreground: hsl(210 40% 98%);\n" .
            "  --color-secondary: hsl(210 40% 96.1%);\n" .
            "  --color-secondary-foreground: hsl(222.2 47.4% 11.2%);\n" .
            "  --color-muted: hsl(210 40% 96.1%);\n" .
            "  --color-muted-foreground: hsl(215.4 16.3% 46.9%);\n" .
            "  --color-accent: hsl(210 40% 96.1%);\n" .
            "  --color-accent-foreground: hsl(222.2 47.4% 11.2%);\n" .
            "  --color-destructive: hsl(0 84.2% 60.2%);\n" .
            "  --color-destructive-foreground: hsl(210 40% 98%);\n" .
            "  --color-border: hsl(214.3 31.8% 91.4%);\n" .
            "  --color-input: hsl(214.3 31.8% 91.4%);\n" .
            "  --color-ring: hsl(222.2 84% 4.9%);\n" .
            "  --radius: 0.5rem;\n" .
            "}\n";
        
        File::put($cssPath, $directives);

        // 1.1 Create lib/utils.ts if missing (critical for shadcn)
        $utilsPath = base_path('app/Support/Frontend/lib/utils.ts');
        if (!File::exists($utilsPath)) {
            File::ensureDirectoryExists(dirname($utilsPath));
            File::put($utilsPath, "import { type ClassValue, clsx } from \"clsx\"\nimport { twMerge } from \"tailwind-merge\"\n\nexport function cn(...inputs: ClassValue[]) {\n  return twMerge(clsx(inputs))\n}\n");
        }

        // 1.2 Create bootstrap.ts for axios setup with CSRF
        $bootstrapPath = base_path('app/Support/Frontend/bootstrap.ts');
        if (!File::exists($bootstrapPath)) {
            $bootstrapContent = <<<'TS'
import axios from 'axios';

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

TS;
            File::put($bootstrapPath, $bootstrapContent);
        }

        // 2. Pre-create components.json to make it non-interactive
        $componentsJson = [
            '$schema' => 'https://ui.shadcn.com/schema.json',
            'style' => 'new-york',
            'rsc' => false,
            'tsx' => true,
            'tailwind' => [
                'config' => '',
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

        // 4. Add core components used by the Starter Kit (including Sidebar layout elements)
        $this->command->info('Adding Shadcn UI components...');
        exec('npx -y shadcn@latest add button card badge input label checkbox alert avatar dropdown-menu sidebar breadcrumb separator collapsible sheet tooltip -y --cwd .', $output, $returnVar);
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

        // 2. Update JS entry point
        $content = preg_replace(
            "/'resources\/js\/app\.(js|jsx|ts|tsx|vue)'/",
            "'app/Support/Frontend/app.$1'",
            $content
        );

        // 2b. Update CSS entry point (resources/css/app.css -> app/Support/Frontend/app.css)
        $content = preg_replace(
            "/'resources\/css\/app\.css'/",
            "'app/Support/Frontend/app.css'",
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
        // Tailwind v4 uses CSS-first configuration via @theme in app.css
        // No tailwind.config.js needed - all configuration is in the CSS file
        // The setupShadcn method already creates the app.css with @theme block
        
        // Just ensure PostCSS is configured
        $this->setupPostCss();
    }

    protected function setupPostCss(): void
    {
        $path = base_path('postcss.config.js');
        
        // Tailwind v4 uses @tailwindcss/postcss package
        $content = "export default {\n" .
            "    plugins: {\n" .
            "        '@tailwindcss/postcss': {},\n" .
            "    },\n" .
            "};\n";

        File::put($path, $content);
    }

    public function configureTsConfig(): void
    {
        $path = base_path('tsconfig.json');
        
        // Always create tsconfig.json (Shadcn requires it)

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

        $commonUtils = 'axios class-variance-authority clsx tailwind-merge lucide-react ziggy-js concurrently tailwindcss-animate autoprefixer @tailwindcss/postcss';

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
    }
}