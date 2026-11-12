# CommonsCal

A Wordpress plugin for library staff, study room and event management built by librarians for librarians. Originally developed by SMTI for McArthur Library. If you need any help setting this up or request a feature, reach out to Corie at cleclair@southernmainetech.org


## Install

1. Copy the `community-calendar-study-rooms` folder into `wp-content/plugins/` (or upload the ZIP under **Plugins > Add New > Upload Plugin**).
2. Activate **CommonsCal**.
3. Create WordPress user accounts for staff and give them the **Editor** role, or grant their role the `manage_calendar_system` capability. Administrators and Editors receive this capability on activation.
4. Open **CommonsCal > Home** in the WordPress dashboard for an overview of upcoming events, room requests, active rooms, and staff entries. The **Upcoming** section lists today's remaining and in-progress events, room bookings, and staff time. The Home page links to event management; event management is no longer a separate navigation tab. Staff can also manage events from the website calendar after signing in.
5. Use **Room Approvals** to review pending requests and approve or deny them. Use **Event Registrations** to select an upcoming event and view its registrants and custom-field responses. Draft upcoming events are included; past events remain accessible through the event manager.
6. Add equipment and quantities under **Settings > Equipment** before accepting equipment reservations. **Reports** provides date-filtered room, equipment, and event reports with separate CSV downloads.

The plugin creates its tables when activated. Deactivating it does not delete events, registrations, rooms, or bookings.

## Updates

Back up the site and database before updating. To update manually, upload the new ZIP under **Plugins > Add New > Upload Plugin** and select **Replace current with uploaded**. Keep the `community-calendar-study-rooms` folder name in the ZIP; plugin data is stored in the database.

Once [the GitHub repository](https://github.com/cleclair-sudo/CommonsCal) is public, an active CommonsCal installation also checks its latest published GitHub release during WordPress's normal plugin update checks. Install available updates from **Plugins** or **Dashboard > Updates**, or enable WordPress's plugin auto-updates if desired. The repository must be public and the site must be able to reach GitHub. To check again, use **Dashboard > Updates > Check again**. If the check fails, WordPress displays a warning on the update screens; uploading a ZIP remains available.

For each update, increment the final version component and create an updated plugin ZIP. For each GitHub release, tag a stable version such as `v0.09.25`, publish the release (not a draft or prerelease), and attach a ZIP named `CommonsCal-0.09.25.zip`. Its single top-level folder must be `community-calendar-study-rooms` and contain `community-calendar-study-rooms.php` with the same version and Update URI. The ZIP must contain the complete plugin, not GitHub's automatically generated source archive. Do not reuse a release version; WordPress only offers newer versions. The first version with GitHub update support must itself be installed by ZIP.

## Code layout

`community-calendar-study-rooms.php` is the WordPress plugin entry point. Feature code is organized under `includes/`:

- `core.php`: database setup, settings, shared helpers, and registration-field definitions.
- `updates.php`: GitHub release checks and WordPress update package validation.
- `frontend-forms.php`: staff access links and the event, room, and staff forms.
- `admin-settings.php`: settings screen, settings persistence, and admin menu setup.
- `admin-management.php`: staff event, room, and staff-calendar management screens and actions.
- `equipment.php` and `reports.php`: equipment inventory and reservations, plus report previews and CSV exports.
- `calendar-system-admin.css`: dashboard-only styling for the Home, Rooms, and Staff calendar pages.
- `room-validation.php` and `room-actions.php`: room booking validation, conflict checks, and booking actions.
- `events.php` and `registrations.php`: event creation/recurrence, registration handling, and reminder delivery.
- `event-series.php` and `waitlist.php`: series editing, attendee cancellations, and waitlist promotion.
- `privacy.php` and `ical.php`: WordPress privacy tools, retention, and public event subscriptions.
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

The unified calendar is a month grid with a separate day box for every date; each box lists that day's events and room bookings with times. Each event can show or hide its registration count and capacity publicly. Room entries follow the public room-privacy settings and never reveal patron contact details. These are native WordPress shortcodes, so the calendar and forms render within the site's theme and can be placed in a page, post, or shortcode block.

Use **Day**, **Week**, or **Month** beneath the category filters to change the public calendar view. Previous and Next move by the selected period, and category filters remain applied. On narrow screens, days are stacked for easier reading and room requests.

Use **Print calendar** to print the selected calendar view. In Month or Week view, **Print events list** prints a chronological list of published events for that period instead of a grid; it respects the selected categories and excludes room bookings. The month list covers the selected month only, while the week list follows the configured start of the week. Printing opens a new window, so pop-ups must be allowed.

The public calendar and **Settings > Calendar shortcodes** include a subscription URL for published events. Copy it into the calendar subscription feature in Google Calendar, Apple Calendar, or Outlook. The feed updates as published events change; refresh timing is controlled by the calendar app. It does not contain room bookings, registrations, or staff entries, and subscribing does not register anyone for an event.

Staff can choose an image from the WordPress Media Library when creating or editing an event. The image is saved with the event and shown on the upcoming-event cards, event listings, and event details.

The unified calendar is interactive: visitors can use **Request** on a day to open the room-request popup, choose an available room, and enter the requested date and time. Staff use **Add** on a day to create a private room booking or check **Public event** to publish an event using that reserved room. Private bookings are hidden from public calendars but still block the room from overlapping reservations. Every event title opens a details popup; its registration form is shown only when staff enable online registration for that event and the site-wide registration setting allows it. Staff can edit events from their calendar entries. The staff calendar remains separate and private; **Add staff time** opens a popup whose type dropdown uses the booking types configured in Settings. A **Staff login** link opens the standard WordPress login and returns to the calendar after sign-in. There is no separate plugin password system. Staff-only controls appear only to authorized users; public room requests require staff approval by default, configurable in Settings. Calendar pages send no-cache headers because their controls depend on the signed-in user's permissions. After updating, clear any existing page, host, or CDN cache for these pages.

When staff edit an event, staff-time entry, or room booking from a calendar, the edit dialog has a **Save changes** action and a delete action. Deleting an event also deletes its registrations and linked room reservation; deleting a staff-time entry removes its linked room reservation. Rooms with bookings or staff entries cannot be deleted; mark them unavailable instead.

Calendar event and room forms group related fields into sections. In a staff room booking, check **Public event** to reveal the event, repeat, series, and registration settings.

Only users with the `manage_calendar_system` capability can view the staff calendar or manage calendar content. Administrators and Editors receive this capability when the plugin is activated; to grant access to another role, assign the capability with a role-management plugin. Staff can also add or remove meetings, sick time, time off, and other entries from **CommonsCal > Staff calendar**. The calendar uses the site's configured timezone and week-start preference.

On plugin updates, the staff calendar table is added automatically without removing existing events, registrations, rooms, or bookings.
Room requests include an optional reason field; staff can review the reason alongside each request in the Rooms panel.

## Settings and customization

Open **CommonsCal > Settings** to choose colors for event and room entries, a default staff color, and a separate color for each staff booking type. Under **Settings > Rooms**, optionally set a calendar color for each room; leave it blank to use the default room color. If room names are hidden from visitors, their bookings use the default color to protect room privacy. Events and staff entries keep their own colors even when linked to rooms. Add or rename types such as sick time and comp time. Add, rename, and remove event categories; the default categories are Teen, Adult, and Children. A category already used by a saved event is retained if you remove it. The Shortcodes section on the Settings page lists the event-only, room-only, and combined calendar options.

Use **CommonsCal > Rooms & Events** to view the monthly room-booking calendar alongside upcoming events, add or edit reservations, and review requests and their reasons. **Settings > Rooms** is where staff add rooms, edit capacity and availability, and maintain the Room Directory. Public room requests include an optional reason-for-requesting field. **Staff calendar** has a private monthly calendar for adding and editing meetings, sick time, and time off.

The Settings panel also includes:

- Public room-booking rules for the maximum advance window, minimum notice, allowed time increments, weekly opening hours, and holiday or special-date hours. Staff can book outside opening hours but receive a warning.
- An optional maximum duration in minutes per public room request. Zero means no maximum; staff bookings are exempt.
- Equipment inventory: add items, set quantities, and mark items unavailable for new reservations. Existing future reservations prevent reducing the quantity below the amount already reserved at once.
- A choice between requiring staff approval for room requests and confirming valid requests immediately.
- Event registration controls to disable public registrations, set a site-wide attendee limit alongside event-specific limits, or close registrations a chosen number of minutes before an event.
- Event registration form customization, including the default registration state for new events, form instructions, attendee name/email labels, submit text, privacy notice, success/closed/full/unavailable messages, reminder opt-in text, and up to 20 reusable default custom fields. Fields can be short text, paragraph text, email, phone, dropdown, or checkbox, and may be required.
- Calendar display options for the first day of the week, initial month, and whether visitors see event category labels.
- Optional email notifications for staff room-request alerts, room-requester receipts, and event-registration confirmations. Notifications are off by default and use the website host's mail configuration.
- Optional staff email alerts for each new event registration, sent to the configured staff notification address.
- Optional 24-hour event reminders. When enabled, each registrant can opt in on the registration form; reminders go only to opted-in attendees.
- Public room-calendar privacy options to hide room names and/or booking status. Requester and registrant contact details remain private to staff.
- Under **Settings > Public booking privacy**, enable **Hide room bookings from logged-out visitors** to show only published events in public calendar listings. Signed-in users can still see public bookings, and room requests and availability checks continue to work. Clear any page or CDN caches after changing this setting.
- Optional personal-data retention in days. Zero (the default) disables automatic cleanup. When enabled, older room bookings and event registrations are anonymized after the booking or event ends, while historical usage and registration counts remain; older waitlist entries are removed. Cleanup runs in batches through WordPress scheduled tasks and permanently removes contact details, request reasons, and registration answers.

Existing behavior remains the default: public room requests require staff approval, event registration is enabled, booking rules and the global event capacity are unrestricted, the calendar follows the WordPress week-start setting and opens to the current month, public event counts and category labels and room names/status are shown, and email notifications are disabled.

## How it works

- Event registration collects a name and email address. Staff can set an optional registration limit. Duplicate registrations for the same event and email are prevented.
- When a registration-enabled event is full, visitors can join its waitlist with the same registration details. If a place opens before registration closes, the earliest waiting person is registered automatically and emailed. Staff can see both lists and cancel an attendee from **Event Registrations**; cancellation also fills available places. Increased event capacity is handled when the event is saved; changes to the site-wide limit are reconciled by WordPress's hourly scheduled task or the next registration attempt. Patrons cannot cancel themselves.
- Staff approve or deny public room requests in **Room Approvals**. The calendar's **Approve / Deny** link opens the corresponding request in that dashboard page; it does not make the decision. Staff can include an optional comment in the approval or denial email; the comment is not saved. Staff cancellations of public room bookings and event registrations send cancellation emails. Deleting a future event notifies its registered and waitlisted attendees. Waitlist and status emails use WordPress mail regardless of the optional submission-confirmation settings. Configure and test the website's mail delivery; failures are logged and, for staff actions, shown in a notice. Email delivery cannot be guaranteed by CommonsCal.
- Staff can create and edit events, publish or leave them as drafts, and set a registration limit.
- Staff must enter their initials each time they create or edit an event. The most recent initials are visible to staff; existing events have no initials until edited.
- Each event has a **Show attendee counts and capacity publicly** option, enabled by default for new events. On upgrade, existing events retain the previous global visibility setting. Staff can still see counts, and registration limits continue to apply even when counts are hidden.
- Each event's registration form can include up to 20 custom fields: short text, paragraph text, email, phone, dropdown, or checkbox. Fields can be marked required; dropdown choices are set one per line. Staff can view submitted answers with each event's registration list.
- Each event can override the site-wide registration default. The event editor reveals the optional event-specific custom-field builder and up to 10 email recipients for registration updates when registration is enabled. Existing events keep their registration state after the database upgrade; new events follow the **Enable registration by default** setting, which is off by default.
- Reminder emails are sent by WordPress's hourly scheduled task. As with other WP-Cron tasks, delivery time depends on site visits or a real server cron triggering WordPress scheduled tasks.
- The event editor and staff-time form have an always-visible optional room dropdown. Choose a room to reserve it for the same scheduled time, or leave **No room reservation** selected. Add rooms under **CommonsCal > Settings > Rooms** first.
- Events can be assigned a configurable category; the public event calendar and event list include a multi-select checkbox category filter that applies immediately when changed.
- Staff can create a named event series or attach an event to an existing series in any event editor. A named series groups individually scheduled events and is independent of recurrence; recurring occurrences can also belong to a named series. Editing an occurrence can change its series, and applying an edit to recurring occurrences applies the chosen series to those occurrences. Existing events are not assigned to a named series automatically.
- The calendar has separate recurring-event and series filters; the series filter can select a particular named series. Event details list all published events in the same named series, including past events, without exposing drafts.
- An attendee registering for a series event can expand **Register for other events in this series**, select individual open events or select all. Only events with identical registration questions are offered. Each event is checked separately when submitted; the result lists successes and failures, including full events and existing registrations. Additional events are not automatically waitlisted.
- When creating a recurring event, staff can enable **Let attendees register for all recurring events**. Each attendee then chooses whether to register for all upcoming occurrences; unavailable, full, or question-mismatched occurrences are reported individually. Staff can change this option when editing recurring events, using the existing edit scope to apply it to multiple occurrences.
- New events can repeat daily, weekly, or monthly, ending on a date (up to two years out) or after a chosen number of occurrences (up to 100). Each repeat is a separate event and registration listing.
- When editing a recurring event, choose this event, this and future recurring events, or all recurring events. Every displayed field, including the room and equipment choices, replaces that field for all selected occurrences; start and end dates shift by the same respective amounts, and registrations stay with their original occurrences. A room or equipment conflict rejects the entire recurring-group edit. Deleting an event still deletes only the selected occurrence.
- Staff can reserve an active room for an event or staff-calendar entry. Reservations block overlapping public requests and are split into daily segments for multi-day schedules. If a room conflict affects a recurring event, that event occurrence is still created and its room reservation is skipped; the save confirmation reports how many reservations were skipped.
- In the staff room-booking form, an overlapping booking can be saved only after staff acknowledge the double-booking warning. Public requests cannot override conflicts. For a public event created from that form, the acknowledgment covers the first occurrence only; later conflicting recurring reservations are skipped and reported. Equipment availability is checked separately.
- Staff can add rooms, set their capacities, and mark rooms unavailable for new requests under **Settings > Rooms**. Rooms with booking history cannot be deleted.
- By default, public room requests remain pending until staff approve or deny them; Settings can instead confirm valid requests immediately. Staff can also create confirmed room bookings directly.
- Overlapping pending requests and approved bookings are treated as conflicts. Public room requests use the WordPress site's configured timezone and are for a single day; reservations linked to multi-day event or staff entries are split into daily segments.
- Room forms show a live conflict warning, and availability is checked again when a booking is saved. Public time choices follow the selected day's opening hours, and the calendar does not show a request button on closed dates. In Settings, choose Open and enter both times for each day with specific hours, then save calendar settings. Staff can book outside hours with a warning. Special dates can be closed or use custom hours and optionally repeat yearly; a specific-date exception takes precedence over a yearly one. Existing daily hours become the initial hours for every weekday.
- Room-request times use 15-minute dropdowns. A stricter configured public time increment still applies, so only times satisfying both rules are offered. Existing staff bookings with other times remain editable.
- Room requests, staff room bookings, events, and staff-calendar entries can reserve equipment even when an event or staff entry has no room. Pending requests and approved reservations count against inventory. An equipment conflict prevents the entire save, including a recurring event series; staff can change the requested quantity or time and retry.
- The Reports page uses inclusive site-local dates. Room and equipment reports show approved reservations, including private staff usage without personal contact details. Event reports include published and draft events overlapping the range and registration counts. Equipment reservations are tracked only from the version that introduced this feature; past usage cannot be reconstructed.

## Personal data requests

WordPress **Tools > Export Personal Data** and **Tools > Erase Personal Data** include CommonsCal records associated with the requested email address. Erasure anonymizes matching room bookings and registrations while retaining their booking and attendance counts, and removes matching waitlist entries. An anonymized future reservation still occupies its room or event place; staff should cancel it separately if the person wants to free that place. Export and erasure cannot identify personal information entered in another person's free-text responses, or remove information from emails, backups, logs, or other plugins. Review the site's own privacy policy and retention requirements before enabling automatic cleanup.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
