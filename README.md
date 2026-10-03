# Barangay Health Center Survey Management System

IT305 Advance Web Development - Act 5 Set B, plus Act 5 Set A
("Development of a Web-Based Resident Personal Information Updating
System for a Barangay Health Center") integrated into the same portal.

## Folder structure

```
barangay_survey/
├── config/
│   └── database.php          database connection settings
├── database/
│   ├── barangay_survey.sql   full schema + sample data, import this first
│   └── migration_set_a.sql   only needed if you already imported the DB
│                              before Set A was added (see below)
├── assets/
│   ├── css/style.css
│   ├── js/script.js
│   └── uploads/residents/    resident profile photos land here
├── includes/
│   ├── functions.php         session handling, login guards, helpers
│   ├── staff_nav.php         shared staff navbar with active-page highlighting
│   └── resident_nav.php      shared resident sidebar
├── resident/                 resident-facing pages
├── staff/                    staff-facing pages
└── index.php                 landing page
```

## How to run it in XAMPP

1. Start XAMPP and turn on Apache and MySQL from the XAMPP Control Panel.
2. Copy the whole `barangay_survey` folder into `C:\xampp\htdocs\` (or wherever
   your XAMPP htdocs folder is on Mac/Linux, usually `/Applications/XAMPP/htdocs/`).
3. Open `http://localhost/phpmyadmin` in your browser.
4. Drop the existing `barangay_survey_db` database if you already have an
   older copy, then click "Import" and choose `database/barangay_survey.sql`.
   This creates the database with all tables (including the resident
   personal-information fields and the `resident_children` table used by
   Set A) and the sample data below.
   - **Already have the database imported from before?** Don't drop it if you
     want to keep your data — instead just import `database/migration_set_a.sql`,
     which adds the new columns/table without touching existing rows.
5. Make sure `assets/uploads/residents/` is writable by the web server (on
   XAMPP this is usually already the case) — that's where uploaded resident
   photos are saved.
6. Open `http://localhost/barangay_survey/` in your browser. That's the app.

## Test accounts

Staff login:
- Username: `admin`
- Password: `admin123`

Resident logins (default password for each is the same as their resident number,
and each will be asked to set a new password on first login only):

| Resident Number | Name                  |
|------------------|-----------------------|
| 2026-0001        | Justin Lian Enriquez  |
| 2026-0002        | Cielo Marie Estolloso  |
| 2026-0003        | Mary Pauleen Salvador  |
| 2026-0004        | Kylie Denise Marasigan |
| 2026-0005        | Aaron Gabriel Ranes    |

## Act 5 - Set A: Resident Personal Information Updating System

This activity's requirements are covered by the existing resident/staff
portal rather than a separate site, since they share the same login system
and `residents` table. Where each requirement lives:

**Residents can (`resident/` pages):**
- Log in with their Resident Number, default password = Resident Number,
  forced password change on first login — `resident/login.php`,
  `resident/change_password.php`.
- View & update personal information (name, civil status, address, contact,
  birthday/auto-computed age, occupation, employer), contact info, spouse
  info, children (add/remove), parents, and character references — all in
  `resident/profile.php`.
- Upload a passport-size photo — photo uploader at the top of
  `resident/profile.php` (JPG/PNG/WEBP, up to 3MB).
- Print their own profile — "Print My Profile" button on
  `resident/profile.php` (browser print-to-PDF).
- Log out — `resident/logout.php`.

**Staff can (`staff/` pages):**
- Secure login & dashboard — `staff/login.php`, `staff/dashboard.php`.
- Add new resident — `staff/register.php` (auto-assigns the Resident Number
  as the default password, per the spec).
- View resident records / search — `staff/resident_management.php`.
- Edit resident information (all fields, photo, children) —
  `staff/resident_edit.php`.
- Delete a resident record — delete button on `staff/resident_management.php`.
- Reset a resident's password back to their Resident Number —
  reset button on `staff/resident_management.php`.
- View updated records (most recently edited first) —
  `staff/updated_records.php`.
- Print a resident's information — `staff/resident_view.php`.
- Generate reports — resident summary section added to `staff/reports.php`,
  plus a CSV export at `staff/resident_export.php`.
- Log out — `staff/logout.php`.

**Data validation:** required fields are enforced both in the HTML (`required`
attributes) and again server-side before any database write; invalid or
incomplete submissions show an inline error instead of silently failing.

**Database:** all of this lives in the same `residents` table plus a new
`resident_children` table — see `database/barangay_survey.sql` /
`database/migration_set_a.sql`. Saving an update always updates the existing
row (`UPDATE ... WHERE resident_id = ?`), it never inserts a duplicate.

### Still needed for submission (these are things your group has to actually do)

The activity's submission requirements aren't things code can generate for
you — make sure your group still puts together:
1. The complete source code (this folder).
2. A short video showing how to use the system.
3. A note on which area of the system each resident in your group worked on.
4. The `.sql` file (already here, in `database/`).
5. Screenshots of: the login page, the dashboard, the update-personal-info
   page, and a successful update confirmation.

## What changed in this update

- Added the 5 dummy resident accounts above to the seed data.
- Survey Start Date can no longer be set earlier than today. This is enforced
  with the `min` attribute on the date picker (frontend) and with
  `is_valid_start_date()` in `includes/functions.php` (backend), used in both
  `staff/survey_add.php` and the new `staff/survey_edit.php`. Editing an
  already-active survey without changing its start date is still allowed even
  if that original date is in the past.
- `responses` now has a `resident_name` column, filled in at submission time,
  so admins can identify who answered without an extra join. Short-answer
  responses in `staff/results.php` now show the respondent's name next to
  each answer.
- Added a "View Respondents" button beside the survey dropdown on
  `staff/results.php`. It opens a modal (styled to match the existing card
  theme) listing every resident who answered the selected survey, along with
  their resident number and submission time.
- Change Password is no longer a separate nav item. It's now a section inside
  `resident/profile.php`, alongside the existing profile fields.
  `resident/change_password.php` still exists, but only as the forced
  first-login flow (redirected to straight from login, never linked in any
  menu). Once the password is changed there, `is_first_login` is set to 0 and
  the prompt never appears again on later logins.
- Added `staff/survey_edit.php` so staff can edit a survey's title,
  description, start date, and end date, pre-filled with the current values
  and validated the same way as creating a new survey.
- Removed the Delete Question feature entirely from
  `staff/question_management.php` (the delete link, the delete route, and the
  backend delete logic). The page is now a read-only list of a survey's
  questions.
- Fixed the staff navigation bar. Every staff page previously had a different,
  hand-written set of nav links with no active-page indicator. All staff
  pages now include the same `includes/staff_nav.php` partial, which
  highlights exactly the current page and keeps the same links everywhere.
- **Act 5 - Set A integration:** extended `residents` with civil status,
  birthday/age, occupation/employer, parents, spouse, references, and a
  photo; added `resident_children`; rebuilt `resident/profile.php` around all
  of it with print support; added `staff/resident_view.php`,
  `staff/resident_edit.php`, `staff/updated_records.php`, and
  `staff/resident_export.php`; added View/Edit/Reset Password/Delete actions
  to `staff/resident_management.php`; added a resident summary to
  `staff/reports.php`; and fixed `staff/register.php` so the default
  password is always the Resident Number, per the spec.

## Notes for the group

- `config/database.php` is where the database name/user/password live. If your
  MySQL root user has a password set, update it there.
- Every page that needs a login includes `includes/functions.php`, which
  starts the session and has `require_resident_login()` / `require_staff_login()`
  to block access if not logged in.
- Passwords are hashed with PHP's `password_hash()` and checked with
  `password_verify()`, never stored in plain text.
- `results.php` computes tallies and renders them as simple bar charts using
  plain CSS, no external chart library needed.
- `reports.php`, `results.php`, `resident/profile.php`, and
  `staff/resident_view.php` all have a Print button that uses the browser's
  print-to-PDF, which covers the "generate printable reports / export"
  requirement without needing an extra library.
- Exporting straight to Excel and a login history report page are still left
  as easy extensions if your group wants to go for the optional points (the
  `login_history` table is already being written to on every login).
