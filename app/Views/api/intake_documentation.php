<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">Integration</p><h2>Intake Data API</h2><p>Retrieve full or incrementally updated intake records as paginated JSON.</p></div><span class="api-version">v1</span></div>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel mb-4"><div class="endpoint-line"><span>POST</span><code><?= esc(base_url('api/v1/intake')) ?></code></div><p class="mt-3 mb-0 text-secondary">Send <code>Content-Type: application/json</code>. When an API key is configured, also send it in the <code>X-API-Key</code> header.</p></section>
        <section class="panel mb-4"><h3>Request payload</h3><pre class="code-block"><code>{
  "Vendor_Code": "vendor-01",
  "Academic_Year": "2024-25",
  "University_AISHE_Code": "U-1234",
  "lastUpdateDate": "2026-09-16",
  "PageNo": 1,
  "PageSize": 1000,
  "Idempotency_Key": "vendor-01-sync-20260916-page-1"
}</code></pre></section>
        <section class="panel"><h3>Response structure</h3><pre class="code-block"><code>{
  "count": 1,
  "data": [{ "Academic Year": "2024-25", "University AISHE Code": "U-1234", "Programme Code": "030142", "TotalIntake": 110 }],
  "pagination": { "currentPage": 2, "pageSize": 1000, "total": 6909, "totalPages": 7, "remainingBeforeCall": 5909, "remainingAfterCall": 4909 },
  "lastUpdatedDate": "2026-09-16 14:32:10",
  "deliveryTracking": {
    "deliveryId": 42,
    "vendorCode": "vendor-01",
    "deliveryMode": "standard",
    "acknowledgementKey": "one-time-key-returned-by-api",
    "acknowledgementStatus": "pending",
    "recordsSentThisCall": 1000,
    "recordsDeliveredInCycle": 2000,
    "pagesDeliveredInCycle": 2,
    "firstRecordId": 1001,
    "lastRecordId": 2000,
    "resumeApplied": true,
    "resumedAfterRecordId": 1000,
    "resumedAfterUpdatedDate": "2026-09-16 14:32:10",
    "syncCompleted": false,
    "nextPageNo": 3,
    "nextIncrementalDate": null
  },
  "success": true
}</code></pre></section>
    </div>
    <div class="col-xl-4">
        <section class="panel mb-4"><h3>Incremental retrieval</h3><p>Leave <code>lastUpdateDate</code> empty to retrieve the full filtered dataset. When a date or ISO 8601 date-time is supplied, the API checks the successful log for the same vendor, dataset, date, and page size, then resumes after its last delivered record.</p><p class="mb-0">Continue until <code>deliveryTracking.syncCompleted</code> is <code>true</code>, then use <code>deliveryTracking.nextIncrementalDate</code> for the next synchronization. The checkpoint remains empty until the cycle is complete.</p></section>
        <section class="panel mb-4"><h3>Redelivery controls</h3><p class="mb-2">Pass either <code>Redelivery_From_Record_ID</code> or <code>Redelivery_From_Date</code>. Redelivery is inclusive of the selected record/date and uses normal <code>PageNo</code> pagination.</p><p class="mb-0 text-secondary">Do not combine redelivery fields with <code>lastUpdateDate</code>.</p></section>
        <section class="panel mb-4"><h3>Safety controls</h3><dl class="api-list"><div><dt>Idempotency-Key</dt><dd>Optional but recommended. Send a unique 8–100 character header or <code>Idempotency_Key</code> payload value when the vendor needs safe retry protection. Replays return the original response without sending records again. Requests without a key continue normally and are still recorded in delivery tracking.</dd></div><div><dt>Rate limit</dt><dd>60 requests per vendor per minute by default; response headers show the remaining allowance.</dd></div></dl></section>
        <section class="panel mb-4"><h3>Vendor acknowledgment</h3><div class="endpoint-line"><span>POST</span><code><?= esc(base_url('api/v1/intake/acknowledge')) ?></code></div><pre class="code-block"><code>{
  "Vendor_Code": "vendor-01",
  "Delivery_ID": 42,
  "Acknowledgement_Key": "key-from-delivery-response",
  "Status": "received successfully"
}</code></pre></section>
        <section class="panel"><h3>Pagination</h3><dl class="api-list"><div><dt>PageNo</dt><dd>Full retrieval: requested page. Incremental retrieval: automatically continued from the API log.</dd></div><div><dt>PageSize</dt><dd>1–1,000; default 1,000. Keep it unchanged during an incremental cycle.</dd></div><div><dt>Ordering</dt><dd>Updated date, then record ID</dd></div></dl></section>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-6 col-xl-3"><div class="mini-metric"><span>Sent records</span><strong><?= number_format((int) ($deliveryStats['sent_records'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="mini-metric"><span>Acknowledged</span><strong class="text-success"><?= number_format((int) ($deliveryStats['acknowledged_records'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="mini-metric"><span>Pending</span><strong class="text-warning"><?= number_format((int) ($deliveryStats['pending_records'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="mini-metric"><span>Failed</span><strong class="text-danger"><?= number_format((int) ($deliveryStats['failed_records'] ?? 0)) ?></strong><small class="table-subtext"><?= number_format((int) ($deliveryStats['failed_requests'] ?? 0)) ?> failed requests</small></div></div>
</div>

<section class="panel p-0 overflow-hidden mt-4">
    <div class="panel-header px-4 pt-4"><div><p class="eyebrow mb-1">Delivery tracking</p><h3>Vendor synchronization state</h3><p class="text-secondary mb-0">The safe checkpoint advances only when every distinct record and page in the current cycle has been delivered.</p></div></div>
    <?php if ($syncStates === []): ?>
        <div class="empty-state compact"><h4>No vendor deliveries tracked yet</h4><p>The first successful API response will create a synchronization state.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Vendor</th><th>Dataset</th><th>Current cycle</th><th>Lifetime sent</th><th>Last record</th><th>Safe incremental date</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($syncStates as $state): ?><tr>
            <td><strong><?= esc($state['vendor_code']) ?></strong><small class="table-subtext"><?= $state['last_delivery_at'] ? esc(date('d M Y, h:i A', strtotime($state['last_delivery_at']))) : 'No delivery date' ?></small></td>
            <td><span class="year-chip"><?= esc($state['academic_year']) ?></span><small class="table-subtext"><?= esc($state['university_aishe_code']) ?></small></td>
            <td><strong><?= number_format((int) $state['cycle_records_delivered']) ?></strong> records<small class="table-subtext"><?= number_format((int) $state['cycle_pages_delivered']) ?> pages tracked</small></td>
            <td><?= number_format((int) $state['records_delivered_total']) ?></td>
            <td><?= $state['last_record_id'] ? '#' . number_format((int) $state['last_record_id']) : '—' ?><small class="table-subtext"><?= esc($state['last_record_updated_at'] ?? 'No record date') ?></small></td>
            <td><?= esc($state['completed_checkpoint_at'] ?? 'Pending') ?></td>
            <td><span class="status <?= (int) $state['sync_completed'] === 1 ? 'status-completed' : 'status-processing' ?>"><?= (int) $state['sync_completed'] === 1 ? 'Complete' : 'In progress' ?></span></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>

<section class="panel p-0 overflow-hidden mt-4">
    <div class="panel-header px-4 pt-4"><div><p class="eyebrow mb-1">Audit history</p><h3>Recent API deliveries</h3><p class="text-secondary mb-0">Each row represents one response released to a vendor.</p></div></div>
    <?php if ($recentDeliveries === []): ?>
        <div class="empty-state compact"><h4>No successful deliveries yet</h4></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th>Delivery</th><th>Vendor</th><th>Mode</th><th>Page</th><th>Records sent</th><th>Acknowledgment</th><th>Record range</th><th>Response watermark</th><th>Delivered at</th></tr></thead><tbody>
        <?php foreach ($recentDeliveries as $delivery): ?><tr>
            <td><strong>#<?= (int) $delivery['id'] ?></strong></td>
            <td><?= esc($delivery['vendor_code']) ?></td>
            <td><span class="status <?= ($delivery['delivery_mode'] ?? 'standard') === 'redelivery' ? 'status-warning' : 'status-valid' ?>"><?= esc($delivery['delivery_mode'] ?? 'standard') ?></span></td>
            <td><?= number_format((int) $delivery['page_no']) ?> / <?= number_format((int) $delivery['total_pages']) ?></td>
            <td><?= number_format((int) $delivery['records_returned']) ?></td>
            <td><span class="status status-<?= esc($delivery['ack_status'] ?? 'pending') ?>"><?= esc($delivery['ack_status'] ?? 'pending') ?></span><small class="table-subtext"><?= esc($delivery['acknowledged_at'] ?? 'Awaiting vendor') ?></small></td>
            <td><?= $delivery['response_first_record_id'] ? '#' . number_format((int) $delivery['response_first_record_id']) : '—' ?> – <?= $delivery['response_last_record_id'] ? '#' . number_format((int) $delivery['response_last_record_id']) : '—' ?></td>
            <td><?= esc($delivery['response_last_updated_at'] ?? '—') ?></td>
            <td><?= esc(date('d M Y, h:i A', strtotime($delivery['requested_at']))) ?></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>

<?php if (auth()->user()->can('intake.api.deliveries.report')): ?>
<section class="panel mt-4"><div class="row g-4 align-items-end"><div class="col-lg-4"><p class="eyebrow mb-1">Reporting</p><h3 class="mb-1">Download delivery report</h3><p class="text-secondary mb-0">Export sent, pending, acknowledged, failed, and redelivery history to Excel.</p></div><div class="col-lg-8"><form method="post" action="<?= url_to('intake.api.deliveries.report') ?>"><?= csrf_field() ?><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="report-vendor">Vendor</label><select class="form-select" id="report-vendor" name="vendor_code"><option value="">All vendors</option><?php foreach ($vendors as $vendor): ?><option value="<?= esc($vendor, 'attr') ?>"><?= esc($vendor) ?></option><?php endforeach ?></select></div><div class="col-md-3"><label class="form-label" for="report-from">From</label><input class="form-control" id="report-from" name="from_date" type="date"></div><div class="col-md-3"><label class="form-label" for="report-to">To</label><input class="form-control" id="report-to" name="to_date" type="date"></div><div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Download</button></div></div></form></div></div></section>
<?php endif ?>

<?php if (auth()->user()->can('intake.api.deliveries.reset')): ?>
<section class="panel mt-4">
    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <p class="eyebrow mb-1">Database maintenance</p>
            <h3>Reset delivery records</h3>
            <p class="text-secondary mb-2">Remove high-volume API delivery history for one vendor or every vendor. Intake records are never deleted.</p>
            <ul class="text-secondary mb-0 ps-3">
                <li><strong>Purge record details</strong> removes completed-cycle per-record entries while preserving response summaries, checkpoints, and active-cycle records.</li>
                <li><strong>Full tracking reset</strong> also removes response summaries and synchronization state, so the next request starts a new delivery cycle.</li>
            </ul>
        </div>
        <div class="col-lg-7">
            <form method="post" action="<?= url_to('intake.api.deliveries.reset') ?>" onsubmit="return window.confirm('Run the selected API delivery maintenance operation?');">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="reset-vendor">Vendor</label>
                        <select class="form-select" id="reset-vendor" name="vendor_code">
                            <option value="">All vendors</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= esc($vendor, 'attr') ?>"><?= esc($vendor) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="reset-scope">Maintenance action</label>
                        <select class="form-select" id="reset-scope" name="reset_scope" required>
                            <option value="details">Purge record details (recommended)</option>
                            <option value="all">Full tracking reset</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check rounded border p-3 ps-5">
                            <input class="form-check-input" type="checkbox" value="1" id="confirm-reset" name="confirm_reset" required>
                            <label class="form-check-label" for="confirm-reset">I understand that the selected delivery history cannot be restored.</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-outline-danger" type="submit">Run delivery maintenance</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>


