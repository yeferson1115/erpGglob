<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(config('business_permissions.groups', []))
            ->flatten()
            ->map(fn ($permission) => strtolower(trim((string) $permission)))
            ->filter()
            ->unique()
            ->values();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = collect(config('business_permissions.groups', []))
            ->flatten()
            ->map(fn ($permission) => strtolower(trim((string) $permission)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        Permission::query()
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
