# 🏗️ Domion – The Ultimate DDD Starter Kit for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)
[![Total Downloads](https://img.shields.io/packagist/dt/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)
[![License](https://img.shields.io/packagist/l/samushi/domion.svg?style=flat-square)](https://packagist.org/packages/samushi/domion)

Welcome to **Domion**! 👋

Simplify your enterprise Laravel development with a robust **Domain-Driven Design (DDD)** architecture. 
**Domion** is not just a structure package; it is a **Complete Starter Kit** that gives you everything you need to launch a professional, scalable application in minutes.

Whether you're building a **RESTful API**, a **React SPA**, or a **Vue.js application**, Domion has you covered with pre-configured authentication, settings modules, and enterprise-grade patterns.

---

## 🚀 Why Choose Domion?

| Feature | Description |
| :--- | :--- |
| **DDD Architecture** | Pre-configured structure with Actions, DTOs, Repositories, and Domain separation. |
| **Three Starter Modes** | Choose between **API**, **React (Inertia)**, or **Vue (Inertia)** during setup. |
| **Built-in Security** | Two-Factor Authentication (2FA) via Laravel Fortify with complete UI. |
| **API Response Builder** | Standardized JSON responses using `marcin-orlowski/laravel-api-response-builder`. |
| **Query Filters** | Powerful filtering system using `samushi/query-filter` integrated in Repositories. |
| **Settings Module** | Profile, Password, Appearance, and Account Deletion – all pre-built. |
| **Multi-tenancy Ready** | Full support for Stancl/Tenancy for SaaS applications. |

---

## 📦 Installation Guide

Follow these simple steps to start your journey with Domion.

### Step 1: Create a Laravel Project
Start with a fresh Laravel installation.

```bash
laravel new my-app
cd my-app
```

### Step 2: Install Domion
Install the package via Composer.

```bash
composer require samushi/domion
```

### Step 3: Setup Your Architecture 🪄
This is where the magic happens. The interactive setup command will:
- Ask you to choose between **API**, **React**, or **Vue** mode
- Configure your project structure
- Install all necessary dependencies
- Scaffold core domains (Auth, Dashboard, Settings)
- Set up 2FA via Fortify (for React/Vue modes)

```bash
php artisan domion:setup
```

### Step 4: Launch Your Application

**For API Mode:**
```bash
php artisan migrate
php artisan serve
```

**For React/Vue Mode:**
```bash
npm install
npm run dev
php artisan migrate
php artisan serve
```

Open your browser and explore your new application!

---

## 🎯 Starter Kit Modes

Domion supports three distinct modes, each optimized for different use cases.

### 1. 🔌 API Mode (Headless)

Perfect for building RESTful APIs, mobile backends, or microservices.

**Features:**
- No frontend UI – pure JSON API endpoints
- Standardized API responses via `laravel-api-response-builder`
- Token-based authentication (Laravel Sanctum)
- Clean controller structure extending `ApiControllers`

**API Response Example:**
```php
// In your Controller
return $this->success($data, 'Data retrieved successfully');

// Response:
{
    "success": true,
    "code": 200,
    "message": "Data retrieved successfully",
    "data": { ... }
}
```

**Error Response:**
```php
return $this->error('Validation failed', 422);

// Response:
{
    "success": false,
    "code": 422,
    "message": "Validation failed"
}
```

---

### 2. ⚛️ React Mode (Inertia.js)

Modern single-page application experience with React and TypeScript.

**Features:**
- Full React + TypeScript + Inertia.js setup
- Pre-built pages: Login, Register, Dashboard, Settings
- Shadcn-inspired UI components
- Dark/Light mode support
- Sidebar navigation layout
- Two-Factor Authentication UI

**Included Pages:**
- `/` – Landing Page
- `/login` – Authentication
- `/register` – User Registration
- `/dashboard` – Protected Dashboard
- `/settings/profile` – Profile Management
- `/settings/password` – Password Update
- `/settings/appearance` – Theme Selection
- `/settings/two-factor` – 2FA Management

---

### 3. 💚 Vue Mode (Inertia.js)

Elegant single-page application with Vue 3 and TypeScript.

**Features:**
- Full Vue 3 + TypeScript + Inertia.js setup
- Same pre-built pages as React mode
- Component-based architecture
- Dark/Light mode support
- Responsive layouts

---

## 🏗️ Architecture Overview

Domion organizes your code into **Domains**. Each domain is self-contained, handling its own logic, database interactions, and frontend views (if applicable).

### Directory Structure

```text
app/
├── Domain/
│   ├── Auth/                    # Authentication Domain
│   │   ├── Actions/             # LoginAction, RegisterAction, etc.
│   │   ├── Controllers/         # AuthController
│   │   ├── Requests/            # LoginRequest, RegisterRequest
│   │   └── web.php              # Domain routes
│   │
│   ├── Dashboard/               # Dashboard Domain
│   │   ├── Controllers/         # DashboardController
│   │   └── web.php              # Dashboard routes
│   │
│   ├── Settings/                # Settings Domain
│   │   ├── Actions/             # UpdateUserProfileAction, DeleteUserAction
│   │   ├── Dto/                 # UpdateProfileDto, UpdatePasswordDto
│   │   ├── Requests/            # ProfileUpdateRequest, PasswordUpdateRequest
│   │   ├── Controllers/         # SettingsController
│   │   └── web.php              # Settings routes
│   │
│   ├── User/                    # User Domain
│   │   ├── Models/              # User.php
│   │   └── Repository/          # UserRepository
│   │
│   └── [YourDomain]/            # Your Custom Business Logic
│       ├── Actions/
│       ├── Dto/
│       ├── Models/
│       ├── Repository/
│       ├── Requests/
│       └── Controllers/
│
├── Support/                     # Shared Infrastructure
│   ├── Controllers/             # Base Controllers (ApiControllers, InertiaControllers)
│   ├── Traits/                  # HttpResponseTrait, InertiaResponseTrait
│   ├── Middleware/              # HandleInertiaRequests
│   └── Frontend/                # Layouts, Components (React/Vue)
│       ├── Layouts/
│       ├── components/
│       └── Pages/
```

---

## 🧱 Core Concepts

### 1. Actions (Business Logic)

Actions are the heart of your business logic. Each action does **one thing** and does it well.

**Creating an Action:**
```bash
php artisan domion:make:action CreateOrder
```

**Action Structure:**
```php
<?php

namespace App\Domain\Orders\Actions;

use Samushi\Domion\Actions\ActionFactory;
use App\Domain\Orders\Dto\CreateOrderDto;
use App\Domain\Orders\Repository\OrderRepository;

class CreateOrderAction extends ActionFactory
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    public function handle(CreateOrderDto $dto): Order
    {
        return $this->orderRepository->create($dto->toArray());
    }
}
```

**Calling an Action:**
```php
// Static call (recommended)
$order = CreateOrderAction::run($dto);

// Or with error handling
$result = CreateOrderAction::runSafe($dto);
if ($result['success']) {
    // Handle success
}
```

---

### 2. DTOs (Data Transfer Objects)

DTOs ensure type safety and clean data flow between layers.

**DTO Structure:**
```php
<?php

namespace App\Domain\Orders\Dto;

use App\Support\DataObjects;

class CreateOrderDto extends DataObjects
{
    public function __construct(
        public readonly int $customerId,
        public readonly array $items,
        public readonly float $total,
    ) {}
}
```

**Creating from Request:**
```php
// In Controller
$dto = CreateOrderDto::fromRequest($request);

// The base DataObjects class automatically maps validated() data to constructor properties
```

**Available Methods:**
- `fromRequest(FormRequest $request)` – Create from validated request
- `fromArray(array $data)` – Create from array
- `toArray()` – Convert to array
- `toCollection()` – Convert to Laravel Collection

---

### 3. Repositories (Data Access Layer)

Repositories abstract database operations and integrate with **Query Filters**.

**Repository Structure:**
```php
<?php

namespace App\Domain\Orders\Repository;

use App\Domain\Orders\Models\Order;
use App\Support\Repositories;
use Illuminate\Database\Eloquent\Model;

class OrderRepository extends Repositories
{
    protected function getModel(): Model
    {
        return new Order();
    }

    // Optional: Define domain-specific filters
    protected function getFilters(): array
    {
        return [
            new \App\Domain\Orders\Filters\StatusFilter(),
            new \App\Domain\Orders\Filters\CustomerFilter(),
        ];
    }
}
```

**Available Repository Methods:**
```php
$repository->all();                    // Get all records
$repository->paginate(15);             // Paginate results
$repository->find($id);                // Find by ID
$repository->findOrFail($id);          // Find or throw exception
$repository->findBy('email', $email);  // Find by column
$repository->findWhere(['status' => 'active']); // Find with conditions
$repository->create($data);            // Create record
$repository->update($data, $id);       // Update record
$repository->delete($id);              // Delete record
$repository->exists($id);              // Check existence
$repository->count();                  // Count records
```

---

### 4. Query Filters (samushi/query-filter)

Domion integrates `samushi/query-filter` for powerful, reusable query filtering.

**Creating a Filter:**
```php
<?php

namespace App\Domain\Orders\Filters;

use Samushi\QueryFilter\Filter;

class StatusFilter extends Filter
{
    public function applyFilter($builder)
    {
        return $builder->where('status', $this->getValue());
    }
}
```

**Usage in Repository:**
Filters are automatically applied when you call `all()` or `paginate()` if query parameters match.

```php
// Request: GET /orders?status=pending&search=john

$orders = $orderRepository->paginate(15);
// Automatically filtered by status and searched!
```

**Built-in Filters:**
- `Search` – Full-text search across searchable columns
- `Order` – Dynamic ordering (e.g., `?order_by=created_at&order_dir=desc`)

---

### 5. Controllers

Controllers are thin and delegate to Actions. They extend base controllers provided by Domion.

**For API Mode:**
```php
<?php

namespace App\Domain\Orders\Controllers;

use App\Support\Controllers\ApiControllers as Controller;

class OrderController extends Controller
{
    public function index(OrderRepository $repository)
    {
        return $this->success($repository->paginate());
    }

    public function store(CreateOrderRequest $request)
    {
        $dto = CreateOrderDto::fromRequest($request);
        $order = CreateOrderAction::run($dto);
        
        return $this->success($order, 'Order created', 201);
    }
}
```

**For React/Vue Mode:**
```php
<?php

namespace App\Domain\Orders\Controllers;

use App\Support\Controllers\InertiaControllers as Controller;

class OrderController extends Controller
{
    public function index(OrderRepository $repository)
    {
        return $this->render('Orders/Index', [
            'orders' => $repository->paginate()
        ]);
    }
}
```

**Available Response Methods:**

| Method | Mode | Description |
| :--- | :--- | :--- |
| `$this->success($data, $message, $code)` | API | Success JSON response |
| `$this->error($message, $code)` | API | Error JSON response |
| `$this->render($page, $props)` | Inertia | Render Inertia page |
| `$this->toRoute($route, $params)` | Inertia | Redirect to named route |
| `$this->toUrl($path)` | Inertia | Redirect to URL |
| `$this->back()` | Inertia | Redirect back |

---

## 🔐 Security Features

### Two-Factor Authentication (2FA)

Domion integrates **Laravel Fortify** for 2FA. This is automatically configured during setup.

**How it works:**
1. User navigates to **Settings → Two Factor Auth**
2. Clicks **Enable**
3. Scans QR Code with authenticator app
4. Saves Recovery Codes
5. Future logins require OTP verification

**Backend:** Fortify handles all 2FA logic (`/user/two-factor-authentication` endpoints).

**Frontend:** Complete React/Vue UI is provided.

---

### API Authentication (Sanctum)

For API mode, authentication is handled via Laravel Sanctum tokens.

```php
// Login endpoint returns token
{
    "success": true,
    "data": {
        "token": "1|abc123...",
        "user": { ... }
    }
}

// Use token in subsequent requests
Authorization: Bearer 1|abc123...
```

---

## 🛠️ CLI Commands

| Command | Description |
| :--- | :--- |
| `php artisan domion:setup` | Interactive project setup |
| `php artisan domion:make:domain {Name}` | Create a new domain |
| `php artisan domion:make:action {Name}` | Generate an Action class |

---

## 📦 Dependencies

Domion automatically installs these packages based on your chosen mode:

| Package | Purpose | Modes |
| :--- | :--- | :--- |
| `inertiajs/inertia-laravel` | SPA bridge | React, Vue |
| `laravel/fortify` | 2FA & Security | React, Vue |
| `laravel/sanctum` | API Authentication | All |
| `marcin-orlowski/laravel-api-response-builder` | Standardized API responses | API |
| `samushi/query-filter` | Query filtering | All |

---

## 🏢 Multi-tenancy Support

Domion supports multi-tenant applications via `stancl/tenancy`.

When enabled, domains are organized into:
- **Central Domains**: Shared across all tenants (e.g., Billing, Admin)
- **Tenant Domains**: Isolated per tenant (e.g., Projects, Orders)

```bash
php artisan domion:make:domain Billing
# > Is this a Central or Tenant domain? [Central]
```

---

## 🤝 Contributing

We welcome contributions! Please fork the repository and submit a pull request.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

Created with ❤️ by **[Sami Maxhuni](https://github.com/samushi)**
