# Patients Manage Enhancements (Robust Includes v2)

This pack keeps your Header/Sidebar/Footer and adds:
- Dynamic search (live client-side + optional server-side)
- Icon + text action buttons
- Full-data export (CSV by default; XLSX optional)

It now includes more fallback locations for the layout files and an optional `include_base.php` you can edit to hard‑set the include directory.

## Files
- `manage.php` — content + robust includes (v2)
- `export_patients.php` — full export endpoint
- `include_base.php` — optional, return the exact folder containing header.php/sidebar.php/footer.php
- `README.md`

## Setup
1. Copy all files to `src/patients/`.
2. If the page still cannot find header/footer, open **project root** `include_base.php` (the one in this ZIP) and set it to the directory that actually contains your layout, e.g.:
   ```php
   <?php return __DIR__ . '/public/includes';
   ```
   or
   ```php
   <?php return __DIR__ . '/src/layouts';
   ```
   Save and reload.
3. Alternatively, you can set an environment variable before Apache starts:
   - Windows (PowerShell):
     ```powershell
     setx PMRMS_INCLUDE_DIR "C:\\xampp\\htdocs\\pmrms\\public\\includes"
     ```
   - Then restart Apache.

## XLSX (optional)
```
composer require phpoffice/phpspreadsheet
```
Then ensure Composer autoload is loaded globally or uncomment the autoload line inside `export_patients.php`.
