<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$summary = json_decode((string) ($job['summary'] ?? ''), true) ?: [];
$status = (string) $job['status'];
$workflow = (string) ($job['workflow_status'] ?? 'draft');
$actor = static fn (?int $id): string => $id ? ($actors[$id] ?? ('User #' . $id)) : '—';
?>
<div class="page-heading">
    <div><p class="eyebrow">Import #<?= esc($job['id']) ?></p><h2><?= esc($job['original_filename']) ?></h2><p><?= esc($summary['message'] ?? 'Result dataset import and publishing audit trail.') ?></p></div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ((int) ($job['rejected_rows'] ?? $job['error_rows']) > 0): ?><a class="btn btn-outline-danger" href="<?= url_to('results.logs.rejected', $job['id']) ?>">Download rejected rows</a><?php endif ?>
        <a class="btn btn-outline-secondary" href="<?= url_to('results.logs') ?>">Back to logs</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl"><div class="mini-metric"><span>Job status</span><strong><span class="status status-<?= esc($status) ?>"><?= esc(str_replace('_', ' ', $status)) ?></span></strong></div></div>
    <div class="col-6 col-xl"><div class="mini-metric"><span>Total rows</span><strong><?= number_format((int) $job['total_rows']) ?></strong></div></div>
    <div class="col-6 col-xl"><div class="mini-metric"><span>Valid</span><strong class="text-success"><?= number_format((int) ($job['valid_rows'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl"><div class="mini-metric"><span>Warnings</span><strong class="text-warning"><?= number_format((int) ($job['warning_rows'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl"><div class="mini-metric"><span>Duplicates</span><strong><?= number_format((int) ($job['duplicate_rows'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl"><div class="mini-metric"><span>Rejected</span><strong class="text-danger"><?= number_format((int) ($job['rejected_rows'] ?? 0)) ?></strong></div></div>
</div>

<?php if (in_array($status, ['queued_preview', 'previewing', 'queued', 'processing'], true)): ?>
<section class="panel mb-4" id="jobLiveProgress" data-progress-url="<?= url_to('results.import.progress', $job['progress_token']) ?>" data-return-url="<?= current_url() ?>">
    <div class="d-flex align-items-center gap-3"><div class="spinner-border text-primary" role="status"></div><div><h3 class="mb-1"><?= in_array($status, ['queued_preview', 'previewing'], true) ? 'Validating workbook' : 'Importing database records' ?></h3><p class="text-secondary mb-0">This page refreshes automatically when the background job changes state.</p></div></div>
</section>
<?php endif ?>

<?php if ($status === 'preview_ready'): ?>
<section class="panel mb-4 border-primary"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div><p class="eyebrow mb-1">Confirmation required</p><h3 class="mb-1">Validation is complete</h3><p class="text-secondary mb-0">Review the sample and counts below. Confirming writes valid and warning rows as a Draft dataset; rejected rows remain in the downloadable report.</p></div>
    <?php if (auth()->user()->can('results.import')): ?><form method="post" action="<?= url_to('results.logs.confirm', $job['id']) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Confirm database import</button></form><?php endif ?>
</div></section>
<?php endif ?>

<div class="row g-4 mb-4">
    <div class="col-xl-8"><section class="panel h-100">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><div><p class="eyebrow mb-1">Publishing workflow</p><h3 class="mb-1">Draft → Validated → Approved → Published</h3><p class="text-secondary mb-0">Vendor API access begins only after publication.</p></div><span class="status status-<?= esc($workflow) ?> align-self-start"><?= esc(ucfirst($workflow)) ?></span></div>
        <div class="workflow-track">
            <?php foreach (['draft' => 'Draft', 'validated' => 'Validated', 'approved' => 'Approved', 'published' => 'Published'] as $key => $label): ?>
                <?php $order = ['draft', 'validated', 'approved', 'published']; $done = array_search($key, $order, true) <= array_search($workflow, $order, true); ?>
                <div class="workflow-node <?= $done ? 'done' : '' ?>"><span></span><strong><?= esc($label) ?></strong></div>
            <?php endforeach ?>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-4">
            <?php if (in_array($status, ['completed', 'completed_with_errors'], true) && $workflow === 'draft' && auth()->user()->can('result_imports.review')): ?><form method="post" action="<?= url_to('results.logs.validate', $job['id']) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Mark validated</button></form><?php endif ?>
            <?php if ($workflow === 'validated' && auth()->user()->can('result_imports.approve')): ?><form method="post" action="<?= url_to('results.logs.approve', $job['id']) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Approve dataset</button></form><?php endif ?>
            <?php if ($workflow === 'approved' && auth()->user()->can('result_imports.publish')): ?><form method="post" action="<?= url_to('results.logs.publish', $job['id']) ?>"><?= csrf_field() ?><button class="btn btn-success" type="submit">Publish to vendor API</button></form><?php endif ?>
        </div>
    </section></div>
    <div class="col-xl-4"><section class="panel h-100"><p class="eyebrow mb-1">Accountability</p><h3>Dataset actors</h3><dl class="row mb-0 small">
        <dt class="col-5">Imported</dt><dd class="col-7"><?= esc($actor(isset($job['imported_by']) ? (int) $job['imported_by'] : null)) ?><br><span class="text-secondary"><?= esc($job['created_at'] ?? '—') ?></span></dd>
        <dt class="col-5">Reviewed</dt><dd class="col-7"><?= esc($actor(isset($job['reviewed_by']) ? (int) $job['reviewed_by'] : null)) ?><br><span class="text-secondary"><?= esc($job['reviewed_at'] ?? '—') ?></span></dd>
        <dt class="col-5">Approved</dt><dd class="col-7"><?= esc($actor(isset($job['approved_by']) ? (int) $job['approved_by'] : null)) ?><br><span class="text-secondary"><?= esc($job['approved_at'] ?? '—') ?></span></dd>
        <dt class="col-5">Published</dt><dd class="col-7"><?= esc($actor(isset($job['published_by']) ? (int) $job['published_by'] : null)) ?><br><span class="text-secondary"><?= esc($job['published_at'] ?? '—') ?></span></dd>
    </dl></section></div>
</div>

<?php if ($previewRows !== []): ?>
<section class="panel p-0 overflow-hidden mb-4">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Import preview</h3><p class="text-secondary mb-0">First <?= number_format(count($previewRows)) ?> non-empty workbook rows and their validation outcome.</p></div></div>
    <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Excel row</th><th>Validation</th><th>Academic year</th><th>Exam year</th><th>Season</th><th>University</th><th>Organization</th><th>Student</th><th>Course</th><th>Result</th><th>Details</th></tr></thead><tbody>
        <?php foreach ($previewRows as $row): ?><tr><td><?= number_format((int) $row['rowNumber']) ?></td><td><span class="status status-<?= esc($row['status']) ?>"><?= esc(ucfirst($row['status'])) ?></span></td><td><?= esc($row['academicYear']) ?></td><td><?= esc($row['examYear']) ?></td><td><?= esc($row['season']) ?></td><td><?= esc($row['univCode']) ?></td><td><strong><?= esc($row['organizationCode']) ?></strong><small class="table-subtext"><?= esc($row['organizationName']) ?></small></td><td><?= esc($row['studentId']) ?></td><td><?= esc($row['course']) ?></td><td><?= esc($row['result']) ?></td><td><?= esc(implode(' ', $row['messages'] ?? [])) ?></td></tr><?php endforeach ?>
    </tbody></table></div>
</section>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-9"><section class="panel p-0 overflow-hidden">
        <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Rejected row details</h3><p class="text-secondary mb-0">Correct these values in Excel and import the workbook again.</p></div></div>
        <?php if ($errors === []): ?><div class="empty-state compact"><h4>No rejected rows</h4><p>Every scanned row passed structural validation.</p></div>
        <?php else: ?><div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Excel row</th><th>Field</th><th>Submitted value</th><th>Error</th></tr></thead><tbody>
            <?php foreach ($errors as $error): ?><tr><td><?= esc($error['row_number'] ?? '—') ?></td><td><code><?= esc($error['field_name'] ?? '—') ?></code></td><td class="raw-value"><?= esc($error['raw_value'] ?? '—') ?></td><td><?= esc($error['error_message']) ?></td></tr><?php endforeach ?>
        </tbody></table></div><div class="panel-footer"><?= $pager->links('errors', 'bootstrap_full') ?></div><?php endif ?>
    </section></div>
    <div class="col-xl-3"><section class="panel"><h3>Job controls</h3><p class="text-secondary small">Queued or failed jobs can be safely cancelled. Failed validation or import jobs can be retried from their failed stage.</p><div class="d-grid gap-2">
        <?php if ($status === 'failed' && auth()->user()->can('results.import')): ?><form method="post" action="<?= url_to('results.logs.retry', $job['id']) ?>"><?= csrf_field() ?><button class="btn btn-outline-primary w-100" type="submit">Retry <?= esc($job['failure_stage'] ?? 'job') ?></button></form><?php endif ?>
        <?php if (in_array($status, ['queued_preview', 'preview_ready', 'queued', 'failed'], true) && auth()->user()->can('results.import')): ?><form method="post" action="<?= url_to('results.logs.cancel', $job['id']) ?>" onsubmit="return confirm('Cancel this import job?')"><?= csrf_field() ?><button class="btn btn-outline-danger w-100" type="submit">Cancel job</button></form><?php endif ?>
        <div class="small text-secondary mt-2">Inserted <?= number_format((int) $job['inserted_rows']) ?><br>Updated <?= number_format((int) $job['updated_rows']) ?><br>Skipped <?= number_format((int) $job['skipped_rows']) ?><br>Import errors <?= number_format((int) $job['error_rows']) ?></div>
    </div></section></div>
</div>
<?= $this->endSection() ?>



