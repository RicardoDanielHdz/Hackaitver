<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'job_title',
        'assigned_branch_code',
        'supplier_rfc',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Determina la ruta de redirección según el rol asignado.
     */
    public function getDashboardRoute(): string
    {
        return match ($this->role) {
            'gerente' => route('dashboard.gerente'),
            'jefe_embarque' => route('dashboard.embarques'),
            'operador' => route('dashboard.recepcion'),
            'proveedor' => route('dashboard.proveedor'),
            'sucursal' => route('dashboard.sucursal'),
            default => route('dashboard.gerente'),
        };
    }

    public function isGerente(): bool
    {
        return $this->role === 'gerente';
    }

    public function isJefeEmbarque(): bool
    {
        return $this->role === 'jefe_embarque';
    }

    public function isOperador(): bool
    {
        return $this->role === 'operador';
    }

    public function isProveedor(): bool
    {
        return $this->role === 'proveedor';
    }

    public function isSucursal(): bool
    {
        return $this->role === 'sucursal';
    }
}
