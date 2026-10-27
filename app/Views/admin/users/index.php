<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2>Portal users</h2><p>Create accounts, assign roles, and control access to the administration console.</p></div>
    <?php if (auth()->user()->can('users.create')): ?><a class="btn btn-primary" href="<?= url_to('admin.users.create') ?>">Create user</a><?php endif ?>
</div>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Authorized accounts</h3><p class="text-secondary mb-0">Access is granted through a single assigned role.</p></div><span class="count-pill"><?= number_format($pager->getTotal('users')) ?> users</span></div>
    <?php if ($users === []): ?>
        <div class="empty-state"><h4>No users found</h4><p>Create the first account to begin assigning portal access.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table portal-table align-middle mb-0">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last active</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($users as $portalUser): ?>
                    <?php
                    $groups = $portalUser->getGroups() ?? [];
                    $role = $groups[0] ?? config('AuthGroups')->defaultGroup;
                    $roleLabel = $roleConfig[$role]['title'] ?? ucfirst(str_replace('_', ' ', $role));
                    $manageable = auth()->user()->inGroup('superadmin') || ! $portalUser->inGroup('superadmin');
                    ?>
                    <tr>
                        <td><strong><?= esc($portalUser->username) ?></strong><small class="table-subtext"><?= esc($portalUser->getEmail() ?? 'No email identity') ?></small></td>
                        <td><span class="role-chip role-<?= esc($role) ?>"><?= esc($roleLabel) ?></span></td>
                        <td><span class="status <?= $portalUser->isBanned() ? 'status-failed' : 'status-completed' ?>"><?= $portalUser->isBanned() ? 'Disabled' : 'Enabled' ?></span></td>
                        <td><?= $portalUser->last_active ? esc($portalUser->last_active->format('d M Y')) : 'Never' ?><small class="table-subtext"><?= $portalUser->last_active ? esc($portalUser->last_active->format('h:i A')) : 'No sign-in recorded' ?></small></td>
                        <td class="text-end">
                            <?php if (auth()->user()->can('users.edit') && $manageable): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url_to('admin.users.edit', $portalUser->id) ?>">Manage</a>
                            <?php else: ?><span class="text-secondary small">Protected</span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="panel-footer"><?= $pager->links('users', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
