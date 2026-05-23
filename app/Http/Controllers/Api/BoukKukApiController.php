<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BoukKukApiController extends Controller
{
    private array $categoryCache = [];
    private array $roleCache = [];
    private array $serviceCache = [];
    private array $userCache = [];

    public function login(Request $request): JsonResponse
    {
        $login = $request->input('email_or_phone');
        $password = $request->input('password');

        $user = DB::table('users')
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$user || $user->is_disabled || !Hash::check((string) $password, $user->password)) {
            return $this->fail('Invalid email or password', 401);
        }

        $token = $user->api_token ?: Str::random(60);
        DB::table('users')->where('id', $user->id)->update(['api_token' => $token]);
        $user = DB::table('users')->where('id', $user->id)->first();

        return $this->ok($this->userArray($user), 'Login successful');
    }

    public function register(Request $request): JsonResponse
    {
        $email = $request->input('email');
        if (DB::table('users')->where('email', $email)->exists()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation failed',
                'errors' => ['email' => ['The email has already been taken.']],
            ], 422);
        }

        $id = DB::table('users')->insertGetId([
            'name' => $request->input('name'),
            'email' => $email,
            'phone' => $request->input('phone'),
            'avatar' => $this->avatarUrl($request->input('name', 'User')),
            'password' => Hash::make((string) $request->input('password')),
            'api_token' => Str::random(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert(['role_id' => 1, 'user_id' => $id]);

        return $this->ok($this->userArray(DB::table('users')->where('id', $id)->first()), 'Registered successfully', 201);
    }

    public function logout(): JsonResponse
    {
        return $this->ok(null, 'Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        return $this->ok($this->userArray($user));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $data = $request->only(['name', 'email', 'phone', 'google_map_url', 'latitude', 'longitude']);
        $data = array_filter($data, fn ($value) => $value !== null);
        $data['updated_at'] = now();
        DB::table('users')->where('id', $user->id)->update($data);

        return $this->ok($this->userArray(DB::table('users')->where('id', $user->id)->first()), 'Profile updated');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $avatar = $request->hasFile('avatar')
            ? $this->storeUploadedFile($request, 'avatar', 'uploads/avatars')
            : $this->avatarUrl($user->name);

        DB::table('users')->where('id', $user->id)->update([
            'avatar' => $avatar,
            'updated_at' => now(),
        ]);

        return $this->ok($this->userArray(DB::table('users')->where('id', $user->id)->first()), 'Avatar updated');
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        DB::table('users')->where('id', $user->id)->update([
            'avatar' => $this->avatarUrl($user->name),
            'updated_at' => now(),
        ]);

        return $this->ok($this->userArray(DB::table('users')->where('id', $user->id)->first()), 'Avatar removed');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make((string) $request->input('new_pass', 'password')),
            'updated_at' => now(),
        ]);

        return $this->ok(null, 'Password changed successfully');
    }

    public function users(Request $request): JsonResponse
    {
        $query = DB::table('users')->orderBy('id');
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->ok($query->get()->map(fn ($user) => $this->userArray($user))->values());
    }

    public function storeUser(Request $request): JsonResponse
    {
        $id = DB::table('users')->insertGetId([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'google_map_url' => $request->input('google_map_url'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'avatar' => $request->hasFile('avatar') ? $this->storeUploadedFile($request, 'avatar', 'uploads/avatars') : $this->avatarUrl($request->input('name', 'User')),
            'password' => Hash::make((string) $request->input('password', 'password')),
            'api_token' => Str::random(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert(['role_id' => (int) $request->input('role_id', 1), 'user_id' => $id]);

        return $this->ok($this->userArray(DB::table('users')->where('id', $id)->first()), 'User created', 201);
    }

    public function showUser(int $id): JsonResponse
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        return $this->ok($this->userArray($user));
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $data = $request->only(['name', 'email', 'phone', 'google_map_url', 'latitude', 'longitude']);
        $data = array_filter($data, fn ($value) => $value !== null);

        if ($request->filled('password')) {
            $data['password'] = Hash::make((string) $request->input('password'));
        }
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $this->storeUploadedFile($request, 'avatar', 'uploads/avatars');
        }
        $data['updated_at'] = now();

        DB::table('users')->where('id', $id)->update($data);
        if ($request->filled('role_id')) {
            $this->replaceRole($id, (int) $request->input('role_id'));
        }

        return $this->ok($this->userArray(DB::table('users')->where('id', $id)->first()), 'User updated');
    }

    public function deleteUser(int $id): JsonResponse
    {
        DB::table('users')->where('id', $id)->delete();
        return $this->ok(null, 'User deleted');
    }

    public function setRole(Request $request, int $id): JsonResponse
    {
        $this->replaceRole($id, (int) $request->input('role_id', 1));
        return $this->ok($this->userArray(DB::table('users')->where('id', $id)->first()), 'Role updated');
    }

    public function disableUser(int $id): JsonResponse
    {
        DB::table('users')->where('id', $id)->update(['is_disabled' => true, 'updated_at' => now()]);
        return $this->ok(null, 'User disabled');
    }

    public function enableUser(int $id): JsonResponse
    {
        DB::table('users')->where('id', $id)->update(['is_disabled' => false, 'updated_at' => now()]);
        return $this->ok(null, 'User enabled');
    }

    public function providers(Request $request): JsonResponse
    {
        $query = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->where('role_user.role_id', 3)
            ->select('users.*')
            ->orderBy('users.id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $this->ok($query->get()->map(fn ($user) => $this->userArray($user))->values());
    }

    public function showProvider(int $id): JsonResponse
    {
        return $this->showUser($id);
    }

    public function categories(): JsonResponse
    {
        return $this->ok(DB::table('categories')->orderBy('id')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $id = DB::table('categories')->insertGetId([
            'name' => $request->input('name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(DB::table('categories')->where('id', $id)->first(), 'Category created', 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        DB::table('categories')->where('id', $id)->update([
            'name' => $request->input('name'),
            'updated_at' => now(),
        ]);

        return $this->ok(DB::table('categories')->where('id', $id)->first(), 'Category updated');
    }

    public function deleteCategory(int $id): JsonResponse
    {
        DB::table('categories')->where('id', $id)->delete();
        return $this->ok(null, 'Category deleted');
    }

    public function services(Request $request): JsonResponse
    {
        $query = DB::table('services')->orderByDesc('id');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($request->filled('category')) {
            $query->where('category_id', (int) $request->query('category'));
        }
        if ($request->filled('creator')) {
            $query->where('creator_id', (int) $request->query('creator'));
        }
        if ($request->query('price_start') !== null && $request->query('price_start') !== '') {
            $query->where('price', '>=', (float) $request->query('price_start'));
        }
        if ($request->query('price_end') !== null && $request->query('price_end') !== '') {
            $query->where('price', '<=', (float) $request->query('price_end'));
        }

        $services = $query->get();
        $this->primeServiceRelations($services->all());
        $items = $services->map(fn ($service) => $this->serviceArray($service))->values();

        return response()->json([
            'result' => true,
            'code' => 1,
            'message' => 'Success',
            ...$this->paginateArray($items->all(), $request),
        ]);
    }

    public function showService(int $id): JsonResponse
    {
        $service = DB::table('services')->where('id', $id)->first();
        if (!$service) {
            return $this->fail('Service not found', 404);
        }

        return $this->ok($this->serviceArray($service));
    }

    public function storeService(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $creatorId = $this->roleId($user?->id) === 3
            ? $user->id
            : (int) ($request->input('creator_id') ?: $this->firstProviderId());

        $id = DB::table('services')->insertGetId([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => (float) $request->input('price', 0),
            'discount' => (int) $request->input('discount', 0),
            'category_id' => (int) $request->input('category_id', 1),
            'creator_id' => $creatorId,
            'image' => $request->hasFile('image') ? $this->storeUploadedFile($request, 'image', 'uploads/services') : asset('demo/service-default.svg'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok($this->serviceArray(DB::table('services')->where('id', $id)->first()), 'Service created', 201);
    }

    public function updateService(Request $request, int $id): JsonResponse
    {
        $data = $request->only(['name', 'description', 'price', 'discount', 'category_id']);
        $data = array_filter($data, fn ($value) => $value !== null);
        if ($request->hasFile('image')) {
            $data['image'] = $this->storeUploadedFile($request, 'image', 'uploads/services');
        }
        $data['updated_at'] = now();

        DB::table('services')->where('id', $id)->update($data);
        return $this->ok($this->serviceArray(DB::table('services')->where('id', $id)->first()), 'Service updated');
    }

    public function deleteService(int $id): JsonResponse
    {
        DB::table('services')->where('id', $id)->delete();
        return $this->ok(null, 'Service deleted');
    }

    public function profileCarts(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        return $this->ok($this->cartSummary($user->id));
    }

    public function storeCart(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $service = DB::table('services')->where('id', (int) $request->input('service_id'))->first();
        if (!$service) {
            return $this->fail('Service not found', 404);
        }

        DB::table('carts')->insert([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'qty' => max(1, (int) $request->input('qty', 1)),
            'price' => (float) $service->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok($this->cartSummary($user->id), 'Added to cart', 201);
    }

    public function deleteCart(Request $request, int $id): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        DB::table('carts')->where('id', $id)->where('user_id', $user->id)->delete();
        return $this->ok($this->cartSummary($user->id), 'Cart item removed');
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $transactionFile = $request->hasFile('transaction_file')
            ? $this->storeUploadedFile($request, 'transaction_file', 'uploads/transactions')
            : asset('demo/transaction-placeholder.svg');

        $cartItems = DB::table('carts')->where('user_id', $user->id)->get();
        foreach ($cartItems as $item) {
            DB::table('payments')->insert([
                'buyer_id' => $user->id,
                'service_id' => $item->service_id,
                'qty' => $item->qty,
                'price' => (float) $item->price * (int) $item->qty,
                'payment_status' => 1,
                'service_status' => 1,
                'transaction_file' => $transactionFile,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('carts')->where('user_id', $user->id)->delete();
        return $this->ok(null, 'Checkout completed');
    }

    public function storeWishlist(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $serviceId = (int) $request->input('service_id');
        DB::table('wishlists')->updateOrInsert(
            ['user_id' => $user->id, 'service_id' => $serviceId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        return $this->ok(null, 'Wishlist updated', 201);
    }

    public function profileWishlists(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $items = DB::table('wishlists')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'service' => $this->serviceArray((int) $item->service_id),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ])
            ->values()
            ->all();

        return response()->json([
            'result' => true,
            'code' => 1,
            'message' => 'Success',
            ...$this->paginateArray($items, $request),
        ]);
    }

    public function deleteWishlist(Request $request, int $id): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        DB::table('wishlists')->where('id', $id)->where('user_id', $user->id)->delete();
        return $this->ok(null, 'Wishlist removed');
    }

    public function paymentCheck(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        $query = DB::table('payments')->orderByDesc('id');

        if ($user && $this->roleId($user->id) === 3) {
            $serviceIds = DB::table('services')->where('creator_id', $user->id)->pluck('id')->all();
            $query->whereIn('service_id', $serviceIds ?: [0]);
        }

        if ($request->query('payment_status') !== null && $request->query('payment_status') !== '') {
            $query->where('payment_status', (int) $request->query('payment_status'));
        }

        $items = $query->get()->map(fn ($payment) => $this->paymentArray($payment))->values()->all();

        return response()->json([
            'result' => true,
            'code' => 1,
            'message' => 'Success',
            ...$this->paginateArray($items, $request),
        ]);
    }

    public function purchased(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (!$user) {
            return $this->fail('Unauthenticated', 401);
        }

        $items = DB::table('payments')
            ->where('buyer_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($payment) => $this->paymentArray($payment))
            ->values()
            ->all();

        $paginated = $this->paginateArray($items, $request);

        return response()->json([
            'result' => true,
            'code' => 1,
            'message' => 'Success',
            'orders' => $paginated['data'],
            ...$paginated,
        ]);
    }

    public function payment(int $id): JsonResponse
    {
        $payment = DB::table('payments')->where('id', $id)->first();
        if (!$payment) {
            return $this->fail('Payment not found', 404);
        }

        return $this->ok($this->paymentArray($payment));
    }

    public function approvePayment(int $id): JsonResponse
    {
        DB::table('payments')->where('id', $id)->update(['payment_status' => 2, 'updated_at' => now()]);
        return $this->ok($this->paymentArray(DB::table('payments')->where('id', $id)->first()), 'Payment approved');
    }

    public function rejectPayment(int $id): JsonResponse
    {
        DB::table('payments')->where('id', $id)->update(['payment_status' => 3, 'updated_at' => now()]);
        return $this->ok($this->paymentArray(DB::table('payments')->where('id', $id)->first()), 'Payment rejected');
    }

    public function setPaymentStatus(Request $request, int $id): JsonResponse
    {
        DB::table('payments')->where('id', $id)->update([
            'service_status' => (int) $request->input('service_status', 1),
            'updated_at' => now(),
        ]);

        return $this->ok($this->paymentArray(DB::table('payments')->where('id', $id)->first()), 'Service status updated');
    }

    public function setPickupSchedule(Request $request, int $id): JsonResponse
    {
        DB::table('payments')->where('id', $id)->update([
            'pickup_schedule' => $request->input('pickup_schedule'),
            'updated_at' => now(),
        ]);

        return $this->ok($this->paymentArray(DB::table('payments')->where('id', $id)->first()), 'Pickup schedule updated');
    }

    public function forgotPassword(): JsonResponse
    {
        return $this->ok(['otp' => '123456'], 'OTP sent');
    }

    public function verifyOtp(): JsonResponse
    {
        return $this->ok(null, 'OTP verified');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $email = $request->input('email') ?: $request->input('otpEmail');
        if ($email) {
            DB::table('users')->where('email', $email)->update([
                'password' => Hash::make((string) ($request->input('new_pass') ?: $request->input('password', 'password'))),
                'updated_at' => now(),
            ]);
        }

        return $this->ok(null, 'Password reset');
    }

    private function ok(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $payload = [
            'result' => true,
            'code' => 1,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    private function fail(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'result' => false,
            'code' => 0,
            'message' => $message,
        ], $status);
    }

    private function authUser(Request $request): ?object
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        return DB::table('users')->where('api_token', $token)->first();
    }

    private function userArray(?object $user): ?array
    {
        if (!$user) {
            return null;
        }

        $this->userCache[(int) $user->id] = $user;
        $roles = $this->rolesForUser((int) $user->id);

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar ?: $this->avatarUrl($user->name),
            'google_map_url' => $user->google_map_url,
            'latitude' => $user->latitude !== null ? (float) $user->latitude : null,
            'longitude' => $user->longitude !== null ? (float) $user->longitude : null,
            'is_disabled' => (int) $user->is_disabled,
            'role' => $roles[0]['name'] ?? null,
            'roles' => $roles,
            'token' => $user->api_token,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function rolesForUser(int $userId): array
    {
        if (array_key_exists($userId, $this->roleCache)) {
            return $this->roleCache[$userId];
        }

        $this->primeRolesForUsers([$userId]);

        return $this->roleCache[$userId] ?? [];
    }

    private function primeRolesForUsers(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $missingIds = array_values(array_filter($userIds, fn ($id) => !array_key_exists($id, $this->roleCache)));

        if ($missingIds === []) {
            return;
        }

        foreach ($missingIds as $id) {
            $this->roleCache[$id] = [];
        }

        DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('role_user.user_id', $missingIds)
            ->orderBy('roles.id')
            ->get(['roles.id', 'roles.name', 'role_user.user_id'])
            ->each(function ($role) {
                $this->roleCache[(int) $role->user_id][] = [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                ];
            });
    }

    private function roleId(?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        $role = DB::table('role_user')->where('user_id', $userId)->orderBy('role_id')->first();
        return $role ? (int) $role->role_id : null;
    }

    private function replaceRole(int $userId, int $roleId): void
    {
        DB::table('role_user')->where('user_id', $userId)->delete();
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $userId]);
    }

    private function firstProviderId(): int
    {
        return (int) (DB::table('role_user')->where('role_id', 3)->value('user_id') ?: 1);
    }

    private function serviceArray(int|object|null $service): ?array
    {
        if (is_int($service)) {
            $service = $this->findService($service);
        }
        if (!$service) {
            return null;
        }

        $this->serviceCache[(int) $service->id] = $service;
        $category = $this->findCategory((int) $service->category_id);
        $creator = $this->findUser((int) $service->creator_id);

        return [
            'id' => (int) $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'price' => (float) $service->price,
            'discount' => (int) $service->discount,
            'image' => $service->image,
            'category' => $category ? [
                'id' => (int) $category->id,
                'name' => $category->name,
            ] : null,
            'creator' => $this->userArray($creator),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function cartSummary(int $userId): array
    {
        $items = DB::table('carts')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();

        $this->primeServicesByIds($items->pluck('service_id')->all());

        $items = $items
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'qty' => (int) $item->qty,
                'price' => (float) $item->price,
                'service' => $this->serviceArray((int) $item->service_id),
            ])
            ->values();

        return [
            'items' => $items,
            'total' => $items->sum(fn ($item) => $item['price'] * $item['qty']),
        ];
    }

    private function paymentArray(?object $payment): ?array
    {
        if (!$payment) {
            return null;
        }

        $buyer = $this->findUser((int) $payment->buyer_id);

        return [
            'id' => (int) $payment->id,
            'buyer' => $this->userArray($buyer),
            'service' => $this->serviceArray((int) $payment->service_id),
            'qty' => (int) $payment->qty,
            'price' => (float) $payment->price,
            'payment_status' => (int) $payment->payment_status,
            'service_status' => (int) $payment->service_status,
            'transaction_file' => $payment->transaction_file,
            'pickup_schedule' => $payment->pickup_schedule,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ];
    }

    private function findCategory(int $id): ?object
    {
        if (!array_key_exists($id, $this->categoryCache)) {
            $this->categoryCache[$id] = DB::table('categories')->where('id', $id)->first();
        }

        return $this->categoryCache[$id];
    }

    private function findService(int $id): ?object
    {
        if (!array_key_exists($id, $this->serviceCache)) {
            $service = DB::table('services')->where('id', $id)->first();
            $this->serviceCache[$id] = $service;
            if ($service) {
                $this->primeServiceRelations([$service]);
            }
        }

        return $this->serviceCache[$id];
    }

    private function findUser(int $id): ?object
    {
        if (!array_key_exists($id, $this->userCache)) {
            $this->userCache[$id] = DB::table('users')->where('id', $id)->first();
        }

        return $this->userCache[$id];
    }

    private function primeServicesByIds(array $serviceIds): void
    {
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
        $missingIds = array_values(array_filter($serviceIds, fn ($id) => !array_key_exists($id, $this->serviceCache)));

        if ($missingIds === []) {
            return;
        }

        $services = DB::table('services')->whereIn('id', $missingIds)->get();
        foreach ($services as $service) {
            $this->serviceCache[(int) $service->id] = $service;
        }
        $this->primeServiceRelations($services->all());
    }

    private function primeServiceRelations(array $services): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map(fn ($service) => (int) $service->category_id, $services))));
        $creatorIds = array_values(array_unique(array_filter(array_map(fn ($service) => (int) $service->creator_id, $services))));

        $missingCategoryIds = array_values(array_filter($categoryIds, fn ($id) => !array_key_exists($id, $this->categoryCache)));
        if ($missingCategoryIds !== []) {
            DB::table('categories')->whereIn('id', $missingCategoryIds)->get()->each(function ($category) {
                $this->categoryCache[(int) $category->id] = $category;
            });
            foreach ($missingCategoryIds as $id) {
                $this->categoryCache[$id] ??= null;
            }
        }

        $missingCreatorIds = array_values(array_filter($creatorIds, fn ($id) => !array_key_exists($id, $this->userCache)));
        if ($missingCreatorIds !== []) {
            DB::table('users')->whereIn('id', $missingCreatorIds)->get()->each(function ($user) {
                $this->userCache[(int) $user->id] = $user;
            });
            foreach ($missingCreatorIds as $id) {
                $this->userCache[$id] ??= null;
            }
        }

        $this->primeRolesForUsers($creatorIds);
    }

    private function paginateArray(array $items, Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, (int) $request->query('per_page', count($items) ?: 20));
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'data' => array_values($data),
            'paginate' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function storeUploadedFile(Request $request, string $field, string $folder): string
    {
        $file = $request->file($field);
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $target = public_path($folder);

        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $file->move($target, $name);

        return asset($folder.'/'.$name);
    }

    private function avatarUrl(string $name): string
    {
        return 'https://ui-avatars.com/api/?background=0D6EFD&color=fff&name='.urlencode($name);
    }
}
