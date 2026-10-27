<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Data operations</p><h2>Portal data at a glance</h2><p>Import, validate, review, and publish admission, examination, intake, placement, expense, and result records from one place.</p></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Admission records</span><strong><?= number_format($stats['records']) ?></strong><small>Admission dataset</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Universities</span><strong><?= number_format($stats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $stats['latest_update'] ? esc(date('d M Y', strtotime($stats['latest_update']))) : 'Not available' ?></strong><small><?= $stats['latest_update'] ? esc(date('h:i A', strtotime($stats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>

<?php if (auth()->user()->can('examinations.view')): ?>
<div class="d-flex justify-content-between align-items-end mb-2"><div><p class="eyebrow mb-1">Examination data</p><h3 class="h5 mb-0">Examination overview</h3></div><a href="<?= url_to('examinations.index') ?>">View records</a></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Examination records</span><strong><?= number_format($examinationStats['records']) ?></strong><small>Published and draft data</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Universities</span><strong><?= number_format($examinationStats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $examinationStats['latest_update'] ? esc(date('d M Y', strtotime($examinationStats['latest_update']))) : 'Not available' ?></strong><small><?= $examinationStats['latest_update'] ? esc(date('h:i A', strtotime($examinationStats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>
<?php endif ?>

<?php if (auth()->user()->can('placements.view')): ?>
<div class="d-flex justify-content-between align-items-end mb-2"><div><p class="eyebrow mb-1">Placement data</p><h3 class="h5 mb-0">Placement overview</h3></div><a href="<?= url_to('placements.index') ?>">View records</a></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Placement records</span><strong><?= number_format($placementStats['records']) ?></strong><small>Published and draft data</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Universities</span><strong><?= number_format($placementStats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Companies</span><strong><?= number_format($placementStats['companies']) ?></strong><small>Unique employers</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $placementStats['latest_update'] ? esc(date('d M Y', strtotime($placementStats['latest_update']))) : 'Not available' ?></strong><small><?= $placementStats['latest_update'] ? esc(date('h:i A', strtotime($placementStats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>
<?php endif ?>

<?php if (auth()->user()->can('intake.view')): ?>
<div class="d-flex justify-content-between align-items-end mb-2"><div><p class="eyebrow mb-1">Intake data</p><h3 class="h5 mb-0">Intake overview</h3></div><a href="<?= url_to('intake.index') ?>">View records</a></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Intake records</span><strong><?= number_format($intakeStats['records']) ?></strong><small>Published and draft data</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Universities</span><strong><?= number_format($intakeStats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $intakeStats['latest_update'] ? esc(date('d M Y', strtotime($intakeStats['latest_update']))) : 'Not available' ?></strong><small><?= $intakeStats['latest_update'] ? esc(date('h:i A', strtotime($intakeStats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>
<?php endif ?>

<?php if (auth()->user()->can('expenses.view')): ?>
<div class="d-flex justify-content-between align-items-end mb-2"><div><p class="eyebrow mb-1">Expense data</p><h3 class="h5 mb-0">Expense overview</h3></div><a href="<?= url_to('expenses.index') ?>">View records</a></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Expense records</span><strong><?= number_format($expenseStats['records']) ?></strong><small>Published and draft data</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Universities</span><strong><?= number_format($expenseStats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $expenseStats['latest_update'] ? esc(date('d M Y', strtotime($expenseStats['latest_update']))) : 'Not available' ?></strong><small><?= $expenseStats['latest_update'] ? esc(date('h:i A', strtotime($expenseStats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>
<?php endif ?>

<?php if (auth()->user()->can('results.view')): ?>
<div class="d-flex justify-content-between align-items-end mb-2"><div><p class="eyebrow mb-1">Result data</p><h3 class="h5 mb-0">Result overview</h3></div><a href="<?= url_to('results.index') ?>">View records</a></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Result records</span><strong><?= number_format($resultStats['records']) ?></strong><small>Published and draft data</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Universities</span><strong><?= number_format($resultStats['universities']) ?></strong><small>Unique AISHE codes</small></div></div>
    <div class="col-sm-6 col-xl-4"><div class="metric-card"><span>Last data update</span><strong class="metric-date"><?= $resultStats['latest_update'] ? esc(date('d M Y', strtotime($resultStats['latest_update']))) : 'Not available' ?></strong><small><?= $resultStats['latest_update'] ? esc(date('h:i A', strtotime($resultStats['latest_update']))) : 'Import a workbook to begin' ?></small></div></div>
</div>
<?php endif ?>

<div class="row g-4">
    <?php if (auth()->user()->can('imports.view')): ?>
    <div class="<?= auth()->user()->can('api.docs') ? 'col-xl-8' : 'col-12' ?>">
        <section class="panel h-100">
            <div class="panel-header"><div><p class="eyebrow mb-1">Activity</p><h3>Recent imports</h3></div><a href="<?= url_to('admissions.logs') ?>">View all logs</a></div>
            <?php if ($recentImports === []): ?>
                <div class="empty-state"><h4>No imports yet</h4><p>Upload the admission Excel template to create the first dataset.</p></div>
            <?php else: ?>
                <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
                <?php foreach ($recentImports as $job): ?><tr>
                    <td><a class="record-link" href="<?= url_to('admissions.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
                    <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
                    <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
                </tr><?php endforeach ?>
                </tbody></table></div>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>
    <?php if (auth()->user()->can('api.docs')): ?>
    <div class="<?= auth()->user()->can('imports.view') ? 'col-xl-4' : 'col-12' ?>">
        <section class="panel h-100">
            <div class="panel-header"><div><p class="eyebrow mb-1">Integration</p><h3>Admission API</h3></div><span class="status status-completed">Ready</span></div>
            <p class="text-secondary">The JSON API supports full and incremental retrieval with page sizes up to 1,000 records.</p>
            <div class="endpoint-box"><span>POST</span><code>/api/v1/admissions</code></div>
            <a class="btn btn-outline-primary w-100 mt-3" href="<?= url_to('api.documentation') ?>">Open API documentation</a>
        </section>
    </div>
    <?php endif ?>
</div>

<?php if (auth()->user()->can('examination_imports.view')): ?>
<section class="panel mt-4">
    <div class="panel-header"><div><p class="eyebrow mb-1">Examination activity</p><h3>Recent examination imports</h3></div><a href="<?= url_to('examinations.logs') ?>">View all logs</a></div>
    <?php if ($recentExaminationImports === []): ?>
        <div class="empty-state compact"><h4>No examination imports yet</h4><p>Upload the examination Excel template to create the first dataset.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Workflow</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
        <?php foreach ($recentExaminationImports as $job): ?><tr>
            <td><a class="record-link" href="<?= url_to('examinations.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
            <td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php endif ?>

<?php if (auth()->user()->can('intake_imports.view')): ?>
<section class="panel mt-4">
    <div class="panel-header"><div><p class="eyebrow mb-1">Intake activity</p><h3>Recent intake imports</h3></div><a href="<?= url_to('intake.logs') ?>">View all logs</a></div>
    <?php if ($recentIntakeImports === []): ?>
        <div class="empty-state compact"><h4>No intake imports yet</h4><p>Upload the intake Excel template to create the first dataset.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Workflow</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
        <?php foreach ($recentIntakeImports as $job): ?><tr>
            <td><a class="record-link" href="<?= url_to('intake.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
            <td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php endif ?>

<?php if (auth()->user()->can('placement_imports.view')): ?>
<section class="panel mt-4">
    <div class="panel-header"><div><p class="eyebrow mb-1">Placement activity</p><h3>Recent placement imports</h3></div><a href="<?= url_to('placements.logs') ?>">View all logs</a></div>
    <?php if ($recentPlacementImports === []): ?>
        <div class="empty-state compact"><h4>No placement imports yet</h4><p>Upload the placement Excel template to create the first dataset.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Workflow</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
        <?php foreach ($recentPlacementImports as $job): ?><tr>
            <td><a class="record-link" href="<?= url_to('placements.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
            <td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php endif ?>

<?php if (auth()->user()->can('expense_imports.view')): ?>
<section class="panel mt-4">
    <div class="panel-header"><div><p class="eyebrow mb-1">Expense activity</p><h3>Recent expense imports</h3></div><a href="<?= url_to('expenses.logs') ?>">View all logs</a></div>
    <?php if ($recentExpenseImports === []): ?>
        <div class="empty-state compact"><h4>No expense imports yet</h4><p>Upload the expense Excel template to create the first dataset.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Workflow</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
        <?php foreach ($recentExpenseImports as $job): ?><tr>
            <td><a class="record-link" href="<?= url_to('expenses.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
            <td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php endif ?>

<?php if (auth()->user()->can('result_imports.view')): ?>
<section class="panel mt-4">
    <div class="panel-header"><div><p class="eyebrow mb-1">Result activity</p><h3>Recent result imports</h3></div><a href="<?= url_to('results.logs') ?>">View all logs</a></div>
    <?php if ($recentResultImports === []): ?>
        <div class="empty-state compact"><h4>No result imports yet</h4><p>Upload the result Excel template to create the first dataset.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>File</th><th>Status</th><th>Workflow</th><th>Inserted</th><th>Updated</th><th>Errors</th></tr></thead><tbody>
        <?php foreach ($recentResultImports as $job): ?><tr>
            <td><a class="record-link" href="<?= url_to('results.logs.show', $job['id']) ?>"><?= esc($job['original_filename']) ?></a></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td>
            <td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['inserted_rows']) ?></td><td><?= number_format($job['updated_rows']) ?></td><td><?= number_format($job['error_rows']) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
