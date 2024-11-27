<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;


class AuthController extends Controller
{

/**
 * @OA\Post(
 *     path="/v1/auth/register",
 *     tags={"Auth"},
 *     summary="Register a new user",
 *     description="Registers a new user, optionally with a profile picture, and returns a JWT token.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "email", "password", "password_confirmation", "phone_number"},
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", example="john@example.com"),
 *             @OA\Property(property="password", type="string", example="password123"),
 *             @OA\Property(property="password_confirmation", type="string", example="password123"),
 *             @OA\Property(property="phone_number", type="string", example="1234567890"),
 *             @OA\Property(property="pic", type="string", format="binary", description="Profile picture file (JPG/PNG, max size 5MB)", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="User registered successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User registered successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIs...")
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example={})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=422),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Validation Error.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="field", type="string", example="email"),
 *                     @OA\Property(property="message", type="string", example="The email has already been taken.")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Server error.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function register(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'required|string|unique:users',
            'pic' => 'nullable|image|mimes:jpg,png|max:5120',
        ]);

        if ($validator->fails()) {
            Log::info($validator->errors()->toArray());
            return response()->json([
                'meta' => ['code' => 422, 'success' => false, 'message' => 'Validation Error.'],
                'result' => [], 'errors' => $validator->errors()
            ], 422);
        }

        $picPath = null;

        if ($request->hasFile('pic')) {
            // Store the pic in `user_pic` folder under `storage/app/public`
            $picPath = $request->file('pic')->store('user_pic', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'pic' => $picPath ? asset('storage/' . $picPath) : null, // Save the full URL path
        ]);

        // Log the activity
        activity('Auth')
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'user_id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
            ])
            ->log('New user registered successfully.');

        return response()->json([
            'meta' => ['code' => 200, 'success' => true, 'message' => 'User registered successfully.'],
            'result' => ['token' => JWTAuth::fromUser($user)], 'errors' => []
        ], 200);
    } catch (\Exception $e) {
        Log::error('Register Error: ' . $e->getMessage());

         // Log the error activity
         activity('Auth')
         ->withProperties([
             'ip_address' => $request->ip(),
             'user_agent' => $request->header('User-Agent'),
             'error_message' => $e->getMessage(),
         ])
         ->log('User registration failed due to a server error.');

        return response()->json([
            'meta' => ['code' => 500, 'success' => false, 'message' => 'Server error.'],
            'result' => [], 'errors' => [$e->getMessage()]
        ], 500);
    }
}


/**
 * @OA\Post(
 *     path="/v1/auth/login",
 *     tags={"Auth"},
 *     summary="Log in a user",
 *     description="Authenticates a user and returns a JWT token upon successful login.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(property="email", type="string", example="john@example.com"),
 *             @OA\Property(property="password", type="string", example="password123")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Login successful.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIs...")
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example={})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Invalid credentials: Email not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Invalid credentials: Email not found.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="field", type="string", example="email"),
 *                     @OA\Property(property="value", type="string", example="john@example.com")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="email", type="array",
 *                     @OA\Items(type="string", example="The provided email address is not registered.")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Invalid credentials: Incorrect password.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=401),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Invalid credentials: Incorrect password.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="password", type="array",
 *                     @OA\Items(type="string", example="The provided password is incorrect.")
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
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {

                $userExists = User::where('email', $request->email)->exists();

                    if (!$userExists) {
                         // Log the activity for a non-existent user
                        activity('Auth')
                        ->withProperties([
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->header('User-Agent'),
                            'email' => $request->email,
                        ])
                        ->log('Login attempt failed - Email not registered.');

                        // The user doesn't exist
                        return response()->json([
                            'meta' => ['code' => 404, 'success' => false, 'message' => 'Invalid credentials.'],
                            'result' => [], 'errors' => [
                                    'email' => ['The provided email address is not registered.']
                                ]
                        ], 404);
                    }

                    // Log the activity for incorrect password
                    activity('Auth')
                    ->withProperties([
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                        'email' => $request->email,
                    ])
                    ->log('Login attempt failed - Incorrect password.');

                    // User exists but password is incorrect
                    return response()->json([
                        'meta' => ['code' => 404, 'success' => false, 'message' => 'Invalid credentials.'],
                        'result' => [],'errors' => [
                            'password' => ['The provided password is incorrect.']
                        ]
                    ], 401);
                
            }

             // Get the authenticated user
            $user = auth()->user();

            // Log the activity for successful login
            activity('Auth')
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'user_id' => $user->id,
                    'email' => $user->email,
                ])
                ->log('User logged in successfully.');

            return response()->json([
                'meta' => ['code' => 200, 'success' => true, 'message' => 'Login successful.'],
                'result' => ['token' => JWTAuth::attempt($credentials)], 'errors' => []
            ], 200);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());

            // Log the activity for server error
            activity('Auth')
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'error_message' => $e->getMessage(),
            ])
            ->log('Login attempt failed - Server error.');

            return response()->json([
                'meta' => ['code' => 500, 'success' => false, 'message' => 'Server error.'],
                'result'=>[], 'errors' => [$e->getMessage()]
            ], 500);
        }
    }


/**
 * @OA\Post(
 *     path="/v1/auth/logout",
 *     tags={"Auth"},
 *     summary="Log out the authenticated user",
 *     description="Invalidates the JWT token and logs the user out.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Logout successful.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Logout successful.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example={})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized - Token missing or invalid.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=401),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Unauthorized - Token missing or invalid.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="Token is missing or invalid.")
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
 *                 @OA\Property(property="message", type="string", example="Internal server error.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
    public function logout(Request $request)
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            JWTAuth::invalidate(JWTAuth::getToken());

            // Log the activity for successful logout
            activity('Auth')
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
            ])
            ->log('User logged out successfully.');

            return response()->json([
                'meta' => ['code' => 200, 'success' => true, 'message' => 'Logout successful.'],
                'result' => [], 'errors' => []
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());

            // Log the activity for server error during logout
            activity('Auth')
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'error_message' => $e->getMessage(),
            ])
            ->log('Logout attempt failed - Server error.');

            return response()->json([
                'meta' => ['code' => 500, 'success' => false, 'message' => 'Server error.'],
                'result'=>[], 'errors' => [$e->getMessage()]
            ], 500);
        }
    }



/**
 * @OA\Post(
 *     path="/v1/auth/refresh",
 *     tags={"Auth"},
 *     summary="Refresh the JWT token",
 *     description="Generates a new JWT token using the current token.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Token refreshed successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Token refreshed successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIs...")
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example={})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized - Token missing or expired.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=401),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Unauthorized - Token missing or expired.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="Token is invalid or expired.")
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
 *                 @OA\Property(property="message", type="string", example="Internal server error.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function refresh(Request $request)
{
    try {
        $token = JWTAuth::parseToken()->refresh(); // Refreshes the current token
        JWTAuth::setToken($token); // Set the new token

        // Get the authenticated user
        $user = auth()->user();

        // Log the activity for token refresh
        activity('Auth')
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
            ])
            ->log('JWT token refreshed successfully.');

        return response()->json([
            'meta' => ['code' => 200, 'success' => true, 'message' => 'Token Refreshed.'],
            'result' => ['token' => $token], 'errors' => []
        ], 200);
    } catch (\Exception $e) {
        Log::error('Refresh Token Error: ' . $e->getMessage());

        // Log the activity for server error during token refresh
        activity('Auth')
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'error_message' => $e->getMessage(),
            ])
            ->log('Token refresh attempt failed - Server error.');

        return response()->json([
            'meta' => ['code' => 500, 'success' => false, 'message' => 'Server error.'],
            'result'=>[], 'errors' => [$e->getMessage()]
        ], 500);
    }
}


/**
 * @OA\Get(
 *     path="/v1/auth/me",
 *     tags={"Auth"},
 *     summary="Get the authenticated user's details",
 *     description="Returns the details of the currently authenticated user.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="User details retrieved successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="User details retrieved successfully.")
 *             ),
 *             @OA\Property(property="result", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="john@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="1234567890"),
 *                 @OA\Property(property="status", type="boolean", example=true),
 *                 @OA\Property(property="pic", type="string", example="http://example.com/storage/user_pic/john_doe.jpg"),
 *                 @OA\Property(property="otp_email", type="string", example="123456"),
 *                 @OA\Property(property="otp_phone", type="string", example="654321"),
 *                 @OA\Property(property="email_verified_at", type="string", example="2024-11-27T10:00:00Z"),
 *                 @OA\Property(property="created_at", type="string", example="2024-11-27T10:00:00Z"),
 *                 @OA\Property(property="updated_at", type="string", example="2024-11-27T10:00:00Z")
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example={})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized - Token is missing or invalid.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=401),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Unauthorized - Token is missing or invalid.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="Token is missing or invalid.")
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
 *                 @OA\Property(property="message", type="string", example="Internal server error.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="string", example={})
 *             ),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
    public function me(Request $request)
    {
        try {
            $user = auth()->user();

            // Log the activity for fetching user details
            activity('Auth')
                ->causedBy($user)
                ->withProperties([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'user_id' => $user->id,
                    'email' => $user->email,
                ])
                ->log('Fetched authenticated user details.');

            return response()->json([
                'meta' => ['code' => 200, 'success' => true, 'message' => 'User Details Retrieved.'],
                'result' => $user, 'errors' => []
            ], 200);
        } catch (\Exception $e) {
            Log::error('Me Error: ' . $e->getMessage());

             // Log the activity for error during fetching user details
            activity('Auth')
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'error_message' => $e->getMessage(),
            ])
            ->log('Failed to fetch authenticated user details.');

            return response()->json([
                'meta' => ['code' => 500, 'success' => false, 'message' => 'Server error.'],
                'result'=>[], 'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
