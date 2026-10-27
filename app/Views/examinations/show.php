<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$sections = [
    'University and study centre' => [
        'University AISHE code' => 'univ_aishe_code', 'University name' => 'univ_name', 'Academic year' => 'academic_year',
        'College / study centre code' => 'college_study_center_code', 'College / study centre name' => 'college_study_center_name',
        'College district' => 'college_district', 'College taluka' => 'college_taluka',
    ],
    'Student' => [
        'Admission form no.' => 'admission_form_no', 'PRN' => 'prn', 'Gender' => 'gender',
        'Medium of appearance' => 'medium_of_appearance', 'Marital status' => 'marital_status', 'Category' => 'category',
        'Student state' => 'student_state', 'Student district' => 'student_district', 'Taluka' => 'taluka',
    ],
    'Course and paper' => [
        'Course abbreviation' => 'course_abbr', 'Course name' => 'course_name', 'Course code' => 'course_code',
        'Course term' => 'course_term', 'Course pattern' => 'course_pattern', 'Branch' => 'branch',
        'Paper code' => 'paper_code', 'Paper name' => 'paper_name', 'Teaching / learning method' => 'teaching_learning_method',
    ],
    'Examination and assessment' => [
        'Exam year' => 'exam_year', 'Session' => 'exam_session', 'Exam form number' => 'exam_form_number',
        'Appearance type' => 'exam_appearance_type', 'Seat number' => 'seat_number',
        'Assessment method' => 'assessment_method', 'Assessment type' => 'assessment_type',
        'Eligibility status' => 'eligibility_status', 'Inward date' => 'inward_date', 'Inward time' => 'inward_time',
        'Source last update date' => 'last_update_date',
    ],
    'Centre and venue' => [
        'Centre code' => 'center_code', 'Centre name' => 'center_name', 'Venue code' => 'venue_code', 'Venue name' => 'venue_name',
        'Preferred centre code' => 'preferred_center_code', 'Preferred centre name' => 'preferred_center_name',
        'Preferred venue code' => 'preferred_venue_code', 'Preferred venue name' => 'preferred_venue_name',
    ],
];
?>
<div class="page-heading">
    <div><p class="eyebrow">Examination record</p><h2><?= esc(($record['prn'] ?? 'NA') !== 'NA' ? $record['prn'] : (($record['seat_number'] ?? 'NA') !== 'NA' ? $record['seat_number'] : $record['exam_form_number'])) ?></h2><p><?= esc($record['college_study_center_name'] ?? 'Study centre not provided') ?> · <span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('examinations.index') ?>">Back to records</a>
</div>

<div class="row g-4">
    <?php foreach ($sections as $heading => $fields): ?>
        <div class="col-xl-6"><section class="panel h-100 detail-panel"><h3><?= esc($heading) ?></h3><dl class="detail-grid">
            <?php foreach ($fields as $label => $key): ?><div><dt><?= esc($label) ?></dt><dd><?= ($record[$key] ?? null) !== null && $record[$key] !== '' ? esc((string) $record[$key]) : '—' ?></dd></div><?php endforeach ?>
        </dl></section></div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>

