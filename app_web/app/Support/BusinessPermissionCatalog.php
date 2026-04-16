<?php

namespace App\Support;

use App\Models\User;

class BusinessPermissionCatalog
{
    public const CREATE_SALE = 'crear venta';
    public const EDIT_SALE = 'editar venta';
    public const DELETE_SALE = 'eliminar venta';

    public const CREATE_PRODUCTS = 'crear productos';
    public const EDIT_PRODUCTS = 'editar productos';
    public const DELETE_PRODUCTS = 'eliminar productos';

    public const CREATE_CATEGORIES = 'crear categorias';
    public const VIEW_CATEGORIES = 'ver categorias';
    public const EDIT_CATEGORIES = 'editar categorias';
    public const DELETE_CATEGORIES = 'eliminar categorias';

    public static function all(): array
    {
        $groups = config('business_permissions.groups', []);

        return collect($groups)
            ->flatten()
            ->map(fn ($permission) => strtolower(trim((string) $permission)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function can(?User $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if (self::bypassesChecks($user)) {
            return true;
        }

        $permission = strtolower(trim($permission));

        return $user->getAllPermissions()
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->contains($permission);
    }

    public static function canAny(?User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($user, (string) $permission)) {
                return true;
            }
        }

        return false;
    }

    public static function ensure(User $user, string $permission): void
    {
        abort_unless(
            self::can($user, $permission),
            403,
            'No tienes permisos para realizar esta acción.'
        );
    }

    public static function ensureAny(User $user, array $permissions): void
    {
        abort_unless(
            self::canAny($user, $permissions),
            403,
            'No tienes permisos para acceder a este módulo.'
        );
    }

    private static function bypassesChecks(User $user): bool
    {
        return $user->hasRole('admin') || strtolower((string) $user->business_role) === 'owner';
    }
}
