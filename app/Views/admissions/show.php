<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$sections = [
    'Institution' => [
        'Academic year' => 'academic_year', 'University code' => 'univ_code', 'University name' => 'univ_name',
        'College code' => 'college_code', 'College name' => 'college_name', 'College state' => 'college_state',
        'College district' => 'college_district', 'College taluka' => 'college_taluka',
        'College city/village' => 'college_city_village', 'College pincode' => 'college_pincode',
    ],
    'Student and admission' => [
        'Application form no.' => 'admission_application_form_no', 'Admission date' => 'admission_date',
        'Gender' => 'gender', 'Religion' => 'religion', 'Category' => 'category', 'Caste' => 'caste',
        'Admitted category' => 'admitted_category', 'PRN' => 'prn', 'Student ID' => 'student_id', 'APAAR / ABC ID' => 'abc_id',
        'Physical disability' => 'physical_disability', 'Marital status' => 'marital_status',
    ],
    'Student location' => [
        'Location category' => 'student_location_category', 'Country' => 'student_country', 'State' => 'student_state',
        'District' => 'student_district', 'Taluka' => 'student_taluka', 'City/village' => 'student_city_village',
        'Pincode' => 'student_location_pincode',
    ],
    'Program and eligibility' => [
        'Program code' => 'program_code', 'Program' => 'program', 'Specialization' => 'specialization',
        'Program type' => 'program_type', 'Program part' => 'program_part', 'Program term' => 'program_term',
        'Mode of learning' => 'mode_of_learning', 'Medium of appearance' => 'medium_of_appearance',
        'Medium of instructions' => 'medium_of_instructions', 'Eligibility status' => 'eligibility_status',
        'Eligibility date' => 'eligibility_date',
    ],
    'Documents and receipt' => [
        'All documents submitted' => 'all_documents_submitted', 'Pending documents' => 'pending_documents_count',
        'Receipt ID' => 'receipt_id', 'Receipt no.' => 'receipt_no', 'Receipt amount' => 'receipt_amount',
    ],
];
?>
<div class="page-heading">
    <div><p class="eyebrow">Admission record</p><h2><?= esc($record['student_id'] ?: ($record['prn'] ?: $record['admission_application_form_no'])) ?></h2><p><?= esc($record['college_name'] ?? 'College not provided') ?> · <span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('admissions.index') ?>">Back to records</a>
</div>

<div class="row g-4">
    <?php foreach ($sections as $heading => $fields): ?>
        <div class="col-xl-6"><section class="panel h-100 detail-panel"><h3><?= esc($heading) ?></h3><dl class="detail-grid">
            <?php foreach ($fields as $label => $key): ?><div><dt><?= esc($label) ?></dt><dd><?= ($record[$key] ?? null) !== null && $record[$key] !== '' ? esc((string) $record[$key]) : '—' ?></dd></div><?php endforeach ?>
        </dl></section></div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
