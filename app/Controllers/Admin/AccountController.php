<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AdminAuditService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;

final class AccountController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        return view('admin/account/index', [
            'title' => 'My Account',
            'user' => $user,
            'roles' => config('AuthGroups')->groups,
            'currentRoles' => $user->getGroups() ?? [],
        ]);
    }

    public function updatePassword(): RedirectResponse
    {
        $rules = [
            'current_password' => 'required',
            'password' => 'required|min_length[12]|max_length[255]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $user = auth()->user();
        $identity = $user->getEmailIdentity();
        if ($identity === null || ! service('passwords')->verify((string) $this->request->getPost('current_password'), (string) $identity->secret2)) {
            return redirect()->back()->with('errors', ['current_password' => 'The current password is incorrect.']);
        }

        $newPassword = (string) $this->request->getPost('password');
        $passwordResult = service('passwords')->check($newPassword, $user);
        if (! $passwordResult->isOK()) {
            return redirect()->back()->with('errors', ['password' => $passwordResult->reason()]);
        }

        $user->password = $newPassword;
        model(UserModel::class)->save($user);
        (new AdminAuditService())->record('account.password_changed', (int) $user->id);

        return redirect()->to(url_to('admin.account'))->with('success', 'Password updated successfully.');
    }
}
