<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ForumLite API Documentation",
 *      description="API documentation for the ForumLite application, providing endpoints for managing users, posts, and comments.",
 *      @OA\Contact(
 *          email="eugenejrmartinez@gmail.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="ForumLite API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter token in format (Bearer <token>)"
 * )
 *
 * @OA\Schema(
 *      schema="ValidationErrorResponse",
 *      title="Validation Error Response",
 *      description="Standard validation error response structure when request validation fails.",
 *      @OA\Property(
 *          property="message",
 *          type="string",
 *          example="The given data was invalid."
 *      ),
 *      @OA\Property(
 *          property="errors",
 *          type="object",
 *          description="An object containing field-specific error messages.",
 *          example={"email": {"The email field is required.", "The email must be a valid email address."}, "password": {"The password field is required."}}
 *      )
 * )
 *
 * @OA\Schema(
 *      schema="PaginationLinks",
 *      title="Pagination Links",
 *      description="Links for paginated results",
 *      @OA\Property(property="first", type="string", format="url", example="http://localhost/api/posts?page=1", nullable=true),
 *      @OA\Property(property="last", type="string", format="url", example="http://localhost/api/posts?page=10", nullable=true),
 *      @OA\Property(property="prev", type="string", format="url", example=null, nullable=true),
 *      @OA\Property(property="next", type="string", format="url", example="http://localhost/api/posts?page=2", nullable=true)
 * )
 *
 * @OA\Schema(
 *      schema="PaginationMeta",
 *      title="Pagination Meta",
 *      description="Meta information for paginated results",
 *      @OA\Property(property="current_page", type="integer", example=1),
 *      @OA\Property(property="from", type="integer", example=1, nullable=true),
 *      @OA\Property(property="last_page", type="integer", example=10),
 *      @OA\Property(property="path", type="string", format="url", example="http://localhost/api/posts"),
 *      @OA\Property(property="per_page", type="integer", example=10),
 *      @OA\Property(property="to", type="integer", example=10, nullable=true),
 *      @OA\Property(property="total", type="integer", example=100),
 *      @OA\Property(
 *          property="links",
 *          type="array",
 *          @OA\Items(
 *              type="object",
 *              @OA\Property(property="url", type="string", format="url", nullable=true, example="http://localhost/api/posts?page=1"),
 *              @OA\Property(property="label", type="string", example="&laquo; Previous"),
 *              @OA\Property(property="active", type="boolean", example=false)
 *          )
 *      )
 * )
 *
 * @OA\Schema(
 *      schema="UnauthenticatedResponse",
 *      title="Unauthenticated Response",
 *      description="Standard response for unauthenticated requests.",
 *      @OA\Property(property="message", type="string", example="Unauthenticated.")
 * )
 *
 * @OA\Schema(
 *      schema="NotFoundResponse",
 *      title="Not Found Response",
 *      description="Standard response when a resource is not found.",
 *      @OA\Property(property="message", type="string", example="Resource not found.")
 * )
 *
 * @OA\Schema(
 *      schema="ForbiddenResponse",
 *      title="Forbidden Response",
 *      description="Standard response when a user is not authorized to perform an action.",
 *      @OA\Property(property="message", type="string", example="This action is unauthorized.")
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @OA\Get(
     *      path="/ping",
     *      operationId="ping",
     *      tags={"General"},
     *      summary="Ping the API",
     *      description="Returns a pong message to indicate the API is responsive.",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="pong")
     *          )
     *      )
     * )
     */
    public function ping()
    {
        return response()->json(['message' => 'pong']);
    }
}
