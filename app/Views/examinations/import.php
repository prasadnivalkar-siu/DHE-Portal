<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Examination data</p><h2>Import Excel workbook</h2><p>Upload, validate, preview, then confirm examination records using the approved 44-column template.</p></div>
    <a class="btn btn-outline-primary" href="<?= base_url('templates/Examination_Format.xlsx') ?>" download>Download template</a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel">
            <form method="post" action="<?= url_to('examinations.import.store') ?>" enctype="multipart/form-data" id="importForm" data-progress-url="<?= base_url('admin/examinations/import/progress') ?>" data-max-upload-bytes="<?= (int) $maxUploadBytes ?>">
                <?= csrf_field('importCsrf') ?>
                <input type="hidden" name="progress_token" id="importProgressToken" value="<?= esc($progressToken, 'attr') ?>">
                <label class="upload-zone" for="examination_file">
                    <span class="upload-icon">XLSX</span><strong>Choose an Excel workbook</strong><span>or drag and drop it here</span><small>.xlsx or .xls · application maximum 100 MB</small>
                    <input class="visually-hidden" type="file" id="examination_file" name="examination_file" accept=".xlsx,.xls" required>
                </label>
                <div class="selected-file d-none" id="selectedFile"><span id="selectedFileName"></span><button class="btn btn-sm btn-link" type="button" id="clearFile">Remove</button></div>
                <?php $errors = session()->getFlashdata('errors') ?? []; ?>
                <?php if ($errors !== []): ?><div class="alert alert-danger mt-3 mb-0"><?php foreach ($errors as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div><?php endif ?>
                <div class="alert alert-danger mt-3 mb-0 d-none" id="importAjaxError" role="alert"></div>

                <div class="import-progress-card d-none" id="importProgress" role="status" aria-live="polite">
                    <div class="import-progress-heading">
                        <div class="import-progress-icon" id="importProgressIcon"><span></span></div>
                        <div>
                            <strong id="importProgressTitle">Uploading workbook</strong>
                            <small id="importProgressMessage">Preparing a secure upload…</small>
                        </div>
                        <strong class="import-progress-percent" id="importProgressPercent">0%</strong>
                    </div>
                    <div class="progress import-progress-track" role="progressbar" aria-label="Examination import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="importProgressBar">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="importProgressFill" style="width: 0%"></div>
                    </div>
                    <div class="import-progress-meta">
                        <span id="importProgressPhase">Upload</span>
                        <span>Elapsed <strong id="importElapsed">00:00</strong></span>
                    </div>
                    <div class="import-record-progress d-none" id="importRecordProgress">
                        <strong><span id="importProcessedRows">0</span> / <span id="importTotalRows">0</span> records</strong>
                        <small id="importRecordBreakdown">Inserted 0 · Updated 0 · Skipped 0 · Errors 0</small>
                    </div>
                    <div class="import-progress-steps" aria-hidden="true">
                        <span class="active" data-import-step="upload">1 <small>Upload</small></span>
                        <span data-import-step="validate">2 <small>Validate</small></span>
                        <span data-import-step="save">3 <small>Preview</small></span>
                        <span data-import-step="complete">4 <small>Confirm</small></span>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="<?= url_to('examinations.index') ?>" id="importCancel">Cancel</a><button class="btn btn-primary" type="submit" id="importButton">Upload and validate</button></div>
            </form>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel mb-4"><h3>Import checks</h3><ul class="check-list"><li>All 44 examination template headers must be present.</li><li>Academic year and university AISHE code are required.</li><li>Each row needs an admission form number, PRN, exam form number, or seat number.</li><li>Unchanged duplicate rows are skipped automatically.</li><li>Invalid rows appear in the import error log.</li></ul></section>
        <section class="panel note-panel"><strong>Blank values</strong><p>Blank cells and cells containing <code>-</code> are saved as <code>NA</code>.</p></section>
    </div>
</div>
<?= $this->endSection() ?>

