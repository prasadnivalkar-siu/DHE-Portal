<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $primaryRole = $currentRoles[0] ?? config('AuthGroups')->defaultGroup; ?>
<div class="page-heading"><div><p class="eyebrow">Profile &amp; security</p><h2>My account</h2><p>Review your identity and keep your administrator password secure.</p></div></div>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="panel h-100">
            <div class="account-identity"><span class="user-avatar large"><?= esc(strtoupper(substr((string) $user->username, 0, 1))) ?></span><div><h3><?= esc($user->username) ?></h3><p><?= esc($user->getEmail()) ?></p></div></div>
            <dl class="api-list mt-4"><div><dt>Role</dt><dd><span class="role-chip role-<?= esc($primaryRole) ?>"><?= esc($roles[$primaryRole]['title'] ?? ucfirst(str_replace('_', ' ', $primaryRole))) ?></span></dd></div><div><dt>Account status</dt><dd><span class="status status-completed">Enabled</span></dd></div><div><dt>Last active</dt><dd><?= $user->last_active ? esc($user->last_active->format('d M Y, h:i A')) : 'Current session' ?></dd></div></dl>
        </section>
    </div>
    <div class="col-xl-7">
        <section class="panel">
            <p class="eyebrow">Security</p><h3>Change password</h3><p class="text-secondary">Choose a unique password with at least 12 characters.</p>
            <form method="post" action="<?= url_to('admin.account.password') ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="current_password">Current password</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                <div class="row g-3"><div class="col-md-6"><label class="form-label" for="password">New password</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div><div class="col-md-6"><label class="form-label" for="password_confirm">Confirm new password</label><input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required></div></div>
                <div class="form-actions justify-content-end"><button class="btn btn-primary" type="submit">Update password</button></div>
            </form>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
