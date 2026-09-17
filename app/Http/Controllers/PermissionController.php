<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * NOTE: this controller was referenced by routes/web.php's module list
 * (uri "permissions", permission "role_permissions") but did not exist in
 * the base repo — any request to it, and `php artisan route:list`, fatally
 * errored with "Class PermissionController does not exist". In practice no
 * "role_permissions.*" permission was seeded either, so the route was also
 * unreachable behind check.permission middleware. Adding this minimal,
 * read-only implementation so the route resolves; it is not part of the
 * travel-agency scope.
 */
class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        return view('roles.permissions', compact('permissions'));
    }

    public function print()
    {
        return redirect()->route('permissions.index');
    }
}
