<?php
$accessMode = old('institution_access_mode', $vendor['institution_access_mode'] ?? 'restricted');
$accessMode = $accessMode === 'all' ? 'all' : 'restricted';
$accessRules = json_decode($vendor['institution_access_rules'] ?? '[]', true);
$accessRules = is_array($accessRules) ? $accessRules : [];
$postedUniversities = old('scope_university', null);
$postedInstitutions = old('scope_institution', null);
if (is_array($postedUniversities) && is_array($postedInstitutions)) {
    $accessRules = [];
    foreach (array_slice($postedUniversities, 0, 200) as $index => $university) {
        $accessRules[] = ['university_code' => is_string($university) ? $university : '', 'institution_code' => is_string($postedInstitutions[$index] ?? null) ? $postedInstitutions[$index] : ''];
    }
}
if ($accessRules === []) {
    $accessRules = [['university_code' => '', 'institution_code' => '']];
}
?>
<div class="col-12" data-institution-access>
    <label class="form-label" for="institution-access-mode">Institution access</label>
    <select class="form-select mb-2" id="institution-access-mode" name="institution_access_mode" data-scope-mode>
        <option value="restricted" <?= $accessMode === 'restricted' ? 'selected' : '' ?>>Only the universities / institutions listed below</option>
        <option value="all" <?= $accessMode === 'all' ? 'selected' : '' ?>>All universities and institutions</option>
    </select>
    <p class="form-text">Applies to consuming and submitting data in the permitted categories.</p>
    <fieldset data-scope-fields <?= $accessMode === 'all' ? 'hidden disabled' : '' ?>>
        <legend class="form-label fs-6">Permitted universities and institutions</legend>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>University AISHE code</th><th>Institution code (optional)</th><th></th></tr></thead><tbody data-scope-rows>
            <?php foreach ($accessRules as $rule): ?>
                <tr data-scope-row>
                    <td><input class="form-control" name="scope_university[]" value="<?= esc($rule['university_code'] ?? '', 'attr') ?>" placeholder="U-1234" maxlength="100" aria-label="University AISHE code" <?= $accessMode === 'restricted' ? 'required' : '' ?>></td>
                    <td><input class="form-control" name="scope_institution[]" value="<?= esc($rule['institution_code'] ?? '', 'attr') ?>" placeholder="Blank = all institutions in this university" maxlength="100" aria-label="Institution code"></td>
                    <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-scope>Remove</button></td>
                </tr>
            <?php endforeach ?>
        </tbody></table></div>
        <button class="btn btn-sm btn-outline-primary" type="button" data-add-scope>Add institution rule</button>
        <div class="form-text mt-2">Each institution code is paired with its university. Leave the institution blank to allow the entire university. Codes ignore case and surrounding spaces; leading zeros remain significant.</div>
        <details class="mt-2 small"><summary>Which institution code should I use?</summary><p class="mt-2 mb-0">Admission uses College_Code; Examination uses COLLEGE/STUDY_CENTER_CODE; Result uses ORG_AISHE_CODE; Intake and Placement use University / College AISHE code; Expenses uses AISHE Code. Add separate rules when these codes differ between categories.</p></details>
    </fieldset>
    <p class="form-text mt-2 mb-0">After changing access, vendors should start a new full synchronization with PageNo 1, an empty lastUpdateDate, and new idempotency keys.</p>
</div>
