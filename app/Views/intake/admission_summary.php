<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Intake analysis</p><h2>Admissions by institution and program</h2><p>Compare imported intake capacity with student admissions for each academic year.</p></div>
</div>

<section class="panel mb-4">
    <form class="row g-3 align-items-end" method="post" action="<?= url_to('intake.admission_summary.filter') ?>">
        <?= csrf_field() ?>
        <div class="col-md-4 col-xl-2"><label class="form-label" for="academic_year">Academic year</label><select class="form-select" id="academic_year" name="academic_year"><option value="">All years</option><?php foreach ($years as $year): ?><option value="<?= esc($year) ?>" <?= $filters['academicYear'] === $year ? 'selected' : '' ?>><?= esc($year) ?></option><?php endforeach ?></select></div>
        <div class="col-md-8 col-xl-3"><label class="form-label" for="institution">Institution</label><select class="form-select" id="institution" name="institution"><option value="">All institutions</option><?php foreach ($institutions as $item): ?><option value="<?= esc($item['key']) ?>" <?= $filters['institution'] === $item['key'] ? 'selected' : '' ?>><?= esc(($item['code'] !== 'NA' ? $item['code'] . ' · ' : '') . $item['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-5 col-xl-3"><label class="form-label" for="search">Program</label><input class="form-control" id="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Program name or code"></div>
        <div class="col-md-4 col-xl-2"><label class="form-label" for="status">Matching status</label><select class="form-select" id="status" name="status"><option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All records</option><option value="matched" <?= $filters['status'] === 'matched' ? 'selected' : '' ?>>Intake matched</option><option value="missing_intake" <?= $filters['status'] === 'missing_intake' ? 'selected' : '' ?>>Intake not matched</option><option value="no_admissions" <?= $filters['status'] === 'no_admissions' ? 'selected' : '' ?>>No admissions</option></select></div>
        <div class="col-md-3 col-xl-2"><label class="form-label" for="per_page">Records per page</label><select class="form-select" id="per_page" name="per_page" onchange="this.form.requestSubmit()"><?php foreach ($pageSizeOptions as $option): ?><option value="<?= $option ?>" <?= $filters['perPage'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach ?></select></div>
        <div class="col-12 d-flex gap-2 justify-content-end"><button class="btn btn-primary" type="submit">Apply</button><button class="btn btn-outline-secondary" type="submit" name="clear_filters" value="1" formnovalidate>Clear</button></div>
    </form>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Total intake</span><strong><?= number_format((int) $totals['intake']) ?></strong><small>Across <?= number_format((int) $totals['institutions']) ?> institutions</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Total admissions</span><strong><?= number_format((int) $totals['admissions']) ?></strong><small>Student admission records</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Seat balance</span><strong class="<?= $totals['available'] < 0 ? 'text-danger' : '' ?>"><?= number_format((int) $totals['available']) ?></strong><small>Intake minus admissions</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><span>Utilization</span><strong><?= $totals['utilization'] === null ? 'NA' : number_format((float) $totals['utilization'], 1) . '%' ?></strong><small><?= number_format((int) $totals['matched']) ?> matched program rows</small></div></div>
</div>

<section class="panel note-panel mb-4">
    <strong>Program matching</strong>
    <p>Programs are matched only by program code; degree names such as <code>M.B.A.</code> and <code>Master of Business Administration</code> are not compared. Numeric program codes ignore leading zeros during matching, so <code>030142</code> matches <code>30142</code>. Records without a usable program code remain unmatched.</p>
</section>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Institution and program summary</h3><p class="text-secondary mb-0"><?= number_format($totalRows) ?> program rows · <?= number_format((int) $totals['missingIntake']) ?> without matched intake · <?= number_format((int) $totals['noAdmissions']) ?> without admissions</p></div></div>
    <?php if ($rows === []): ?>
        <div class="empty-state"><h4>No summary records found</h4><p>Adjust the academic year, institution, program, or matching filters.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table admission-intake-table align-middle mb-0"><thead><tr><th class="academic-year-column">Academic year</th><th>Program</th><th>Program code</th><th>Total intake</th><th>Admissions</th><th>Seat balance</th><th>Utilization</th><th>Matching</th></tr></thead><tbody>
        <?php $lastGroup = null; foreach ($rows as $row): ?>
            <?php if ($lastGroup !== $row['institution_group_key']): $lastGroup = $row['institution_group_key']; $institutionSummary = $institutionTotals[$lastGroup] ?? ['intake' => 0, 'admissions' => 0, 'available' => 0]; ?>
                <tr class="report-institution-row"><td colspan="8"><div><span><strong><?= esc($row['institution_name']) ?></strong><small><?= esc($row['institution_code']) ?></small></span><span>Intake <strong><?= number_format($institutionSummary['intake']) ?></strong></span><span>Admissions <strong><?= number_format($institutionSummary['admissions']) ?></strong></span><span>Balance <strong class="<?= $institutionSummary['available'] < 0 ? 'text-danger' : '' ?>"><?= number_format($institutionSummary['available']) ?></strong></span></div></td></tr>
            <?php endif ?>
            <tr>
                <td class="academic-year-column"><span class="year-chip"><?= esc($row['academic_year']) ?></span></td>
                <td><strong><?= esc($row['program_name']) ?></strong><?php if ($row['intake_program_name'] !== 'NA' && $row['intake_program_name'] !== $row['program_name']): ?><small class="table-subtext">Intake: <?= esc($row['intake_program_name']) ?></small><?php endif ?><?php if ($row['course_name'] !== 'NA'): ?><small class="table-subtext">Course: <?= esc($row['course_name']) ?></small><?php endif ?></td>
                <td><?= esc($row['program_code']) ?></td>
                <td><strong><?= $row['intake_total'] === null ? '—' : number_format((int) $row['intake_total']) ?></strong></td>
                <td><strong><?= number_format((int) $row['admission_count']) ?></strong></td>
                <td><strong class="<?= $row['available_seats'] !== null && $row['available_seats'] < 0 ? 'text-danger' : '' ?>"><?= $row['available_seats'] === null ? '—' : number_format((int) $row['available_seats']) ?></strong></td>
                <td><?= $row['utilization'] === null ? '—' : '<strong class="' . ($row['utilization'] > 100 ? 'text-danger' : '') . '">' . number_format((float) $row['utilization'], 1) . '%</strong>' ?></td>
                <td><?php if ($row['match_status'] === 'matched'): ?><span class="status status-valid">Matched</span><?php elseif ($row['match_status'] === 'missing_intake'): ?><span class="status status-warning">Intake not matched</span><?php else: ?><span class="status status-draft">No admissions</span><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody></table></div>
        <div class="panel-footer report-pagination">
            <span>Page <?= number_format($filters['page']) ?> of <?= number_format($pageCount) ?></span>
            <?php if ($pageCount > 1): ?><form method="post" action="<?= url_to('intake.admission_summary.filter') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="academic_year" value="<?= esc($filters['academicYear']) ?>"><input type="hidden" name="institution" value="<?= esc($filters['institution']) ?>"><input type="hidden" name="search" value="<?= esc($filters['search']) ?>"><input type="hidden" name="status" value="<?= esc($filters['status']) ?>"><input type="hidden" name="per_page" value="<?= $filters['perPage'] ?>">
                <div class="btn-group btn-group-sm" role="group" aria-label="Report pages"><button class="btn btn-outline-secondary" type="submit" name="page" value="1" <?= $filters['page'] <= 1 ? 'disabled' : '' ?>>First</button><button class="btn btn-outline-secondary" type="submit" name="page" value="<?= max(1, $filters['page'] - 1) ?>" <?= $filters['page'] <= 1 ? 'disabled' : '' ?>>Previous</button><button class="btn btn-outline-secondary" type="submit" name="page" value="<?= min($pageCount, $filters['page'] + 1) ?>" <?= $filters['page'] >= $pageCount ? 'disabled' : '' ?>>Next</button><button class="btn btn-outline-secondary" type="submit" name="page" value="<?= $pageCount ?>" <?= $filters['page'] >= $pageCount ? 'disabled' : '' ?>>Last</button></div>
            </form><?php endif ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
