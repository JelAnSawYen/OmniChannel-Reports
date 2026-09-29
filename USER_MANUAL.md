# OmniChannel Inventory

## User Manual

**Audience:** operations staff and supervisors who enter and maintain inventory records.  
**Companion document:** `DOCUMENTATION.md` is the technical guide for IT.  
**Application:** OmniChannel Inventory, an internal web system for campaigns, gateways, SIMs, SIP channels, allocations, PDC servers, and call archives.

This manual describes how the system behaves today. Sites are built into the application. Users attach records to those sites. Users do not create new sites.

---

## 1. Signing in

1. Open the application address provided by IT.
2. Enter your email and password.
3. If your account is new, open the verification email and confirm the address before using the system.
4. Use **Forgot password?** on the login page if you need a reset link. Reset links are sent to the email on the account.

Passwords must be at least 12 characters and include uppercase letters, lowercase letters, a number, and a symbol.

Two roles are in use:

| Role | What you can do |
| --- | --- |
| Administrator | All inventory pages, user management, login history, audit logs, and maintenance. Can add, edit, delete, import, and export. |
| Standard User | Dashboard, Campaigns, GSM Gateway, Channel Allocation, Globe SIM, and Smart SIM. Can view and export. Cannot add, edit, delete, or import, and cannot open administration pages. |

Your name is in the account menu at the top right. **My Profile** changes your name and password. **Logout** ends the session.

---

## 2. How the screens work

Every inventory page uses the same pattern.

| Action | Where |
| --- | --- |
| Find a record | Search box on that page. Search applies to the fields shown for that module. |
| Add | **+** or the add button. Required fields are marked by the form. Save stays on the same page and shows a success or error message. |
| Edit | Open the row action and choose edit. Saving replaces that record. |
| Delete | Delete one row, or select several rows and use bulk delete. Deletion asks for confirmation. |
| Export | **Export Data** downloads an Excel file of the current list, including the active search. |
| Import | **Data Transfer**. Download that page’s template, fill it, preview, then confirm. Nothing is saved until you confirm a preview that has no errors. |

Import rules that apply on every page:

- Download the template from the page you are filling. Column names must match that template.
- A blank cell copies the value from the row above only on the group columns listed in section 4. Names, serial numbers, channels, and other one-per-row values do not copy.
- A cell that contains only `-` is saved blank. It does not copy the row above, and later blank cells in that column stay blank.
- A required cell that is still blank fails that row.
- A row made only of blanks and dashes is skipped.
- If any row has an error, the file is not imported. Download the error file, fix the sheet, and preview again.

---

## 3. Order to enter data

Some pages stand alone. The others reject a record until an earlier page already has the name they need.

### 3.1 Campaigns

Enter the campaign name, FTE, and site first. This is the master list.

Channel Allocation, Program Inbound Numbers, and Archive Recordings accept only a campaign that is already on this page. They do not create a campaign for you.

PDC Servers may use a campaign from this list. If you type a name that is not on Campaigns, PDC keeps that name on the PDC group only. It does not add a row to Campaigns.

Sites on Campaigns, Signal Boosters, and Defective GSM: Alcar, CG3, CTN, Estancia, SC5, Skyrise, WFH.

### 3.2 GSM Gateway or Program Location

These two pages are the same gateway records.

- Use **GSM Gateway** to add the device: Hostname, IP, Serial Number, Channel Count, Function, Site, User, and Password.
- Use **Program Location** to work with that gateway under one site. The site is fixed to the location you opened: Alcar, CG3, CTN, Estancia, SC5, Skyrise, or PDC.

Create the gateway before you create SIMs. A SIM must name a hostname that already exists. Each gateway needs its own serial number and its own IP address. A gateway does not need a SIM yet.

### 3.3 Globe SIM and Smart SIM

- Hostname must match a GSM Gateway hostname.
- Port must be a number from 1 through that gateway’s Channel Count.
- Enter at least one other field besides Hostname and Port: IMEI, Mobile Number, Plan, Account Number, or a contract date.
- The same mobile number cannot be used on both Globe SIM and Smart SIM.
- The same IMEI or mobile number cannot be repeated on the same network.
- A blank mobile number is allowed.

Saving a SIM places it on that gateway port. Deleting a SIM removes it from the gateway.

### 3.4 SIP Channels, then Channel Range

SIP Channels can be entered at any time. A SIP record does not need a campaign.

Enter SIP Name, and when you have them: Pilot Number, Channel Count, Channel Range, Network, and Date Activation. A channel range is a start and an end, such as `300-310`.

**Channel Range** lists the individual numbers under a SIP. The SIP Name must already exist on SIP Channels. A blank SIP Name on the next Excel row copies the SIP above it. Each channel number must be new.

### 3.5 Channel Allocation

Do this only after both of these exist:

- The campaign is on the Campaigns page.
- The channel is an existing SIP Name or an existing GSM Hostname.

Channel Allocation does not create the campaign, the SIP, or the gateway. FTE on this page comes from the campaign. Network and channel count come from the SIP or the GSM gateway you named.

**On the form,** you may save the campaign row with Caller ID, Prefix, and Remarks and leave Channel blank. That stores the primary row only.

**In Excel,** every row that has data must include a Channel. A campaign-only row exported with a blank Channel cannot be imported. The preview reports **Channel is required**. Put an existing SIP Name or GSM Hostname in Channel, then import again.

One campaign can have many channels. In Excel, leave Campaign, Caller ID, Prefix, and Remarks blank on the following rows to keep the values from the row above. Type `-` in Caller ID, Prefix, or Remarks when that value should be blank. Channel does not copy down.

### 3.6 Program Inbound Numbers

The campaign must already be on the Campaigns page. Each row needs at least one mobile number or one landline.

- A mobile number should already be on Globe SIM or Smart SIM. The page then fills GSM Gateway, Port, and Network from that SIM. If you type those yourself, they have to match the SIM.
- A landline must match a Channel Number that already exists under Channel Range.
- GSM Gateway, Port, and Network apply to mobile numbers.

### 3.7 PDC Servers

Add servers when you know the campaign name and the site. PDC sites include the campaign sites plus both PDC and WFH.

Several servers often belong to one campaign. In Excel, fill Campaign, Site, Date Endorse, and DNS on the first row. Leave those cells blank on the next server rows to keep the same values. Type `-` in one of those cells when that value should be blank. Hostname and Source IP are required on every server, and each Source IP can be used only once.

### 3.8 Archive Recordings

The campaign must already be on the Campaigns page. Each recording needs a file name and a call date and time. In Excel, a blank Campaign copies the campaign from the row above.

### 3.9 Signal Boosters and Defective GSM

These do not require campaigns, SIMs, or gateways.

- Signal Boosters need a model, a unique serial number, and a status. Location, when entered, must be one of the campaign sites.
- Defective GSM needs a serial tag and a status. Location, when entered, must be one of the campaign sites.

---

## 4. Excel columns that copy

| Page | A blank cell copies the row above | Does not copy | A lone `-` |
| --- | --- | --- | --- |
| Campaigns | FTE, Site | Campaign name | Saved blank. Name, FTE, and Site are required, so `-` fails that column. |
| PDC Servers | Campaign, Site, Date Endorse, DNS | Hostname, Source IP, and the other server fields | Clears a copied column. On server columns it is saved blank. Hostname and Source IP stay required. |
| SIP Channels | None | Every column | Saved blank. A range such as `300-310` is kept. `-` in SIP Name fails the row. |
| Channel Range | SIP Name | Channel Number | Clears SIP Name. `-` in Channel Number fails the row. |
| Channel Allocation | Campaign, Caller ID, Prefix, Remarks | Channel, Line Priority | Clears Caller ID, Prefix, and Remarks. `-` in Channel fails the row because Channel is required. `-` in Line Priority is saved blank. Network, Channel Count, and FTE are filled by the system. |
| Archive Recordings | Campaign | File Name, Call Date & Time, and the other call columns | Saved blank on the call columns. File Name and Call Date & Time stay required. |
| GSM Gateway | Hostname, IP, Serial Number, Channel Count, Function, Site, User, Password | Port, IMEI, Mobile Number, Network, Plan, Remarks | Saved blank. A dash on a copied gateway column stops the copy for the rows below. |
| Program Location | Username, Database | Site Code, IP Address | Saved blank. Site Name is the location you are importing. Username and Database stay required. |
| Globe SIM and Smart SIM | None | Every column | Saved blank and is not stored. |
| Program Inbound Numbers | Campaign | Mobile, Landline, GSM Gateway, Port, Network, Remarks | Saved blank. |
| Signal Boosters | Model, Location, Status | Serial Number, Specifications | Saved blank. Serial Number stays required. |
| Defective GSM | Location | Serial Tag, Issue, Reported On, Status | Saved blank. Serial Tag and Status stay required. |

---

## 5. What updates other pages

After records exist, a change on one page updates the pages that point at it.

| You change this | These pages follow |
| --- | --- |
| Campaign name on Campaigns | Program Inbound Numbers shows the new name. |
| SIP Name, Network, or Channel Count | Channel Allocation rows that use that SIP Name update. Deleting the SIP removes those allocation rows. |
| GSM Hostname, Network, or Channel Count | Channel Allocation rows that use that hostname update. |
| GSM IP address | Globe SIM and Smart SIM rows on that gateway take the new IP. |
| GSM Hostname | Program Inbound Numbers that point at that gateway show the new hostname. |
| Delete a GSM Gateway | Channel Allocation rows for that gateway are removed. SIMs lose that IP. Inbound numbers lose that gateway. |
| SIM mobile number, or delete a SIM | Program Inbound Numbers that use that mobile number update the number, gateway, port, and network. Deleting the SIM also takes it off the gateway port. |

Channel Allocation reads Campaigns, SIP Channels, and GSM Gateway. It does not write those records. If the SIP or gateway is renamed, the allocation row is renamed with it. If the SIP or gateway is deleted, the allocation row is removed.

---

## 6. Module reference

### Dashboard

Summary counts for campaigns, gateways, SIMs, and related inventory. Cards open the matching list when your role can see that page.

### Campaigns

Master campaign name, FTE, and site. Required before Channel Allocation, Program Inbound Numbers, and Archive Recordings.

### PDC Servers

Servers grouped by campaign and site. Hostname and Source IP are unique per server. OS, RAM, CPU, storage, and passwords describe the machine.

### SIP Channels

SIP Name, pilot number, channel count, range, network, and activation date. SIP Name is the value Channel Allocation uses for a SIP channel.

### Channel Range

One row per channel number under a SIP. Open it from the arrow beside SIP Channels.

### Channel Allocation

Assigns an existing SIP Name or GSM Hostname to a master campaign, with Caller ID, Prefix, Line Priority, and Remarks. See section 3.5 for the difference between the form and Excel.

### Archive Recordings

Call recordings tied to a master campaign: file name, call date and time, caller number, agent number, duration, location, and storage path.

### GSM Gateway

Gateway identity and credentials, plus the ports created from Channel Count. SIMs occupy those ports.

### Network

Globe SIM and Smart SIM. Open the group from the Network row. Mobile numbers must be unique across both networks.

### Program Inbound Numbers

Campaign numbers that resolve to a SIM (mobile) or a Channel Range number (landline).

### Signal Boosters

Model, unique serial number, specifications, location, and status.

### Defective GSM

Serial tag, issue, reported date, location, and status.

### Program Location

The same gateways as GSM Gateway, opened by site.

### Settings (Administrator)

From the account menu:

- **User Management** creates and edits users, assigns Administrator or Standard User, and sets whether a Standard User may add, edit, delete, or export.
- **Login History** lists login, logout, and failed login. Records older than 14 days can be cleared and are removed by the daily schedule.
- **Audit Logs** lists create, update, delete, import, and export activity. Login, logout, failed login, and backup creation are not listed here. The same 14-day retention applies.

### My Profile

Every signed-in user can change their own name and password.

---

## 7. Sidebar

SIP Channels is a link to the SIP Channels page. The arrow beside it shows or hides Channel Range. Channel Range is open when you are on the Channel Range page.

Network shows or hides Globe SIM and Smart SIM. That group is open when you are on Globe SIM or Smart SIM.

Opening a submenu moves the items under it down. Closing it returns those items to their previous positions.
