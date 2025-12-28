<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Samushi\Domion\Helpers\DomainHelpers;
use Exception;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'domion:make:migration',
    description: 'Generate a new migration inside a specific domain.',
)]
class MakeDomainMigration extends GeneratorCommand
{
    protected $type = 'Migration';

    public function handle(): int
    {
        try {
            $domain = $this->argument('domain');
            $name = $this->argument('name');

            if (empty($domain) || empty($name)) {
                $this->error('Domain and name arguments are required.');
                return ConsoleCommand::FAILURE;
            }

            $this->info("Creating migration '{$name}' for domain '{$domain}'...");

            $migrationPath = DomainHelpers::migrationPath($domain);
            File::ensureDirectoryExists($migrationPath);

            $timestamp = date('Y_m_d_His');
            $fileName = "{$timestamp}_{$name}.php";
            $filePath = "{$migrationPath}/{$fileName}";

            if (File::exists($filePath)) {
                $this->error("Migration file already exists: {$fileName}");
                return ConsoleCommand::FAILURE;
            }

            $table = $this->resolveTableName($name);
            $content = $this->getMigrationStub($table);

            File::put($filePath, $content);
            $this->info("✅ Migration created successfully: {$fileName}");

            return ConsoleCommand::SUCCESS;
        } catch (Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            return ConsoleCommand::FAILURE;
        }
    }

    protected function getMigrationStub(?string $table): string
    {
        if ($table) {
            return "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\Database\Migrations\Migration;\nuse Illuminate\Database\Schema\Blueprint;\nuse Illuminate\Support\Facades\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$table}', function (Blueprint \$table) {\n            \$table->uuid('id')->primary();\n            \$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$table}');\n    }\n};\n";
        }

        return "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\Database\Migrations\Migration;\nuse Illuminate\Database\Schema\Blueprint;\nuse Illuminate\Support\Facades\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        //\n    }\n\n    public function down(): void\n    {\n        //\n    }\n};\n";
    }

    protected function getStub(): string
    {
        return '';
    }

    protected function getArguments(): array
    {
        return [
            ['domain', InputArgument::REQUIRED, 'The domain name'],
            ['name', InputArgument::REQUIRED, 'The name of the migration'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['create', null, InputOption::VALUE_OPTIONAL, 'The table to be created'],
            ['table', null, InputOption::VALUE_OPTIONAL, 'The table to migrate'],
        ];
    }

    private function resolveTableName(string $name): ?string
    {
        if ($this->option('create')) {
            return $this->option('create');
        }

        if ($this->option('table')) {
            return $this->option('table');
        }

        if (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
