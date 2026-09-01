# OmniChannel Reports

A Laravel + SQLite internal management application for Media Gateways and related IT operations.

## Added professional features

- Professional navy/blue responsive UI based on the supplied reference image.
- Dashboard with live gateway, user, role, telco, configuration, activity, and login summaries.
- Media Gateway CRUD with search, sorting, pagination, duplicate detection, validation, status checks, and display-only row numbering.
- Excel-compatible `.xlsx` import/export with import preview, validation, and duplicate detection.
- Role-based permissions for System Administrator, Administrator, Standard User, and custom User Types.
- User account status, last-login tracking, login history, and password management.
- Activity/audit logging for important administrative actions.
- Notifications for offline gateways and recent failed logins.
- Telco Cost, Channel Prefix, Channel Port, and Network Prefix management modules.
- Database backup, restore, downloadable backups, and scheduled daily backups.
- Maintenance and system information tools.
- Existing login, add, edit, delete, search, sort, pagination, and logout confirmation workflows are retained.

## Local setup

1. Make sure PHP 8.3+, Composer, and Node.js are installed.
2. Run `composer install` if vendor dependencies are not present.
3. If you want to rebuild frontend assets, run `npm install` then `npm run build`.
4. The distributed SQLite database is already upgraded with the new feature tables/columns. For a fresh database, run `php artisan migrate --seed`.
5. Start the application with `php artisan serve`.
6. For automatic daily backups, run `php artisan schedule:work` while developing, or configure Laravel's scheduler in your production environment.

## Excel import format

Use an `.xlsx` file with these headers:

`Site Name | Site Code | IP Address | Username | Database`

The import screen previews the rows and skips invalid/duplicate records before insertion.

## Important

The application keeps Media Gateway database primary keys intact. The `Id` displayed in the Media Gateway table is a non-destructive display counter, so deleting database record 4 does not cause the database primary key to be rewritten.
