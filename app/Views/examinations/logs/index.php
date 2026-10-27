<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Audit trail</p><h2>Import logs</h2><p>Review every examination workbook import and its row-level outcome.</p></div>
    <?php if (auth()->user()->can('examinations.import')): ?><a class="btn btn-primary" href="<?= url_to('examinations.import') ?>">New import</a><?php endif ?>
</div>

<section class="panel p-0 overflow-hidden">
    <?php if ($jobs === []): ?>
        <div class="empty-state"><h4>No import logs yet</h4><p>Import a workbook to create the first log.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>#</th><th>File</th><th>Job status</th><th>Workflow</th><th>Total</th><th>Valid</th><th>Warnings</th><th>Duplicates</th><th>Rejected</th><th>Started</th><th></th></tr></thead><tbody>
        <?php foreach ($jobs as $job): ?><tr>
            <td><?= number_format($job['id']) ?></td><td><strong><?= esc($job['original_filename']) ?></strong><small class="table-subtext"><?= esc(substr($job['file_hash'], 0, 12)) ?>…</small></td>
            <td><span class="status status-<?= esc($job['status']) ?>"><?= esc(str_replace('_', ' ', $job['status'])) ?></span></td><td><span class="status status-<?= esc($job['workflow_status'] ?? 'draft') ?>"><?= esc($job['workflow_status'] ?? 'draft') ?></span></td>
            <td><?= number_format($job['total_rows']) ?></td><td><?= number_format($job['valid_rows'] ?? 0) ?></td><td><?= number_format($job['warning_rows'] ?? 0) ?></td><td><?= number_format($job['duplicate_rows'] ?? 0) ?></td><td><?= number_format($job['rejected_rows'] ?? 0) ?></td>
            <td><?= esc(date('d M Y, h:i A', strtotime($job['started_at'] ?? $job['created_at']))) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= url_to('examinations.logs.show', $job['id']) ?>">Details</a></td>
        </tr><?php endforeach ?>
        </tbody></table></div><div class="panel-footer"><?= $pager->links('imports', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>

