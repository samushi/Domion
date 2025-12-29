<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'domion:setup',
    description: 'Setup the DDD architecture folders and initial structure',
)]
class SetupCommand extends Command
{
    public function handle(): int
    {
        \Laravel\Prompts\intro('🏗️  Domion: Professional DDD Setup');

        if (!\Laravel\Prompts\confirm('Do you want to initialize the Domion architecture?', true)) {
            \Laravel\Prompts\error('Setup cancelled.');
            return self::FAILURE;
        }

        // 1. Architecture Choice (Radio style)
        $tenancy = \Laravel\Prompts\select(
            label: 'Select your architecture type:',
            options: [
                'standard' => 'Standard (Single Application)',
                'tenancy' => 'Multi-tenancy (Central & Tenant Domains)'
            ],
            default: 'standard'
        ) === 'tenancy';

        // 2. Frontend Mode (Radio style)
        $mode = \Laravel\Prompts\select(
            label: 'Which frontend mode do you want to use?',
            options: [
                'api' => 'API Only (STALESS)',
                'react' => 'React (Inertia.js)',
                'vue' => 'Vue (Inertia.js)',
                'livewire' => 'Livewire (TALL)',
                'blade' => 'Standard Blade Views'
            ],
            default: 'react'
        );

        // 3. Auth Integration
        $auth = \Laravel\Prompts\select(
            label: 'Which authentication package are you using?',
            options: [
                'sanctum' => 'Sanctum (Bearer Tokens / SPA)',
                'passport' => 'Passport (Full OAuth2)',
                'none' => 'None / Custom'
            ],
            default: 'sanctum'
        );

        // 4. Cleanup Legacy Structure
        $cleanup = \Laravel\Prompts\confirm('Do you want to move default Providers to App/Providers and delete app/Models and app/Http?', true);

        // 5. Starter Kit
        $starterKit = \Laravel\Prompts\confirm('Do you want to install an example Auth domain (Login/Register/Dashboard)?', true);

        // 6. Install Dependencies FIRST (Interactive)
        $this->setupDependencies($mode, $auth, $tenancy);

        \Laravel\Prompts\spin(
            function() use ($mode, $tenancy, $cleanup, $starterKit, $auth) {
                $this->setupFolders($tenancy);
                
                if ($cleanup) {
                    $this->cleanupLaravelDefaults();
                }

                if ($starterKit) {
                    $this->installStarterKit($mode);
                }

                $this->setupRoutes();
                if (in_array($mode, ['react', 'vue'])) {
                    $activeExtension = $this->setupFrontend($mode);
                    $this->setupVite($activeExtension);
                    $this->setupJsConfig();
                    $this->setupTailwind($mode);
                }
            },
            'Configuring Domion Architecture...'
        );

        // Install dependencies after configuration (Interactive)
        $this->setupDependencies($mode, $auth, $tenancy);

        $this->saveConfiguration($auth, $mode, $tenancy);

        \Laravel\Prompts\outro('✅ Domion Setup completed successfully!');
        $this->info('Please run: <fg=green;options=bold>composer dump-autoload</>');
        
        if (\Laravel\Prompts\confirm('Do you want to start the development server now?', true)) {
            $this->info('🚀 Starting development servers... (Press Ctrl+C to stop)');
            
            $command = 'php artisan serve';
            if (in_array($mode, ['react', 'vue'])) {
                // Run both in parallel and wait for them to catch Ctrl+C
                $command = 'php artisan serve & npm run dev --silent > /dev/null 2>&1 & wait';
            }

            passthru($command);
        }

        return self::SUCCESS;
    }

    protected function setupFrontend(string $mode): string
    {
        $extensions = ['js', 'jsx', 'tsx'];
        $appPath = null;
        $activeExtension = 'js';

        foreach ($extensions as $ext) {
            $path = base_path("resources/js/app.{$ext}");
            if (File::exists($path)) {
                $appPath = $path;
                $activeExtension = $ext;
                break;
            }
        }

        if (!$appPath) {
            $this->warn('Could not find resources/js/app.js (or .jsx/.tsx). Manual Inertia setup required.');
            return 'js';
        }

        // If using React/Vue, ensure the file has a compatible extension for JSX/SFC
        if (in_array($mode, ['react', 'vue']) && $activeExtension === 'js') {
            if ($mode === 'react') {
                $newExt = 'tsx';
                $newPath = base_path("resources/js/app.{$newExt}");
                File::move($appPath, $newPath);
                $appPath = $newPath;
                $activeExtension = $newExt;
            }
        }

        $content = File::get($appPath);
        
        // If app.js is basically empty or default Laravel, install the full starter kit app.js
        if (!str_contains($content, 'createInertiaApp')) {
            $stubName = $mode === 'react' ? 'ReactApp' : 'VueApp';
            $this->generateFromStub($stubName, $appPath);
            $content = File::get($appPath);
            $this->components->twoColumnDetail('Inertia Entry Point', '<fg=green;options=bold>INSTALLED</>');
        }
        
        if (str_contains($content, 'app/Domain')) {
            $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=yellow;options=bold>EXISTS</>');
        } else if (str_contains($content, 'createInertiaApp')) {
            $globPattern = "../../app/Domain/*/Frontend/Pages/**/*.{js,jsx,ts,tsx,vue}";
            $injection = "\n// Domion DDD Resolver\n" .
                "const domainPages = import.meta.glob('{$globPattern}');\n" .
                "const resolveDomainPage = (name, domainPages, resolver) => {\n" .
                "    if (name.includes('::')) {\n" .
                "        const [domain, page] = name.split('::');\n" .
                "        const path = Object.keys(domainPages).find(p => p.toLowerCase().includes(`/domain/\${domain.toLowerCase()}/frontend/pages/\${page.toLowerCase()}`));\n" .
                "        if (path) return typeof domainPages[path] === 'function' ? domainPages[path]() : domainPages[path];\n" .
                "    }\n" .
                "    return resolver(name);\n" .
                "};\n\n";

            if (preg_match('/(?<!import\s\{)(?<![a-zA-Z0-9])createInertiaApp\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $content = substr($content, 0, $pos) . $injection . substr($content, $pos);
            } else {
                $content = $injection . $content;
            }
            
            $resolvePattern = '/resolve:\s*\(?name\)?\s*=>\s*resolvePageComponent\((.*?)\),?/s';
            $replacement = "resolve: (name) => resolveDomainPage(name, domainPages, (name) => resolvePageComponent($1)),";
            
            if (preg_match($resolvePattern, $content)) {
                $content = preg_replace($resolvePattern, $replacement, $content);
                File::put($appPath, $content);
                $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=green;options=bold>INSTALLED</>');
            }
        }

        // 3. Ensure app.blade.php exists in Support Resources
        $supportViewPath = base_path('app/Support/Resources/views');
        if (!File::isDirectory($supportViewPath)) {
            File::makeDirectory($supportViewPath, 0755, true);
        }

        $viewPath = $supportViewPath . '/app.blade.php';
        if (!File::exists($viewPath)) {
            $this->generateFromStub('AppView', $viewPath, [
                'mode' => $mode,
                'ext' => $activeExtension,
                'viteReactRefresh' => $mode === 'react' ? '@viteReactRefresh' : ''
            ]);
            $this->components->twoColumnDetail('Support Root View (DDD)', '<fg=green;options=bold>CREATED</>');
        }

        // Add explicit React import if missing (fixes ReferenceError: React is not defined)
        if ($mode === 'react' && !str_contains($content, "import React")) {
            $content = "import React from 'react';\n" . $content;
            File::put($appPath, $content);
        }

        // Ensure app.css is imported in app.js/tsx
        if (!str_contains($content, "import '../css/app.css'")) {
             $content = "import '../css/app.css';\n" . $content;
             File::put($appPath, $content);
        }

        // Ensure app.css is setup correctly
        $this->setupCss();

        return $activeExtension;
    }

    protected function setupCss(): void
    {
        $cssPath = base_path('resources/css/app.css');
        if (!File::exists(dirname($cssPath))) {
            File::makeDirectory(dirname($cssPath), 0755, true);
        }
        
        $content = File::exists($cssPath) ? File::get($cssPath) : '';
        if (!str_contains($content, '@tailwind base')) {
             $tailwindDirectives = "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n\n";
             File::put($cssPath, $tailwindDirectives . $content);
        }
    }

    protected function setupVite(string $activeExtension = 'js'): void
    {
        $path = base_path('vite.config.js');
        if (!File::exists($path)) return;

        $content = File::get($path);
        
        // Update entry point to match the possibly renamed file (app.js -> app.tsx)
        $content = preg_replace("/['\"]resources\/js\/app\..*?['\"]/", "'resources/js/app.{$activeExtension}'", $content);

        if (str_contains($content, "'@domain'")) {
            $this->components->twoColumnDetail('Vite Aliases (@domain)', '<fg=yellow;options=bold>EXISTS</>');
            return;
        }

        $aliasAddition = "alias: {\n                '@': '/resources/js',\n                '@domain': '/app/Domain',\n                '@support': '/app/Support',\n                '@app': '/app/App',\n            },";

        if (str_contains($content, 'resolve: {')) {
            $content = str_replace('resolve: {', "resolve: {\n            {$aliasAddition}", $content);
        } else {
            $content = preg_replace('/plugins: \[.*?\],/s', "$0\n        resolve: {\n            {$aliasAddition}\n        },", $content);
        }

        File::put($path, $content);
        $this->components->twoColumnDetail('Vite Aliases (@domain)', '<fg=green;options=bold>INSTALLED</>');
    }

    protected function setupJsConfig(): void
    {
        $path = File::exists(base_path('tsconfig.json')) ? base_path('tsconfig.json') : base_path('jsconfig.json');
        $isNew = !File::exists($path);

        $config = $isNew ? ['compilerOptions' => ['baseUrl' => '.', 'paths' => []]] : json_decode(File::get($path), true);
        if (!isset($config['compilerOptions']['paths'])) $config['compilerOptions']['paths'] = [];

        // Add path aliases
        $config['compilerOptions']['paths']['@/*'] = ['resources/js/*'];
        $config['compilerOptions']['paths']['@domain/*'] = ['app/Domain/*'];
        $config['compilerOptions']['paths']['@support/*'] = ['app/Support/*'];
        $config['compilerOptions']['paths']['@app/*'] = ['app/App/*'];

        // Ensure JSX support is enabled for React
        if (!isset($config['compilerOptions']['jsx'])) {
            $config['compilerOptions']['jsx'] = 'react-jsx';
        }

        // Add Ziggy types for route() function
        if (!isset($config['compilerOptions']['types'])) {
            $config['compilerOptions']['types'] = [];
        }
        if (!in_array('@types/ziggy-js', $config['compilerOptions']['types'])) {
            $config['compilerOptions']['types'][] = '@types/ziggy-js';
        }

        File::put($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $label = basename($path);
        $this->components->twoColumnDetail("IDE Paths ({$label})", "<fg=green;options=bold>" . ($isNew ? 'CREATED' : 'UPDATED') . "</>");
    }

    protected function setupTailwind(string $mode): void
    {
        $path = base_path('tailwind.config.js');
        if (!File::exists($path)) return;

        $content = File::get($path);
        
        // Ensure Domain folders are scanned for Tailwind classes
        // Use regex to avoid duplicating if already there with slight variations
        if (!str_contains($content, './app/Domain')) {
            $extensionPattern = $mode === 'vue' ? 'vue' : 'js,ts,jsx,tsx';
            $domainContent = "'./app/Domain/**/*.{{$extensionPattern}}', './app/Support/**/*.{{$extensionPattern}}', ";
            
            // Inject into content array
            $content = preg_replace('/content: \s*\[/', "content: [\n        {$domainContent}", $content);
            
            File::put($path, $content);
            $this->components->twoColumnDetail('Tailwind Content Paths', '<fg=green;options=bold>UPDATED</>');
        }
    }

    protected function setupDependencies(string $mode, string $auth, bool $tenancy = false): void
    {
        $composerPackages = [];
        $npmPackages = [];
        $npmDevPackages = [];

        // Core DDD dependencies
        if (in_array($mode, ['react', 'vue'])) {
            $composerPackages[] = 'inertiajs/inertia-laravel';
            $composerPackages[] = 'tightenco/ziggy';

            if ($mode === 'react') {
                $npmPackages[] = '@inertiajs/react';
                $npmPackages[] = 'react';
                $npmPackages[] = 'react-dom';
                $npmDevPackages[] = '@vitejs/plugin-react';
                $npmDevPackages[] = '@types/react';
                $npmDevPackages[] = '@types/react-dom';
                $npmDevPackages[] = '@types/ziggy-js';

                // shadcn/ui dependencies
                $npmPackages[] = 'class-variance-authority';
                $npmPackages[] = 'clsx';
                $npmPackages[] = 'tailwind-merge';
                $npmPackages[] = 'lucide-react';
                $npmPackages[] = '@radix-ui/react-slot';
                $npmPackages[] = '@radix-ui/react-label';
                $npmPackages[] = '@radix-ui/react-checkbox';
                $npmPackages[] = '@radix-ui/react-dropdown-menu';
                $npmPackages[] = '@radix-ui/react-avatar';
                $npmPackages[] = '@radix-ui/react-alert-dialog';
                $npmDevPackages[] = 'tailwindcss-animate';
            } else {
                $npmPackages[] = '@inertiajs/vue3';
                $npmPackages[] = 'vue';
                $npmDevPackages[] = '@vitejs/plugin-vue';

                // shadcn-vue dependencies
                $npmPackages[] = 'radix-vue';
                $npmPackages[] = 'class-variance-authority';
                $npmPackages[] = 'clsx';
                $npmPackages[] = 'tailwind-merge';
                $npmPackages[] = 'lucide-vue-next';
                $npmDevPackages[] = 'tailwindcss-animate';
            }
        }

        if ($mode === 'livewire') {
            $composerPackages[] = 'livewire/livewire';
            $composerPackages[] = 'livewire/flux';
        }

        // Multi-tenancy
        if ($tenancy) {
            $composerPackages[] = 'stancl/tenancy';
        }

        // Auth Integration
        if (str_contains($auth, 'Sanctum')) {
            $composerPackages[] = 'laravel/sanctum';
        } elseif (str_contains($auth, 'Passport')) {
            $composerPackages[] = 'laravel/passport';
        }

        // Spatie Permissions
        if ($this->confirm('Install Spatie Laravel Permission?', true)) {
            $composerPackages[] = 'spatie/laravel-permission';
        }

        if (!empty($composerPackages) && $this->confirm('Install PHP dependencies (Composer)?', true)) {
            $this->info('Installing: ' . implode(', ', $composerPackages));
            $packages = implode(' ', $composerPackages);
            exec("COMPOSER_NO_INTERACTION=1 composer require {$packages} --quiet");
        }

        $allNpmPackages = array_merge($npmPackages, $npmDevPackages);
        if (!empty($allNpmPackages) && $this->confirm('Install JS dependencies (NPM)?', true)) {
            if (!empty($npmPackages)) {
                $this->info('Installing production packages: ' . implode(', ', $npmPackages));
                exec("npm install " . implode(' ', $npmPackages) . " --save");
            }

            if (!empty($npmDevPackages)) {
                $this->info('Installing dev packages: ' . implode(', ', $npmDevPackages));
                exec("npm install " . implode(' ', $npmDevPackages) . " --save-dev");
            }

            // Setup shadcn utilities
            if (in_array($mode, ['react', 'vue'])) {
                $this->setupShadcnUtils($mode);
            }

            $this->info('Building frontend assets (Vite)...');
            exec("npm run build");
        }

    }

    protected function setupShadcnUtils(string $mode): void
    {
        $utilsDir = base_path('resources/js/lib');
        if (!File::isDirectory($utilsDir)) {
            File::makeDirectory($utilsDir, 0755, true);
        }

        // Create cn utility function (used by all shadcn components)
        $utilsContent = $mode === 'react'
            ? "import { type ClassValue, clsx } from 'clsx';\nimport { twMerge } from 'tailwind-merge';\n\nexport function cn(...inputs: ClassValue[]) {\n  return twMerge(clsx(inputs));\n}\n"
            : "import { type ClassValue, clsx } from 'clsx';\nimport { twMerge } from 'tailwind-merge';\n\nexport function cn(...inputs: ClassValue[]) {\n  return twMerge(clsx(inputs));\n}\n";

        File::put($utilsDir . '/utils.ts', $utilsContent);

        // Create Ziggy types file for route() function
        $typesDir = base_path('resources/js/types');
        if (!File::isDirectory($typesDir)) {
            File::makeDirectory($typesDir, 0755, true);
        }

        $ziggyTypesContent = <<<'TS'
import { route as ziggyRoute } from 'ziggy-js';

declare global {
    function route(name: string, params?: Record<string, any>, absolute?: boolean): string;
    function route(): {
        current: (name?: string, params?: Record<string, any>) => boolean | string;
        params: Record<string, any>;
    };
}

export {};
TS;
        File::put($typesDir . '/ziggy.d.ts', $ziggyTypesContent);

        // Create components/ui directory
        $componentsDir = base_path('resources/js/components/ui');
        if (!File::isDirectory($componentsDir)) {
            File::makeDirectory($componentsDir, 0755, true);
        }

        // Generate shadcn component stubs based on mode
        if ($mode === 'react') {
            $this->generateShadcnReactComponents($componentsDir);
        } else {
            $this->generateShadcnVueComponents($componentsDir);
        }

        $this->components->twoColumnDetail('shadcn/ui utilities', '<fg=green;options=bold>INSTALLED</>');
    }

    protected function generateShadcnReactComponents(string $dir): void
    {
        // Button component
        $buttonContent = <<<'TSX'
import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary/90',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-10 px-4 py-2',
        sm: 'h-9 rounded-md px-3',
        lg: 'h-11 rounded-md px-8',
        icon: 'h-10 w-10',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return (
      <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />
    );
  }
);
Button.displayName = 'Button';

export { Button, buttonVariants };
TSX;
        File::put($dir . '/button.tsx', $buttonContent);

        // Input component
        $inputContent = <<<'TSX'
import * as React from 'react';
import { cn } from '@/lib/utils';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(
          'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
          className
        )}
        ref={ref}
        {...props}
      />
    );
  }
);
Input.displayName = 'Input';

export { Input };
TSX;
        File::put($dir . '/input.tsx', $inputContent);

        // Label component
        $labelContent = <<<'TSX'
import * as React from 'react';
import * as LabelPrimitive from '@radix-ui/react-label';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const labelVariants = cva(
  'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70'
);

const Label = React.forwardRef<
  React.ElementRef<typeof LabelPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root> & VariantProps<typeof labelVariants>
>(({ className, ...props }, ref) => (
  <LabelPrimitive.Root ref={ref} className={cn(labelVariants(), className)} {...props} />
));
Label.displayName = LabelPrimitive.Root.displayName;

export { Label };
TSX;
        File::put($dir . '/label.tsx', $labelContent);

        // Checkbox component
        $checkboxContent = <<<'TSX'
import * as React from 'react';
import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const Checkbox = React.forwardRef<
  React.ElementRef<typeof CheckboxPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof CheckboxPrimitive.Root>
>(({ className, ...props }, ref) => (
  <CheckboxPrimitive.Root
    ref={ref}
    className={cn(
      'peer h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
      className
    )}
    {...props}
  >
    <CheckboxPrimitive.Indicator className={cn('flex items-center justify-center text-current')}>
      <Check className="h-4 w-4" />
    </CheckboxPrimitive.Indicator>
  </CheckboxPrimitive.Root>
));
Checkbox.displayName = CheckboxPrimitive.Root.displayName;

export { Checkbox };
TSX;
        File::put($dir . '/checkbox.tsx', $checkboxContent);

        // Card component
        $cardContent = <<<'TSX'
import * as React from 'react';
import { cn } from '@/lib/utils';

const Card = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn('rounded-lg border bg-card text-card-foreground shadow-sm', className)}
      {...props}
    />
  )
);
Card.displayName = 'Card';

const CardHeader = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn('flex flex-col space-y-1.5 p-6', className)} {...props} />
  )
);
CardHeader.displayName = 'CardHeader';

const CardTitle = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLHeadingElement>>(
  ({ className, ...props }, ref) => (
    <h3 ref={ref} className={cn('text-2xl font-semibold leading-none tracking-tight', className)} {...props} />
  )
);
CardTitle.displayName = 'CardTitle';

const CardDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
  ({ className, ...props }, ref) => (
    <p ref={ref} className={cn('text-sm text-muted-foreground', className)} {...props} />
  )
);
CardDescription.displayName = 'CardDescription';

const CardContent = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn('p-6 pt-0', className)} {...props} />
  )
);
CardContent.displayName = 'CardContent';

const CardFooter = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn('flex items-center p-6 pt-0', className)} {...props} />
  )
);
CardFooter.displayName = 'CardFooter';

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent };
TSX;
        File::put($dir . '/card.tsx', $cardContent);

        // Alert component
        $alertContent = <<<'TSX'
import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const alertVariants = cva(
  'relative w-full rounded-lg border p-4 [&>svg~*]:pl-7 [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground',
  {
    variants: {
      variant: {
        default: 'bg-background text-foreground',
        destructive: 'border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

const Alert = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants>
>(({ className, variant, ...props }, ref) => (
  <div ref={ref} role="alert" className={cn(alertVariants({ variant }), className)} {...props} />
));
Alert.displayName = 'Alert';

const AlertTitle = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLHeadingElement>>(
  ({ className, ...props }, ref) => (
    <h5 ref={ref} className={cn('mb-1 font-medium leading-none tracking-tight', className)} {...props} />
  )
);
AlertTitle.displayName = 'AlertTitle';

const AlertDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn('text-sm [&_p]:leading-relaxed', className)} {...props} />
  )
);
AlertDescription.displayName = 'AlertDescription';

export { Alert, AlertTitle, AlertDescription };
TSX;
        File::put($dir . '/alert.tsx', $alertContent);

        // Badge component
        $badgeContent = <<<'TSX'
import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
        secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
        outline: 'text-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

export interface BadgeProps extends React.HTMLAttributes<HTMLDivElement>, VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
TSX;
        File::put($dir . '/badge.tsx', $badgeContent);

        // Avatar component
        $avatarContent = <<<'TSX'
import * as React from 'react';
import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cn } from '@/lib/utils';

const Avatar = React.forwardRef<
  React.ElementRef<typeof AvatarPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Root>
>(({ className, ...props }, ref) => (
  <AvatarPrimitive.Root
    ref={ref}
    className={cn('relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full', className)}
    {...props}
  />
));
Avatar.displayName = AvatarPrimitive.Root.displayName;

const AvatarImage = React.forwardRef<
  React.ElementRef<typeof AvatarPrimitive.Image>,
  React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Image>
>(({ className, ...props }, ref) => (
  <AvatarPrimitive.Image ref={ref} className={cn('aspect-square h-full w-full', className)} {...props} />
));
AvatarImage.displayName = AvatarPrimitive.Image.displayName;

const AvatarFallback = React.forwardRef<
  React.ElementRef<typeof AvatarPrimitive.Fallback>,
  React.ComponentPropsWithoutRef<typeof AvatarPrimitive.Fallback>
>(({ className, ...props }, ref) => (
  <AvatarPrimitive.Fallback
    ref={ref}
    className={cn('flex h-full w-full items-center justify-center rounded-full bg-muted', className)}
    {...props}
  />
));
AvatarFallback.displayName = AvatarPrimitive.Fallback.displayName;

export { Avatar, AvatarImage, AvatarFallback };
TSX;
        File::put($dir . '/avatar.tsx', $avatarContent);

        // Dropdown Menu component
        $dropdownMenuContent = <<<'TSX'
import * as React from 'react';
import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { Check, ChevronRight, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

const DropdownMenu = DropdownMenuPrimitive.Root;
const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;
const DropdownMenuGroup = DropdownMenuPrimitive.Group;
const DropdownMenuPortal = DropdownMenuPrimitive.Portal;
const DropdownMenuSub = DropdownMenuPrimitive.Sub;
const DropdownMenuRadioGroup = DropdownMenuPrimitive.RadioGroup;

const DropdownMenuSubTrigger = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.SubTrigger>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.SubTrigger> & {
    inset?: boolean;
  }
>(({ className, inset, children, ...props }, ref) => (
  <DropdownMenuPrimitive.SubTrigger
    ref={ref}
    className={cn(
      'flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent data-[state=open]:bg-accent',
      inset && 'pl-8',
      className
    )}
    {...props}
  >
    {children}
    <ChevronRight className="ml-auto h-4 w-4" />
  </DropdownMenuPrimitive.SubTrigger>
));
DropdownMenuSubTrigger.displayName = DropdownMenuPrimitive.SubTrigger.displayName;

const DropdownMenuSubContent = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.SubContent>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.SubContent>
>(({ className, ...props }, ref) => (
  <DropdownMenuPrimitive.SubContent
    ref={ref}
    className={cn(
      'z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
      className
    )}
    {...props}
  />
));
DropdownMenuSubContent.displayName = DropdownMenuPrimitive.SubContent.displayName;

const DropdownMenuContent = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.Content>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Content>
>(({ className, sideOffset = 4, ...props }, ref) => (
  <DropdownMenuPrimitive.Portal>
    <DropdownMenuPrimitive.Content
      ref={ref}
      sideOffset={sideOffset}
      className={cn(
        'z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
        className
      )}
      {...props}
    />
  </DropdownMenuPrimitive.Portal>
));
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName;

const DropdownMenuItem = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.Item>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Item> & {
    inset?: boolean;
  }
>(({ className, inset, ...props }, ref) => (
  <DropdownMenuPrimitive.Item
    ref={ref}
    className={cn(
      'relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      inset && 'pl-8',
      className
    )}
    {...props}
  />
));
DropdownMenuItem.displayName = DropdownMenuPrimitive.Item.displayName;

const DropdownMenuCheckboxItem = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.CheckboxItem>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.CheckboxItem>
>(({ className, children, checked, ...props }, ref) => (
  <DropdownMenuPrimitive.CheckboxItem
    ref={ref}
    className={cn(
      'relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      className
    )}
    checked={checked}
    {...props}
  >
    <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
      <DropdownMenuPrimitive.ItemIndicator>
        <Check className="h-4 w-4" />
      </DropdownMenuPrimitive.ItemIndicator>
    </span>
    {children}
  </DropdownMenuPrimitive.CheckboxItem>
));
DropdownMenuCheckboxItem.displayName = DropdownMenuPrimitive.CheckboxItem.displayName;

const DropdownMenuRadioItem = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.RadioItem>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.RadioItem>
>(({ className, children, ...props }, ref) => (
  <DropdownMenuPrimitive.RadioItem
    ref={ref}
    className={cn(
      'relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      className
    )}
    {...props}
  >
    <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
      <DropdownMenuPrimitive.ItemIndicator>
        <Circle className="h-2 w-2 fill-current" />
      </DropdownMenuPrimitive.ItemIndicator>
    </span>
    {children}
  </DropdownMenuPrimitive.RadioItem>
));
DropdownMenuRadioItem.displayName = DropdownMenuPrimitive.RadioItem.displayName;

const DropdownMenuLabel = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.Label>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Label> & {
    inset?: boolean;
  }
>(({ className, inset, ...props }, ref) => (
  <DropdownMenuPrimitive.Label
    ref={ref}
    className={cn('px-2 py-1.5 text-sm font-semibold', inset && 'pl-8', className)}
    {...props}
  />
));
DropdownMenuLabel.displayName = DropdownMenuPrimitive.Label.displayName;

const DropdownMenuSeparator = React.forwardRef<
  React.ElementRef<typeof DropdownMenuPrimitive.Separator>,
  React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Separator>
>(({ className, ...props }, ref) => (
  <DropdownMenuPrimitive.Separator
    ref={ref}
    className={cn('-mx-1 my-1 h-px bg-muted', className)}
    {...props}
  />
));
DropdownMenuSeparator.displayName = DropdownMenuPrimitive.Separator.displayName;

const DropdownMenuShortcut = ({ className, ...props }: React.HTMLAttributes<HTMLSpanElement>) => {
  return <span className={cn('ml-auto text-xs tracking-widest opacity-60', className)} {...props} />;
};
DropdownMenuShortcut.displayName = 'DropdownMenuShortcut';

export {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuCheckboxItem,
  DropdownMenuRadioItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuGroup,
  DropdownMenuPortal,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuRadioGroup,
};
TSX;
        File::put($dir . '/dropdown-menu.tsx', $dropdownMenuContent);
    }

    protected function generateShadcnVueComponents(string $dir): void
    {
        // Button component for Vue
        $buttonContent = <<<'VUE'
<script setup lang="ts">
import { type HTMLAttributes, computed } from 'vue';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import { type VariantProps, cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary/90',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-10 px-4 py-2',
        sm: 'h-9 rounded-md px-3',
        lg: 'h-11 rounded-md px-8',
        icon: 'h-10 w-10',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
);

type ButtonVariants = VariantProps<typeof buttonVariants>;

interface Props extends PrimitiveProps {
  variant?: ButtonVariants['variant'];
  size?: ButtonVariants['size'];
  class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
  as: 'button',
});

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});
</script>

<template>
  <Primitive v-bind="delegatedProps" :class="cn(buttonVariants({ variant, size }), props.class)">
    <slot />
  </Primitive>
</template>
VUE;
        File::put($dir . '/Button.vue', $buttonContent);

        // Input component for Vue
        $inputContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<{
  defaultValue?: string | number;
  modelValue?: string | number;
  class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
});
</script>

<template>
  <input
    v-model="modelValue"
    :class="cn('flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50', props.class)"
  />
</template>
VUE;
        File::put($dir . '/Input.vue', $inputContent);

        // Label component for Vue
        $labelContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Label as LabelPrimitive } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
  for?: string;
}>();
</script>

<template>
  <LabelPrimitive
    :class="cn('text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70', props.class)"
    :for="props.for"
  >
    <slot />
  </LabelPrimitive>
</template>
VUE;
        File::put($dir . '/Label.vue', $labelContent);

        // Checkbox component for Vue
        $checkboxContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { CheckboxIndicator, CheckboxRoot, type CheckboxRootEmits, type CheckboxRootProps } from 'radix-vue';
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<CheckboxRootEmits>();
</script>

<template>
  <CheckboxRoot
    v-bind="props"
    :class="cn('peer h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground', props.class)"
    @update:checked="emits('update:checked', $event)"
  >
    <CheckboxIndicator class="flex items-center justify-center text-current">
      <Check class="h-4 w-4" />
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
VUE;
        File::put($dir . '/Checkbox.vue', $checkboxContent);

        // Card component for Vue
        $cardContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <div :class="cn('rounded-lg border bg-card text-card-foreground shadow-sm', props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/Card.vue', $cardContent);

        $cardHeaderContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <div :class="cn('flex flex-col space-y-1.5 p-6', props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/CardHeader.vue', $cardHeaderContent);

        $cardTitleContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <h3 :class="cn('text-2xl font-semibold leading-none tracking-tight', props.class)">
    <slot />
  </h3>
</template>
VUE;
        File::put($dir . '/CardTitle.vue', $cardTitleContent);

        $cardDescriptionContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <p :class="cn('text-sm text-muted-foreground', props.class)">
    <slot />
  </p>
</template>
VUE;
        File::put($dir . '/CardDescription.vue', $cardDescriptionContent);

        $cardContentContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <div :class="cn('p-6 pt-0', props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/CardContent.vue', $cardContentContent);

        // Alert component for Vue
        $alertContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { type VariantProps, cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const alertVariants = cva(
  'relative w-full rounded-lg border p-4 [&>svg~*]:pl-7 [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground',
  {
    variants: {
      variant: {
        default: 'bg-background text-foreground',
        destructive: 'border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

type AlertVariants = VariantProps<typeof alertVariants>;

const props = defineProps<{
  variant?: AlertVariants['variant'];
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <div role="alert" :class="cn(alertVariants({ variant }), props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/Alert.vue', $alertContent);

        $alertDescriptionContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <div :class="cn('text-sm [&_p]:leading-relaxed', props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/AlertDescription.vue', $alertDescriptionContent);

        // Badge component for Vue
        $badgeContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { type VariantProps, cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
        secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
        outline: 'text-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

type BadgeVariants = VariantProps<typeof badgeVariants>;

const props = defineProps<{
  variant?: BadgeVariants['variant'];
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <div :class="cn(badgeVariants({ variant }), props.class)">
    <slot />
  </div>
</template>
VUE;
        File::put($dir . '/Badge.vue', $badgeContent);

        // Avatar component for Vue
        $avatarContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { AvatarRoot } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <AvatarRoot :class="cn('relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full', props.class)">
    <slot />
  </AvatarRoot>
</template>
VUE;
        File::put($dir . '/Avatar.vue', $avatarContent);

        $avatarFallbackContent = <<<'VUE'
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { AvatarFallback } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
  <AvatarFallback :class="cn('flex h-full w-full items-center justify-center rounded-full bg-muted', props.class)">
    <slot />
  </AvatarFallback>
</template>
VUE;
        File::put($dir . '/AvatarFallback.vue', $avatarFallbackContent);
    }

    protected function saveConfiguration(string $auth, string $mode, bool $tenancy = false): void
    {
        $configPath = base_path('config/domion.php');
        
        $authValue = strtolower($auth);
        $modeValue = strtolower($mode);

        $content = "<?php\n\nreturn [\n    'auth' => '{$authValue}',\n    'mode' => '{$modeValue}',\n    'tenancy' => " . ($tenancy ? 'true' : 'false') . ",\n    'paths' => [\n        'apps' => 'app/App',\n        'domains' => 'app/Domain',\n        'support' => 'app/Support',\n    ]\n];\n";

        File::put($configPath, $content);
        $this->components->twoColumnDetail('Config File (config/domion.php)', '<fg=green;options=bold>CREATED</>');
    }

    protected function setupFolders(bool $tenancy = false): void
    {
        $folders = [
            'app/App', 
            'app/App/Providers',
            'app/Domain', 
            'app/Support',
        ];

        if ($tenancy) {
            $folders[] = 'app/Domain/Central';
            $folders[] = 'app/Domain/Tenant';
        }

        // Shared resources go to Support
        $folders[] = 'app/Support/Resources/views';

        foreach ($folders as $folder) {
            if (!File::isDirectory(base_path($folder))) {
                File::makeDirectory(base_path($folder), 0755, true);
            }
        }
    }

    protected function cleanupLaravelDefaults(): void
    {
        // 1. Delete Models and Http (Legacy)
        if (File::isDirectory(base_path('app/Models'))) {
            File::deleteDirectory(base_path('app/Models'));
        }
        if (File::isDirectory(base_path('app/Http'))) {
            File::deleteDirectory(base_path('app/Http'));
        }
        
        // Clean up Shared domain if it exists at root (we moved it to Support)
        if (File::isDirectory(base_path('app/Domain/Shared'))) {
            File::deleteDirectory(base_path('app/Domain/Shared'));
        }

        if (File::isDirectory(base_path('app/Http/Controllers'))) {
            // Keep the folder but remove default files if needed, 
            // actually we relocated the logic to Domains
        }

        $filesToDelete = [
            app_path('Http/Controllers/Controller.php'),
            base_path('resources/views/welcome.blade.php'),
            base_path('resources/views/app.blade.php'),
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) File::delete($file);
        }

        // 2. Move Providers to app/App/Providers and fix namespaces
        if (File::isDirectory(base_path('app/Providers'))) {
            if (!File::isDirectory(base_path('app/App/Providers'))) {
                File::makeDirectory(base_path('app/App/Providers'), 0755, true);
            }
            
            $files = File::files(base_path('app/Providers'));
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
                
                $targetFile = base_path('app/App/Providers/' . $file->getFilename());
                File::put($targetFile, $content);
            }
            File::deleteDirectory(base_path('app/Providers'));
        }
    }

    protected function setupRoutes(): void
    {
        // 1. API Discovery
        $apiPath = base_path('routes/api.php');
        if (File::exists($apiPath)) {
            $content = File::get($apiPath);
            if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                $newContent = "<?php\n\nuse Samushi\Domion\Helpers\DomainHelpers;\n\nDomainHelpers::loadDomainRoutes();\n";
                File::put($apiPath, $newContent);
            }
        }

        // 2. Web / Landing Support
        $webPath = base_path('routes/web.php');
        if (File::exists($webPath)) {
            $content = File::get($webPath);
            
            // Comment out or replace the default welcome view to avoid "View not found" errors
            $content = preg_replace("/return\s+view\(\s*['\"]welcome['\"]\s*\);/", "return redirect('/login');", $content);
            
            // Add Domain discovery to web.php as well
            if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                $content .= "\n\nuse Samushi\Domion\Helpers\DomainHelpers;\nDomainHelpers::loadDomainRoutes();\n";
            }
            File::put($webPath, $content);
        }
    }

    protected function setupBootstrap(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');
        $bootstrapProviders = base_path('bootstrap/providers.php');

        // 1. Handle app.php (Application Core)
        if (File::exists($bootstrapApp)) {
            $content = File::get($bootstrapApp);

            if (!str_contains($content, '->useAppPath')) {
                // Fluent chaining after create()
                $pattern = '/->create\(\);/s';
                $replacement = "->create()\n        ->useAppPath(realpath(__DIR__.'/../app/App'));";
                
                if (preg_match($pattern, $content)) {
                    $newContent = preg_replace($pattern, $replacement, $content);
                    File::put($bootstrapApp, $newContent);
                }
            }
        }
        
        // 2. Handle providers.php (Laravel 11+)
        if (File::exists($bootstrapProviders)) {
            $content = File::get($bootstrapProviders);
            
            // Fix relocated providers namespaces
            $content = str_replace('App\Providers\\', 'App\App\Providers\\', $content);
            
            // Register DomionServiceProvider if not present
            // Note: Domain service providers are auto-registered by DomionServiceProvider::registerDomainProviders()
            if (!str_contains($content, 'DomionServiceProvider::class')) {
                $providerEntry = "    Samushi\Domion\Providers\DomionServiceProvider::class,\n];";
                $content = str_replace('];', $providerEntry, $content);
            }

            File::put($bootstrapProviders, $content);
        }
    }

    protected function setupComposer(): void
    {
        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);

            $composer['autoload']['psr-4']['App\\'] = 'app/App/';
            $composer['autoload']['psr-4']['App\\Domain\\'] = 'app/Domain/';
            $composer['autoload']['psr-4']['App\\Support\\'] = 'app/Support/';

            File::put(
                $composerPath, 
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
        }
    }

    protected function installStarterKit(string $mode): void
    {
        // 1. Setup Domains & Support
        $this->createDomainStructure('User', $mode);
        $this->createDomainStructure('Auth', $mode);

        // Update auth.php to use Domain User model
        $this->updateAuthConfig();

        // Ensure Support has view directory
        File::ensureDirectoryExists(base_path('app/Support/Resources/views'));

        // Ensure DTO folder exists
        File::ensureDirectoryExists(base_path('app/Domain/Auth/Dto'));

        $baseController = match ($mode) {
            'react', 'vue' => 'InertiaControllers',
            'api' => 'ApiControllers',
            default => 'WebControllers'
        };

        // 2. Relocate User Migration
        $this->relocateUserMigrations();

        // 3. Create DTO
        $this->generateFromStub('LoginDto', base_path('app/Domain/Auth/Dto/LoginDto.php'), [
            'namespace' => 'App\Domain\Auth\Dto',
        ]);

        // 4. Create User Repository
        $this->generateFromStub('UserRepository', base_path('app/Domain/User/Repository/UserRepository.php'), [
            'namespace' => 'App\Domain\User\Repository',
            'modelNamespace' => 'App\Domain\User\Models',
        ]);

        // 5. Create Auth Actions
        $this->generateFromStub('LoginAction', base_path('app/Domain/Auth/Actions/LoginAction.php'), [
            'namespace' => 'App\Domain\Auth\Actions',
            'dtoNamespace' => 'App\Domain\Auth\Dto',
            'repositoryNamespace' => 'App\Domain\User\Repository',
        ]);

        $this->generateFromStub('LogoutAction', base_path('app/Domain/Auth/Actions/LogoutAction.php'), [
            'namespace' => 'App\Domain\Auth\Actions',
        ]);

        // 6. Create Requests
        $this->generateFromStub('LoginRequest', base_path('app/Domain/Auth/Requests/LoginRequest.php'), [
            'namespace' => 'App\Domain\Auth\Requests',
            'class' => 'LoginRequest'
        ]);

        if ($mode === 'api') {
            $this->generateFromStub('RefreshTokenAction', base_path('app/Domain/Auth/Actions/RefreshTokenAction.php'), [
                'namespace' => 'App\Domain\Auth\Actions'
            ]);
        }

        // 7. Create User Model
        $this->generateFromStub('UserModel', base_path('app/Domain/User/Models/User.php'), [
            'namespace' => 'App\Domain\User\Models'
        ]);

        // 8. Create Unified AuthController
        $this->generateFromStub('AuthController', base_path('app/Domain/Auth/Controllers/AuthController.php'), [
            'namespace' => 'App\Domain\Auth\Controllers',
            'baseController' => $baseController,
            'actionsNamespace' => 'App\Domain\Auth\Actions',
            'dtoNamespace' => 'App\Domain\Auth\Dto',
            'requestsNamespace' => 'App\Domain\Auth\Requests',
            'domain' => 'auth',
        ]);

        // 9. Create Auth Service Provider (for route configuration)
        $this->generateFromStub('AuthServiceProvider', base_path('app/Domain/Auth/Providers/AuthServiceProvider.php'), [
            'namespace' => 'App\Domain\Auth\Providers',
        ]);

        // 6. Create Seeders
        $this->generateFromStub('RolesAndPermissionsSeeder', base_path('app/Domain/User/Database/Seeders/RolesAndPermissionsSeeder.php'), [
            'namespace' => 'App\Domain\User\Database\Seeders',
            'class' => 'RolesAndPermissionsSeeder'
        ]);

        $this->generateFromStub('UserSeeder', base_path('app/Domain/User/Database/Seeders/UserSeeder.php'), [
            'namespace' => 'App\Domain\User\Database\Seeders',
            'class' => 'UserSeeder',
            'modelNamespace' => 'App\Domain\User\Models'
        ]);

        // Create Routes in Auth domain with full auth flow
        $routeContent = <<<'ROUTES'
<?php

use Illuminate\Support\Facades\Route;
use App\Domain\Auth\Controllers\AuthController;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

ROUTES;
        File::put(base_path('app/Domain/Auth/web.php'), $routeContent);

        // 7. Create Factory
        $this->generateFromStub('Factory', base_path('app/Domain/User/Database/Factories/UserFactory.php'), [
            'namespace' => 'App\Domain\User\Database\Factories',
            'modelNamespace' => 'App\Domain\User\Models',
            'model' => 'User',
            'class' => 'UserFactory'
        ]);

        // 10. Create Landing Domain & Pages
        $this->createDomainStructure('Landing', $mode);
        $this->generateFromStub('LandingController', base_path('app/Domain/Landing/Controllers/LandingController.php'), [
            'namespace' => 'App\Domain\Landing\Controllers',
            'baseController' => $baseController,
            'class' => 'LandingController',
            'domain' => 'landing',
            'view' => 'Landing'
        ]);

        // Create Landing Service Provider (for root route configuration)
        $this->generateFromStub('LandingServiceProvider', base_path('app/Domain/Landing/Providers/LandingServiceProvider.php'), [
            'namespace' => 'App\Domain\Landing\Providers',
        ]);

        // Create Landing routes
        $landingRoute = "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Domain\Landing\Controllers\LandingController;\n\nRoute::get('/', [LandingController::class, 'index'])->name('landing');\n";
        File::put(base_path('app/Domain/Landing/web.php'), $landingRoute);

        // 11. Create Dashboard Domain & Pages
        $this->createDomainStructure('Dashboard', $mode);
        $this->generateFromStub('DashboardController', base_path('app/Domain/Dashboard/Controllers/DashboardController.php'), [
            'namespace' => 'App\Domain\Dashboard\Controllers',
            'baseController' => $baseController,
            'class' => 'DashboardController',
            'domain' => 'dashboard',
            'view' => 'Dashboard'
        ]);

        // Create Dashboard Service Provider
        $this->generateFromStub('DashboardServiceProvider', base_path('app/Domain/Dashboard/Providers/DashboardServiceProvider.php'), [
            'namespace' => 'App\Domain\Dashboard\Providers',
        ]);

        $dashboardRoute = "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Domain\Dashboard\Controllers\DashboardController;\n\nRoute::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');\n";
        File::put(base_path('app/Domain/Dashboard/web.php'), $dashboardRoute);

        // Remove default web.php content
        $webPath = base_path('routes/web.php');
        if (File::exists($webPath)) {
             $content = "<?php\n\nuse Samushi\Domion\Helpers\DomainHelpers;\n\n// Default Laravel routes are replaced by Domain Routes\nDomainHelpers::loadDomainRoutes();\n";
             File::put($webPath, $content);
        }

        // Frontend Pages Generation
        $landingFrontendPath = base_path('app/Domain/Landing/Frontend/Pages');
        File::ensureDirectoryExists($landingFrontendPath);

        $dashboardFrontendPath = base_path('app/Domain/Dashboard/Frontend/Pages');
        File::ensureDirectoryExists($dashboardFrontendPath);

        $authFrontendPath = base_path('app/Domain/Auth/Frontend/Pages');
        File::ensureDirectoryExists($authFrontendPath);

        if ($mode === 'react') {
            // Landing Page
            $this->generateFromStub('ReactLanding', $landingFrontendPath . '/Landing.tsx');

            // Auth Pages (shadcn/ui)
            $this->generateFromStub('ReactLogin', $authFrontendPath . '/Login.tsx');
            $this->generateFromStub('ReactRegister', $authFrontendPath . '/Register.tsx');
            $this->generateFromStub('ReactForgotPassword', $authFrontendPath . '/ForgotPassword.tsx');
            $this->generateFromStub('ReactResetPassword', $authFrontendPath . '/ResetPassword.tsx');

            // Dashboard Page
            $this->generateFromStub('ReactDashboard', $dashboardFrontendPath . '/Dashboard.tsx');

        } elseif ($mode === 'vue') {
            // Landing Page
            $this->generateFromStub('VueLanding', $landingFrontendPath . '/Landing.vue');

            // Auth Pages (shadcn-vue)
            $this->generateFromStub('VueLogin', $authFrontendPath . '/Login.vue');
            $this->generateFromStub('VueRegister', $authFrontendPath . '/Register.vue');
            $this->generateFromStub('VueForgotPassword', $authFrontendPath . '/ForgotPassword.vue');
            $this->generateFromStub('VueResetPassword', $authFrontendPath . '/ResetPassword.vue');

            // Dashboard Page
            $this->generateFromStub('VueDashboard', $dashboardFrontendPath . '/Dashboard.vue');

        } elseif ($mode === 'livewire') {
            // Livewire with Flux UI
            $authViewPath = base_path('app/Domain/Auth/Resources/views/livewire');
            File::ensureDirectoryExists($authViewPath);

            $authComponentPath = base_path('app/Domain/Auth/Livewire');
            File::ensureDirectoryExists($authComponentPath);

            // Landing Page
            $landingViewPath = base_path('app/Domain/Landing/Resources/views/livewire');
            File::ensureDirectoryExists($landingViewPath);
            $landingComponentPath = base_path('app/Domain/Landing/Livewire');
            File::ensureDirectoryExists($landingComponentPath);
            $this->generateFromStub('LivewireLanding', $landingComponentPath . '/Landing.php');
            $this->generateFromStub('LivewireLandingView', $landingViewPath . '/landing.blade.php');

            // Auth Components & Views
            $this->generateFromStub('LivewireLogin', $authComponentPath . '/Login.php');
            $this->generateFromStub('LivewireLoginView', $authViewPath . '/login.blade.php');

            $this->generateFromStub('LivewireRegister', $authComponentPath . '/Register.php');
            $this->generateFromStub('LivewireRegisterView', $authViewPath . '/register.blade.php');

            $this->generateFromStub('LivewireForgotPassword', $authComponentPath . '/ForgotPassword.php');
            $this->generateFromStub('LivewireForgotPasswordView', $authViewPath . '/forgot-password.blade.php');

            $this->generateFromStub('LivewireResetPassword', $authComponentPath . '/ResetPassword.php');
            $this->generateFromStub('LivewireResetPasswordView', $authViewPath . '/reset-password.blade.php');

            // Dashboard
            $dashboardViewPath = base_path('app/Domain/Dashboard/Resources/views/livewire');
            File::ensureDirectoryExists($dashboardViewPath);
            $dashboardComponentPath = base_path('app/Domain/Dashboard/Livewire');
            File::ensureDirectoryExists($dashboardComponentPath);
            $this->generateFromStub('LivewireDashboard', $dashboardComponentPath . '/Dashboard.php');
            $this->generateFromStub('LivewireDashboardView', $dashboardViewPath . '/dashboard.blade.php');

        } else {
            // Blade with Tailwind
            $landingViewPath = base_path('app/Domain/Landing/Resources/views/pages');
            File::ensureDirectoryExists($landingViewPath);
            $this->generateFromStub('BladeLanding', $landingViewPath . '/landing.blade.php');

            $authViewPath = base_path('app/Domain/Auth/Resources/views/pages');
            File::ensureDirectoryExists($authViewPath);
            $this->generateFromStub('BladeLogin', $authViewPath . '/login.blade.php');
            $this->generateFromStub('BladeRegister', $authViewPath . '/register.blade.php');
            $this->generateFromStub('BladeForgotPassword', $authViewPath . '/forgot-password.blade.php');
            $this->generateFromStub('BladeResetPassword', $authViewPath . '/reset-password.blade.php');

            $dashboardViewPath = base_path('app/Domain/Dashboard/Resources/views/pages');
            File::ensureDirectoryExists($dashboardViewPath);
            $this->generateFromStub('BladeDashboard', $dashboardViewPath . '/dashboard.blade.php');
        }
    }

    protected function updateConfigMode(string $mode): void
    {
        $configPath = config_path('domion.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = preg_replace("/'mode' => '.*'/", "'mode' => '{$mode}'", $content);
            File::put($configPath, $content);
        }
    }

    protected function createDomainStructure(string $domain, string $mode = 'api'): void
    {
        $base = base_path("app/Domain/{$domain}");
        $folders = [
            $base . '/Actions',
            $base . '/Controllers',
            $base . '/Requests',
            $base . '/Models',
            $base . '/Repository',
            $base . '/Dto',
            $base . '/Providers',
            $base . '/Database/Migrations',
            $base . '/Database/Seeders',
            $base . '/Database/Factories',
        ];

        // Only create Frontend/Resources folders if strictly necessary
        if (in_array($mode, ['react', 'vue'])) {
            // Check if we are creating a fresh domain or updating
            // Ideally we only create these if the user asks, but for starter kit we force them
            // For generic domains, we might want to keep them empty if not used.
            // But to avoid "missing directory" errors during globbing, valid empty dirs are safer.
            $folders[] = $base . '/Frontend/Pages';
            $folders[] = $base . '/Frontend/Components';
            
            // Cleanup Resource folder if it was created by accident
            if (File::isDirectory($base . '/Resources')) {
                File::deleteDirectory($base . '/Resources');
            }
        } else {
            $folders[] = $base . '/Resources/views/pages';
            $folders[] = $base . '/Resources/views/components';
        }

        foreach ($folders as $folder) {
            if (!File::isDirectory($folder)) {
                File::makeDirectory($folder, 0755, true);
            }
        }
    }

    protected function updateAuthConfig(): void
    {
        $authConfigPath = config_path('auth.php');
        if (!File::exists($authConfigPath)) {
            return;
        }

        $content = File::get($authConfigPath);

        // Update User model path from App\Models\User to App\Domain\User\Models\User
        $content = str_replace(
            "App\\Models\\User::class",
            "App\\Domain\\User\\Models\\User::class",
            $content
        );

        // Also handle the escaped version
        $content = str_replace(
            "'model' => App\\Models\\User::class",
            "'model' => App\\Domain\\User\\Models\\User::class",
            $content
        );

        File::put($authConfigPath, $content);
        $this->components->twoColumnDetail('Auth Config (User Model)', '<fg=green;options=bold>UPDATED</>');
    }

    protected function relocateUserMigrations(): void
    {
        $migrationDir = base_path('database/migrations');
        $targetDir = base_path('app/Domain/User/Database/Migrations');

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        if (File::isDirectory($migrationDir)) {
            $files = File::files($migrationDir);
            foreach ($files as $file) {
                if (str_contains($file->getFilename(), 'create_users_table')) {
                    File::move($file->getRealPath(), $targetDir . '/' . $file->getFilename());
                    $this->info("Moved migration: " . $file->getFilename());
                }
            }
        }
    }

    protected function generateFromStub(string $stub, string $target, array $replacements = []): void
    {
        $stubPath = __DIR__ . '/../stubs/' . $stub . '.stub';
        
        if (!File::exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");
            return;
        }

        $content = File::get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        File::put($target, $content);
    }
}
