<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DDDStructure extends Command
{
    protected $signature = 'make:ddd
                            {context : Bounded Context, e.g. Sales, ProductCatalog, Inventory}
                            {entity? : Entity to scaffold inside an existing context (optional)}
                            {--force : Overwrite existing files without confirmation}';

    protected $description = 'Creates a Bounded Context with Hexagonal + DDD structure, or adds entity stubs to an existing one.';

    private string $uri;
    private string $contextStudly;
    private ?string $entityStudly = null;

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
        $this->contextStudly = Str::studly($this->argument('context'));
        $this->uri           = base_path("src/{$this->contextStudly}");

        $entityArg = $this->argument('entity');

        if ($entityArg) {
            $this->entityStudly = Str::studly($entityArg);
            return $this->scaffoldEntity();
        }

        return $this->scaffoldContext();
    }

    /*
    *  Create the full Bounded Context (hexagon)
    */

    private function scaffoldContext(): int
    {
        if (File::exists($this->uri) && ! $this->option('force')) {
            if (! $this->confirm("Bounded Context [{$this->contextStudly}] already exists. Overwrite?")) {
                $this->warn('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $this->info("Creating Bounded Context [{$this->contextStudly}]");
        $this->newLine();

        $this->createDirectories();
        $this->createRouteFile();
        $this->createServiceProvider();
        $this->registerServiceProvider();
        $this->linkRoutesInMainFile();

        $this->newLine();
        $this->info("  Bounded Context [{$this->contextStudly}] created.");
        $this->line("  Add entities with: php artisan make:ddd {$this->contextStudly} YourEntity");

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
        File::delete("{$routeDir}/.gitkeep");
        File::put($routePath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n// {$this->contextStudly} routes\n");

        $this->newLine();
        $this->line('  Route stub created: Interfaces/Http/Routes/api.php');
    }

    private function createServiceProvider(): void
    {
        $providerPath = "{$this->uri}/{$this->contextStudly}ServiceProvider.php";
        $namespace    = "Src\\{$this->contextStudly}";

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Illuminate\Support\ServiceProvider;

        class {$this->contextStudly}ServiceProvider extends ServiceProvider
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

        File::put($providerPath, $content);
        $this->line("  ServiceProvider created: {$this->contextStudly}ServiceProvider.php");
    }

    private function registerServiceProvider(): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        $providerClass = "Src\\{$this->contextStudly}\\{$this->contextStudly}ServiceProvider";
        $providerLine  = "    {$providerClass}::class,";
        $content       = File::get($providersPath);

        if (str_contains($content, $providerClass)) {
            $this->line('  ServiceProvider already registered, skipping.');
            return;
        }

        $updated = preg_replace('/^(\];)$/m', "{$providerLine}\n$1", $content);

        if ($updated === null || $updated === $content) {
            $this->warn("  Could not auto-register. Add manually to bootstrap/providers.php:");
            $this->warn("  {$providerLine}");
            return;
        }

        File::put($providersPath, $updated);
        $this->line('  ServiceProvider registered in bootstrap/providers.php');
    }

    private function linkRoutesInMainFile(): void
    {
        $mainRoutesPath = base_path('routes/api.php');

        if (! File::exists($mainRoutesPath)) {
            File::ensureDirectoryExists(base_path('routes'));
            File::put($mainRoutesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
            $this->line('  routes/api.php created automatically.');
        }

        $routeLine    = "src/{$this->contextStudly}/Interfaces/Http/Routes/api.php";
        $routesContent = File::get($mainRoutesPath);

        if (str_contains($routesContent, $routeLine)) {
            $this->line('  Route already linked in routes/api.php, skipping.');
            return;
        }

        $prefix  = Str::snake($this->contextStudly);
        $snippet = "\nRoute::prefix('{$prefix}')->group(base_path('{$routeLine}'));\n";

        File::append($mainRoutesPath, $snippet);
        $this->line("  Routes linked in routes/api.php  (prefix: /{$prefix})");
    }

    /*
    *  Add entity stubs inside an existing Bounded Context
    */

    private function scaffoldEntity(): int
    {
        if (! File::exists($this->uri)) {
            $this->error("Bounded Context [{$this->contextStudly}] does not exist.");
            $this->line("  Create it first with: php artisan make:ddd {$this->contextStudly}");
            return Command::FAILURE;
        }

        $this->info("Adding entity [{$this->entityStudly}] -> [{$this->contextStudly}]");
        $this->newLine();

        $namespace = "Src\\{$this->contextStudly}";

        $stubs = [
            "Domain/Entities/{$this->entityStudly}.php"
            => $this->stubEntity($namespace),

            "Domain/Contracts/{$this->entityStudly}RepositoryInterface.php"
            => $this->stubRepositoryInterface($namespace),

            "Application/DTOs/{$this->entityStudly}DTO.php"
            => $this->stubDTO($namespace),

            "Application/UseCases/Create{$this->entityStudly}UseCase.php"
            => $this->stubUseCase($namespace),

            "Infrastructure/Persistence/Eloquent{$this->entityStudly}Repository.php"
            => $this->stubRepository($namespace),

            "Interfaces/Http/Controllers/{$this->entityStudly}Controller.php"
            => $this->stubController($namespace),

            "Interfaces/Http/Requests/{$this->entityStudly}Request.php"
            => $this->stubRequest($namespace),

            "Interfaces/Http/Resources/{$this->entityStudly}Resource.php"
            => $this->stubResource($namespace),
        ];

        foreach ($stubs as $relativePath => $stubContent) {
            $fullPath = "{$this->uri}/{$relativePath}";

            if (File::exists($fullPath) && ! $this->option('force')) {
                $this->warn("  Skipped (exists): {$relativePath}");
                continue;
            }

            File::ensureDirectoryExists(dirname($fullPath), 0755, true);
            File::put($fullPath, $stubContent);
            $this->line("  + {$relativePath}");
        }

        $this->newLine();
        $this->info("Entity [{$this->entityStudly}] scaffolded inside [{$this->contextStudly}].");
        $this->newLine();
        $this->line("    Remember to bind the repository in {$this->contextStudly}ServiceProvider:");
        $this->line("    {$namespace}\\Domain\\Contracts\\{$this->entityStudly}RepositoryInterface::class");
        $this->line("    {$namespace}\\Infrastructure\\Persistence\\Eloquent{$this->entityStudly}Repository::class");

        return Command::SUCCESS;
    }

    // ─── Stubs ────────────────────────────────────────────────────────────────

    private function stubEntity(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Domain\\Entities;

        class {$this->entityStudly}
        {
            public function __construct(
                //
            ) {}
        }
        PHP;
    }

    private function stubRepositoryInterface(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Domain\\Contracts;

        use {$ns}\\Domain\\Entities\\{$this->entityStudly};

        interface {$this->entityStudly}RepositoryInterface
        {
            public function findById(int \$id): ?{$this->entityStudly};
            public function save({$this->entityStudly} \$entity): void;
            public function delete(int \$id): void;
        }
        PHP;
    }

    private function stubDTO(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Application\\DTOs;

        class {$this->entityStudly}DTO
        {
            public function __construct(
                //
            ) {}
        }
        PHP;
    }

    private function stubUseCase(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Application\\UseCases;

        use {$ns}\\Application\\DTOs\\{$this->entityStudly}DTO;
        use {$ns}\\Domain\\Contracts\\{$this->entityStudly}RepositoryInterface;

        class Create{$this->entityStudly}UseCase
        {
            public function __construct(
                private readonly {$this->entityStudly}RepositoryInterface \$repository,
            ) {}

            public function execute({$this->entityStudly}DTO \$dto): void
            {
                //
            }
        }
        PHP;
    }

    private function stubRepository(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Infrastructure\\Persistence;

        use {$ns}\\Domain\\Contracts\\{$this->entityStudly}RepositoryInterface;
        use {$ns}\\Domain\\Entities\\{$this->entityStudly};

        class Eloquent{$this->entityStudly}Repository implements {$this->entityStudly}RepositoryInterface
        {
            public function findById(int \$id): ?{$this->entityStudly}
            {
                //
            }

            public function save({$this->entityStudly} \$entity): void
            {
                //
            }

            public function delete(int \$id): void
            {
                //
            }
        }
        PHP;
    }

    private function stubController(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Interfaces\\Http\\Controllers;

        use Illuminate\\Http\\JsonResponse;
        use Illuminate\\Routing\\Controller;
        use {$ns}\\Application\\UseCases\\Create{$this->entityStudly}UseCase;
        use {$ns}\\Interfaces\\Http\\Requests\\{$this->entityStudly}Request;
        use {$ns}\\Interfaces\\Http\\Resources\\{$this->entityStudly}Resource;

        class {$this->entityStudly}Controller extends Controller
        {
            public function __construct(
                private readonly Create{$this->entityStudly}UseCase \$createUseCase,
            ) {}

            public function store({$this->entityStudly}Request \$request): JsonResponse
            {
                return response()->json([], 201);
            }
        }
        PHP;
    }

    private function stubRequest(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Interfaces\\Http\\Requests;

        use Illuminate\\Foundation\\Http\\FormRequest;

        class {$this->entityStudly}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
                    //
                ];
            }
        }
        PHP;
    }

    private function stubResource(string $ns): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns}\\Interfaces\\Http\\Resources;

        use Illuminate\\Http\\Resources\\Json\\JsonResource;

        class {$this->entityStudly}Resource extends JsonResource
        {
            public function toArray(\$request): array
            {
                return [
                    //
                ];
            }
        }
        PHP;
    }
}
