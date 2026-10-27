<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

final class RolesController extends BaseController
{
    public function index(): string
    {
        $config = config('AuthGroups');
        $rows = db_connect()->table('auth_groups_users')
            ->select('group')
            ->select('COUNT(DISTINCT user_id) AS user_count', false)
            ->groupBy('group')
            ->get()
            ->getResultArray();

        return view('admin/roles/index', [
            'title' => 'Roles & Permissions',
            'roles' => $config->groups,
            'permissions' => $config->permissions,
            'matrix' => $config->matrix,
            'counts' => array_column($rows, 'user_count', 'group'),
        ]);
    }
}
