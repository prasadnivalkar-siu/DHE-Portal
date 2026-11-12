# DHE Portal

DHE Portal is a category-based higher education data portal. **Admission Data**, **Examination Data**, **Intake Data**, **Placement Data**, **Expense Data**, and **Result Data** support audited Excel imports, approval/publishing workflows, and paginated JSON APIs for full and incremental retrieval.

## Included in this build

- Admission dashboard with record, university, college, and update statistics
- Searchable admission records with academic-year and university filters
- Record detail view covering all 45 admission fields
- `.xlsx` and `.xls` import with a 100 MB application upload limit
- Queued background Excel imports with live processed/total row counts, inserted/updated/skipped/error totals, elapsed time, and validation states
- Exact 45-column header validation with compatibility aliases
- Chunked workbook reading to control memory usage on larger files
- Row-level validation and import error logs
- Duplicate-safe imports: unchanged rows are skipped and do not advance `updated_at`
- Paginated Admission Data API with a maximum page size of 1,000
- Examination Data list/detail screens, 44-column workbook import, validation preview, rejected-row export, and publishing workflow
- Paginated Examination Data API with delivery tracking, acknowledgment, redelivery, rate limiting, and idempotency protection
- Intake Data list/detail screens, 14-column workbook import, validation preview, rejected-row export, and publishing workflow
- Paginated Intake Data API with delivery tracking, acknowledgment, redelivery, rate limiting, and idempotency protection
- Placement Data list/detail screens, 14-column workbook import, validation preview, rejected-row export, and publishing workflow
- Paginated Placement Data API with delivery tracking, acknowledgment, redelivery, rate limiting, and idempotency protection
- Expense Data list/detail screens, 8-column workbook import, validation preview, rejected-row export, and publishing workflow
- Paginated Expense Data API with delivery tracking, acknowledgment, redelivery, rate limiting, and idempotency protection
- Result Data list/detail screens, 440-column workbook import, validation preview, rejected-row export, and publishing workflow
- Paginated Result Data API with four-field dataset filtering, delivery tracking, acknowledgment, redelivery, rate limiting, and idempotency protection
- Incremental API filtering through `lastUpdateDate`
- Vendor-specific `X-API-Key` authentication, category access, and configurable request limits
- Secure administrator sign-in powered by CodeIgniter Shield
- Role-based access for Super Administrator, Administrator, Data Manager, Auditor, and Viewer
- User provisioning, role assignment, account disabling, and password changes
- Administrative audit trail for user, role, status, and password events
- Responsive Bootstrap interface and mobile navigation

## Technology

- PHP 8.2 or newer
- CodeIgniter 4.7.4
- CodeIgniter Shield 1.4.1
- PhpSpreadsheet 5.9.0
- MySQL 9.7 LTS deployment target
- Bootstrap 5.3.8
- jQuery 4.0.0
- HTML5 and custom CSS

The local development database was validated with the available XAMPP MariaDB 10.4 server. The migrations use standard CodeIgniter database types and are designed for the MySQL deployment target.

## Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Copy `.env.example` to `.env` and set the application URL and MySQL credentials.

3. Create the database:

   ```sql
   CREATE DATABASE dhe_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Run the migrations:

   ```bash
   php spark migrate --all
   ```

5. Create the first Super Administrator. The generated temporary password is shown once:

   ```bash
   php spark dhe:create-admin admin@example.org admin.name
   ```

6. Start the local application:

   ```bash
   php spark serve --host 127.0.0.1 --port 8080
   ```

7. Open `http://127.0.0.1:8080/`, sign in, and change the temporary password from **My Account**.

The application generates clean URLs without `index.php`. For Apache deployments, point the virtual host document root to the project's `public` directory and keep `mod_rewrite` enabled; the included `public/.htaccess` handles routing.

For 100 MB workbook uploads, configure PHP with `upload_max_filesize = 100M` and `post_max_size = 110M` or higher. The web server or hosting control panel must also allow request bodies of at least 110 MB.

The supplied reference workbooks are available at `public/templates/Admission_Format.xlsx`, `public/templates/Examination_Format.xlsx`, `public/templates/Intake_Template.xlsx`, `public/templates/Placement_Template.xlsx`, `public/templates/Expense_Template.xlsx`, and `public/templates/Result_Template.xlsx`, and from each category's **Download template** button.

## Administration and roles

All browser-based portal pages require an authenticated account. Permissions are enforced in routes as well as hidden from the navigation when unavailable.

| Role | Data access | Imports and logs | User administration |
| --- | --- | --- | --- |
| Super Administrator | View and import | Full access | Full access, including Super Administrators |
| Administrator | View and import | Full access | Create and manage non-Super-Administrator users |
| Data Manager | View and import | Full access | None |
| Auditor | Read only | View logs | None |
| Viewer | Read only | None | None |

The portal prevents users from changing their own role or disabling themselves. It also prevents the final enabled Super Administrator from being demoted or disabled. Available roles and their permission matrix are defined in `app/Config/AuthGroups.php`.

## Admission import rules

The importer requires all 45 reference columns. It normalizes these supplied-format inconsistencies:

- `Stident_City/Village` and `Student_City/Village` both map to the student city/village field.
- `PRN` is accepted with ordinary or non-breaking trailing spaces.
- `ABC ID`, `APAAR ID (ABC ID)`, and `APAAR_ID` map to the same internal field.
- `Univ_Code` and `University_AISHE_Code` map to the university AISHE code.
- A blank cell or hyphen (`-`, `–`, or `—`) is stored as `NA`. Actual dates, amounts, and document counts are still validated and normalized.

Every data row requires at least one of application form number, PRN, or Student ID so it has a stable identity. Blank and hyphen cells are stored as `NA`. Supported actual dates include Excel dates, `YYYY-MM-DD`, `DD-MM-YYYY`, `DD/MM/YYYY`, and `15-Oct-2021`.

The row identity key combines academic year, university code, college code, application form number, PRN, and Student ID. Reimporting an unchanged row counts it as skipped. Changing its other values updates the existing row and its incremental API timestamp. Workbooks are loaded once and persisted in 500-row database chunks so larger imports avoid repeated XLSX parsing.

An import can also be run from the command line:

```bash
php spark dhe:import-admissions "C:\\path\\to\\Admission_Format.xlsx"
```

## Examination import rules

The examination importer requires all 44 fields from `Examination_Format.xlsx`. Header matching is case- and separator-tolerant, including the supplied lowercase template headings and the uppercase data-dictionary names. Blank cells and hyphens are stored as `NA`.

`UNIV_AISHE_CODE` and `ACADEMIC_YEAR` are required. Every row must also contain at least one stable student/exam identifier: `ADMISSION_FORM_NO`, `PRN`, `EXAM_FORM_NUMBER`, or `SEAT_NUMBER`. Examination identity additionally includes exam/session, course/term/branch, paper, and assessment type so separate exam-paper attempts are not collapsed.

Imports use the same streamed 500-row processing, preview counts, rejected-row download, retry/cancellation, and `Draft → Validated → Approved → Published` workflow as admissions. Only published examination records are exposed by the API.

An examination import can also be run from the command line:

```bash
php spark dhe:import-examinations "C:\\path\\to\\Examination_Format.xlsx"
```

## Intake import rules

The intake importer requires all 14 fields from `Intake_Template.xlsx`. Header matching accepts both the supplied `Faculty (Temporory)` spelling and the corrected `Faculty (Temporary)` spelling. Blank cells and hyphens are stored as `NA`.

`Academic Year`, `University AISHE Code`, and `University / College AISHE code` are required. `TotalIntake` and the permanent, temporary, and CHB faculty values must be non-negative whole numbers when supplied. The stable row identity combines academic year, university, institution, program, course, level, and designation, allowing changed intake or faculty totals to update the same draft record.

Imports use the same streamed 500-row processing, preview counts, rejected-row download, retry/cancellation, and `Draft → Validated → Approved → Published` workflow as the other categories. Only published intake records are exposed by the API.

An intake import can also be run from the command line:

```bash
php spark dhe:import-intakes "C:\\path\\to\\Intake_Template.xlsx"
```

## Placement import rules

The placement importer requires all 14 fields from `Placement_Template.xlsx`. Header matching accepts the supplied compact `ProgramName` and `CourseName` headings as well as spaced or underscore variants. Blank cells and hyphens are stored as `NA`, and surrounding whitespace such as the template's academic-year suffix is removed.

`Academic Year`, `University AISHE Code`, `University / College AISHE code`, and `Company Name` are required. The five package-range columns must contain non-negative whole numbers when supplied. The stable row identity combines academic year, university, institution, program, course, and company, allowing corrected package counts to update the same draft record.

Imports use streamed 500-row processing, validation previews, rejected-row downloads, retry/cancellation, and the `Draft → Validated → Approved → Published` workflow. Only published placement records are exposed by the API.

An import can also be run from the command line:

```bash
php spark dhe:import-placements "C:\\path\\to\\Placement_Template.xlsx"
```

## Expense import rules

The expense importer requires all 8 fields from `Expense_Template.xlsx`. Header matching is case- and separator-tolerant and also accepts `Academic_Year` as an alias for the supplied `Financial year` heading. Blank cells and hyphens are stored as `NA`.

`Financial year`, `University AISHE Code`, and `AISHE Code` are required. Salary and non-salary expenses must be non-negative numbers when supplied; commas, `INR`, `Rs.`, and the rupee symbol are accepted. The stable row identity combines financial year, university, and institution so corrected amounts update the same draft record.

Imports use streamed 500-row processing, validation previews, rejected-row downloads, retry/cancellation, and the `Draft → Validated → Approved → Published` workflow. Only published expense records are exposed by the API.

An import can also be run from the command line:

```bash
php spark dhe:import-expenses "C:\\path\\to\\Expense_Template.xlsx"
```

## Result import rules

The result importer requires all 440 fields from `Result_Template.xlsx`. Header matching is case-, spacing-, separator-, and non-breaking-space-tolerant while preserving the exact supplied field names in API responses. Blank cells and hyphens are stored as `NA`.

`DOI`, `DOV`, `DOP`, `DOQ`, `DOS`, and `LAST_UPDATE_DATE` accept either `0` or a valid date value. A supplied zero is preserved as `0` and does not reject the row.

`SESSION`, `UNIV_AISHE_CODE`, `YEAR`, and `SEASON` are required. They map respectively to the API dataset fields `Academic_Year`, `University_AISHE_Code`, `Exam_Year`, and `Season`. Every row must also contain at least one stable student identifier: `PRN`, `REGN_NO`, `RROLL`, or `ABC_ACCOUNT_ID`.

The stable identity additionally includes organization, course, semester, examination type, and term type so separate student result attempts are not collapsed. All 440 values are preserved in a compact JSON payload while synchronization and display fields remain indexed for efficient filtering. Imports use streamed 500-row processing, validation previews, rejected-row downloads, retry/cancellation, and the `Draft → Validated → Approved → Published` workflow. Only published result records are exposed by the API.

A result import can also be run from the command line:

```bash
php spark dhe:import-results "C:\\path\\to\\Result_Template.xlsx"
```

## Admission Data API

### Endpoint

```text
POST /api/v1/admissions
Content-Type: application/json
```

### Payload

```json
{
  "Vendor_Code": "vendor-01",
  "Academic_Year": "2024-25",
  "University_AISHE_Code": "U-1234",
  "lastUpdateDate": "2026-09-16",
  "PageNo": 1,
  "PageSize": 1000
}
```

`Academic_Year` and `University_AISHE_Code` are required. `Vendor_Code` identifies the receiving party for delivery tracking; it defaults to `default-vendor` for backward compatibility and can alternatively be supplied through the `X-Vendor-Code` header. `PageNo` defaults to `1`, and `PageSize` defaults to and cannot exceed `1000`.

Leave `lastUpdateDate` empty to fetch the full filtered dataset using ordinary page-number pagination. A date-only value is interpreted as midnight at the start of that date in the application timezone. Use an ISO 8601 date-time when an exact synchronization boundary is needed.

When `lastUpdateDate` is supplied, retrieval is resumable. The API finds the latest successful log with the same vendor, academic year, university, normalized update date, and page size, then starts strictly after that response's last record (ordered by `updated_at`, then record ID). The first request starts at the supplied date; later requests using the same payload automatically continue from the saved cursor. Keep `PageSize` unchanged for the duration of the incremental cycle. `PageNo` remains accepted for compatibility, but the effective incremental page sequence is assigned from the API log to prevent skipped records.

The response includes:

- `count`: number of records returned on the current page
- `data`: the 45 uploaded admission fields
- `pagination`: current page, page size, total records, and total pages
- `lastUpdatedDate`: greatest `updated_at` value on the returned page
- `deliveryTracking`: delivery ID, record range, records/pages tracked in the current cycle, lifetime delivery count, next page number, completion status, and the safe next incremental date
- `deliveryTracking.resumeApplied`, `resumedAfterRecordId`, and `resumedAfterUpdatedDate`: whether the incremental cursor was used and the exact prior record boundary
- `success`: request outcome

Process every page until `deliveryTracking.syncCompleted` is `true`. Only then use `deliveryTracking.nextIncrementalDate` as the next `lastUpdateDate`. Every response and exact admission record ID is stored in the delivery audit tables; page retries are retained as separate delivery events while cycle progress counts distinct records.

Administrators and Super Administrators can run **Database maintenance** from the API Documentation page. **Purge record details** reclaims completed-cycle exact-record history while preserving delivery summaries, safe incremental checkpoints, and the records required by active cycles. **Full tracking reset** also removes response logs and synchronization state for the selected vendor (or all vendors), making its next request a new delivery cycle. Both actions leave admission data unchanged and are recorded in the administrative audit trail.

### API key

Configure `encryption.key` once (`php spark key:generate` if no encryption key exists), then run `php spark migrate --all`. Use **Administration → Vendors** (Administrator or Super Administrator) to create a vendor. Enter a unique immutable vendor code, name, contact email, allowed categories, status, and requests-per-minute limit (per category). Creation generates a 256-bit random API key. Authentication uses its SHA-256 hash; an encrypted copy allows authorized administrators to reveal the current key.

Clients must send the vendor key on all data and acknowledgment endpoints:

```text
Content-Type: application/json
X-API-Key: <vendor-key-generated-in-the-portal>
```

The key determines the vendor identity for delivery tracking, acknowledgments, idempotency, and rate limits. `Vendor_Code` and `X-Vendor-Code` are optional; when supplied, each must match the authenticated vendor. A mismatch or disallowed category returns 403. Missing/invalid keys and inactive vendors return 401. An unavailable vendor database returns 503; access is never opened automatically.

Use **Show API key** in the vendor list or Manage page to display an existing key, then **Hide API key** or **Copy key**. Reveals require the `vendors.keys.reveal` permission (Administrators and Super Administrators by default), use a CSRF-protected POST request, prevent response caching, and record `vendor.key_viewed` without putting the key in audit logs. Normal vendor-list responses and page refreshes do not contain the full key.

Use **Manage → Rotate API key** to replace a key, optionally keeping the immediately previous key usable for a short overlap. Set the vendor to **Inactive** to suspend both keys. Key replacement retains the vendor's delivery history and checkpoints. Creation, edits, status changes, expiry changes, overlap revocation, and rotation are recorded in administrative audit logs without plaintext keys or hashes. Preserve `encryption.key` with your deployment configuration and database backups; encrypted vendor keys require the matching encryption key. Migration 000017 backfills the configured legacy key only if its hash still matches a current vendor. Other older hash-only keys cannot be recovered: rotate them to enable viewing and give the replacement key to the vendor. Missing or incorrect encryption configuration does not disable hash-based API authentication.

Migration 000015 imports an existing nonempty `dhe.apiKey` as **Existing API integration**, bound only to `default-vendor` with access to all six categories. Requests using that key must omit Vendor_Code or use `default-vendor`. It cannot impersonate other vendor codes. After migration the .env setting is no longer used for authentication, including after key regeneration; it may be removed from the deployment configuration. If no shared key exists, create a vendor before API access is possible. Existing histories under other vendor codes remain intact and can be continued by creating a vendor with the exact same code.

## Examination Data API

The examination API uses the same payload, pagination, incremental cursor, redelivery, acknowledgment, rate-limit, idempotency, reporting, and reset behavior described above:

```text
POST /api/v1/examinations
POST /api/v1/examinations/acknowledge
```

`Academic_Year` and `University_AISHE_Code` identify the dataset. Response rows contain the 44 uppercase examination data-dictionary fields. Examination delivery state is isolated from admission delivery state in its own tracking tables.

## Intake Data API

The intake API uses the same full/incremental retrieval, delivery tracking, redelivery, acknowledgment, rate-limit, idempotency, reporting, and reset behavior:

```text
POST /api/v1/intake
POST /api/v1/intake/acknowledge
```

`Academic_Year` and `University_AISHE_Code` identify the dataset. Response rows contain the 14 Intake template fields. Intake delivery state is isolated from admission and examination delivery state in its own tracking tables.

## Placement Data API

The placement API uses the same full/incremental retrieval, delivery tracking, redelivery, acknowledgment, rate-limit, idempotency, reporting, and reset behavior:

```text
POST /api/v1/placements
POST /api/v1/placements/acknowledge
```

`Academic_Year` and `University_AISHE_Code` identify the dataset. Response rows contain the 14 Placement template fields. Placement delivery state is isolated from the other categories in its own tracking tables.

## Expense Data API

The expense API uses the same full/incremental retrieval, delivery tracking, redelivery, acknowledgment, rate-limit, idempotency, reporting, and reset behavior:

```text
POST /api/v1/expenses
POST /api/v1/expenses/acknowledge
```

`Financial_Year` (or the compatible `Academic_Year` alias) and `University_AISHE_Code` identify the dataset. Response rows contain the 8 Expense template fields. Expense delivery state is isolated from the other categories in its own tracking tables.

## Result Data API

The result API uses the same full/incremental retrieval, delivery tracking, redelivery, acknowledgment, rate-limit, idempotency, reporting, and reset behavior:

```text
POST /api/v1/results
POST /api/v1/results/acknowledge
```

`Academic_Year`, `University_AISHE_Code`, `Exam_Year`, and `Season` are required and jointly identify the dataset. Response rows contain all 440 exact Result template fields. Result delivery state and incremental cursors are isolated by vendor and all four dataset fields.

Result Academic Year is treated as a cohort family based on its starting year. Payload `2021-22` therefore includes stored sessions `2021-22`, `2021-23`, `2021-24`, `2021-25`, and `2021-26`. The original `SESSION` remains unchanged in each response record.

Result Season values are canonicalized by removing a trailing year. For example, `Winter 2021` and `Winter` both become `Winter`; `Summer 2021` and `Summer` both become `Summer`. The canonical value is used for imports, queries, delivery tracking, and responses.

## API key expiry and rotation

Run `php spark migrate --all` to apply migration 000020. Existing keys retain their current no-expiry behavior. Newly created keys and the rotation form default to expiry in 90 days; administrators may choose a future expiry date/time or explicitly leave it blank for no expiry. All times shown in Vendor Management use the application's timezone. **Manage → Save expiry** changes the current key's deadline without replacing it; expired keys return HTTP 401 on every API endpoint. No scheduled job is required.

**Rotate API key** generates a replacement immediately and offers no overlap, 15 minutes, 1 hour (default), 6 hours, or 24 hours. The server caps overlap at 1,440 minutes and at the earlier of both keys' expiry dates. Expired keys are never revived by rotation. Only the immediately previous hash/prefix is retained for authentication during overlap; its encrypted plaintext is not retained. Rotating again ends any older overlap immediately. **End overlap now** revokes the previous key without affecting the current key. Shortening the current expiry also shortens the previous key's deadline when necessary; extending/removing expiry cannot extend an existing overlap. Both keys stop working when the vendor is inactive.

Both keys identify the same vendor and share category/institution permissions, rate limits, idempotency records, delivery logs, and incremental checkpoints. Vendors can retry with the replacement key without creating another delivery or batch. API responses for authenticated keys include `X-API-Key-Status: current` or `previous`, plus `X-API-Key-Expires-At` in ISO 8601 format when a deadline applies. Vendor Management shows expiry/expiring-soon status and last usage separately for each key. **Show API key** displays the current key. Rotation, expiry, and overlap controls require `vendors.manage` and CSRF-protected POST; stale key forms are rejected after another rotation.

## Institution access restrictions

Run `php spark migrate --all` to apply migration 000018. In **Administration → Vendors → Manage → Institution access**, choose all institutions or add paired university/institution code rules. A blank institution code explicitly grants the whole university. Codes ignore case and surrounding whitespace; leading zeros remain significant (`001` differs from `1`). New vendors default to restricted access and require at least one rule. Existing vendors retain unrestricted access until an administrator restricts them. Scope changes are audited.

Rules apply to both consume and submit permissions across all six categories. Admission matches `Univ_Code` + `College_Code`; Examination matches `UNIV_AISHE_CODE` + `COLLEGE/STUDY_CENTER_CODE`; Result matches `UNIV_AISHE_CODE` + `ORG_AISHE_CODE`; Intake and Placement match `University AISHE Code` + `University / College AISHE code`; Expenses matches `University AISHE Code` + `AISHE Code`. Add separate paired rules if institution codes differ between categories.

Consumption rejects unauthorized universities with HTTP 403 and filters records, totals, pagination, incremental queries, and redelivery by the current scope. API payloads do not need an extra institution parameter. Access changes isolate cached responses and delivery cursors; old idempotency keys return 409 rather than replaying a response from another scope. Acknowledgments from another scope return 404. After changing scope, start a full synchronization using `PageNo: 1`, an empty `lastUpdateDate`, and fresh idempotency keys so newly granted institutions' older records are included.

Submission checks every row's institution and the existing target record before accepting an update. Out-of-scope rows are rejected without changing data, including Examination identity collisions across colleges. Status and retries recheck accepted batch rows against current permissions and return 403 after access is revoked. Review and publication recheck the producer's current category, active status, and institution access; a revoked draft cannot be published.

## Vendor activity dashboard

Run `php spark migrate --all` to create the indexed request activity table (migration 000019). Open **Administration → Vendor activity**, or use **Activity dashboard** on the Vendors page and **View activity** on a vendor's Manage page. The `vendors.activity` permission controls the dashboard, filters, pagination, and CSV report; Administrators and Super Administrators have it through `vendors.*`.

The dashboard shows request counts, success rate, failures (including access denials and rate limiting), idempotency replays, average response time, daily/weekly trends, category and vendor breakdowns, records sent/acknowledged/pending/reported failed, and current Draft/Validated/Approved/Published submission counts. Vendor, category, and inclusive date filters (up to 366 days) and pagination use CSRF-protected POST requests stored in the administrator's session. Filter values never appear in the URL. Request CSV exports honor the filters and are limited to 10,000 rows; narrow the filters for a larger history.

Request tracking starts after installation and includes recognized active vendor keys, before-filter rejections, acknowledgments, imports, status calls, and idempotent retries. Unknown/inactive credentials cannot be reliably attributed to a vendor. The log stores request metadata only, without API keys, payloads, or student records. Logging failure does not prevent API delivery. Delivery and submission summaries also use retained historical logs; delivery totals count delivery occurrences (including redelivery), not unique student records. Delivery tracking resets remove their corresponding historical totals. Acknowledgment totals and submission workflow counts reflect their current state for deliveries/batches originating in the selected period.

## Vendor data ingestion API

Run `php spark migrate --all` after deployment. Existing vendors keep their consume permissions; submission access is disabled until an administrator selects categories under **Vendors → Manage → Submit data for verification**. A vendor can be a producer, a consumer, or both, with different category lists. The same vendor-specific `X-API-Key` authenticates both directions. Submitting data never grants publication permissions.

All six categories support:

```text
POST /api/v1/{category}/import
POST /api/v1/{category}/import/status
```

`{category}` is `admissions`, `examinations`, `results`, `intake`, `expenses`, or `placements`. Send `Content-Type: application/json` and `X-API-Key`. Import requires an `Idempotency-Key` header or `Idempotency_Key` body value (8–100 characters, letters/numbers/periods/underscores/colons/hyphens). `Vendor_Code` and `X-Vendor-Code` are optional and must match the key when provided.

```json
{
  "Idempotency_Key": "admissions-2024-batch-001",
  "Records": [
    {
      "Academic_Year": "2024-25",
      "Univ_Code": "U-1234",
      "College_Code": "C-1234",
      "StudentID": "student-001",
      "Program_code": "0007"
    }
  ]
}
```

Use the category's response-field names or Excel header aliases. A request contains 1–500 full records and at most 8 MB of JSON. Blank/dash/omitted optional values become `NA`; omitted optional values on an update replace prior values, so this is a full-record submission, not PATCH. Required identifiers and the same schema/duplicate rules as Excel apply. Result submissions use `SESSION`, `YEAR`, and `SEASON` inside each record. Internal fields, publication status, nested values, and unknown fields are rejected.

HTTP 201 returns `batchId`, `importJobId`, `vendorCode`, `category`, `workflowStatus`, `totalRows`, `acceptedRows`, `duplicateRows`, `rejectedRows`, and `rows` with each one-based row number, outcome, and errors. Partial validation is supported: accepted rows are staged; rejected rows are not accepted. All-rejected batches are logged with HTTP 422. Identical duplicates are skipped. Later conflicting rows for the same identity in one request are rejected.

Idempotency is scoped to vendor and category and retained with the import log: retrying the same records/key replays the original result; changing records with the same key returns 409. Corrected data requires a new key. Submission and status share the configured vendor rate limit per category, separately from consumption requests. HTTP 429 includes Retry-After. Status uses `{"Batch_ID": 42}` and only returns batches belonging to the authenticated vendor and category.

Submissions appear in existing category **Import Logs**, marked with their source vendor and batch ID. Administrators can inspect the first 20 rows, download all submitted rows or rejected rows as CSV, and use the existing role-protected **Draft → Validated → Approved → Published** actions. Staged records are stored separately from published data. Updates preserve the old published version until the whole batch is published atomically; publication changes the update timestamp so incremental consumers receive changes. If a target changed since submission, publishing stops without applying this batch, and a new submission is required. Batch and actor history are retained.

## Tests and checks

Run the automated suite:

```bash
composer test -- --testdox
```

Useful verification commands:

```bash
php spark routes
php spark migrate:status
composer audit
```

The automated tests cover all six reference schemas, field aliases, blank normalization, date/time, amount, and count normalization, identity/hash behavior, external API serialization, streaming XLSX reading, idempotency, and role-definition boundaries.
