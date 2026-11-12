<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$actions = ['retrieve' => 'Retrieve data', 'acknowledge' => 'Acknowledgment', 'import' => 'Submit data', 'import_status' => 'Submission status'];
$successRate = $totals['requests'] === 0 ? null : round($totals['successful'] / $totals['requests'] * 100, 1);
$averageMs = $totals['requests'] === 0 ? null : round($totals['duration_total'] / $totals['requests']);
$maxTrend = max(1, ...array_column($trend, 'requests'));
?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2>Vendor activity</h2><p>Monitor API traffic, deliveries, and submitted datasets across all six categories.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= url_to('admin.vendors.activity') ?>">Refresh</a>
        <form method="post" action="<?= url_to('admin.vendors.activity.report') ?>"><?= csrf_field() ?><button class="btn btn-outline-primary" type="submit">Download requests CSV</button></form>
        <?php if (auth()->user()->can('vendors.view')): ?><a class="btn btn-primary" href="<?= url_to('admin.vendors') ?>">Manage vendors</a><?php endif ?>
    </div>
</div>
<section class="panel mb-4" aria-label="Activity filters">
    <form method="post" action="<?= url_to('admin.vendors.activity.filter') ?>" class="row g-3 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="page" value="1">
        <div class="col-lg-3 col-md-6"><label class="form-label" for="activity-vendor">Vendor</label><select id="activity-vendor" class="form-select" name="vendor_id"><option value="">All vendors</option><?php foreach ($vendors as $vendor): ?><option value="<?= (int) $vendor['id'] ?>" <?= $filters['vendor_id'] === (int) $vendor['id'] ? 'selected' : '' ?>><?= esc($vendor['name'] . ' (' . $vendor['vendor_code'] . ')' . ((int) $vendor['is_active'] === 0 ? ' — Inactive' : '')) ?></option><?php endforeach ?></select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label" for="activity-category">Category</label><select class="form-select" id="activity-category" name="category"><option value="">All categories</option><?php foreach ($categories as $key => $label): ?><option value="<?= esc($key, 'attr') ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label" for="activity-from">From date</label><input class="form-control" id="activity-from" type="date" name="date_from" value="<?= esc($filters['date_from'], 'attr') ?>" required></div>
        <div class="col-lg-2 col-md-6"><label class="form-label" for="activity-to">To date</label><input class="form-control" id="activity-to" type="date" name="date_to" value="<?= esc($filters['date_to'], 'attr') ?>" required></div>
        <div class="col-lg-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply filters</button><button class="btn btn-outline-secondary" type="submit" name="clear_filters" value="1" formnovalidate>Reset</button></div>
    </form>
    <p class="small text-secondary mt-3 mb-0"><?= esc($filters['date_from']) ?> through <?= esc($filters['date_to']) ?>, inclusive · <?= esc(date_default_timezone_get()) ?> · <?= number_format($activeVendors) ?> active of <?= number_format(count($vendors)) ?> registered vendors.</p>
</section>
<div class="row g-3 mb-4">
    <?php foreach ([['API requests', number_format($totals['requests']), number_format($totals['replays']) . ' idempotent replays'], ['Successful requests', $successRate === null ? '—' : $successRate . '%', number_format($totals['successful']) . ' HTTP 2xx responses'], ['Failed requests', number_format($totals['failed']), number_format($totals['denied']) . ' access denied · ' . number_format($totals['rate_limited']) . ' rate limited'], ['Average response', $averageMs === null ? '—' : number_format($averageMs) . ' ms', 'Completed requests including retries']] as [$label, $value, $detail]): ?>
        <div class="col-xl-3 col-sm-6"><div class="metric-card"><span><?= esc($label) ?></span><strong><?= esc($value) ?></strong><small><?= esc($detail) ?></small></div></div>
    <?php endforeach ?>
</div>
<div class="row g-3 mb-2">
    <?php foreach ([['Records sent', 'sent', 'Recorded delivery occurrences'], ['Acknowledged', 'acknowledged', 'Vendor confirmed receipt'], ['Pending acknowledgment', 'pending', 'Awaiting a vendor response'], ['Reported failed', 'delivery_failed', 'Vendor reported delivery failure']] as [$label, $key, $detail]): ?>
        <div class="col-xl-3 col-sm-6"><div class="mini-metric"><span><?= esc($label) ?></span><strong><?= number_format($totals[$key]) ?></strong><small class="text-secondary"><?= esc($detail) ?></small></div></div>
    <?php endforeach ?>
</div>
<p class="small text-secondary mb-4">Record totals include redeliveries and reflect the current acknowledgment status of retained deliveries in this date range. Delivery tracking resets remove that delivery history.</p>
<section class="panel mb-4">
    <div class="panel-header flex-wrap"><div><h3>Request activity</h3><p class="small text-secondary mb-0">Hover or focus a bar for counts. Ranges longer than 31 days are grouped by week.</p></div><div class="small text-nowrap"><span class="activity-legend activity-legend-ok"></span>HTTP 2xx–3xx <span class="activity-legend activity-legend-error ms-3"></span>HTTP 4xx–5xx</div></div>
    <?php if ($totals['requests'] === 0): ?>
        <div class="empty-state py-4"><h4>No recorded requests in this range</h4><p>Request history starts with API calls made after activity tracking was installed. Retained deliveries and submissions are shown separately below.</p></div>
    <?php else: ?>
        <div class="activity-trend" role="group" aria-label="Request counts by date">
            <?php foreach ($trend as $point): ?>
                <div class="activity-trend-point" tabindex="0" aria-label="<?= esc($point['label'] . ': ' . $point['requests'] . ' requests, ' . $point['failed'] . ' failures', 'attr') ?>" title="<?= esc($point['label'] . ': ' . $point['requests'] . ' requests, ' . $point['failed'] . ' failures', 'attr') ?>">
                    <div class="activity-trend-track"><div class="activity-trend-bar" style="height: <?= round($point['requests'] / $maxTrend * 100, 2) ?>%"><div class="activity-trend-failed" style="height: <?= $point['requests'] > 0 ? round($point['failed'] / $point['requests'] * 100, 2) : 0 ?>%"></div></div></div>
                    <small><?= esc(str_replace('Week of ', '', $point['label'])) ?></small>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <p class="small text-secondary mt-3 mb-0">Requests made with a recognized active vendor key, including denied category/institution access, retries, acknowledgments, and submission-status calls. Request history <?= $trackingStartedAt ? 'begins ' . esc($trackingStartedAt) : 'will begin with the next API call' ?>. Credentials and record payloads are never stored in this activity log.</p>
</section>
<section class="panel p-0 overflow-hidden mb-4">
    <div class="panel-header px-4 pt-4"><div><h3>Activity by category</h3><p class="small text-secondary mb-0">Delivery and submission totals include retained history from before request tracking was added.</p></div></div>
    <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Category</th><th>Requests</th><th>Failed requests</th><th>Sent</th><th>Acknowledged</th><th>Pending</th><th>Reported failed</th><th>Submitted batches</th><th>Accepted rows</th><th>Rejected rows</th></tr></thead><tbody>
        <?php foreach ($categoryRows as $row): ?><tr><td><strong><?= esc($row['label']) ?></strong></td><?php foreach (['requests', 'failed', 'sent', 'acknowledged', 'pending', 'delivery_failed', 'batches', 'accepted', 'rejected'] as $key): ?><td><?= number_format($row[$key]) ?></td><?php endforeach ?></tr><?php endforeach ?>
    </tbody></table></div>
</section>
<div class="row g-4 mb-4">
    <div class="col-xl-8"><section class="panel p-0 overflow-hidden h-100"><div class="panel-header px-4 pt-4"><div><h3>Most active vendors</h3><p class="small text-secondary mb-0">Top 20 for the selected period, ordered by requests, records sent, then batches.</p></div></div>
        <?php if ($vendorRows === []): ?><div class="empty-state py-4"><p>No retained activity matches these filters.</p></div><?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Vendor</th><th>Requests</th><th>Failed</th><th>Sent</th><th>Pending</th><th>Batches</th><th></th></tr></thead><tbody>
        <?php foreach (array_slice($vendorRows, 0, 20) as $vendor): ?><tr><td><strong><?= esc($vendor['name']) ?></strong><small class="table-subtext"><?= esc($vendor['vendor_code']) ?><?= $vendor['is_active'] !== null && (int) $vendor['is_active'] === 0 ? ' · Inactive' : '' ?></small></td><?php foreach (['requests', 'failed', 'sent', 'pending', 'batches'] as $key): ?><td><?= number_format($vendor[$key]) ?></td><?php endforeach ?><td><?php if ($vendor['id'] !== null): ?><form method="post" action="<?= url_to('admin.vendors.activity.filter') ?>"><?= csrf_field() ?><input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>"><input type="hidden" name="page" value="1"><button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">View activity</button></form><?php endif ?></td></tr><?php endforeach ?>
        </tbody></table></div><?php endif ?>
    </section></div>
    <div class="col-xl-4"><section class="panel h-100"><h3>Submitted datasets</h3><p class="small text-secondary">Current workflow status for batches submitted in the selected period.</p>
        <?php foreach (['draft' => 'Draft', 'validated' => 'Validated', 'approved' => 'Approved', 'published' => 'Published'] as $key => $label): ?><div class="d-flex align-items-center justify-content-between py-2 border-bottom"><span class="status status-<?= $key ?>"><?= $label ?></span><strong><?= number_format($totals[$key]) ?></strong></div><?php endforeach ?>
        <p class="small text-secondary mt-3 mb-0"><?= number_format($totals['accepted']) ?> accepted rows · <?= number_format($totals['duplicates']) ?> duplicate rows · <?= number_format($totals['rejected']) ?> rejected rows. Review datasets in each category's Import Logs.</p>
    </section></div>
</div>
<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3>Recent requests</h3><p class="small text-secondary mb-0"><?= number_format($requestTotal) ?> requests match the filters · newest first · 25 per page.</p></div></div>
    <?php if ($requests === []): ?><div class="empty-state py-4"><p>No recorded API requests match these filters.</p></div><?php else: ?>
    <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Time / ID</th><th>Vendor</th><th>Category</th><th>Action</th><th>HTTP response</th><th>Duration</th><th>IP address</th></tr></thead><tbody>
        <?php foreach ($requests as $request): ?><tr><td class="text-nowrap"><?= esc($request['requested_at']) ?><small class="table-subtext">#<?= (int) $request['id'] ?></small></td><td><strong><?= esc($request['vendor_name'] ?? $request['vendor_code']) ?></strong><small class="table-subtext"><?= esc($request['vendor_code']) ?></small></td><td><?= esc($categories[$request['category']] ?? $request['category']) ?></td><td><?= esc($actions[$request['action']] ?? $request['action']) ?><small class="table-subtext"><?= esc($request['method']) ?><?= (int) $request['is_replay'] === 1 ? ' · Idempotent replay' : '' ?></small></td><td><span class="status status-<?= (int) $request['response_code'] < 400 ? 'completed' : 'failed' ?>"><?= (int) $request['response_code'] ?></span></td><td class="text-nowrap"><?= number_format((int) $request['duration_ms']) ?> ms</td><td><?= esc($request['request_ip']) ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <div class="panel-footer d-flex flex-wrap align-items-center justify-content-between gap-2"><span class="small text-secondary">Page <?= number_format($page) ?> of <?= number_format($pages) ?></span><div class="d-flex gap-2"><?php foreach (['Previous' => $page - 1, 'Next' => $page + 1] as $label => $target): ?><form method="post" action="<?= url_to('admin.vendors.activity.filter') ?>"><?= csrf_field() ?><input type="hidden" name="page" value="<?= $target ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $target < 1 || $target > $pages ? 'disabled' : '' ?>><?= $label ?></button></form><?php endforeach ?></div></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
