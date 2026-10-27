<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$hasPermission = static function (array $grants, string $permission): bool {
    if (in_array($permission, $grants, true)) {
        return true;
    }
    [$scope] = explode('.', $permission, 2);
    return in_array($scope . '.*', $grants, true);
};
?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2>Roles &amp; permissions</h2><p>Review the fixed access profiles used across the DHE Portal.</p></div>
    <?php if (auth()->user()->can('users.view')): ?><a class="btn btn-outline-primary" href="<?= url_to('admin.users') ?>">Assign user roles</a><?php endif ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($roles as $key => $role): ?>
        <div class="col-md-6 col-xl"><article class="role-card"><span class="role-chip role-<?= esc($key) ?>"><?= esc($role['title']) ?></span><strong><?= number_format((int) ($counts[$key] ?? 0)) ?></strong><small>assigned <?= (int) ($counts[$key] ?? 0) === 1 ? 'user' : 'users' ?></small><p><?= esc($role['description']) ?></p></article></div>
    <?php endforeach ?>
</div>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Permission matrix</h3><p class="text-secondary mb-0">Roles are centrally configured so access remains predictable and reviewable.</p></div></div>
    <div class="table-responsive">
        <table class="table portal-table permission-table mb-0">
            <thead><tr><th>Permission</th><?php foreach ($roles as $role): ?><th class="text-center"><?= esc($role['title']) ?></th><?php endforeach ?></tr></thead>
            <tbody><?php foreach ($permissions as $permission => $description): ?><tr><td><strong><?= esc($permission) ?></strong><small class="table-subtext"><?= esc($description) ?></small></td><?php foreach ($roles as $key => $role): ?><td class="text-center"><span class="permission-mark <?= $hasPermission($matrix[$key] ?? [], $permission) ? 'allowed' : 'denied' ?>" aria-label="<?= $hasPermission($matrix[$key] ?? [], $permission) ? 'Allowed' : 'Not allowed' ?>"><?= $hasPermission($matrix[$key] ?? [], $permission) ? '✓' : '—' ?></span></td><?php endforeach ?></tr><?php endforeach ?></tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
