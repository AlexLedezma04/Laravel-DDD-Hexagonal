<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DDDStructure extends Command
{
    protected $signature = 'make:ddd
                            {context : The bounded context, e.g. admin, lms, job_request}
                            {entity  : The entity name, e.g. books}
                            {--force : Overwrite existing structure without confirmation}';

    protected $description = 'Creates Hexagonal + DDD folder structure for the given entity';

    private string $uri;
    private string $context;
    private string $entity;
    private string $contextStudly;
    private string $entityStudly;

    private array $directories = [
        'Domain/Entities',
        'Domain/Aggregates',
        'Domain/ValueObjects',
        'Domain/Events',
        'Domain/Contracts',

        'Application/UseCases',
        'Application/DTOs',
        'Application/Services',

        'Infrastructure/Persistence',
        'Infrastructure/Listeners',
        'Infrastructure/Jobs',

        'Interfaces/Http/Controllers',
        'Interfaces/Http/Requests',
        'Interfaces/Http/Resources',
        'Interfaces/Http/Routes',
    ];

    public function handle(): int
    {
        $this->context       = $this->argument('context');
        $this->entity        = $this->argument('entity');
        $this->contextStudly = Str::studly($this->context);
        $this->entityStudly  = Str::studly($this->entity);
        $this->uri           = base_path("src/{$this->context}/{$this->entity}");

        if (File::exists($this->uri) && ! $this->option('force')) {
            if (! $this->confirm("The context [{$this->context}/{$this->entity}] already exists. Overwrite?")) {
                $this->warn('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $this->info("Creating Hexagonal + DDD structure for [{$this->contextStudly}\\{$this->entityStudly}]");
        $this->newLine();

        $this->createDirectories();
        $this->createRouteFile();
        $this->createServiceProvider();
        $this->registerServiceProvider();
        $this->linkRoutesInMainFile();

        $this->newLine();
        $this->info("Structure [{$this->entityStudly}] created successfully inside context [{$this->contextStudly}].");

        return Command::SUCCESS;
    }

    private function createDirectories(): void
    {
        $this->line('  Creating directories:');

        foreach ($this->directories as $dir) {
            $fullPath = "{$this->uri}/{$dir}";
            File::ensureDirectoryExists($fullPath, 0755, true);
            File::put("{$fullPath}/.gitkeep", '');
            $this->line("    + {$dir}");
        }
    }

    private function createRouteFile(): void
    {
        $routeDir  = "{$this->uri}/Interfaces/Http/Routes";
        $routePath = "{$routeDir}/api.php";

        File::ensureDirectoryExists($routeDir, 0755, true);

        $content = <<<PHP
        <?php

        use Illuminate\Support\Facades\Route;

        PHP;

        File::delete("{$routeDir}/.gitkeep");
        File::put($routePath, $content);

        $this->newLine();
        $this->line('  Route stub created: Interfaces/Http/Routes/api.php');
    }

    private function createServiceProvider(): void
    {
        $providerPath = "{$this->uri}/{$this->entityStudly}ServiceProvider.php";
        $namespace    = "Src\\{$this->contextStudly}\\{$this->entityStudly}";

        $content = <<<PHP
        <?php

        namespace {$namespace};

        use Illuminate\Support\ServiceProvider;

        class {$this->entityStudly}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                \$this->loadRoutesFrom(__DIR__ . '/Interfaces/Http/Routes/api.php');
            }
        }
        PHP;

        File::ensureDirectoryExists($this->uri, 0755, true);
        File::put($providerPath, $content);

        $this->line("  ServiceProvider stub created: {$this->entityStudly}ServiceProvider.php");
    }

    private function registerServiceProvider(): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        $providerClass = "Src\\{$this->contextStudly}\\{$this->entityStudly}\\{$this->entityStudly}ServiceProvider";
        $providerLine  = "    {$providerClass}::class,";

        $content = File::get($providersPath);

        if (str_contains($content, $providerClass)) {
            $this->line('  ServiceProvider already registered in bootstrap/providers.php, skipping.');
            return;
        }

        $updated = preg_replace(
            '/^(\];)$/m',
            "{$providerLine}\n$1",
            $content
        );

        if ($updated === null || $updated === $content) {
            $this->warn("  Could not auto-register ServiceProvider. Add it manually to bootstrap/providers.php:");
            $this->warn("  {$providerLine}");
            return;
        }

        File::put($providersPath, $updated);
        $this->line("  ServiceProvider registered in bootstrap/providers.php");
    }

    private function linkRoutesInMainFile(): void
    {
        $mainRoutesPath = base_path('routes/api.php');

        if (! File::exists($mainRoutesPath)) {
            File::ensureDirectoryExists(base_path('routes'));
            File::put($mainRoutesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
            $this->line('  routes/api.php did not exist, created automatically.');
        }

        $routeLine         = "src/{$this->context}/{$this->entity}/Interfaces/Http/Routes/api.php";
        $mainRoutesContent = File::get($mainRoutesPath);

        if (str_contains($mainRoutesContent, $routeLine)) {
            $this->line('  Route already linked in routes/api.php, skipping.');
            return;
        }

        $prefix  = "{$this->context}_{$this->entity}";
        $snippet = "\nRoute::prefix('{$prefix}')->group(base_path('{$routeLine}'));\n";

        File::append($mainRoutesPath, $snippet);
        $this->line("  Module route linked in routes/api.php  (prefix: {$prefix})");
    }
}
