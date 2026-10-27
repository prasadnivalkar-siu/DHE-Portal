<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <?php $studentId = ($record['prn'] ?? 'NA') !== 'NA' ? $record['prn'] : ((($record['registration_no'] ?? 'NA') !== 'NA') ? $record['registration_no'] : ($record['roll_no'] ?? 'NA')); ?>
    <div><p class="eyebrow">Result record</p><h2><?= esc($record['organization_aishe_code']) ?> · <?= esc($studentId) ?></h2><p><?= esc($record['organization_name'] ?? 'Organization not provided') ?> · <?= esc($record['exam_year']) ?> <?= esc($record['season']) ?> · <span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('results.index') ?>">Back to records</a>
</div>

<section class="panel mb-4"><div class="row g-3">
    <div class="col-sm-6 col-xl-3"><div class="mini-metric"><span>Academic year</span><strong><?= esc($record['academic_year']) ?></strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="mini-metric"><span>Exam</span><strong><?= esc($record['exam_year'] . ' · ' . $record['season']) ?></strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="mini-metric"><span>Course</span><strong><?= esc($record['course_code'] ?? 'NA') ?></strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="mini-metric"><span>Result</span><strong><?= esc($record['result_status'] ?? 'NA') ?></strong></div></div>
</div></section>

<details class="panel mb-4" open><summary class="fw-bold">General result fields</summary><dl class="detail-grid mt-4">
    <?php foreach ($generalHeaders as $header): ?><div><dt><?= esc(str_replace("\xC2\xA0", ' ', $header)) ?></dt><dd><?= esc((string) ($payload[$header] ?? 'NA')) ?></dd></div><?php endforeach ?>
</dl></details>

<?php foreach ($subjectSections as $heading => $headers): ?>
<details class="panel mb-3"><summary class="fw-bold"><?= esc($heading) ?></summary><dl class="detail-grid mt-4">
    <?php foreach ($headers as $header): ?><div><dt><?= esc($header) ?></dt><dd><?= esc((string) ($payload[$header] ?? 'NA')) ?></dd></div><?php endforeach ?>
</dl></details>
<?php endforeach ?>
<?= $this->endSection() ?>
