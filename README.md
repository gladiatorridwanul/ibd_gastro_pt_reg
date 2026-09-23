# PMRMS v2.1 (PHP + MySQL)

- **CSRF redeclare fix** (require_once + guards)
- **Full-Profile cards** structure (see v2 build)
- **Geo Import** (manual CSV) and **One‑Click Geo Import** (GitHub CSVs)

## Quick Start (XAMPP)
1) Extract to `C:\xampp\htdocs\pmrms_v2_1`
2) Create DB `pmrms` and import `database/schema.sql`
3) Edit `config/config.php` (DB creds, base_url)
4) Open `http://localhost/pmrms_v2_1/public` → Login: `admin@example.com / Admin@12345`
5) Administration → **One‑Click Geo Import** or **Geo Import** to load Bangladesh Divisions/Districts/Thanas
