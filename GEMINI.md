# Project Overview

This is a Laravel 12 project designed as a multi-gateway system for managing charges. It utilizes a modern architecture based on Actions, Queries, and Custom Exceptions to separate concerns and improve code organization.

## Architecture

The project follows a specific architectural pattern to handle business logic:

*   **Actions (Write Operations):** Actions encapsulate all write operations (Commands). They are responsible for executing business logic and returning domain objects (Models, Collections). Actions never return HTTP responses directly but instead throw Custom Exceptions for business rule violations. This makes them reusable across different entry points like Controllers, Jobs, and Artisan Commands.

*   **Queries (Read Operations):** Queries are responsible for all read operations. They directly use Eloquent to fetch data from the database, often with explicit eager loading. Queries return Models, Collections, or Paginators.

*   **Custom Exceptions:** Custom Exceptions are used to handle business logic errors and control HTTP status codes without coupling the Actions to the HTTP layer. Each custom exception can have its own `render()` method to generate a specific JSON response.

## Tech Stack

*   **Backend:** Laravel 12, PHP 8.2+
*   **Frontend:** Vite, Tailwind CSS
*   **Database:** MySQL 8.0+ or PostgreSQL 14+
*   **Cache & Queue:** Redis
*   **Authentication:** Laravel Sanctum

## Building and Running

### Setup

1.  **Install Dependencies:**
    ```bash
    composer install
    npm install
    ```

2.  **Configure Environment:**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3.  **Run Migrations:**
    ```bash
    php artisan migrate
    ```

### Development

*   **Start Development Servers:**
    ```bash
    composer run dev
    ```
    This command will concurrently start the PHP development server, the queue listener, the log watcher, and the Vite development server.

### Testing

*   **Run Tests:**
    ```bash
    php artisan test
    ```

## Development Conventions

*   **Code Style:** The project follows the PSR-12 coding standard, enforced by Laravel Pint.
*   **Static Analysis:** PHPStan is used for static analysis to catch potential errors before runtime.
*   **Directory Structure:** The project uses a custom directory structure to organize Actions, Queries, DTOs, and other components.
*   **API Versioning:** The API is versioned, with routes organized under `/api/v1`.
*   **Security:** The project uses Laravel Sanctum for API authentication, and includes configurations for CORS and Rate Limiting.
