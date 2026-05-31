# Legacy Audit Migration — Production Runbook

Migrates 2026 audits (spaces, criterion values, observations, photos) from the
legacy `efectimedios` MySQL DB into CheckMedia V2. Run on the production server
(v2 `public_html`), where the legacy DB and the old app's photo files are local.

## Prerequisites

- This branch (`feat/legacy-audit-migration`) deployed to the v2 server.
- S3 working (`AWS_*` set; confirmed by the S3 feature already shipped).
- Access to the legacy DB `u829554871_efectimedios` and the old app's photo dir.

## Steps

1. **Deploy** the branch to the server and `composer install --no-dev` if needed.

2. **Set in the production `.env`:**
   ```
   # Legacy DB (connection data confirmed from the legacy app)
   LEGACY_DB_HOST=localhost            # on the server; or auditoriaefectimedios.com from outside
   LEGACY_DB_PORT=3306
   LEGACY_DB_DATABASE=u829554871_efectimedios
   LEGACY_DB_USERNAME=u829554871_admin
   LEGACY_DB_PASSWORD=<the legacy DB password>

   # Absolute base path to the OLD app's public_html on the server.
   # Verify with `pwd` inside the old app's public_html.
   LEGACY_PHOTOS_PATH=/home/<user>/domains/auditoriaefectimedios.com/public_html
   ```
   Leave `LEGACY_DB_*` host/creds blank ONLY if the legacy DB shares the primary
   app's MySQL credentials (it does not here — set them explicitly).

3. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

4. **Sanity-check the legacy connection and row count:**
   ```bash
   php artisan tinker --execute="echo DB::connection('legacy')->table('estado_ele')->where('fechaEstado','like','2026-%')->count();"
   ```
   Expected: prints the number of 2026 legacy audits. A connection error here means
   the `LEGACY_DB_*` values are wrong — fix before proceeding.

5. **Verify the photo base path** (so photos are not silently skipped):
   ```bash
   ls "$LEGACY_PHOTOS_PATH/fotos/auditoria/2026" | head
   ```
   Expected: lists week-number directories (e.g. `1`, `2`, ...). If this errors,
   fix `LEGACY_PHOTOS_PATH` — otherwise every photo is skipped (the migrator skips
   missing files silently and reports "Photos uploaded: 0", not a failure).

6. **Run the migration:**
   ```bash
   php artisan migrate:legacy-audits --year=2026
   ```

7. **Review the printed counters:**
   - `Migrated audits` — audits created/updated.
   - `Skipped (invalid date)` — rows with `0000-00-00`/bad dates.
   - `Photos uploaded` — files moved to S3.

8. **Spot-check** in `/admin`: open a migrated audit, confirm its 4 active criterion
   values and that photos load from S3. The migration user is `migration` (inactive);
   the legacy auditor id is recorded in each audit's observation for traceability.

## Notes

- **Idempotent:** safe to re-run. Spaces upsert on `external_code`; audits upsert on
  `(advertising_space_id, year, week, audit_type)`; values are rebuilt; photos use a
  deterministic S3 key with an existence check.
- **Week:** the audit's `year`/`week` are recomputed from `fechaEstado` using the app's
  `Audit::getCalendarYearAndWeek()` (NOT the legacy ISO `semanaEstado`). Photo file
  paths use the legacy `semanaEstado` (how files were stored on disk).
- **Criteria:** the inactive `vandalism` criterion is seeded so the legacy
  `anomaliaEstado` value is preserved without showing in the new audit form.
- **No maintenances** are created by the migration.
