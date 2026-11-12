<?php
[$schema] = \App\Libraries\VendorImportSchema::definition($category);
$batch = db_connect()->table('vendor_api_imports')->where('category', $category)->where('import_job_id', $job['id'])->get()->getRowArray();
$submittedRows = $batch ? db_connect()->table('vendor_api_import_rows')->where('batch_id', $batch['id'])->orderBy('row_number')->limit(20)->get()->getResultArray() : [];
?>
<section class="panel mb-4 border-primary">
    <p class="eyebrow">Vendor API submission<?= $batch ? ' #' . (int) $batch['id'] : '' ?></p>
    <h3>Submitted by <?= esc($job['source_vendor_code']) ?></h3>
    <form class="mb-3" method="post" action="<?= url_to($category . '.vendor_submission.report', $job['id']) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-primary">Download all submitted rows (CSV)</button></form>
    <p>Accepted changes remain in Draft until this dataset is validated, approved, and published. Previously published records stay available while these changes are reviewed.</p>
    <?php if ($batch): ?><p class="small text-secondary">Received <?= esc($batch['created_at']) ?> · <?= (int) $job['valid_rows'] ?> accepted changes · <?= (int) $job['duplicate_rows'] ?> unchanged/duplicate rows · <?= (int) $job['rejected_rows'] ?> rejected rows</p><?php endif ?>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Row</th><th>Outcome</th><th>Data / validation errors (first 20 rows)</th></tr></thead><tbody>
    <?php foreach ($submittedRows as $submitted): ?>
        <?php $data = json_decode($submitted['normalized_payload'] ?? '[]', true) ?: []; $issues = json_decode($submitted['errors'] ?? '[]', true) ?: []; ?>
        <tr><td><?= (int) $submitted['row_number'] ?></td><td><?= esc(ucfirst($submitted['outcome'])) ?></td><td>
            <?php foreach ($issues as $issue): ?><div class="text-danger small"><?= esc($issue['field'] . ': ' . $issue['message']) ?></div><?php endforeach ?>
            <details><summary>View record</summary><pre class="code-block mt-2"><code><?= esc(json_encode($data !== [] ? $schema::toApi($data) : json_decode($submitted['submitted_payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre></details>
        </td></tr>
    <?php endforeach ?>
    </tbody></table></div>
    <?php if ((int) $job['rejected_rows'] > 0): ?><p class="mb-0">Rejected rows were not accepted. The submitting vendor can retrieve all row errors using the import status API and submit corrected rows with a new idempotency key.</p><?php endif ?>
</section>
