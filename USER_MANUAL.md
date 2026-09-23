# OmniChannel Inventory — User Manual

This is the order to enter data, and how the pages stay connected after that.

The site names are already in the system. You do not create sites. You attach records to them.

- Program Location pages: Alcar, CG3, CTN, Estancia, SC5, Skyrise, PDC
- Campaigns, Signal Boosters, and Defective GSM use: Alcar, CG3, CTN, Estancia, SC5, Skyrise, WFH
- PDC Servers can use any of those, including both PDC and WFH

## Enter data in this order

Some pages can be filled on their own. The rest fail until an earlier page already has the record they need.

### 1. Campaigns

Enter the campaign name, FTE, and site here first. This is the master list.

Channel Allocation, Program Inbound Numbers, and Archive Recordings will not accept a campaign that is not on this page. They never create a campaign for you.

PDC Servers can use a campaign from this list. If you type a name that is not here, PDC keeps that name on the PDC group only. It does not add a new row to Campaigns.

### 2. GSM Gateway or Program Location

These two pages are the same gateway records.

- Use **GSM Gateway** when you are adding the device: Hostname, IP, Serial Number, Channel Count, Function, Site, User, and Password.
- Use **Program Location** when you are adding that gateway under one site. The site name is fixed to the location you opened.

A gateway does not need a SIM yet. Create the gateway before you create SIMs, because a SIM has to name a hostname that already exists.

Each gateway needs its own serial number and its own IP address.

### 3. Globe SIM and Smart SIM

Create the SIM after the gateway exists.

- Hostname must match a GSM Gateway hostname.
- Port must be a real port on that gateway, from 1 up to that gateway’s Channel Count.
- Enter at least one other field besides Hostname and Port: IMEI, Mobile Number, Plan, Account Number, or a contract date.
- The same mobile number cannot be on both Globe SIM and Smart SIM. The same IMEI or mobile number also cannot be repeated on the same network.
- A blank mobile number is allowed. `-` in an optional SIM field is saved blank.

Saving a SIM places it on that gateway port. Deleting a SIM removes it from the gateway.

### 4. SIP Channels, then Channel Range

SIP Channels can be entered any time. A SIP record does not need a campaign.

Enter SIP Name, and when you have them: Pilot Number, Channel Count, Channel Range, Network, and Date Activation. A channel range is written as a start and end, such as `300-310`.

**Channel Range** is the list of individual numbers under a SIP. The SIP Name must already exist on SIP Channels. A blank SIP Name on the next Excel row copies the SIP above it. Each channel number has to be new.

### 5. Channel Allocation

Do this only after both of these exist:

- The campaign is on the Campaigns page.
- The channel is either an existing SIP Name or an existing GSM Hostname.

Channel Allocation does not create the campaign, the SIP, or the gateway. FTE on this page comes from the campaign. Network and channel count come from the SIP or the GSM gateway you named.

One campaign can have many channels. In Excel, leave Campaign, Caller ID, Prefix, and Remarks blank on the following rows to keep the values from the row above. Type `-` in Caller ID, Prefix, or Remarks when that value should be blank.

### 6. Program Inbound Numbers

The campaign must already be on the Campaigns page. Each row needs at least one mobile number or one landline.

- A **mobile** number should already be on Globe SIM or Smart SIM. The page then fills GSM Gateway, Port, and Network from that SIM. If you type those yourself, they have to match the SIM.
- A **landline** must match a Channel Number that already exists under Channel Range.
- GSM Gateway, Port, and Network are only for mobile numbers.

### 7. PDC Servers

You can add PDC servers once you know the campaign name and the site.

Several servers often belong to one campaign. In Excel, fill Campaign, Site, Date Endorse, and DNS on the first row. Leave those cells blank on the next server rows to keep the same values. Type `-` in one of those cells when that value should be blank. Hostname and Source IP are required on every server, and each Source IP can be used only once.

### 8. Archive Recordings

The campaign must already be on the Campaigns page. Each recording needs a file name and a call date and time. In Excel, a blank Campaign copies the campaign from the row above.

### Anytime: Signal Boosters and Defective GSM

These do not create or require campaigns, SIMs, or gateways.

- Signal Boosters need a model, a unique serial number, and a status. Location, when you enter one, must be one of the campaign sites.
- Defective GSM needs a serial tag and a status. Location, when you enter one, must be one of the campaign sites.

## What stays connected

After the records exist, a change on one page updates the pages that point at it.

| You change this | These pages follow |
| --- | --- |
| Campaign name on Campaigns | Program Inbound Numbers shows the new name. |
| SIP Name, Network, or Channel Count | Channel Allocation rows that use that SIP Name update. Deleting the SIP removes those allocation rows. |
| GSM Hostname, Network, or Channel Count | Channel Allocation rows that use that hostname update. |
| GSM IP address | Globe SIM and Smart SIM rows on that gateway take the new IP. |
| GSM Hostname | Program Inbound Numbers that point at that gateway show the new hostname. |
| Delete a GSM Gateway | Channel Allocation rows for that gateway are removed. SIMs lose that IP. Inbound numbers lose that gateway. |
| SIM mobile number, or delete a SIM | Program Inbound Numbers that use that mobile number update the number, gateway, port, and network. Deleting the SIM also takes it off the gateway port. |

Channel Allocation never writes back to Campaigns, SIP Channels, or GSM Gateway. It only reads them. If the SIP or gateway is renamed, the allocation row is renamed with it. If the SIP or gateway is deleted, the allocation row is removed.

## Excel import

Use **Data Transfer** on the page you are filling. Download that page’s template so the columns match.

The import follows the same order as manual entry. A SIM import fails if the hostname is not already a GSM Gateway. A Channel Allocation import fails if the campaign is not on Campaigns, or if the channel is not an existing SIP Name or GSM Hostname.

On a sheet with several rows for the same group:

- Leave a group cell blank to copy the row above. Campaign name, serial numbers, SIM fields, and other one-per-row values do not copy.
- Type `-` to save that cell blank and stop the rows below from copying the old value.
- A required cell that is still blank fails that row.

The columns that copy, and the columns that do not, are listed in the developer guide under **Excel import**.
