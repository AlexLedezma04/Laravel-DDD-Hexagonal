***

# DDD Structure Generator

A Laravel Artisan command that scaffolds a Hexagonal + Domain-Driven Design folder structure for a given **Bounded Context**, and optionally adds entity stubs inside an existing one.

## Usage

```bash
php artisan make:ddd {context}

php artisan make:ddd {context} {entity}
```

### Arguments

| Argument  | Required | Description                                                        | Example                    |
|-----------|----------|--------------------------------------------------------------------|----------------------------|
| `context` | Yes   | The Bounded Context name (StudlyCase recommended)                  | `Sales`, `ProductCatalog`  |
| `entity`  | No    | Entity to scaffold inside an existing context                      | `Product`, `Order`         |

### Options

| Option    | Description                                       |
|-----------|---------------------------------------------------|
| `--force` | Overwrite existing files without confirmation     |

### Examples

```bash
php artisan make:ddd Sales

php artisan make:ddd Sales Product

# Force overwrite existing files
php artisan make:ddd Sales Product --force
```

***

## Scaffolding a Bounded Context

Running the command **without an entity** creates the full hexagonal folder skeleton under `src/{Context}/`:

```
src/Sales/
├── Domain/
│   ├── Entities/
│   ├── Aggregates/
│   ├── ValueObjects/
│   ├── Events/
│   └── Contracts/
├── Application/
│   ├── UseCases/
│   ├── DTOs/
│   └── Services/
├── Infrastructure/
│   ├── Persistence/
│   ├── Listeners/
│   └── Jobs/
├── Interfaces/
│   └── Http/
│       ├── Controllers/
│       ├── Requests/
│       ├── Resources/
│       └── Routes/
│           └── api.php
└── SalesServiceProvider.php
```

### What gets done automatically

- All directories are created with `.gitkeep` placeholders
- A `{Context}ServiceProvider.php` is generated; it auto-loads the context's `Routes/api.php`
- The service provider is registered in `bootstrap/providers.php`
- The context route file is linked into `routes/api.php` under the prefix `/{context_snake_case}`
  - e.g. `Sales` → `/sales`, `ProductCatalog` → `/product_catalog`

***

## Scaffolding an Entity

Running the command **with an entity argument** adds PHP stub files into an already existing Bounded Context. The context must exist first.

```
src/Sales/
├── Domain/
│   ├── Entities/
│   │   └── Product.php
│   └── Contracts/
│       └── ProductRepositoryInterface.php
├── Application/
│   ├── DTOs/
│   │   └── ProductDTO.php
│   └── UseCases/
│       └── CreateProductUseCase.php
├── Infrastructure/
│   └── Persistence/
│       └── EloquentProductRepository.php
└── Interfaces/
    └── Http/
        ├── Controllers/
        │   └── ProductController.php
        ├── Requests/
        │   └── ProductRequest.php
        └── Resources/
            └── ProductResource.php
```

### What gets generated per entity

| File | Purpose |
|---|---|
| `Domain/Entities/{Entity}.php` | Domain entity class |
| `Domain/Contracts/{Entity}RepositoryInterface.php` | Repository contract with `findById`, `save`, `delete` |
| `Application/DTOs/{Entity}DTO.php` | Data Transfer Object |
| `Application/UseCases/Create{Entity}UseCase.php` | Use case wired to the repository interface |
| `Infrastructure/Persistence/Eloquent{Entity}Repository.php` | Eloquent implementation of the repository interface |
| `Interfaces/Http/Controllers/{Entity}Controller.php` | HTTP controller with a `store` action |
| `Interfaces/Http/Requests/{Entity}Request.php` | Form request with `authorize` and `rules` stubs |
| `Interfaces/Http/Resources/{Entity}Resource.php` | JSON API resource |

### Binding the repository

After scaffolding an entity, register the interface-to-implementation binding in your context's ServiceProvider:

```php
// src/Sales/SalesServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \Src\Sales\Domain\Contracts\ProductRepositoryInterface::class,
        \Src\Sales\Infrastructure\Persistence\EloquentProductRepository::class,
    );
}
```