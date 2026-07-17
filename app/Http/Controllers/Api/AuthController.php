<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const TOKEN_LIFETIME_SECONDS = 86400;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($this->isSuperAdmin($credentials['email'], $credentials['password'])) {
            return $this->loginResponse(
                id: 'super-admin',
                name: 'Super Admin',
                email: (string) config('services.warehouse_auth.super_admin_email'),
                role: 'co',
            );
        }

        $warehouse = Warehouse::query()
            ->where('manager_email', $credentials['email'])
            ->where('status', 'active')
            ->first();

        if ($warehouse?->password && Hash::check($credentials['password'], $warehouse->password)) {
            return $this->loginResponse(
                id: $warehouse->uuid ?? $warehouse->id,
                name: $warehouse->manager_name ?: $warehouse->warehouse_name,
                email: $warehouse->manager_email,
                role: 'wo',
            );
        }

        $region = Region::query()
            ->where('manager_email', $credentials['email'])
            ->where('status', 'active')
            ->first();

        if ($region?->password && Hash::check($credentials['password'], $region->password)) {
            return $this->loginResponse(
                id: $region->uuid ?? $region->id,
                name: $region->manager_name ?: $region->region_name,
                email: $region->manager_email,
                role: 'ro',
            );
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid email or password.',
        ], 401);
    }

    private function isSuperAdmin(string $email, string $password): bool
    {
        $configuredEmail = Str::lower((string) config('services.warehouse_auth.super_admin_email'));
        $configuredPassword = (string) config('services.warehouse_auth.super_admin_password');

        return $configuredEmail !== ''
            && $configuredPassword !== ''
            && hash_equals($configuredEmail, Str::lower($email))
            && hash_equals($configuredPassword, $password);
    }

    private function loginResponse(int|string $id, string $name, string $email, string $role): JsonResponse
    {
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addSeconds(self::TOKEN_LIFETIME_SECONDS);
        $token = Crypt::encryptString(json_encode([
            'sub' => (string) $id,
            'email' => $email,
            'role' => $role,
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'role' => $role,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_LIFETIME_SECONDS,
            'user' => [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ],
        ]);
    }
}
