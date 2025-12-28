# 🏗️ Domion

[![Latest Version on Packagist](https://img.shields.io/packagist/v/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)
[![Total Downloads](https://img.shields.io/packagist/dt/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)
[![License](https://img.shields.io/packagist/l/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)

**Domion** is a high-performance, enterprise-grade Domain-Driven Design (DDD) framework for Laravel. It streamlines the creation of complex, scalable applications by automating the delivery layer, authentication integration, and frontend orchestration.

---

## 🚀 Why Domion?

Standard Laravel applications often struggle with scaling when business logic grows. **Domion** solves this by organizing your code into **Domains**, decoupled from the framework's core, while providing a "Zero-Config" experience for modern frontend stacks.

- **Enterprise Ready**: Full support for Multi-tenancy (Stancl/Tenancy) and high-complexity business flows.
- **Modern Stack First**: Automated setup for **React**, **Vue**, **Inertia.js**, and **Livewire**.
- **Event-Driven Scaffolding**: Automatically generates Actions, Repositories, Jobs, Events, and Observers.
- **Smart Resolvers**: Use `Auth::Login` syntax in your controllers to render frontend pages across domains.
- **Zero-Touch Discovery**: Automatically registers domain routes, service providers, and observers.

---

## 📦 Installation

You can install the package via composer:

```bash
composer require samushi/domion
```

## 🛠️ Unified Setup

Domion features a highly interactive CLI to prepare your project in seconds.

```bash
php artisan domion:setup
```

The setup command will:
1.  **Architecture Selection**: Choose between *Standard* or *Multi-tenancy*.
2.  **Frontend Orchestration**: Select React (Inertia), Vue (Inertia), Livewire, or API-only.
3.  **Dependency Auto-Injection**: Automatically installs necessary packages (Inertia, Sanctum, Stancl/Tenancy, etc.).
4.  **Vite & IDE Integration**: Configures Vite aliases (`@domain`, `@support`) and `jsconfig.json`/`tsconfig.json`.
5.  **Bootstrap Patching**: Optimizes Laravel's bootstrap to use the DDD structure.

---

## 🏗️ Core Concepts

### 1. Creating a Domain
Domains are the heart of your application. Each domain is self-contained.

```bash
php artisan domion:make:domain Catalog
```

This creates a complete structure:
```text
app/Domain/Catalog/
├── Actions/         # Pure business logic (ActionFactory)
├── Controllers/     # Delivery layer (extends Inertia/Api/Web controllers)
├── Database/        # Migrations & Seeders
├── Events/          # Domain events
├── Jobs/            # Queued jobs
├── Models/          # Eloquent models
├── Observers/       # Auto-registered model observers
├── Repository/      # Data access layer
└── Frontend/        # Domain-specific React/Vue pages & components
```

### 2. Actions (The Logic)
Actions encapsulate single business operations.

```bash
php artisan domion:make:action CreateProduct
```

### 3. Smart Frontend Resolving
Stop worrying about long paths. Domion resolves pages from any domain using a simple alias:

```php
// In any Controller
return $this->render('Catalog::Product/Detail', [
    'product' => $product
]);
```
*Domion automatically finds the page in `app/Domain/Catalog/Frontend/Pages/Product/Detail.tsx`.*

---

## 🏢 Multi-tenancy (Enterprise)

If enabled, Domion organizes domains into `Central` and `Tenant` scopes. This is perfectly integrated with `stancl/tenancy`.

- **Central Domains**: Management, Billing, Client Onboarding.
- **Tenant Domains**: Core business logic specific to each tenant.

When creating a domain, Domion will ask for the scope:
```bash
php artisan domion:make:domain Billing
# > Is this a Central or Tenant domain? [Central]
```

---

## 🛠️ Configuration

After setup, your configuration is stored in `config/domion.php`.

```php
return [
    'mode' => 'react', // api, react, vue, livewire, blade
    'tenancy' => true,
    'paths' => [
        'domains' => 'app/Domain',
    ]
];
```

---

## ✨ Design Principles

1.  **Decoupling**: Business logic (Actions) should never depend on the delivery layer (Controllers).
2.  **Consistency**: Use **Repositories** for all database interactions to ensure testability.
3.  **Automation**: If it can be automated (Route loading, Observer binding), Domion does it.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

## 🤝 Contributing

We welcome contributions! Please fork the repository and submit a pull request.

Created with ❤️ by **[Sami Maxhuni](https://github.com/samushi)**
