<?php
$examples = [
    'admissions' => ['Academic_Year' => '2024-25', 'Univ_Code' => 'U-1234', 'College_Code' => 'C-1234', 'StudentID' => 'student-001', 'Program_code' => '0007'],
    'examinations' => ['ACADEMIC_YEAR' => '2024-25', 'UNIV_AISHE_CODE' => 'U-1234', 'COLLEGE/STUDY_CENTER_CODE' => 'C-1234', 'EXAM_YEAR' => '2024', 'PRN' => 'student-001', 'PAPER_CODE' => 'P-01', 'PAPER_NAME' => 'English'],
    'results' => ['SESSION' => '2024-25', 'UNIV_AISHE_CODE' => 'U-1234', 'ORG_AISHE_CODE' => 'C-1234', 'YEAR' => '2024', 'SEASON' => 'Winter', 'PRN' => 'student-001', 'RESULT' => 'PASS'],
    'intake' => ['Academic Year' => '2024-25', 'University AISHE Code' => 'U-1234', 'University / College AISHE code' => 'C-1234', 'Programme Code' => '0007', 'TotalIntake' => 60],
    'expenses' => ['Financial year' => '2024-25', 'University AISHE Code' => 'U-1234', 'AISHE Code' => 'C-1234', 'Salary Expense (in Rs.)' => 50000],
    'placements' => ['Academic Year' => '2024-25', 'University AISHE Code' => 'U-1234', 'University / College AISHE code' => 'C-1234', 'Company Name' => 'Example Company', 'Package range (1 to 3 Lakh)' => 10],
];
?>
<section class="panel mb-4">
    <h3>Submit data to the portal</h3>
    <div class="endpoint-line mb-3"><span>POST</span><code><?= esc(base_url('api/v1/' . $category . '/import')) ?></code></div>
    <p>Enable <strong>Submit data for verification → <?= esc(\App\Services\VendorApiKeyService::CATEGORIES[$category]) ?></strong> for the vendor. Send its <code>X-API-Key</code>, <code>Content-Type: application/json</code>, and a unique <code>Idempotency-Key</code> (8–100 letters, numbers, periods, underscores, colons, or hyphens). The key may alternatively be sent as <code>Idempotency_Key</code> in the body.</p>
    <pre class="code-block"><code><?= esc(json_encode(['Idempotency_Key' => 'vendor-import-2024-batch-001', 'Records' => [$examples[$category]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
    <p>Use the category response field names or accepted Excel header aliases. Send <strong>1–500 full record objects</strong>, up to <strong>8 MB</strong> of JSON, per request. Omitted optional fields, blank values, and <code>-</code> become <code>NA</code>; updates replace the full record and are not partial patches. Keep codes as strings to preserve leading zeros. Unknown fields, workflow fields, and nested field values are rejected.</p>
    <p>HTTP <code>201</code> returns <code>batchId</code>, <code>importJobId</code>, <code>workflowStatus: draft</code>, <code>acceptedRows</code>, <code>duplicateRows</code>, <code>rejectedRows</code>, and <code>rows</code> with one-based row numbers and validation errors. Valid rows can be accepted even when other rows are rejected. If every row is rejected, the batch is logged and HTTP <code>422</code> is returned. Identical existing records are duplicates; conflicting values for the same identity inside one batch are rejected.</p>
    <p>Retry an identical request with the same idempotency key to receive its original result without creating another batch (<code>X-Idempotency-Replayed: true</code>). Different records with the same key return <code>409</code>. Import keys remain reserved for that vendor and category. Use a new key for corrected rows or a new batch.</p>
    <p>Administrators review submissions in this category's <strong>Import Logs</strong>: <strong>Draft → Validated → Approved → Published</strong>. Existing published data remains available while changes are staged. Publication applies accepted changes atomically and updates their portal update timestamps. If a record changed since the draft arrived, publication stops for review; resubmit current values with a new key. Vendors cannot approve or publish through the API.</p>
    <p>Institution restrictions apply to every row. Include the permitted university and institution codes; out-of-scope rows are rejected with an <strong>Institution access</strong> error. Updates also require permission for the existing record's institution. Current permissions are checked again during review and publication. If an accepted batch falls outside the vendor's current scope, status and idempotent retries return <code>403</code>.</p>
    <h4 class="h6">Check your submission</h4>
    <div class="endpoint-line mb-3"><span>POST</span><code><?= esc(base_url('api/v1/' . $category . '/import/status')) ?></code></div>
    <pre class="code-block"><code>{ "Batch_ID": 42 }</code></pre>
    <p class="mb-0">Use the same submitting vendor key. Only that vendor's batches are visible. Status includes the current workflow, publication date, and all row outcomes. Import and status calls share a separate per-vendor, per-category request limit; <code>429</code> includes <code>Retry-After</code>.</p>
</section>
