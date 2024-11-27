<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="API Documentation - Floating Blood Bank",
 *     description="Version 1 All REST API's",
 *     @OA\Contact(
 *         email="jayantadas.dev@gmail.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://api.floatingbloodbank.com/api",
 *     description="Production Server"
 * )
 * 
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 * 
 * @OA\Tag(
 *     name="Auth",
 *     description="Endpoints for authentication and user management."
 * )
 * 
 *  @OA\Tag(
 *     name="Activity Logs",
 *     description="Endpoints for activity logs. Using this API's we can get all user logs."
 * )
 * 
 *
 */
class Setup
{
    // This class is intentionally left empty.
}
