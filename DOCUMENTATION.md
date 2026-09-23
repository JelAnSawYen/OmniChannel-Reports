# OmniChannel Inventory — Developer Guide

For the order to enter data and how the pages stay connected, see `USER_MANUAL.md`.


## Overall structure

```
app/
  Console/Commands/          Artisan commands (log prune, backup)
  Http/Controllers/          HTTP layer, grouped by module
  Http/Middleware/           Global request middleware
  Http/Requests/Gsm/         GSM / Media Gateway form requests
  Models/                    Eloquent models (Laravel convention: one Models folder)
  Providers/                 App service provider
  Services/                  Business services (shared + module folders)
  Support/                   Helpers and catalogs (shared + module folders)
  View/Composers/            Layout data for the sidebar/header

resources/
  css/app.css                Global styles (all pages)
  js/app.js                  Global JavaScript (all pages)
  views/                     Blade pages, grouped by module
  views/layouts/             Main shell (sidebar, header) and guest layout
  views/partials/            Shared UI pieces (campaign combo, import, dates)

routes/web.php               All HTTP routes
routes/console.php           Scheduled commands
database/                    Migrations, seeders, factories
tests/                       PHPUnit feature and unit tests
```

## Global UI and shared code

| What | Where |
| --- | --- |
| Main layout, sidebar, header, account menu | `resources/views/layouts/app.blade.php` |
| Sidebar/header data (permissions, alerts) | `app/View/Composers/AppLayoutComposer.php` |
| Guest/login layout | `resources/views/layouts/guest.blade.php` |
| Global CSS | `resources/css/app.css` |
| Global JavaScript | `resources/js/app.js` |
| Shared campaign dropdown | `resources/views/partials/campaign-combo.blade.php` |
| Shared Data Transfer / import UI | `resources/views/partials/data-transfer.blade.php` |
| Shared import script | `resources/views/partials/inventory-import-script.blade.php` |
| Shared MDY date picker | `resources/views/partials/mdy-date-field.blade.php` |
| Shared inventory import trait | `app/Http/Controllers/HandlesInventoryImport.php` |
| Shared XLSX export/import | `app/Services/XlsxService.php`, `app/Services/InventoryImportService.php` |
| Module catalog (operations modules, locations) | `app/Support/OperationCatalog.php` |
| Import column maps | `app/Support/InventoryImportCatalog.php` |
| Blank cell and dash check | `app/Support/ImportCell.php` |
| Shared audit writer | `app/Services/Logs/AuditLogger.php` |
| Routes | `routes/web.php` |
| Middleware / permissions | `bootstrap/app.php`, `app/Http/Middleware/` |
| Validation helpers | `app/Support/PasswordRules.php`, Form Requests under `app/Http/Requests/` |

After CSS or JS changes, run `npm run build`.

---

## Excel import

Data Transfer uses the same two cell rules on every page.

- A blank cell copies the last real value from the rows above, but only on the columns listed below as copying.
- A cell that contains only `-` is saved blank. It does not copy the row above, and the rows below stop copying that column.
- A required column that is still blank after that fails the row.
- A row that is only blanks and dashes is skipped.

`InventoryImportService` applies this for Campaigns, GSM Gateway, Program Location, Globe SIM, Smart SIM, Program Inbound Numbers, Signal Boosters, and Defective GSM. Columns in `no_carry`, `unique`, or `ip_fields` in `InventoryImportCatalog` do not copy. The other import services apply the same dash rule themselves.

| Page | Copies a blank from the row above | Does not copy | Dash |
| --- | --- | --- | --- |
| Campaigns | FTE, Site | Campaign name | Empty. Name, FTE, and Site are required, so `-` fails that column. |
| PDC Servers | Campaign, Site, Date Endorse, DNS | Hostname, Source IP, OS, RAM, CPU, Storage, Admin Username, Password, SQL DB Password | Clears a copied column. On the server columns it is saved blank. Hostname and Source IP stay required. |
| SIP Channels | None | Every column | Empty. A range such as `300-310` is kept. `-` in SIP Name fails the row. |
| Channel Range | SIP Name | Channel Number | Clears SIP Name. `-` in Channel Number fails the row. |
| Channel Allocation | Campaign, Caller ID, Prefix, Remarks | Channel, Line Priority | Clears Caller ID, Prefix, and Remarks. `-` in Channel fails the row. `-` in Line Priority is saved blank. Network, Channel Count, and FTE are filled by the system. |
| Archive Recordings | Campaign | File Name, Call Date & Time, Caller Number, Agent Number, Duration, Location, Storage Path | Empty on the call columns. File Name and Call Date & Time stay required. |
| GSM Gateway | Hostname, IP, Serial Number, Channel Count, Function, Site, User, Password | Port, IMEI, Mobile Number, Network, Plan, Remarks | Empty. A dash on a copied gateway column stops the copy for the rows below. |
| Program Inbound Numbers | Campaign | Mobile, Landline, GSM Gateway, Port, Network, Remarks | Empty. |
| Signal Boosters | Model, Location, Status | Serial Number, Specifications | Empty. `-` in Specifications is saved blank. Serial Number stays required. |
| Defective GSM | Location | Serial Tag, Issue, Reported On, Status | Empty. `-` in Issue or Reported On is saved blank. Serial Tag and Status stay required. |
| Program Location | Username, Database | Site Code, IP Address | Empty. Site Name is always the location being imported. Username and Database stay required. |
| Globe SIM and Smart SIM | None | Every column | Empty, and it is not stored. |

Globe SIM and Smart SIM also cannot share a mobile number. The check is `GsmSimInventory::mobileOwnedByOtherNetwork()`. Excel import calls it from `InventoryImportService`. Add and Edit call it from `OperationsDataController`. A blank mobile number is still allowed. The same number on the same network is still rejected by that network's own unique rule.

To change which columns copy → `no_carry` and `unique` in `InventoryImportCatalog`, or `CARRY_FIELDS` in the module import service.  
To change what `-` means → `app/Support/ImportCell.php`, then the carry loop in `InventoryImportService` and each module import service.

---

## Where to change each area

### Sidebar / header
`resources/views/layouts/app.blade.php`  
`app/View/Composers/AppLayoutComposer.php`  
`resources/css/app.css` (`.sidebar`, `.app-header`)  
`resources/js/app.js` (account menu, notifications)

### Global styles / JavaScript
`resources/css/app.css`  
`resources/js/app.js`

### Routes
`routes/web.php`

### Validation
- Module forms: the module controller (and GSM Form Requests below)
- Passwords: `app/Support/PasswordRules.php`
- GSM / Media Gateway: `app/Http/Requests/Gsm/`

### Business logic
Module controllers + the matching service/support class listed below. Shared catalogs live in `app/Support/`.

---

## Authentication

- Controllers: `app/Http/Controllers/Auth/` (`LoginController`, MFA, password reset, email verification)
- Views: `resources/views/auth/`
- MFA service: `app/Services/Auth/TotpService.php`
- User model: `app/Models/User.php`

To change login / logout / failed-login recording → `app/Http/Controllers/Auth/LoginController.php`

---

## Administration (Users, Profile, Maintenance)

- Controllers: `app/Http/Controllers/Admin/`
- Views: `resources/views/users/`, `resources/views/profile/`, `resources/views/maintenance/`
- Backup service: `app/Services/Admin/DatabaseBackupService.php`
- Models: `app/Models/User.php`, `UserType.php`, `MailSetting.php`

To change Users → `app/Http/Controllers/Admin/UserController.php` and `resources/views/users/`  
To change Profile → `app/Http/Controllers/Admin/ProfileController.php` and `resources/views/profile/`  
To change Maintenance → `app/Http/Controllers/Admin/MaintenanceController.php` and `resources/views/maintenance/`

---

## Dashboard

- Controller: `app/Http/Controllers/DashboardController.php`
- View: `resources/views/dashboard/`
- Overview numbers: `app/Services/DashboardOverviewService.php`
- Alerts: `app/Services/OperationalAlerts.php`

---

## Campaigns

- Controller: `app/Http/Controllers/Campaigns/CampaignController.php`
- View: `resources/views/campaigns/`
- Model: `app/Models/ChannelAllocationCampaign.php` (shared campaign master, also used by SIP, PDC, Channel Allocation, Archive)

To change Campaigns → those three plus the shared combo partial.

---

## Channel Allocation

- Controller: `app/Http/Controllers/ChannelAllocation/ChannelAllocationController.php`
- View: `resources/views/channel-allocations/`
- Import: `app/Services/ChannelAllocation/ChannelAllocationImportService.php`
- Model: `app/Models/ChannelAllocation.php`

---

## PDC Servers

- Controller: `app/Http/Controllers/Pdc/PdcServerController.php`
- View: `resources/views/pdc-servers/`
- Import: `app/Services/Pdc/PdcServerImportService.php`
- Models: `app/Models/PdcGroup.php`, `app/Models/PdcServer.php`
- Date helper (also used by SIP and operations): `app/Support/PdcEndorseDate.php`

---

## SIP Channels and Channel Range List

- Controllers: `app/Http/Controllers/Sip/`
- Views: `resources/views/sip-channels/`, `resources/views/channel-range-list/`
- Import: `app/Services/Sip/`
- Models: `app/Models/SipChannel.php`, `app/Models/SipChannelNumber.php`

---

## GSM Gateway and Program Location

- Controllers: `app/Http/Controllers/Gsm/` (`MediaGatewayController` also serves Media Gateways)
- Views: `resources/views/media-gateways/`, `resources/views/program-location/`
- Form requests: `app/Http/Requests/Gsm/`
- Support: `app/Support/Gsm/` (gateway writer, import mapper, location status)
- Shared SIM lookup: `app/Support/GsmSimInventory.php`
- Models: `app/Models/MediaGateway.php`, `app/Models/GatewaySimAssignment.php`

To change GSM Gateway → `app/Http/Controllers/Gsm/MediaGatewayController.php` and `resources/views/media-gateways/`  
To change Program Location → `app/Http/Controllers/Gsm/LocationController.php` and `resources/views/program-location/`

---

## Globe SIM, Smart SIM, and other operations modules

These modules share one controller and one view. The module list is in `OperationCatalog`.

- Controller: `app/Http/Controllers/Operations/OperationsDataController.php`
- View: `resources/views/operations/index.blade.php`
- Catalog: `app/Support/OperationCatalog.php`
- Inbound helpers: `app/Support/Inbound/`
- Models:
  - Globe SIM → `app/Models/GlobeSim.php`
  - Smart SIM → `app/Models/SmartSim.php`
  - Program Inbound Numbers → `app/Models/ProgramInboundNumber.php`
  - Signal Boosters → `app/Models/SignalBooster.php`
  - Defective GSM → `app/Models/DefectiveGsm.php`
  - Telco Cost, Channel Prefix, Channel Port, Network Prefix → matching files in `app/Models/`

To change Globe SIM or Smart SIM → `OperationsDataController`, `operations/index.blade.php`, the SIM model, and `GsmSimInventory::mobileOwnedByOtherNetwork()` for the cross-network mobile-number rule.

---

## Archive Recordings

- Controller: `app/Http/Controllers/Archive/ArchiveRecordingController.php`
- View: `resources/views/archive-recordings/`
- Import / storage: `app/Services/Archive/`, `app/Support/Archive/`
- Model: `app/Models/ArchiveRecording.php`

---

## Login History

- Controller: `app/Http/Controllers/Logs/LoginLogController.php`
- View: `resources/views/login-history/`
- Model: `app/Models/LoginLog.php`
- Recording of Login / Logout / Failed Login: `app/Http/Controllers/Auth/LoginController.php`
- Retention: `app/Services/Logs/LogRetentionService.php`
- Prune command: `app/Console/Commands/PruneOldLogs.php`

To change Login History → `LoginLogController` and `resources/views/login-history/`  
To change what gets recorded → `LoginController`

---

## Audit Logs

- Controller: `app/Http/Controllers/Logs/ActivityLogController.php`
- View: `resources/views/audit-logs/`
- Model: `app/Models/AuditLog.php`
- Writer: `app/Services/Logs/AuditLogger.php` (used by all modules)
- Retention: `app/Services/Logs/LogRetentionService.php`

To change Audit Logs → `ActivityLogController` and `resources/views/audit-logs/`  
To change how activities are stored → `AuditLogger` and the module controller that calls it

Login, Logout, Failed Login, and Created Backup are excluded from Audit Logs.

---

## Reports

- Channel Utilization: `app/Http/Controllers/Reports/ChannelUtilizationController.php`, `resources/views/channel-utilization/`
- System Health: `app/Http/Controllers/Reports/SystemHealthController.php`, `resources/views/system-health/`, `app/Services/SystemHealthService.php`

---

## Database

Migrations and seeders stay in `database/`. Do not move or rewrite them for organization. Models stay in `app/Models/` so Laravel factories, auth, and existing class names keep working.
