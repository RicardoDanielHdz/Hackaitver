<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    /**
     * Registra un nuevo usuario (interno CEDIS o externo) clasificado por rol RBAC.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'role' => ['required', 'in:gerente,jefe_embarque,operador,proveedor,sucursal'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'assigned_branch_code' => ['nullable', 'string', 'max:16', 'required_if:role,sucursal'],
            'supplier_rfc' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = $validated['role'];
        $defaultTitle = match ($role) {
            'gerente' => 'Gerente General de Operaciones CEDIS',
            'jefe_embarque' => 'Jefe de Embarque y Tráfico',
            'operador' => 'Operador de Almacén y Recepción',
            'proveedor' => 'Proveedor Externo Autorizado',
            'sucursal' => 'Receptor en Sucursal Destino',
        };

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
            'role' => $role,
            'job_title' => trim((string) ($validated['job_title'] ?? '')) ?: $defaultTitle,
            'assigned_branch_code' => $role === 'sucursal' ? strtoupper(trim((string) $validated['assigned_branch_code'])) : null,
            'supplier_rfc' => $role === 'proveedor' && ! empty($validated['supplier_rfc'])
                ? strtoupper(trim((string) $validated['supplier_rfc']))
                : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => sprintf('Usuario %s (%s) registrado correctamente.', $user->name, $user->email),
            'user' => $this->formatUser($user),
            'users' => User::query()->orderBy('role')->orderBy('name')->get()->map(fn (User $u): array => $this->formatUser($u))->values(),
        ], 201);
    }

    /**
     * Activa o suspende una cuenta de usuario (impidiendo que el gerente se suspenda a sí mismo).
     */
    public function toggleStatus(User $user): JsonResponse
    {
        if (Auth::id() === $user->id) {
            return response()->json([
                'message' => 'No puedes suspender tu propia cuenta de Gerente activa.',
            ], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => $user->is_active
                ? sprintf('Cuenta %s reactivada.', $user->email)
                : sprintf('Cuenta %s suspendida.', $user->email),
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        $isExternal = in_array($user->role, ['proveedor', 'sucursal'], true);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'classification' => $isExternal ? 'EXTERNO' : 'INTERNO CEDIS',
            'job_title' => $user->job_title,
            'assigned_branch_code' => $user->assigned_branch_code,
            'supplier_rfc' => $user->supplier_rfc,
            'is_active' => (bool) $user->is_active,
            'created_at' => $user->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }
}
