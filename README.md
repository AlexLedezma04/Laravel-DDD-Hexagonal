***

# DDD Structure Generator

A Laravel Artisan command that scaffolds a Hexagonal + Domain-Driven Design folder structure for a given bounded context and entity.

## Usage

```bash
php artisan make:ddd {context} {entity} [--force]
```

### Arguments

| Argument  | Description                                      | Example             |
|-----------|--------------------------------------------------|---------------------|
| `context` | The bounded context the entity belongs to        | `admin`, `sales`    |
| `entity`  | The entity name to scaffold                      | `products`, `users` |

### Options

| Option    | Description                                         |
|-----------|-----------------------------------------------------|
| `--force` | Overwrite existing structure without confirmation   |

### Examples

```bash
# Create structure for "books" inside the "lms" context
php artisan make:ddd sales products

# Force overwrite if structure already exists
php artisan make:ddd sales products --force
```

## Generated Structure

All files are created under `src/{context}/{entity}/`:

```
src/sales/products/
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
└── ProductsServiceProvider.php
```

## What the command does automatically

- Creates all directories with `.gitkeep` placeholders
- Generates a `{Entity}ServiceProvider.php` that loads the module's routes
- Registers the service provider in `bootstrap/providers.php`
- Links the module route file into `routes/api.php` under the prefix `{context}_{entity}`

***
