<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
/**
 * @OA\Post(
 *     path="/v1/users",
 *     tags={"Users"},
 *     summary="Create a new user",
 *     description="Creates a new user and returns the created user details. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "email", "password", "phone_number", "status"},
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", example="john@example.com"),
 *             @OA\Property(property="password", type="string", example="password123"),
 *             @OA\Property(property="phone_number", type="string", example="1234567890"),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="pic", type="string", format="binary", nullable=true, description="Profile picture (JPG/PNG)")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="User created successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=201),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User created successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="john@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                 @OA\Property(property="status", type="boolean", example=true),
 *                 @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg"),
 *                 @OA\Property(property="created_at", type="string", example="2024-11-27T10:00:00Z"),
 *                 @OA\Property(property="updated_at", type="string", example="2024-11-27T10:00:00Z")
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=422),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Validation error.")
 *             ),
 *            @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="email", type="array",
 *                     @OA\Items(type="string", example="The email has already been taken.")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function createUser(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|unique:users',
            'status' => 'required|boolean',
            'pic' => 'nullable|image|mimes:jpg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'success' => false,
                    'message' => 'Validation error.',
                ],
                'result' => [],
                'errors' => $validator->errors(),
            ], 422);
        }

        $picPath = null;
        if ($request->hasFile('pic')) {
            $picPath = $request->file('pic')->store('user_pic', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'status' => $request->status,
            'pic' => $picPath ? asset('storage/' . $picPath) : null,
        ]);

        // Log the activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_details' => $user->toArray(),
                'description' => 'A new user was created with name ' . $user->name,
            ])
            ->log('User Created');

        return response()->json([
            'meta' => [
                'code' => 201,
                'success' => true,
                'message' => 'User created successfully.',
            ],
            'result' => $user,
            'errors' => [],
        ], 201);
    } catch (\Exception $e) {
        Log::error('Create User Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}



/**
 * @OA\Get(
 *     path="/v1/users",
 *     tags={"Users"},
 *     summary="Get all users",
 *     description="Retrieve a list of all users. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Users retrieved successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Users retrieved successfully.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="John Doe"),
 *                     @OA\Property(property="email", type="string", example="john@example.com"),
 *                     @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                     @OA\Property(property="status", type="boolean", example=true),
 *                     @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg"),
 *                     @OA\Property(property="created_at", type="string", example="2024-11-27T10:00:00Z"),
 *                     @OA\Property(property="updated_at", type="string", example="2024-11-27T10:00:00Z")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function getAllUsers(Request $request)
{
    try {
        $users = User::all();

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_count' => $users->count(),
                'description' => 'Retrieved all users.',
            ])
            ->log('Users Retrieved');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'Users retrieved successfully.',
            ],
            'result' => $users,
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Get All Users Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}



/**
 * @OA\Get(
 *     path="/v1/users/{id}",
 *     tags={"Users"},
 *     summary="Get a single user's details",
 *     description="Retrieve the details of a specific user by their ID. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the user to retrieve.",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="User retrieved successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User retrieved successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="john@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                 @OA\Property(property="status", type="boolean", example=true),
 *                 @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg"),
 *                 @OA\Property(property="created_at", type="string", example="2024-11-27T10:00:00Z"),
 *                 @OA\Property(property="updated_at", type="string", example="2024-11-27T10:00:00Z")
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="User not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="User not found.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="The specified user does not exist.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function getUserById($id, Request $request)
{
    try {
        $user = User::find($id);

        if (!$user) {
            // Log activity for not found
            activity('User')
                ->causedBy(auth()->user())
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'description' => "Failed to retrieve user with ID: $id",
                ])
                ->log('User Not Found');

            return response()->json([
                'meta' => [
                    'code' => 404,
                    'success' => false,
                    'message' => 'User not found.',
                ],
                'result' => [],
                'errors' => ['The specified user does not exist.'],
            ], 404);
        }

        // Log activity for success
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'user' => $user,
                'description' => "Retrieved user with ID: $id",
            ])
            ->log('User Retrieved');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'User retrieved successfully.',
            ],
            'result' => $user,
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Get User By ID Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}



/**
 * @OA\Post(
 *     path="/v1/users/bulk",
 *     tags={"Users"},
 *     summary="Create multiple users",
 *     description="Create multiple users in a single request. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object",
 *                 required={"name", "email", "password", "phone_number"},
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="john@example.com"),
 *                 @OA\Property(property="password", type="string", example="password123"),
 *                 @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                 @OA\Property(property="status", type="boolean", example=true),
 *                 @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg", nullable=true)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Users created successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=201),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Users created successfully.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="John Doe"),
 *                     @OA\Property(property="email", type="string", example="john@example.com"),
 *                     @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                     @OA\Property(property="status", type="boolean", example=true),
 *                     @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=422),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Validation error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="The email field is required.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function createUsers(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            '*.name' => 'required|string|max:255',
            '*.email' => 'required|string|email|max:255|unique:users',
            '*.password' => 'required|string|min:8',
            '*.phone_number' => 'required|string|unique:users',
            '*.status' => 'nullable|boolean',
            '*.pic' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'success' => false,
                    'message' => 'Validation error.',
                ],
                'result' => [],
                'errors' => $validator->errors(),
            ], 422);
        }

        $users = [];
        foreach ($request->all() as $userData) {
            $userData['password'] = Hash::make($userData['password']);
            $users[] = User::create($userData);
        }

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'description' => 'Created multiple users.',
            ])
            ->log('Users Created');

        return response()->json([
            'meta' => [
                'code' => 201,
                'success' => true,
                'message' => 'Users created successfully.',
            ],
            'result' => $users,
            'errors' => [],
        ], 201);
    } catch (\Exception $e) {
        Log::error('Create Users Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}


/**
 * @OA\Put(
 *     path="/v1/users/{id}",
 *     tags={"Users"},
 *     summary="Update a specific user",
 *     description="Updates the details of a specific user by ID. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID of the user to update",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Jane Doe"),
 *             @OA\Property(property="email", type="string", example="jane@example.com"),
 *             @OA\Property(property="phone_number", type="string", example="9876543210"),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="User updated successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User updated successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="Jane Doe"),
 *                 @OA\Property(property="email", type="string", example="jane@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="9876543210"),
 *                 @OA\Property(property="status", type="boolean", example=true),
 *                 @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic.jpg")
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="User not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="User not found.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function updateUser(Request $request, $id)
{
    try {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'meta' => [
                    'code' => 404,
                    'success' => false,
                    'message' => 'User not found.',
                ],
                'result' => [],
                'errors' => [],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'phone_number' => 'nullable|string|unique:users,phone_number,' . $id,
            'status' => 'nullable|boolean',
            'pic' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'success' => false,
                    'message' => 'Validation error.',
                ],
                'result' => [],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($request->all());

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'description' => 'Updated user with ID: ' . $id,
                'user_details' => $user->toArray(),
            ])
            ->log('User Updated');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'User updated successfully.',
            ],
            'result' => $user,
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Update User Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}


/**
 * @OA\Delete(
 *     path="/v1/users/{id}",
 *     tags={"Users"},
 *     summary="Delete a specific user",
 *     description="Deletes a specific user by ID. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID of the user to delete",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="User deleted successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User deleted successfully.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="User not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="User not found.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function deleteUser(Request $request, $id)
{
    try {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'meta' => [
                    'code' => 404,
                    'success' => false,
                    'message' => 'User not found.',
                ],
                'result' => [],
                'errors' => [],
            ], 404);
        }

        $user->delete();

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'description' => 'Deleted user with ID: ' . $id,
                'user_details' => $user->toArray(),
            ])
            ->log('User Deleted');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'User deleted successfully.',
            ],
            'result' => [],
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Delete User Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}



/**
 * @OA\Delete(
 *     path="/v1/users",
 *     tags={"Users"},
 *     summary="Delete multiple users",
 *     description="Deletes multiple users by IDs. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="ids", type="array", description="Array of user IDs to delete",
 *                 @OA\Items(type="integer", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Users deleted successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Users deleted successfully.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="One or more users not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="One or more users not found.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function deleteUsers(Request $request)
{
    try {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'success' => false,
                    'message' => 'No IDs provided.',
                ],
                'result' => [],
                'errors' => [],
            ], 422);
        }

        $users = User::whereIn('id', $ids)->get();

        if ($users->isEmpty()) {
            return response()->json([
                'meta' => [
                    'code' => 404,
                    'success' => false,
                    'message' => 'One or more users not found.',
                ],
                'result' => [],
                'errors' => [],
            ], 404);
        }

        // Collect details of users being deleted for activity logging
        $userDetails = $users->toArray();

        User::whereIn('id', $ids)->delete();

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'description' => 'Deleted multiple users',
                'user_details' => $userDetails,
            ])
            ->log('Users Deleted');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'Users deleted successfully.',
            ],
            'result' => [],
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Delete Users Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}


/**
 * @OA\Patch(
 *     path="/v1/users/{id}/status",
 *     tags={"Users"},
 *     summary="Update user status",
 *     description="Updates the status of a user. Only boolean values are accepted. JWT Bearer token is required.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID of the user to update",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="User status updated successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User status updated successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="status", type="boolean", example=true)
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="User not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="User not found.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Invalid status value.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=422),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Invalid status value.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Internal server error.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="string", example="An empty array.")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function updateUserStatus(Request $request, $id)
{
    try {
        $validator = Validator::make($request->all(), [
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid status value.',
                ],
                'result' => [],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'meta' => [
                    'code' => 404,
                    'success' => false,
                    'message' => 'User not found.',
                ],
                'result' => [],
                'errors' => [],
            ], 404);
        }

        $user->update(['status' => $request->status]);

        // Log activity
        activity('User')
            ->causedBy(auth()->user())
            ->withProperties([
                'ip_address' => $request->ip(),
                'description' => 'Updated user status',
                'user_id' => $user->id,
                'new_status' => $request->status,
            ])
            ->log('User status updated');

        return response()->json([
            'meta' => [
                'code' => 200,
                'success' => true,
                'message' => 'User status updated successfully.',
            ],
            'result' => [
                'id' => $user->id,
                'status' => $user->status,
            ],
            'errors' => [],
        ], 200);
    } catch (\Exception $e) {
        Log::error('Update User Status Error: ' . $e->getMessage());
        return response()->json([
            'meta' => [
                'code' => 500,
                'success' => false,
                'message' => 'Internal server error.',
            ],
            'result' => [],
            'errors' => [$e->getMessage()],
        ], 500);
    }
}

}
