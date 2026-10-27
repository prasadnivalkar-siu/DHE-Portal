<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DHE Portal for category-wise higher education data management">
    <title><?= esc($title ?? 'DHE Portal') ?> · DHE Portal</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= base_url('assets/css/portal.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/portal.css') ?>">
</head>
<body>
<?php
$path = uri_string();
$currentUser = auth()->user();
$currentGroups = $currentUser->getGroups() ?? [];
$currentRole = $currentGroups[0] ?? config('AuthGroups')->defaultGroup;
$roleTitle = config('AuthGroups')->groups[$currentRole]['title'] ?? ucfirst(str_replace('_', ' ', $currentRole));
$initial = strtoupper(substr((string) ($currentUser->username ?: $currentUser->getEmail()), 0, 1));

$categoryMenus = [];
$admissionItems = [];
if ($currentUser->can('admissions.view')) {
    $admissionItems[] = ['label' => 'Data', 'url' => url_to('admissions.index'), 'active' => str_starts_with($path, 'admin/admissions') && ! str_contains($path, '/import') && ! str_contains($path, '/logs')];
}
if ($currentUser->can('admissions.import')) {
    $admissionItems[] = ['label' => 'Import Excel', 'url' => url_to('admissions.import'), 'active' => $path === 'admin/admissions/import'];
}
if ($currentUser->can('imports.view')) {
    $admissionItems[] = ['label' => 'Import Logs', 'url' => url_to('admissions.logs'), 'active' => str_starts_with($path, 'admin/admissions/logs')];
}
if ($currentUser->can('api.docs')) {
    $admissionItems[] = ['label' => 'API Documentation', 'url' => url_to('api.documentation'), 'active' => $path === 'admin/api-documentation'];
}
if ($admissionItems !== []) {
    $categoryMenus[] = ['label' => 'Admission', 'active' => str_starts_with($path, 'admin/admissions') || $path === 'admin/api-documentation', 'items' => $admissionItems];
}

$examinationItems = [];
if ($currentUser->can('examinations.view')) {
    $examinationItems[] = ['label' => 'Data', 'url' => url_to('examinations.index'), 'active' => str_starts_with($path, 'admin/examinations') && ! str_contains($path, '/import') && ! str_contains($path, '/logs')];
}
if ($currentUser->can('examinations.import')) {
    $examinationItems[] = ['label' => 'Import Excel', 'url' => url_to('examinations.import'), 'active' => $path === 'admin/examinations/import'];
}
if ($currentUser->can('examination_imports.view')) {
    $examinationItems[] = ['label' => 'Import Logs', 'url' => url_to('examinations.logs'), 'active' => str_starts_with($path, 'admin/examinations/logs')];
}
if ($currentUser->can('examination.api.docs')) {
    $examinationItems[] = ['label' => 'API Documentation', 'url' => url_to('examination.api.documentation'), 'active' => $path === 'admin/examination-api-documentation'];
}
if ($examinationItems !== []) {
    $categoryMenus[] = ['label' => 'Examination', 'active' => str_starts_with($path, 'admin/examinations') || $path === 'admin/examination-api-documentation', 'items' => $examinationItems];
}

$intakeItems = [];
if ($currentUser->can('intake.view')) {
    $intakeItems[] = ['label' => 'Data', 'url' => url_to('intake.index'), 'active' => str_starts_with($path, 'admin/intake') && ! str_contains($path, '/import') && ! str_contains($path, '/logs') && ! str_contains($path, '/admission-summary') && ! str_contains($path, '-api-')];
}
if ($currentUser->can('intake.admissions.report')) {
    $intakeItems[] = ['label' => 'Admissions Summary', 'url' => url_to('intake.admission_summary'), 'active' => str_starts_with($path, 'admin/intake/admission-summary')];
}
if ($currentUser->can('intake.import')) {
    $intakeItems[] = ['label' => 'Import Excel', 'url' => url_to('intake.import'), 'active' => $path === 'admin/intake/import'];
}
if ($currentUser->can('intake_imports.view')) {
    $intakeItems[] = ['label' => 'Import Logs', 'url' => url_to('intake.logs'), 'active' => str_starts_with($path, 'admin/intake/logs')];
}
if ($currentUser->can('intake.api.docs')) {
    $intakeItems[] = ['label' => 'API Documentation', 'url' => url_to('intake.api.documentation'), 'active' => $path === 'admin/intake-api-documentation'];
}
if ($intakeItems !== []) {
    $categoryMenus[] = ['label' => 'Intake', 'active' => str_starts_with($path, 'admin/intake'), 'items' => $intakeItems];
}

$placementItems = [];
if ($currentUser->can('placements.view')) {
    $placementItems[] = ['label' => 'Data', 'url' => url_to('placements.index'), 'active' => str_starts_with($path, 'admin/placements') && ! str_contains($path, '/import') && ! str_contains($path, '/logs')];
}
if ($currentUser->can('placements.import')) {
    $placementItems[] = ['label' => 'Import Excel', 'url' => url_to('placements.import'), 'active' => $path === 'admin/placements/import'];
}
if ($currentUser->can('placement_imports.view')) {
    $placementItems[] = ['label' => 'Import Logs', 'url' => url_to('placements.logs'), 'active' => str_starts_with($path, 'admin/placements/logs')];
}
if ($currentUser->can('placements.api.docs')) {
    $placementItems[] = ['label' => 'API Documentation', 'url' => url_to('placements.api.documentation'), 'active' => $path === 'admin/placement-api-documentation'];
}
if ($placementItems !== []) {
    $categoryMenus[] = ['label' => 'Placement', 'active' => str_starts_with($path, 'admin/placements') || $path === 'admin/placement-api-documentation', 'items' => $placementItems];
}

$expenseItems = [];
if ($currentUser->can('expenses.view')) {
    $expenseItems[] = ['label' => 'Data', 'url' => url_to('expenses.index'), 'active' => str_starts_with($path, 'admin/expenses') && ! str_contains($path, '/import') && ! str_contains($path, '/logs')];
}
if ($currentUser->can('expenses.import')) {
    $expenseItems[] = ['label' => 'Import Excel', 'url' => url_to('expenses.import'), 'active' => $path === 'admin/expenses/import'];
}
if ($currentUser->can('expense_imports.view')) {
    $expenseItems[] = ['label' => 'Import Logs', 'url' => url_to('expenses.logs'), 'active' => str_starts_with($path, 'admin/expenses/logs')];
}
if ($currentUser->can('expenses.api.docs')) {
    $expenseItems[] = ['label' => 'API Documentation', 'url' => url_to('expenses.api.documentation'), 'active' => $path === 'admin/expense-api-documentation'];
}
if ($expenseItems !== []) {
    $categoryMenus[] = ['label' => 'Expenses', 'active' => str_starts_with($path, 'admin/expenses') || $path === 'admin/expense-api-documentation', 'items' => $expenseItems];
}

$resultItems = [];
if ($currentUser->can('results.view')) {
    $resultItems[] = ['label' => 'Data', 'url' => url_to('results.index'), 'active' => str_starts_with($path, 'admin/results') && ! str_contains($path, '/import') && ! str_contains($path, '/logs')];
}
if ($currentUser->can('results.import')) {
    $resultItems[] = ['label' => 'Import Excel', 'url' => url_to('results.import'), 'active' => $path === 'admin/results/import'];
}
if ($currentUser->can('result_imports.view')) {
    $resultItems[] = ['label' => 'Import Logs', 'url' => url_to('results.logs'), 'active' => str_starts_with($path, 'admin/results/logs')];
}
if ($currentUser->can('results.api.docs')) {
    $resultItems[] = ['label' => 'API Documentation', 'url' => url_to('results.api.documentation'), 'active' => $path === 'admin/result-api-documentation'];
}
if ($resultItems !== []) {
    $categoryMenus[] = ['label' => 'Result', 'active' => str_starts_with($path, 'admin/results') || $path === 'admin/result-api-documentation', 'items' => $resultItems];
}

$categoryOrder = array_flip(['Admission', 'Examination', 'Result', 'Intake', 'Expenses', 'Placement']);
usort(
    $categoryMenus,
    static fn (array $left, array $right): int =>
        ($categoryOrder[$left['label']] ?? PHP_INT_MAX) <=> ($categoryOrder[$right['label']] ?? PHP_INT_MAX),
);
?>
<div class="app-shell">
    <aside class="portal-sidebar d-none d-lg-flex">
        <a class="brand" href="<?= url_to('dashboard') ?>" aria-label="DHE Portal home">
            <span class="brand-mark">DHE</span>
            <span><strong>DHE Portal</strong><small>Administration Console</small></span>
        </a>
        <nav class="portal-nav" aria-label="Main navigation">
            <span class="nav-label">Overview</span>
            <a class="<?= $path === 'admin' ? 'active' : '' ?>" href="<?= url_to('dashboard') ?>"><span>Dashboard</span></a>

            <?php if ($categoryMenus !== []): ?>
                <span class="nav-label">Data categories</span>
                <?php foreach ($categoryMenus as $category): ?>
                    <details class="nav-category <?= $category['active'] ? 'active-category' : '' ?>" <?= $category['active'] ? 'open' : '' ?>>
                        <summary><span><?= esc($category['label']) ?></span><span class="nav-chevron" aria-hidden="true"></span></summary>
                        <div class="nav-submenu">
                            <?php foreach ($category['items'] as $item): ?>
                                <a class="<?= $item['active'] ? 'active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['label']) ?></a>
                            <?php endforeach ?>
                        </div>
                    </details>
                <?php endforeach ?>
            <?php endif ?>

            <?php if ($currentUser->can('users.view') || $currentUser->can('roles.view')): ?>
                <span class="nav-label">Administration</span>
                <?php if ($currentUser->can('users.view')): ?>
                    <a class="<?= str_starts_with($path, 'admin/users') ? 'active' : '' ?>" href="<?= url_to('admin.users') ?>"><span>Users</span></a>
                <?php endif ?>
                <?php if ($currentUser->can('roles.view')): ?>
                    <a class="<?= str_starts_with($path, 'admin/roles') ? 'active' : '' ?>" href="<?= url_to('admin.roles') ?>"><span>Roles &amp; Permissions</span></a>
                <?php endif ?>
            <?php endif ?>
        </nav>
        <div class="sidebar-foot"><span class="status-dot"></span>Secure admin session</div>
    </aside>

    <div class="portal-main">
        <header class="portal-topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav">Menu</button>
            <div><span class="topbar-kicker">Directorate of Higher Education</span><h1><?= esc($title ?? 'DHE Portal') ?></h1></div>
            <div class="dropdown ms-auto">
                <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar"><?= esc($initial) ?></span>
                    <span class="d-none d-md-block text-start"><strong><?= esc($currentUser->username) ?></strong><small><?= esc($roleTitle) ?></small></span>
                    <span aria-hidden="true">▾</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><span class="dropdown-item-text small text-secondary"><?= esc($currentUser->getEmail()) ?></span></li>
                    <li><a class="dropdown-item" href="<?= url_to('admin.account') ?>">My Account</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url_to('logout') ?>">Sign out</a></li>
                </ul>
            </div>
        </header>

        <main class="portal-content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert"><?= esc(session()->getFlashdata('success')) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            <?php endif ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= esc(session()->getFlashdata('error')) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            <?php endif ?>
            <?php if ($errors = session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger" role="alert"><strong>Please correct the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
            <?php endif ?>
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="mobileNavLabel">DHE Portal</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body mobile-links">
        <div class="mobile-user"><span class="user-avatar"><?= esc($initial) ?></span><div><strong><?= esc($currentUser->username) ?></strong><small><?= esc($roleTitle) ?></small></div></div>
        <a href="<?= url_to('dashboard') ?>">Dashboard</a>
        <?php foreach ($categoryMenus as $category): ?>
            <details class="mobile-category <?= $category['active'] ? 'active-category' : '' ?>" <?= $category['active'] ? 'open' : '' ?>>
                <summary><span><?= esc($category['label']) ?></span><span class="nav-chevron" aria-hidden="true"></span></summary>
                <div class="mobile-submenu">
                    <?php foreach ($category['items'] as $item): ?>
                        <a class="<?= $item['active'] ? 'active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['label']) ?></a>
                    <?php endforeach ?>
                </div>
            </details>
        <?php endforeach ?>
        <?php if ($currentUser->can('users.view')): ?><a href="<?= url_to('admin.users') ?>">Users</a><?php endif ?>
        <?php if ($currentUser->can('roles.view')): ?><a href="<?= url_to('admin.roles') ?>">Roles &amp; Permissions</a><?php endif ?>
        <a href="<?= url_to('admin.account') ?>">My Account</a>
        <a class="text-danger" href="<?= url_to('logout') ?>">Sign out</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="<?= base_url('assets/js/portal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/portal.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
