# CommonsCal

A WordPress plugin for publishing community events, collecting event registrations, and handling study-room bookings.

Developed by Southern Maine Technology Initiative.

## Install

1. Copy the `community-calendar-study-rooms` folder into `wp-content/plugins/` (or upload the ZIP under **Plugins > Add New > Upload Plugin**).
2. Activate **CommonsCal**.
3. Create WordPress user accounts for staff and give them the **Editor** role, or grant their role the `manage_calendar_system` capability. Administrators and Editors receive this capability on activation.
4. Open **CommonsCal > Home** in the WordPress dashboard for an overview of upcoming events, room requests, active rooms, and staff entries. The Home page links to event management; event management is no longer a separate navigation tab. Staff can also manage events from the website calendar after signing in.
5. Use **Room Approvals** to review pending requests and approve or deny them. Use **Event Registrations** to select an upcoming event and view its registrants and custom-field responses. Draft upcoming events are included; past events remain accessible through the event manager.
6. Add equipment and quantities under **Settings > Equipment** before accepting equipment reservations. **Reports** provides date-filtered room, equipment, and event reports with separate CSV downloads.

The plugin creates its tables when activated. Deactivating it does not delete events, registrations, rooms, or bookings.

## Code layout

`community-calendar-study-rooms.php` is the WordPress plugin entry point. Feature code is organized under `includes/`:

- `core.php`: database setup, settings, shared helpers, and registration-field definitions.
- `frontend-forms.php`: staff access links and the event, room, and staff forms.
- `admin-settings.php`: settings screen, settings persistence, and admin menu setup.
- `admin-management.php`: staff event, room, and staff-calendar management screens and actions.
- `equipment.php` and `reports.php`: equipment inventory and reservations, plus report previews and CSV exports.
- `calendar-system-admin.css`: dashboard-only styling for the Home, Rooms, and Staff calendar pages.
- `room-validation.php` and `room-actions.php`: room booking validation, conflict checks, and booking actions.
- `events.php` and `registrations.php`: event creation/recurrence, registration handling, and reminder delivery.
- `calendars.php`: calendar rendering, public notices, and WordPress shortcodes.

Keep the entry point at the plugin folder root so WordPress can discover and activate the plugin. PHP and JavaScript functions include short comments describing their purpose.

## Add it to the website

Create a WordPress page and add the shortcodes for the calendars you want to show:

- `[calendar_event_calendar]` displays the unified monthly calendar of published events and pending or approved room bookings.
- `[calendar_room_calendar]` is retained as a compatibility alias for the same unified calendar. Use only one calendar shortcode on a page.
- `[calendar_events]` displays published events only, including event details and registration forms.
- `[calendar_upcoming_events]` displays the next three published events as compact cards. Set `count="4"` or `count="5"` to show up to five cards; the allowed range is three to five. Event cards include the event image, date, and a details button that opens its registration dialog.
- `[calendar_rooms]` displays room bookings and upcoming published events. Private bookings and event-linked room reservations are not shown as separate room entries.
- `[calendar_combined]` explicitly displays the combined events and public room bookings calendar.
- `[calendar_staff_calendar]` displays the staff-only monthly calendar. Visitors who are not signed in as calendar staff cannot view staff schedule details.
- `[calendar_system]` displays the unified calendar, upcoming event listings, and event registration forms.
- `[calendar_room_request]` displays the public study-room request form.

The unified calendar is a month grid with a separate day box for every date; each box lists that day's events and room bookings with times. Event entries show their registration count. Room entries follow the public room-privacy settings and never reveal patron contact details. These are native WordPress shortcodes, so the calendar and forms render within the site's theme and can be placed in a page, post, or shortcode block.

Staff can choose an image from the WordPress Media Library when creating or editing an event. The image is saved with the event and shown on the upcoming-event cards, event listings, and event details.

The unified calendar is interactive: visitors can use **Request** on a day to open the room-request popup, choose an available room, and enter the requested date and time. Staff use **Add** on a day to create a private room booking or check **Public event** to publish an event using that reserved room. Private bookings are hidden from public calendars but still block the room from overlapping reservations. Every event title opens a details popup; its registration form is shown only when staff enable online registration for that event and the site-wide registration setting allows it. Staff can edit events from their calendar entries. The staff calendar remains separate and private; **Add staff time** opens a popup whose type dropdown uses the booking types configured in Settings. A **Staff login** link opens the standard WordPress login and returns to the calendar after sign-in. There is no separate plugin password system. Staff-only controls appear only to authorized users; public room requests require staff approval by default, configurable in Settings. Calendar pages send no-cache headers because their controls depend on the signed-in user's permissions. After updating, clear any existing page, host, or CDN cache for these pages.

When staff edit an event, staff-time entry, or room booking from a calendar, the edit dialog has a **Save changes** action and a delete action. Deleting an event also deletes its registrations and linked room reservation; deleting a staff-time entry removes its linked room reservation. Rooms with bookings or staff entries cannot be deleted; mark them unavailable instead.

Only users with the `manage_calendar_system` capability can view the staff calendar or manage calendar content. Administrators and Editors receive this capability when the plugin is activated; to grant access to another role, assign the capability with a role-management plugin. Staff can also add or remove meetings, sick time, time off, and other entries from **CommonsCal > Staff calendar**. The calendar uses the site's configured timezone and week-start preference.

On plugin updates, the staff calendar table is added automatically without removing existing events, registrations, rooms, or bookings.
Room requests include an optional reason field; staff can review the reason alongside each request in the Rooms panel.

## Settings and customization

Open **CommonsCal > Settings** to choose colors for event and room entries, a default staff color, and a separate color for each staff booking type. Add or rename types such as sick time and comp time. Add, rename, and remove event categories; the default categories are Teen, Adult, and Children. A category already used by a saved event is retained if you remove it. The Shortcodes section on the Settings page lists the event-only, room-only, and combined calendar options.

Use **CommonsCal > Rooms & Events** to view the monthly room-booking calendar alongside upcoming events, add or edit reservations, and review requests and their reasons. **Settings > Rooms** is where staff add rooms, edit capacity and availability, and maintain the Room Directory. Public room requests include an optional reason-for-requesting field. **Staff calendar** has a private monthly calendar for adding and editing meetings, sick time, and time off.

The Settings panel also includes:

- Public room-booking rules for the maximum advance window, minimum notice, allowed time increments, and daily opening hours. These rules do not restrict staff bookings.
- Equipment inventory: add items, set quantities, and mark items unavailable for new reservations. Existing future reservations prevent reducing the quantity below the amount already reserved at once.
- A choice between requiring staff approval for room requests and confirming valid requests immediately.
- Event registration controls to disable public registrations, set a site-wide attendee limit alongside event-specific limits, or close registrations a chosen number of minutes before an event.
- Event registration form customization, including the default registration state for new events, form instructions, attendee name/email labels, submit text, privacy notice, success/closed/full/unavailable messages, reminder opt-in text, and up to 20 reusable default custom fields. Fields can be short text, paragraph text, email, phone, dropdown, or checkbox, and may be required.
- Calendar display options for the first day of the week, initial month, and whether visitors see event attendee counts and category labels.
- Optional email notifications for staff room-request alerts, room-requester receipts, and event-registration confirmations. Notifications are off by default and use the website host's mail configuration.
- Optional staff email alerts for each new event registration, sent to the configured staff notification address.
- Optional 24-hour event reminders. When enabled, each registrant can opt in on the registration form; reminders go only to opted-in attendees.
- Public room-calendar privacy options to hide room names and/or booking status. Requester and registrant contact details remain private to staff.

Existing behavior remains the default: public room requests require staff approval, event registration is enabled, booking rules and the global event capacity are unrestricted, the calendar follows the WordPress week-start setting and opens to the current month, public event counts and category labels and room names/status are shown, and email notifications are disabled.

## How it works

- Event registration collects a name and email address. Staff can set an optional registration limit. Duplicate registrations for the same event and email are prevented.
- Staff can create and edit events, publish or leave them as drafts, and set a registration limit.
- Each event's registration form can include up to 20 custom fields: short text, paragraph text, email, phone, dropdown, or checkbox. Fields can be marked required; dropdown choices are set one per line. Staff can view submitted answers with each event's registration list.
- Each event can override the site-wide registration default. The event editor reveals the optional event-specific custom-field builder and up to 10 email recipients for registration updates when registration is enabled. Existing events keep their registration state after the database upgrade; new events follow the **Enable registration by default** setting, which is off by default.
- Reminder emails are sent by WordPress's hourly scheduled task. As with other WP-Cron tasks, delivery time depends on site visits or a real server cron triggering WordPress scheduled tasks.
- The event editor and staff-time form have an always-visible optional room dropdown. Choose a room to reserve it for the same scheduled time, or leave **No room reservation** selected. Add rooms under **CommonsCal > Settings > Rooms** first.
- Events can be assigned a configurable category; the public event calendar and event list include a multi-select checkbox category filter that applies immediately when changed.
- New events can repeat daily, weekly, or monthly, ending on a date (up to two years out) or after a chosen number of occurrences (up to 100). Each repeat is a separate event and registration listing.
- Staff can reserve an active room for an event or staff-calendar entry. Reservations block overlapping public requests and are split into daily segments for multi-day schedules. If a room conflict affects a recurring event, that event occurrence is still created and its room reservation is skipped; the save confirmation reports how many reservations were skipped.
- Staff can add rooms, set their capacities, and mark rooms unavailable for new requests under **Settings > Rooms**. Rooms with booking history cannot be deleted.
- By default, public room requests remain pending until staff approve or deny them; Settings can instead confirm valid requests immediately. Staff can also create confirmed room bookings directly.
- Overlapping pending requests and approved bookings are treated as conflicts. Public room requests use the WordPress site's configured timezone and are for a single day; reservations linked to multi-day event or staff entries are split into daily segments.
- Room-request times use 15-minute dropdowns. A stricter configured public time increment still applies, so only times satisfying both rules are offered. Existing staff bookings with other times remain editable.
- Room requests, staff room bookings, events, and staff-calendar entries can reserve equipment even when an event or staff entry has no room. Pending requests and approved reservations count against inventory. An equipment conflict prevents the entire save, including a recurring event series; staff can change the requested quantity or time and retry.
- The Reports page uses inclusive site-local dates. Room and equipment reports show approved reservations, including private staff usage without personal contact details. Event reports include published and draft events overlapping the range and registration counts. Equipment reservations are tracked only from the version that introduced this feature; past usage cannot be reconstructed.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
