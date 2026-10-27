<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$roleTitle = $roles[$currentRole]['title'] ?? (config('AuthGroups')->groups[$currentRole]['title'] ?? ucfirst(str_replace('_', ' ', $currentRole)));
$isSelf = (int) auth()->id() === (int) $managedUser->id;
?>
<div class="page-heading">
    <div><p class="eyebrow">User administration</p><h2><?= esc($managedUser->username) ?></h2><p><?= esc($managedUser->getEmail() ?? 'No email identity') ?></p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('admin.users') ?>">Back to users</a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel mb-4">
            <div class="panel-header"><div><p class="eyebrow mb-1">Access control</p><h3>Assigned role</h3></div><span class="role-chip role-<?= esc($currentRole) ?>"><?= esc($roleTitle) ?></span></div>
            <?php if ($isSelf): ?>
                <div class="alert alert-info mb-0">Your own role cannot be changed. Ask another Super Administrator if a role change is required.</div>
            <?php elseif (auth()->user()->can('roles.assign')): ?>
                <form method="post" action="<?= url_to('admin.users.role', $managedUser->id) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label" for="role">Portal role</label><select class="form-select" id="role" name="role" required><?php foreach ($roles as $key => $details): ?><option value="<?= esc($key) ?>" <?= $currentRole === $key ? 'selected' : '' ?>><?= esc($details['title']) ?> — <?= esc($details['description']) ?></option><?php endforeach ?></select></div><div class="col-md-4"><button class="btn btn-primary w-100" type="submit">Update role</button></div></div>
                </form>
            <?php endif ?>
        </section>

        <section class="panel">
            <div class="panel-header"><div><p class="eyebrow mb-1">Account status</p><h3>Sign-in access</h3></div><span class="status <?= $managedUser->isBanned() ? 'status-failed' : 'status-completed' ?>"><?= $managedUser->isBanned() ? 'Disabled' : 'Enabled' ?></span></div>
            <?php if ($isSelf): ?>
                <div class="alert alert-info mb-0">You cannot disable your own account.</div>
            <?php else: ?>
                <p class="text-secondary">Disabling an account blocks future sign-ins without deleting its identity or audit history.</p>
                <form method="post" action="<?= url_to('admin.users.status', $managedUser->id) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $managedUser->isBanned() ? 'enabled' : 'disabled' ?>">
                    <button class="btn <?= $managedUser->isBanned() ? 'btn-outline-success' : 'btn-outline-danger' ?>" type="submit"><?= $managedUser->isBanned() ? 'Enable account' : 'Disable account' ?></button>
                </form>
            <?php endif ?>
        </section>
    </div>
    <div class="col-xl-4">
        <aside class="panel user-summary">
            <p class="eyebrow">Account details</p>
            <dl class="api-list"><div><dt>User ID</dt><dd>#<?= (int) $managedUser->id ?></dd></div><div><dt>Role</dt><dd><?= esc($roleTitle) ?></dd></div><div><dt>Last active</dt><dd><?= $managedUser->last_active ? esc($managedUser->last_active->format('d M Y, h:i A')) : 'Never' ?></dd></div><div><dt>Created</dt><dd><?= $managedUser->created_at ? esc($managedUser->created_at->format('d M Y')) : 'Not recorded' ?></dd></div></dl>
        </aside>
    </div>
</div>
<?= $this->endSection() ?>
