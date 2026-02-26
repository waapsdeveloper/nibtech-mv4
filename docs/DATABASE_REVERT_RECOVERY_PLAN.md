# Plan: Identify Records Missing After Database Revert

## Context

- **Setup before revert:** Live app used a **dedicated database on Digital Ocean**. A duplicate droplet ran PM2 services; the main droplet served the live URL.
- **What happened:** A mishap required reverting to the previous setup. The app was re-attached to the **local database**.
- **Problem:** During the revert window, some records were **updated (or inserted) only in the Digital Ocean database**. Those changes are **missing in the reverted local database**.
- **Goal:** Find and list those records so you can either merge them into local or document them for manual recovery.

---

## Prerequisites

- [ ] **Access to both databases**
  - **Source of truth for “new” data:** Digital Ocean dedicated DB (the one that had live traffic and got updates).
  - **Current live DB:** Your reverted local DB (or whatever DB the app points to now).
- [ ] **Credentials** for both (host, port, database name, user, password).
- [ ] **Revert window:** Approximate start and end time when the app was on the DO DB (e.g. “from morning today until revert”). This helps narrow which tables/rows to focus on if you use timestamps.
- [ ] **List of important tables** (optional but recommended). If unsure, plan to compare all application tables.

---

## Definitions

| Term | Meaning |
|------|--------|
| **DO DB** | Digital Ocean dedicated database (has the updates made during the revert window). |
| **Local DB** | Reverted database (current live); missing those updates. |
| **Missing in local** | Rows that exist in DO DB but not in Local DB (by primary key). |
| **Updated in DO** | Rows that exist in both but have different column values (DO has newer data). |

You need to find:
1. **Rows in DO that are missing in Local** (inserts that happened on DO only).
2. **Rows that exist in both but differ** (updates that happened on DO only).

---

## Step 1: Define the Revert Window

- Note the **start time** when the app was switched to the DO database.
- Note the **end time** when you reverted back to local.
- If your tables have `updated_at` / `created_at` (Laravel default), you can use this window to filter and reduce the set of rows to compare.

Example (adjust to your timezone):

- Window start: `2025-02-19 06:00:00`
- Window end:   `2025-02-19 12:00:00`

---

## Step 2: List Tables to Compare

- Get the list of tables from one of the databases (DO or Local; they should match if schema was in sync).

**Using MySQL CLI (run on either DB):**

```sql
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'your_database_name'
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

- Optionally, maintain a **priority list** (e.g. `users`, `orders`, `marketplace`, `listing_thirty_orders`, `stock_deduction_logs`, etc.) and run the comparison for those first.

---

## Step 3: Identify Primary Key(s) Per Table

For each table you compare, you need a **unique key** to match rows between DO and Local (usually `id` or a composite primary key).

**Using MySQL:**

```sql
SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'your_database_name'
  AND CONSTRAINT_NAME = 'PRIMARY'
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

- Document for each important table: primary key column(s). Most Laravel tables use a single `id` column.

---

## Step 4: Choose a Comparison Method

Pick one (or combine) of the approaches below.

---

### Method A: Export and Diff (good for small/medium data)

1. **Export from DO DB** (per table or full DB):
   ```bash
   mysqldump -h <DO_HOST> -u <DO_USER> -p <DO_DATABASE> --no-create-info --skip-extended-insert --complete-insert > do_data.sql
   ```
   Or table-by-table for specific tables.

2. **Export from Local DB** in the same way:
   ```bash
   mysqldump -h 127.0.0.1 -u <LOCAL_USER> -p <LOCAL_DATABASE> --no-create-info --skip-extended-insert --complete-insert > local_data.sql
   ```

3. **Diff the two files** (e.g. with a text diff tool, or by loading into temp tables and using SQL to find differences). Rows only in `do_data` are “missing in local”; rows in both with different content are “updated in DO”.

- **Pros:** Simple, no custom code.  
- **Cons:** Large tables produce large files; diff can be heavy.

---

### Method B: Row Count + Checksum (quick sanity check)

1. For each table, compare **row counts** on DO vs Local:
   ```sql
   SELECT COUNT(*) FROM table_name;
   ```
2. Optionally compare **checksums** (MySQL 5.7+):
   ```sql
   SELECT * FROM table_name ORDER BY id;  -- then checksum in app, or use CHECKSUM TABLE table_name;
   ```
   `CHECKSUM TABLE` gives a single value per table; if different, the table has differences.

- **Pros:** Fast to see which tables differ.  
- **Cons:** Does not show which rows; only “this table differs”.

---

### Method C: Export Primary Keys + Critical Columns from DO, Compare in Local (recommended)

1. From **DO DB**, export for each important table:
   - Primary key(s).
   - Any timestamp columns (`updated_at`, `created_at`).
   - Critical business columns you care about.

   Example for a table with `id` and `updated_at`:
   ```sql
   SELECT id, updated_at, created_at
   FROM your_table
   WHERE updated_at BETWEEN '2025-02-19 06:00:00' AND '2025-02-19 12:00:00'
   ORDER BY id;
   ```
   Export to CSV (e.g. `do_table_name.csv`).

2. Load that CSV into a temp table in **Local DB** (or use a script), then run SQL to find:
   - **IDs in DO export that don’t exist in Local** → “missing in local”.
   - **IDs that exist in both but with older `updated_at` in Local** → “updated in DO only”.

3. For those IDs, you can then **full-export only those rows from DO** and prepare INSERT/UPDATE statements for Local.

- **Pros:** Targeted, uses timestamp window, works well with Laravel’s `updated_at`/`created_at`.  
- **Cons:** Requires a few SQL exports and a small script or manual steps.

---

### Method D: Laravel Artisan / PHP Script (reusable)

- Add a **second DB connection** in `.env` / `config/database.php` for the DO database (e.g. `do_database`).
- Create an Artisan command or a one-off script that:
  1. For each table (or a configurable list):
     - Reads primary keys (and optionally `updated_at`/`created_at`) from both connections.
  2. Finds:
     - Keys present in DO but not in Local → missing rows.
     - Keys present in both where `updated_at` (or hash of important columns) differs → updated rows.
  3. Outputs a report (CSV or JSON) and optionally generates SQL or Eloquent-friendly data to apply on Local.

- **Pros:** Repeatable, can be run anytime; can respect the revert window via timestamps.  
- **Cons:** Requires writing and testing the script.

---

## Step 5: Produce a “Missing / Updated” Report

For each table, produce:

1. **List of primary key values** that are in DO but **missing in Local** (insert these or document for manual insert).
2. **List of primary key values** that exist in both but are **updated in DO** (update Local from DO or document).

Keep this report in a file (e.g. `docs/DATABASE_REVERT_DIFF_REPORT_YYYY-MM-DD.md` or a CSV) so you can apply changes in a controlled way.

---

## Step 6: Apply Changes to Local (Recovery)

- **Option 1 – Manual SQL:** For each missing/updated row, run `INSERT` or `UPDATE` on Local using values from DO (export those rows from DO and run on Local).
- **Option 2 – mysqldump selected rows:** Harder; usually done by exporting full tables from DO and then importing into a staging DB and copying only the diff rows.
- **Option 3 – Laravel/script:** Use the script from Method D to generate `INSERT`/`UPDATE` statements or to sync via Eloquent (with care for side effects and validations).

Always **back up the current local DB** before applying any merge.

---

## Step 7: Prevention for Next Time

- **Avoid single point of failure:** If you switch DB again, consider:
  - Short, planned cutover windows.
  - A one-way or two-way sync script (e.g. from Local → DO or DO → Local) that runs on a schedule, so that if you revert, you can re-run sync from DO back to Local for the revert window.
- **Retain DO DB for a few days** after revert so you can run this comparison and recovery without time pressure.
- **Document** which DB is “source of truth” at each phase (e.g. “during revert window, DO was live; after revert, Local is live and DO is read-only backup”).

---

## Quick Reference: Useful MySQL Commands

```sql
-- Row count per table (run on both DBs and diff)
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- Rows updated in a time window (Laravel tables with updated_at)
SELECT * FROM your_table
WHERE updated_at BETWEEN '2025-02-19 06:00:00' AND '2025-02-19 12:00:00';
```

---

## Summary Checklist

- [ ] Define revert window (start/end).
- [ ] List tables to compare (all or priority list).
- [ ] Get primary key(s) per table.
- [ ] Choose comparison method (A–D) and run it.
- [ ] Generate missing/updated report per table.
- [ ] Back up local DB, then apply recovery (insert/update from DO).
- [ ] Keep DO DB available and document prevention steps for future switches.

If you want, the next step can be a concrete Artisan command or SQL script tailored to your Laravel app and table list.
