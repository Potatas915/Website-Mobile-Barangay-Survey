# Barangay Health Center Survey Management System

IT305 Advance Web Development - Act 5 Set B, plus Act 5 Set A
("Development of a Web-Based Resident Personal Information Updating
System for a Barangay Health Center") integrated into the same portal.

The backend is **Firebase Realtime Database**. There is no MySQL server, no
phpMyAdmin step, and no `config/database.php` any more.

## Folder structure

```
barangay_survey/
├── config/
│   └── firebase.php           Firebase database URL (and optional token)
├── firebase/
│   ├── barangay_survey_export.json   root-import file for a fresh database
│   └── database.rules.json     Realtime Database security rules
├── database/
│   └── barangay_survey.sql    original MySQL schema + seed, kept as the
│                              reference the export is verified against
├── api/
│   └── build_export.php       seed -> JSON generator, parity checker,
│                              and database seeder
├── assets/
│   ├── css/style.css
│   ├── js/script.js
│   └── uploads/residents/     resident profile photos land here
├── includes/
│   ├── firebase.php           REST client: read/write, counters, indexes
│   ├── functions.php          session handling, login guards, helpers
│   ├── staff_nav.php          shared staff navbar with active-page highlighting
│   └── resident_nav.php       shared resident sidebar
├── resident/                  resident-facing pages
├── staff/                     staff-facing pages
└── index.php                  landing page
```

## Setup

1. Put the folder somewhere your web server can serve it. Any PHP 8 setup
   works - XAMPP (`C:\xampp\htdocs\` or `/Applications/XAMPP/htdocs/`),
   `php -S localhost:8000`, or a shared host.
2. Open `config/firebase.php` and set `$firebase_database_url` to your own
   project's database URL from the Firebase console
   (Realtime Database -> the "Database URL" at the top of the page). It must
   start with `https://` and have **no trailing slash**.
3. In the Firebase console apply `firebase/database.rules.json`, or paste its
   contents into Realtime Database -> Rules and publish.
4. Seed the database. Either import the JSON by hand, or run the seeder:

   ```
   php api/build_export.php --push --verify
   ```

   The seeder builds the JSON from `database/barangay_survey.sql`, checks it
   against the SQL row by row, then writes the whole file to the database root
   and verifies again. Add `--verify` on its own to only check and print.
5. Make sure `assets/uploads/residents/` is writable by the web server - that
   is where uploaded resident photos are saved.
6. Open the site. That's the app.

### About the security rules

`firebase/database.rules.json` ships with open `.read` / `.write`, because
that is what a classroom demo needs: the PHP pages make anonymous REST calls
with no authentication. The practical consequence is that **anyone who knows
the database URL can read every resident record and password hash, and can
write to the database directly.** Fine for a submission, not for production -
before using real resident data, lock the rules down and set
`$firebase_database_token` in `config/firebase.php` to an admin token.

## Firebase layout

Firebase has no joins, no tables, and no `AUTO_INCREMENT`, so the old schema is
expressed differently:

| Old MySQL | Firebase |
|-----------|----------|
| `residents` table | `residents/{resident_id}` |
| `resident_children` table | `residents/{resident_id}/children/{child_id}` |
| `survey_questions` table | `surveys/{survey_id}/questions/{question_id}` |
| `survey_choices` table | `.../questions/{question_id}/choices/{choice_id}` |
| `survey_results` table | `responses/{response_id}/answers/{question_id}` |
| `AUTO_INCREMENT` | `counters/{collection}` |
| `UNIQUE` columns read on login | `lookup/resident_number/...`, `lookup/staff_username/...` |
| SQL `NULL` | `""` |

`includes/firebase.php` wraps all of it, so pages call `fb_rows("residents")`,
`fb_insert(...)`, `fb_update(...)` and friends instead of writing SQL. Pages
that need to aggregate (the staff dashboard, reports, results) load the
collection once and compute in PHP.

Two things worth knowing if you touch the client:

- Firebase stores numeric keys as `1..n` and returns them to `json_encode()` as
  an array with a leading `null`. `fb_decode()` strips that padding so
  `$row["id"]` and ordinary list handling behave. If you call the REST API
  yourself from App Inventor or JavaScript you will see the leading `null` and
  have to skip it.
- Firebase drops empty objects, so an optional node that has no children comes
  back as `null`, not `[]`. Use `fb_node()` or `?? []` when reading one.

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
| 2026-0003        | Mary Pauleen Salvador |
| 2026-0004        | Kylie Denise Marasigan |
| 2026-0005        | Aaron Gabriel Ranes    |

## Act 5 - Set A: Resident Personal Information Updating System

This activity's requirements are covered by the existing resident/staff
portal rather than a separate site, since they share the same login system
and the same `residents` collection. Where each requirement lives:

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

**Storage:** all of this lives in the `residents` collection, with children
nested under each resident. Saving an update patches the existing resident
(`fb_update`), it never inserts a duplicate; pages that rewrite a whole
profile use `fb_replace` so a single write can't leave a half-saved record.

### Still needed for submission (these are things your group has to actually do)

The activity's submission requirements aren't things code can generate for
you — make sure your group still puts together:
1. The complete source code (this folder).
2. A short video showing how to use the system.
3. A note on which area of the system each resident in your group worked on.
4. Screenshots of: the login page, the dashboard, the update-personal-info
   page, and a successful update confirmation.
5. A note that the database is imported from `firebase/barangay_survey_export.json`
   rather than a `.sql` file, and that `database/barangay_survey.sql` is kept
   as the schema reference.

## Notes for the group

- `config/firebase.php` is where the database URL lives. If you point the app
  at your own project, re-run `php api/build_export.php --push` to seed it.
- `database/barangay_survey.sql` is still the source of truth for the seed
  data. `api/build_export.php` reads it, and `--verify` fails loudly if the
  JSON and the SQL disagree, so if you edit the seed, edit the SQL and
  regenerate rather than hand-editing the JSON.
- Every page that needs a login includes `includes/functions.php`, which
  starts the session and has `require_resident_login()` / `require_staff_login()`
  to block access if not logged in.
- Passwords are stored in plain text and compared with `hash_equals()`. This is
  a deliberate choice for this class project so that the PHP portal and the
  Flutter mobile app can share one database without either needing a bcrypt
  implementation the other cannot verify. It means anyone who can read the
  Firebase database can read every password, so the rules must stay locked down
  before this is used with real resident data.
- Firebase writes are not transactional. Where the old code used
  `begin_transaction()`, the converted pages either write one whole subtree in
  a single `PUT` (surveys with their questions, question sets) or write and
  then record history. Two staff clicking Save on the same resident at the same
  instant is the one case that could interleave, which is also why
  `counters/` ids are read-then-write.
- `results.php` computes tallies and renders them as simple bar charts using
  plain CSS, no external chart library needed.
- `reports.php`, `results.php`, `resident/profile.php`, and
  `staff/resident_view.php` all have a Print button that uses the browser's
  print-to-PDF, which covers the "generate printable reports / export"
  requirement without needing an extra library.
- Exporting straight to Excel and a login history report page are still left
  as easy extensions if your group wants to go for the optional points (the
  `login_history` collection is already being written to on every login).