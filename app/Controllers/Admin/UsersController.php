<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AdminAuditService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

final class UsersController extends BaseController
{
    public function index(): string
    {
        $model = model(UserModel::class);

        return view('admin/users/index', [
            'title' => 'User Management',
            'users' => $model->withIdentities()->withGroups()->orderBy('id', 'DESC')->paginate(25, 'users'),
            'pager' => $model->pager,
            'roleConfig' => config('AuthGroups')->groups,
        ]);
    }

    public function create(): string
    {
        return view('admin/users/create', [
            'title' => 'Create User',
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $roles = $this->assignableRoles();
        $rules = [
            'username' => 'required|min_length[3]|max_length[30]|regex_match[/^[a-zA-Z0-9.]+$/]|is_unique[users.username]',
            'email' => 'required|valid_email|max_length[254]|is_unique[auth_identities.secret]',
            'password' => 'required|min_length[12]|max_length[255]',
            'password_confirm' => 'required|matches[password]',
            'role' => 'required|in_list[' . implode(',', array_keys($roles)) . ']',
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = new User([
            'username' => trim((string) $this->request->getPost('username')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))),
            'password' => (string) $this->request->getPost('password'),
            'active' => 1,
        ]);
        $passwordResult = service('passwords')->check((string) $this->request->getPost('password'), $user);
        if (! $passwordResult->isOK()) {
            return redirect()->back()->withInput()->with('errors', ['password' => $passwordResult->reason()]);
        }

        try {
            $users = model(UserModel::class);
            if (! $users->save($user)) {
                return redirect()->back()->withInput()->with('errors', $users->errors());
            }
            $created = $users->findById($users->getInsertID());
            $role = (string) $this->request->getPost('role');
            $created->syncGroups($role);
            (new AdminAuditService())->record('user.created', (int) $created->id, ['role' => $role]);

            return redirect()->to(url_to('admin.users'))->with('success', 'User created successfully.');
        } catch (Throwable $exception) {
            log_message('error', 'User creation failed: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'The user could not be created.');
        }
    }

    public function edit(int $id): string|RedirectResponse
    {
        $user = $this->findUser($id);
        if (! $this->mayManage($user)) {
            return redirect()->to(url_to('admin.users'))->with('error', 'You cannot manage this user.');
        }

        return view('admin/users/edit', [
            'title' => 'Edit User',
            'managedUser' => $user,
            'roles' => $this->assignableRoles(),
            'currentRole' => $user->getGroups()[0] ?? config('AuthGroups')->defaultGroup,
        ]);
    }

    public function updateRole(int $id): RedirectResponse
    {
        $user = $this->findUser($id);
        $actor = auth()->user();
        $roles = $this->assignableRoles();
        $role = (string) $this->request->getPost('role');

        if ((int) $actor->id === $id) {
            return redirect()->back()->with('error', 'You cannot change your own role.');
        }
        if (! $this->mayManage($user) || ! isset($roles[$role])) {
            return redirect()->back()->with('error', 'That role assignment is not permitted.');
        }

        $currentRole = $user->getGroups()[0] ?? null;
        if ($currentRole === 'superadmin' && $role !== 'superadmin' && ! $this->hasAnotherUsableSuperadmin($id)) {
            return redirect()->back()->with('error', 'At least one enabled Super Administrator must remain.');
        }

        $user->syncGroups($role);
        (new AdminAuditService())->record('user.role_changed', $id, ['from' => $currentRole, 'to' => $role]);

        return redirect()->to(url_to('admin.users.edit', $id))->with('success', 'User role updated.');
    }

    public function updateStatus(int $id): RedirectResponse
    {
        $user = $this->findUser($id);
        $actor = auth()->user();
        $status = (string) $this->request->getPost('status');

        if ((int) $actor->id === $id && $status === 'disabled') {
            return redirect()->back()->with('error', 'You cannot disable your own account.');
        }
        if (! $this->mayManage($user) || ! in_array($status, ['enabled', 'disabled'], true)) {
            return redirect()->back()->with('error', 'That account status change is not permitted.');
        }
        if ($status === 'disabled' && $user->inGroup('superadmin') && ! $this->hasAnotherUsableSuperadmin($id)) {
            return redirect()->back()->with('error', 'At least one enabled Super Administrator must remain.');
        }

        if ($status === 'enabled') {
            $user->unBan();
        } else {
            $user->ban('Disabled by a portal administrator.');
        }
        (new AdminAuditService())->record('user.status_changed', $id, ['status' => $status]);

        return redirect()->to(url_to('admin.users.edit', $id))->with('success', 'User status updated.');
    }

    /** @return array<string, array<string, string>> */
    private function assignableRoles(): array
    {
        $roles = config('AuthGroups')->groups;
        if (! auth()->user()->inGroup('superadmin')) {
            unset($roles['superadmin']);
        }

        return $roles;
    }

    private function findUser(int $id): User
    {
        $user = model(UserModel::class)->withIdentities()->withGroups()->findById($id);
        if ($user === null) {
            throw PageNotFoundException::forPageNotFound('User not found.');
        }

        return $user;
    }

    private function mayManage(User $target): bool
    {
        return auth()->user()->inGroup('superadmin') || ! $target->inGroup('superadmin');
    }

    private function hasAnotherUsableSuperadmin(int $excludedUserId): bool
    {
        return db_connect()->table('auth_groups_users g')
            ->join('users u', 'u.id = g.user_id')
            ->where('g.group', 'superadmin')
            ->where('g.user_id !=', $excludedUserId)
            ->groupStart()
                ->where('u.status IS NULL', null, false)
                ->orWhere('u.status !=', 'banned')
            ->groupEnd()
            ->where('u.deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
    }
}
