# ForumLite API

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>
<p align="center">
  A lightweight, modern RESTful API for a forum application, built with Laravel.
</p>

## About ForumLite API

ForumLite API provides a backend foundation for a simple discussion platform. It offers endpoints for user authentication, managing posts, and handling comments, built with a focus on clean architecture and modern API practices.

The API includes features like pagination, search, rate limiting, and row limits for database tables, ensuring a robust and scalable service.

## Key Features

*   **Authentication:** Secure user registration, login, and logout using Laravel Sanctum (token-based).
*   **Post Management:** Full CRUD operations for posts (create, read, update, delete).
    *   List posts with pagination and default sorting.
    *   Search posts by title or content.
    *   View single post details, including comment count.
*   **Comment Management:** Full CRUD operations for comments.
    *   List comments for a specific post with pagination.
    *   Users can create, update, and delete their own comments.
*   **User-Specific Content:** Endpoints for authenticated users to retrieve their own posts and comments.
*   **API Resources:** Consistent JSON response formatting using Laravel API Resources.
*   **Input Validation:** Server-side validation for all create/update operations.
*   **Error Handling:** Standardized JSON error responses.
*   **Rate Limiting:** Applied to authentication and general API routes to prevent abuse.
*   **Table Row Limits:** Hard limits on the number of users, posts, and comments to manage resource usage.
*   **CORS Handling:** Configured to allow requests from all origins (`*`).
*   **API Documentation:** Comprehensive API documentation generated using L5-Swagger.
*   **Ping Endpoint:** A simple `/api/ping` endpoint to check API responsiveness.
*   **Root Endpoint:** A welcoming message at `/api` with links to key resources and documentation.

## Technology Stack

*   **Backend:** Laravel (PHP)
*   **Database:** PostgreSQL
*   **Authentication:** Laravel Sanctum
*   **API Documentation:** L5-Swagger (OpenAPI)
*   **Caching (for Rate Limiting):** Redis
*   **Testing:** PHPUnit
*   **Frontend (Landing Page):** Tailwind CSS, JavaScript

## Prerequisites

*   PHP (version as per `composer.json`, typically ^8.1 or ^8.2)
*   Composer
*   Node.js & NPM (or Yarn)
*   PostgreSQL
*   Redis

## Setup and Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/eugenemartinez/forum-lite-api.git
    cd forum-lite-api
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Install JavaScript dependencies:**
    ```bash
    npm install
    # or
    # yarn install
    ```

4.  **Set up your environment file:**
    *   Copy `.env.example` to `.env`:
        ```bash
        cp .env.example .env
        ```
    *   Generate your application key:
        ```bash
        php artisan key:generate
        ```
    *   Configure your database connection details (PostgreSQL), Redis connection, and other necessary environment variables in the `.env` file:
        ```
        DB_CONNECTION=pgsql
        DB_HOST=127.0.0.1
        DB_PORT=5432
        DB_DATABASE=your_db_name
        DB_USERNAME=your_db_user
        DB_PASSWORD=your_db_password

        REDIS_HOST=127.0.0.1
        REDIS_PASSWORD=null
        REDIS_PORT=6379
        ```

5.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

6.  **Build frontend assets (for the landing page):**
    ```bash
    npm run dev
    # or for production
    # npm run build
    ```

7.  **Serve the application:**
    *   Using Laravel's built-in server (for local development):
        ```bash
        php artisan serve
        ```
    *   Or configure a local web server like Nginx/Apache or use Laravel Valet/Herd.

## API Documentation

Comprehensive API documentation is available, generated using L5-Swagger. Once the application is running, you can access it at:

*   **[http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)** (if using `php artisan serve`)

The API root endpoint (`/api`) also provides a link to the documentation.

## Available Endpoints (Overview)

The API provides a range of endpoints for managing users, posts, and comments. For a detailed list of all endpoints, request/response schemas, and to try them out, please refer to the [API Documentation](#api-documentation).

Key groups of endpoints include:
*   **Authentication:** `/api/register`, `/api/login`, `/api/logout`
*   **User Profile:** `/api/user` (get authenticated user), `/api/user/posts`, `/api/user/comments`
*   **Posts:** `/api/posts`, `/api/posts/{id}`
*   **Comments:** `/api/posts/{postId}/comments`, `/api/comments/{commentId}`
*   **Utilities:** `/api/ping`

## Running Tests

To run the feature tests for the API:
```bash
php artisan test
```
Ensure you have a separate testing database configured (e.g., in `.env.testing` or `phpunit.xml`) if you don't want tests to affect your development database.

## License

The ForumLite API is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
