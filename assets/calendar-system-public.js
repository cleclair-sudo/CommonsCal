/* Initializes interactive public and staff calendar controls. */
(function () {
    'use strict';

    var labels = window.calsysPublic || {};

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }
        var button = event.target.closest('[data-calsys-print]');
        if (!button) {
            return;
        }
        var calendar = button.closest('.calsys-calendar');
        var stylesheet = document.getElementById('calsys-public-css') || document.getElementById('calsys-public-calendar-css');
        if (!calendar || !stylesheet) {
            window.alert(labels.printUnavailable || 'The printable view could not be loaded.');
            return;
        }
        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.alert(labels.printBlocked || 'Please allow pop-ups to print the calendar.');
            return;
        }
        var printDocument = printWindow.document;
        printDocument.documentElement.lang = document.documentElement.lang;
        printDocument.body.className = 'calsys-print-page';
        var period = calendar.querySelector('.calsys-calendar-navigation span').textContent;
        var heading = calendar.querySelector('.calsys-calendar-heading h2').textContent;
        printDocument.title = heading + ' - ' + period;
        if (button.getAttribute('data-calsys-print') === 'events') {
            var list = calendar.querySelector('.calsys-print-events').cloneNode(true);
            list.removeAttribute('hidden');
            printDocument.body.appendChild(list);
        } else {
            var title = printDocument.createElement('h1');
            title.textContent = heading;
            printDocument.body.appendChild(title);
            var subtitle = printDocument.createElement('p');
            subtitle.className = 'calsys-print-period';
            subtitle.textContent = period;
            printDocument.body.appendChild(subtitle);
            var table = calendar.querySelector('.calsys-calendar-table').cloneNode(true);
            table.classList.add(calendar.classList.contains('calsys-view-week') ? 'calsys-print-week' :
                (calendar.classList.contains('calsys-view-day') ? 'calsys-print-day' : 'calsys-print-month'));
            table.querySelectorAll('.calsys-day-action, .calsys-entry-action, .calsys-entry-decision').forEach(function (control) {
                control.remove();
            });
            table.querySelectorAll('button.calsys-calendar-entry-title').forEach(function (control) {
                var text = printDocument.createElement('span');
                text.className = 'calsys-calendar-entry-title';
                text.textContent = control.textContent;
                control.replaceWith(text);
            });
            printDocument.body.appendChild(table);
        }
        var link = printDocument.createElement('link');
        link.rel = 'stylesheet';
        link.href = stylesheet.href;
        link.onload = function () {
            printWindow.focus();
            printWindow.print();
        };
        link.onerror = function () {
            printWindow.close();
            window.alert(labels.printUnavailable || 'The printable view could not be loaded.');
        };
        printDocument.head.appendChild(link);
    });

    // Sets a named form control when it is present in the form.
    function setValue(form, name, value) {
        var field = form.elements.namedItem(name);
        if (field) {
            if (value && field instanceof HTMLSelectElement && field.hasAttribute('data-calsys-room-time') &&
                !Array.prototype.some.call(field.options, function (option) { return option.value === value; })) {
                var previousTime = document.createElement('option');
                previousTime.value = value;
                previousTime.textContent = value;
                previousTime.setAttribute('data-calsys-existing-time', '');
                field.appendChild(previousTime);
            }
            field.value = value || '';
        }
    }

    // Restores saved equipment quantities when opening an existing entry.
    function setEquipment(form, encoded) {
        var quantities;
        try {
            quantities = JSON.parse(encoded || '{}');
            if (!quantities || typeof quantities !== 'object') {
                throw new Error('Invalid equipment quantities.');
            }
        } catch (error) {
            console.error('Unable to load equipment reservations.', error);
            return;
        }
        Object.keys(quantities).forEach(function (id) {
            setValue(form, 'equipment[' + id + ']', String(quantities[id]));
        });
    }

    function filterRoomTimes(form, hours) {
        if (form.dataset.staffBooking === '1') {
            return;
        }
        form.querySelectorAll('select[data-calsys-room-time]').forEach(function (select) {
            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) {
                    return;
                }
                var allowed = hours && (hours.mode === 'unrestricted' ||
                    (hours.mode === 'open' && (select.name === 'starts_at'
                        ? option.value >= hours.open && option.value < hours.close
                        : option.value > hours.open && option.value <= hours.close)));
                option.disabled = !allowed;
                option.hidden = !allowed;
            });
            if (select.selectedOptions.length && select.selectedOptions[0].disabled) {
                select.value = '';
            }
        });
    }

    // Checks availability without exposing booking details; the server checks again on save.
    function updateRoomAvailability(form) {
        var message = form.querySelector('[data-calsys-availability]');
        var date = form.elements.namedItem('booking_date');
        var room = form.elements.namedItem('room_id');
        var start = form.elements.namedItem('starts_at');
        var end = form.elements.namedItem('ends_at');
        var acknowledgement = form.querySelector('[data-calsys-conflict-ack]');
        var checkbox = acknowledgement && acknowledgement.querySelector('input');
        if (checkbox) {
            checkbox.checked = false;
            checkbox.required = false;
            checkbox.disabled = true;
            acknowledgement.hidden = true;
        }
        if (!message || !date || !room || !start || !end) {
            return;
        }
        form._calsysAvailabilityRequest = (form._calsysAvailabilityRequest || 0) + 1;
        var requestId = form._calsysAvailabilityRequest;
        message.textContent = '';
        if (!date.value) {
            filterRoomTimes(form, null);
            return;
        }
        var params = new URLSearchParams({
            action: 'calsys_room_availability',
            nonce: form.dataset.availabilityNonce,
            date: date.value
        });
        var publicEvent = form.elements.namedItem('public_event');
        var overnight = form.dataset.staffBooking === '1' && publicEvent && publicEvent.checked;
        if (room.value && start.value && end.value && (start.value < end.value || overnight)) {
            params.set('room_id', room.value);
            params.set('start', start.value);
            params.set('end', end.value);
            if (overnight) {
                params.set('overnight', '1');
            }
            var bookingId = form.elements.namedItem('booking_id');
            if (bookingId && bookingId.value) {
                params.set('booking_id', bookingId.value);
            }
        }
        fetch(form.dataset.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: params.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Room availability request failed.');
            }
            return response.json();
        }).then(function (result) {
            if (requestId !== form._calsysAvailabilityRequest) {
                return;
            }
            if (!result.success || !result.data || !result.data.hours) {
                throw new Error('Invalid room availability response.');
            }
            var hours = result.data.hours;
            var previousStart = start.value;
            var previousEnd = end.value;
            filterRoomTimes(form, hours);
            if (start.value !== previousStart || end.value !== previousEnd) {
                updateRoomAvailability(form);
                return;
            }
            var messages = [];
            if (hours.mode === 'closed') {
                messages.push(message.dataset.closed);
            }
            if (form.dataset.staffBooking === '1' && start.value && end.value &&
                (hours.mode === 'closed' || (hours.mode === 'open' && (start.value < hours.open || end.value > hours.close)))) {
                messages.push(message.dataset.outside);
            }
            if (result.data.conflict) {
                messages.push(message.dataset.conflict);
                if (checkbox) {
                    acknowledgement.hidden = false;
                    checkbox.disabled = false;
                    checkbox.required = true;
                }
            }
            message.textContent = messages.join(' ');
        }).catch(function (error) {
            if (requestId === form._calsysAvailabilityRequest) {
                filterRoomTimes(form, { mode: 'unrestricted' });
                message.textContent = message.dataset.error;
                console.error('Unable to check room availability.', error);
            }
        });
    }

    // Updates an event form's selected image and its visible preview.
    function updateEventImage(form, imageId, imageUrl, imageAlt) {
        var imageField = form.querySelector('[data-calsys-event-image-id]');
        var preview = form.querySelector('[data-calsys-event-image-preview]');
        var removeButton = form.querySelector('[data-calsys-remove-event-image]');
        if (!imageField || !preview) {
            return;
        }
        var hasImage = Number(imageId) > 0;
        imageField.value = hasImage ? String(imageId) : '0';
        preview.innerHTML = '';
        if (hasImage && imageUrl) {
            var image = document.createElement('img');
            image.className = 'calsys-event-image-preview-img';
            image.src = imageUrl;
            image.alt = imageAlt || '';
            image.loading = 'lazy';
            preview.appendChild(image);
        }
        if (removeButton) {
            removeButton.hidden = !hasImage;
        }
    }

    // Synchronizes registration, room, and recurrence controls with their toggles.
    function updateFormControls(form) {
        var publicEventToggle = form.querySelector('[data-calsys-toggle-public-event]');
        if (publicEventToggle) {
            var publicEventFields = form.querySelector('[data-calsys-public-event-fields]');
            var privateBookingFields = form.querySelectorAll('[data-calsys-private-booking-field]');
            var publicEvent = publicEventToggle.checked;
            if (publicEventFields) {
                publicEventFields.hidden = !publicEvent;
                var publicInputs = publicEventFields.querySelectorAll('input, select, textarea, button');
                for (var publicIndex = 0; publicIndex < publicInputs.length; publicIndex++) {
                    publicInputs[publicIndex].disabled = !publicEvent;
                }
            }
            for (var privateIndex = 0; privateIndex < privateBookingFields.length; privateIndex++) {
                privateBookingFields[privateIndex].hidden = publicEvent;
                var privateInputs = privateBookingFields[privateIndex].querySelectorAll('input, select, textarea');
                for (var inputIndex = 0; inputIndex < privateInputs.length; inputIndex++) {
                    privateInputs[inputIndex].disabled = publicEvent;
                    privateInputs[inputIndex].required = !publicEvent;
                }
            }
            var roomSaveButton = form.querySelector('[data-calsys-save-room]');
            if (roomSaveButton) {
                roomSaveButton.textContent = publicEvent
                    ? (labels.savePublicEvent || 'Save public event')
                    : (labels.confirmRoomBooking || 'Confirm room booking');
            }
        }

        var registrationToggle = form.querySelector('[data-calsys-toggle-registration]');
        var registrationSettings = form.querySelector('[data-calsys-registration-settings]');
        if (registrationToggle && registrationSettings) {
            registrationSettings.hidden = !registrationToggle.checked;
            var registrationInputs = registrationSettings.querySelectorAll('input, select, textarea, button');
            for (var registrationIndex = 0; registrationIndex < registrationInputs.length; registrationIndex++) {
                registrationInputs[registrationIndex].disabled = !registrationToggle.checked || (publicEventToggle && !publicEventToggle.checked);
            }
        }
        var seriesChoice = form.querySelector('[name="series_id"]');
        var seriesName = form.querySelector('[name="series_name"]');
        if (seriesChoice && seriesName) {
            seriesName.required = !seriesChoice.disabled && seriesChoice.value === 'new';
        }

        var roomToggle = form.querySelector('[data-calsys-toggle-room]');
        var roomSelect = form.querySelector('[data-calsys-room-select]');
        if (roomToggle && roomSelect) {
            roomSelect.disabled = !roomToggle.checked;
            roomSelect.required = roomToggle.checked;
            if (!roomToggle.checked) {
                roomSelect.value = '';
            }
        }

        var recurrenceToggle = form.querySelector('[data-calsys-toggle-recurrence]');
        var recurrenceFields = form.querySelectorAll('[data-calsys-recurrence-field]');
        var recurrenceVisible = recurrenceToggle && recurrenceToggle.checked;
        var registerRecurrences = form.querySelector('[data-calsys-register-recurrences]');
        if (registerRecurrences) {
            registerRecurrences.hidden = !recurrenceVisible && !form.dataset.calsysEditingRecurrence;
            var registerRecurrencesInput = registerRecurrences.querySelector('input');
            if (registerRecurrencesInput) {
                registerRecurrencesInput.disabled = registerRecurrences.hidden || (publicEventToggle && !publicEventToggle.checked);
            }
        }
        for (var i = 0; i < recurrenceFields.length; i++) {
            recurrenceFields[i].hidden = !recurrenceVisible;
        }
        var endType = form.querySelector('[data-calsys-recurrence-end]');
        var occurrences = form.querySelector('[data-calsys-occurrences]');
        var endDate = form.querySelector('[data-calsys-end-date]');
        if (occurrences && endDate) {
            occurrences.hidden = !recurrenceVisible || !endType || endType.value !== 'count';
            endDate.hidden = !recurrenceVisible || !endType || endType.value !== 'date';
            var countInput = occurrences.querySelector('input');
            var dateInput = endDate.querySelector('input');
            if (countInput) {
                countInput.required = recurrenceVisible && endType.value === 'count';
            }
            if (dateInput) {
                dateInput.required = recurrenceVisible && endType.value === 'date';
            }
        }
    }

    // Initializes conditional controls in forms within the supplied DOM scope.
    function initializeFormControls(scope) {
        var forms = scope.querySelectorAll('.calsys-action-form, .calsys-admin-form');
        for (var i = 0; i < forms.length; i++) {
            updateFormControls(forms[i]);
        }
    }

    // Opens a dialog and focuses its first usable input.
    function openDialog(dialog) {
        if (!dialog) {
            return;
        }
        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }
        dialog.classList.add('is-open');
        var firstInput = dialog.querySelector('input:not([type="hidden"]), select');
        if (firstInput) {
            firstInput.focus();
        }
        dialog.scrollTop = 0;
    }

    // Closes a dialog using native dialog support or the compatibility fallback.
    function closeDialog(dialog) {
        if (!dialog) {
            return;
        }
        dialog.classList.remove('is-open');
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    // Opens the event editor belonging to a calendar widget.
    function openEventDialog(widget) {
        openDialog(widget.querySelector('.calsys-event-dialog'));
    }

    // Opens the room booking form belonging to a calendar widget.
    function openRoomDialog(widget) {
        openDialog(widget.querySelector('.calsys-room-dialog'));
    }

    // Opens the staff schedule editor belonging to a calendar widget.
    function openStaffDialog(widget) {
        openDialog(widget.querySelector('.calsys-staff-dialog'));
    }

    // Resets the event editor and clears edit-only state before creating an event.
    function resetEventForm(widget) {
        var form = widget.querySelector('.calsys-event-editor form');
        if (!form) {
            return null;
        }
        form.reset();
        delete form.dataset.calsysEditingRecurrence;
        var savedInitials = form.querySelector('[data-calsys-saved-initials]');
        if (savedInitials) {
            savedInitials.textContent = '';
        }
        var seriesScope = form.querySelector('[data-calsys-series-scope]');
        if (seriesScope) {
            seriesScope.hidden = true;
        }
        updateEventImage(form, 0, '', '');
        setValue(form, 'event_id', '0');
        var deleteForm = widget.querySelector('[data-calsys-delete-event-form]');
        if (deleteForm) {
            deleteForm.hidden = true;
            setValue(deleteForm, 'event_id', '0');
        }
        var saveButton = form.querySelector('[data-calsys-save-event]');
        if (saveButton) {
            saveButton.textContent = labels.saveEvent || 'Save event';
        }
        var registrationToggle = form.querySelector('[data-calsys-toggle-registration]');
        if (registrationToggle) {
            registrationToggle.checked = registrationToggle.defaultChecked;
        }
        var fieldsContainer = form.querySelector('[data-calsys-registration-fields]');
        if (fieldsContainer) {
            fieldsContainer.innerHTML = '';
            fieldsContainer.dataset.nextIndex = '0';
            try {
                var defaultFields = JSON.parse(fieldsContainer.dataset.defaultFields || '[]');
                if (!Array.isArray(defaultFields)) {
                    throw new Error('Default registration fields must be an array.');
                }
                defaultFields.forEach(function (field) {
                    createRegistrationField(fieldsContainer, field);
                });
            } catch (error) {
                console.error('Unable to restore default registration fields.', error);
            }
        }
        var registrationRecipients = form.querySelector('[name="registration_recipients"]');
        if (registrationRecipients) {
            registrationRecipients.value = '';
        }
        initializeFormControls(form.parentNode);
        var recurrenceRow = widget.querySelector('[data-calsys-recurrence-toggle-row]');
        if (recurrenceRow) {
            recurrenceRow.hidden = false;
        }
        var heading = widget.querySelector('.calsys-event-editor h2');
        if (heading) {
            heading.textContent = labels.addEvent || 'Add an event';
        }
        return form;
    }

    // Shows and requires the options field only for dropdown registration fields.
    function updateRegistrationFieldOptions(row) {
        var type = row.querySelector('[data-calsys-registration-field-type]');
        var options = row.querySelector('[data-calsys-registration-field-options]');
        if (type && options) {
            options.hidden = type.value !== 'select';
            var input = options.querySelector('textarea');
            if (input) {
                input.required = type.value === 'select';
            }
        }
    }

    // Creates a custom registration-field editor row from its saved definition.
    function createRegistrationField(container, field) {
        var types = labels.registrationFieldTypes || {};
        var index = parseInt(container.dataset.nextIndex || '0', 10);
        container.dataset.nextIndex = String(index + 1);
        var fieldPrefix = container.dataset.namePrefix || 'registration_fields';
        var prefix = fieldPrefix + '[' + index + ']';
        var row = document.createElement('div');
        row.className = 'calsys-registration-field-row';
        row.setAttribute('data-calsys-registration-field-row', '');

        var labelWrap = document.createElement('label');
        labelWrap.appendChild(document.createTextNode(labels.registrationFieldLabel || 'Field label'));
        var labelInput = document.createElement('input');
        labelInput.name = prefix + '[label]';
        labelInput.type = 'text';
        labelInput.maxLength = 100;
        labelInput.required = true;
        labelInput.value = field.label || '';
        labelWrap.appendChild(labelInput);
        row.appendChild(labelWrap);

        var typeWrap = document.createElement('label');
        typeWrap.appendChild(document.createTextNode(labels.registrationFieldType || 'Field type'));
        var typeSelect = document.createElement('select');
        typeSelect.name = prefix + '[type]';
        typeSelect.setAttribute('data-calsys-registration-field-type', '');
        // Adds each supported field type to the row's type selector.
        Object.keys(types).forEach(function (key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = types[key];
            typeSelect.appendChild(option);
        });
        typeSelect.value = field.type || 'text';
        typeWrap.appendChild(typeSelect);
        row.appendChild(typeWrap);

        var optionsWrap = document.createElement('label');
        optionsWrap.setAttribute('data-calsys-registration-field-options', '');
        optionsWrap.appendChild(document.createTextNode(labels.registrationFieldOptions || 'Dropdown options (one per line)'));
        var optionsInput = document.createElement('textarea');
        optionsInput.name = prefix + '[options]';
        optionsInput.rows = 2;
        optionsInput.value = Array.isArray(field.options) ? field.options.join('\n') : '';
        optionsWrap.appendChild(optionsInput);
        row.appendChild(optionsWrap);

        var requiredWrap = document.createElement('label');
        requiredWrap.className = 'calsys-registration-field-required';
        var requiredInput = document.createElement('input');
        requiredInput.name = prefix + '[required]';
        requiredInput.type = 'checkbox';
        requiredInput.value = '1';
        requiredInput.checked = !!field.required;
        requiredWrap.appendChild(requiredInput);
        requiredWrap.appendChild(document.createTextNode(' ' + (labels.registrationFieldRequired || 'Required')));
        row.appendChild(requiredWrap);

        var removeButton = document.createElement('button');
        removeButton.className = 'button';
        removeButton.type = 'button';
        removeButton.setAttribute('data-calsys-remove-registration-field', '');
        removeButton.textContent = labels.registrationFieldRemove || 'Remove field';
        row.appendChild(removeButton);
        container.appendChild(row);
        updateRegistrationFieldOptions(row);
    }

    // Resets the staff editor and hides any delete action from a prior edit.
    function resetStaffForm(widget) {
        var form = widget.querySelector('.calsys-staff-editor form');
        if (!form) {
            return null;
        }
        form.reset();
        setValue(form, 'entry_id', '0');
        var deleteForm = widget.querySelector('[data-calsys-delete-staff-form]');
        if (deleteForm) {
            deleteForm.hidden = true;
            setValue(deleteForm, 'entry_id', '0');
        }
        var saveButton = form.querySelector('[data-calsys-save-staff]');
        if (saveButton) {
            saveButton.textContent = labels.saveStaffEntry || 'Save staff entry';
        }
        initializeFormControls(form.parentNode);
        var heading = widget.querySelector('.calsys-staff-editor h2');
        if (heading) {
            heading.textContent = labels.addStaffTime || 'Add staff time';
        }
        return form;
    }

    // Resets the room form and hides any delete action from a prior edit.
    function resetRoomForm(widget) {
        var form = widget.querySelector('.calsys-room-request form');
        if (!form) {
            return null;
        }
        form.reset();
        form._calsysAvailabilityRequest = (form._calsysAvailabilityRequest || 0) + 1;
        var availability = form.querySelector('[data-calsys-availability]');
        if (availability) {
            availability.textContent = '';
        }
        var acknowledgement = form.querySelector('[data-calsys-conflict-ack]');
        if (acknowledgement) {
            acknowledgement.hidden = true;
            var checkbox = acknowledgement.querySelector('input');
            checkbox.checked = false;
            checkbox.required = false;
            checkbox.disabled = true;
        }
        form.querySelectorAll('[data-calsys-existing-time]').forEach(function (option) { option.remove(); });
        setValue(form, 'booking_id', '0');
        var deleteForm = widget.querySelector('[data-calsys-delete-room-booking-form]');
        if (deleteForm) {
            deleteForm.hidden = true;
            setValue(deleteForm, 'booking_id', '0');
        }
        var saveButton = form.querySelector('[data-calsys-save-room]');
        if (saveButton) {
            saveButton.textContent = form.elements.namedItem('staff_booking')
                ? (labels.confirmRoomBooking || 'Confirm room booking')
                : (labels.sendRequest || 'Send request');
        }
        initializeFormControls(form.parentNode);
        var heading = widget.querySelector('.calsys-room-request h2');
        if (heading) {
            heading.textContent = form.elements.namedItem('staff_booking')
                ? (labels.addRoomBooking || 'Add a booking')
                : (labels.requestRoom || 'Request a study room');
        }
        var submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.textContent = form.elements.namedItem('staff_booking')
                ? (labels.confirmRoomBooking || 'Confirm room booking')
                : (labels.sendRequest || 'Send request');
        }
        return form;
    }

    // Adds and removes custom registration-field rows.
    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }
        var chooseImageButton = event.target.closest('[data-calsys-select-event-image]');
        if (chooseImageButton) {
            var imageForm = chooseImageButton.closest('form');
            if (!imageForm || !window.wp || !window.wp.media) {
                window.alert(labels.mediaLibraryUnavailable || 'The WordPress Media Library is not available on this page.');
                return;
            }
            var eventDialog = chooseImageButton.closest('.calsys-modal');
            if (eventDialog) {
                closeDialog(eventDialog);
            }
            var imageFrame = window.wp.media({
                title: labels.chooseEventImage || 'Choose event image',
                button: { text: labels.useEventImage || 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });
            imageFrame.on('close', function () {
                if (eventDialog) {
                    openDialog(eventDialog);
                }
            });
            imageFrame.on('select', function () {
                var attachment = imageFrame.state().get('selection').first().toJSON();
                if (!attachment || !attachment.mime || attachment.mime.indexOf('image/') !== 0) {
                    window.alert(labels.invalidEventImage || 'Please choose an image file.');
                    return;
                }
                var imageSize = attachment.sizes && (attachment.sizes.thumbnail || attachment.sizes.medium);
                updateEventImage(
                    imageForm,
                    attachment.id,
                    imageSize ? imageSize.url : attachment.url,
                    attachment.alt || ''
                );
            });
            imageFrame.open();
            return;
        }
        var removeImageButton = event.target.closest('[data-calsys-remove-event-image]');
        if (removeImageButton) {
            var removeImageForm = removeImageButton.closest('form');
            if (removeImageForm) {
                updateEventImage(removeImageForm, 0, '', '');
            }
            return;
        }
        var addFieldButton = event.target.closest('[data-calsys-add-registration-field]');
        if (addFieldButton) {
            var fieldContainer = addFieldButton.parentNode.querySelector('[data-calsys-registration-fields]');
            if (fieldContainer && fieldContainer.querySelectorAll('[data-calsys-registration-field-row]').length < 20) {
                createRegistrationField(fieldContainer, {});
            }
            return;
        }
        var removeFieldButton = event.target.closest('[data-calsys-remove-registration-field]');
        if (removeFieldButton) {
            var fieldRow = removeFieldButton.closest('[data-calsys-registration-field-row]');
            if (fieldRow) {
                fieldRow.remove();
            }
        }
    });

    // Handles calendar dialog opening, editing, date prefill, and reset actions.
    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }
        if (event.target.matches('.calsys-modal')) {
            closeDialog(event.target);
            return;
        }
        var button = event.target.closest('[data-calsys-open-registration], [data-calsys-open-event-details], [data-calsys-close-registration], [data-calsys-open-room], [data-calsys-close-room], [data-calsys-add-room], [data-calsys-edit-room], [data-calsys-add-event], [data-calsys-edit-event], [data-calsys-open-event], [data-calsys-close-event], [data-calsys-reset-event], [data-calsys-open-staff], [data-calsys-close-staff], [data-calsys-add-staff], [data-calsys-edit-staff], [data-calsys-reset-staff], [data-calsys-reset-room]');
        if (!button) {
            return;
        }

        if (button.hasAttribute('data-calsys-open-registration') || button.hasAttribute('data-calsys-open-event-details')) {
            var registrationId = button.getAttribute('data-calsys-open-registration') || button.getAttribute('data-calsys-open-event-details');
            var registrationDialogs = document.querySelectorAll('.calsys-registration-dialog');
            for (var i = 0; i < registrationDialogs.length; i++) {
                if (registrationDialogs[i].getAttribute('data-calsys-registration-dialog') === registrationId) {
                    openDialog(registrationDialogs[i]);
                    break;
                }
            }
            return;
        }

        if (button.hasAttribute('data-calsys-close-registration')) {
            var registrationDialog = button.closest('.calsys-registration-dialog');
            closeDialog(registrationDialog);
            return;
        }

        var widget = button.closest('.calsys-calendar-widget');
        if (!widget) {
            return;
        }

        if (button.hasAttribute('data-calsys-open-room')) {
            var openRoomForm = resetRoomForm(widget);
            if (openRoomForm) {
                updateRoomAvailability(openRoomForm);
            }
            openRoomDialog(widget);
        } else if (button.hasAttribute('data-calsys-close-room')) {
            closeDialog(widget.querySelector('.calsys-room-dialog'));
        } else if (button.hasAttribute('data-calsys-open-staff')) {
            resetStaffForm(widget);
            openStaffDialog(widget);
        } else if (button.hasAttribute('data-calsys-close-staff')) {
            closeDialog(widget.querySelector('.calsys-staff-dialog'));
        } else if (button.hasAttribute('data-calsys-open-event')) {
            resetEventForm(widget);
            openEventDialog(widget);
        } else if (button.hasAttribute('data-calsys-close-event')) {
            var eventDialog = widget.querySelector('.calsys-event-dialog');
            closeDialog(eventDialog);
        } else if (button.hasAttribute('data-calsys-add-room')) {
            var roomForm = resetRoomForm(widget);
            if (roomForm) {
                setValue(roomForm, 'booking_date', button.dataset.date);
                updateRoomAvailability(roomForm);
                openRoomDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-edit-room')) {
            var editRoomForm = resetRoomForm(widget);
            if (editRoomForm) {
                setValue(editRoomForm, 'booking_id', button.dataset.bookingId);
                setValue(editRoomForm, 'room_id', button.dataset.roomId);
                setValue(editRoomForm, 'booking_date', button.dataset.date);
                setValue(editRoomForm, 'starts_at', button.dataset.start);
                setValue(editRoomForm, 'ends_at', button.dataset.end);
                setValue(editRoomForm, 'requester_name', button.dataset.name);
                setValue(editRoomForm, 'requester_email', button.dataset.email);
                setEquipment(editRoomForm, button.dataset.equipment);
                updateRoomAvailability(editRoomForm);
                var roomHeading = widget.querySelector('.calsys-room-request h2');
                if (roomHeading) {
                    roomHeading.textContent = labels.editRoomBooking || 'Edit room booking';
                }
                var roomSubmit = editRoomForm.querySelector('button[type="submit"]');
                if (roomSubmit) {
                    roomSubmit.textContent = labels.saveChanges || 'Save changes';
                }
                var roomDeleteForm = widget.querySelector('[data-calsys-delete-room-booking-form]');
                if (roomDeleteForm) {
                    roomDeleteForm.hidden = false;
                    setValue(roomDeleteForm, 'booking_id', button.dataset.bookingId);
                }
                openRoomDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-add-event')) {
            var eventForm = resetEventForm(widget);
            if (eventForm) {
                setValue(eventForm, 'starts_at', button.dataset.date + 'T10:00');
                setValue(eventForm, 'ends_at', button.dataset.date + 'T11:00');
                openEventDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-edit-event')) {
            var editEventForm = resetEventForm(widget);
            if (editEventForm) {
                setValue(editEventForm, 'event_id', button.dataset.eventId);
                if (button.dataset.recurrenceGroup) {
                    editEventForm.dataset.calsysEditingRecurrence = '1';
                }
                setValue(editEventForm, 'series_id', button.dataset.seriesId || '0');
                var registerRecurrences = editEventForm.querySelector('[name="register_all_recurrences"]');
                if (registerRecurrences) {
                    registerRecurrences.checked = button.dataset.registerAllRecurrences === '1';
                }
                var seriesScope = editEventForm.querySelector('[data-calsys-series-scope]');
                if (seriesScope) {
                    seriesScope.hidden = !button.dataset.recurrenceGroup;
                }
                var eventDeleteForm = widget.querySelector('[data-calsys-delete-event-form]');
                if (eventDeleteForm) {
                    eventDeleteForm.hidden = false;
                    setValue(eventDeleteForm, 'event_id', button.dataset.eventId);
                }
                var eventSaveButton = editEventForm.querySelector('[data-calsys-save-event]');
                if (eventSaveButton) {
                    eventSaveButton.textContent = labels.saveChanges || 'Save changes';
                }
                setValue(editEventForm, 'title', button.dataset.title);
                var savedInitials = editEventForm.querySelector('[data-calsys-saved-initials]');
                if (savedInitials && button.dataset.staffInitials) {
                    savedInitials.textContent = (labels.lastSavedBy || 'Last saved by: %s').replace('%s', button.dataset.staffInitials);
                }
                setEquipment(editEventForm, button.dataset.equipment);
                setValue(editEventForm, 'description', button.dataset.description);
                updateEventImage(editEventForm, button.dataset.imageId, button.dataset.imageUrl, button.dataset.imageAlt);
                setValue(editEventForm, 'starts_at', button.dataset.start);
                setValue(editEventForm, 'ends_at', button.dataset.end);
                setValue(editEventForm, 'capacity', button.dataset.capacity);
                var showRegistrationCounts = editEventForm.querySelector('[name="show_registration_counts"]');
                if (showRegistrationCounts) {
                    showRegistrationCounts.checked = button.dataset.showRegistrationCounts === '1';
                }
                setValue(editEventForm, 'status', button.dataset.status);
                setValue(editEventForm, 'category', button.dataset.category);
                var registrationToggle = editEventForm.querySelector('[data-calsys-toggle-registration]');
                if (registrationToggle) {
                    registrationToggle.checked = button.dataset.registrationEnabled === '1';
                }
                var registrationRecipients = editEventForm.querySelector('[name="registration_recipients"]');
                if (registrationRecipients) {
                    try {
                        var eventRecipients = JSON.parse(button.dataset.registrationRecipients || '[]');
                        registrationRecipients.value = Array.isArray(eventRecipients) ? eventRecipients.join('\n') : '';
                    } catch (error) {
                        console.error('Unable to load event registration recipients.', error);
                    }
                }
                var fieldsContainer = editEventForm.querySelector('[data-calsys-registration-fields]');
                if (fieldsContainer) {
                    try {
                        var eventFields = JSON.parse(button.dataset.registrationFields || '[]');
                        if (!Array.isArray(eventFields)) {
                            throw new Error('Registration fields must be an array.');
                        }
                        // Restores each saved custom field in the event editor.
                        eventFields.forEach(function (field) {
                            createRegistrationField(fieldsContainer, field);
                        });
                    } catch (error) {
                        console.error('Unable to load event registration fields.', error);
                    }
                }
                var eventRoomToggle = editEventForm.querySelector('[data-calsys-toggle-room]');
                if (eventRoomToggle) {
                    eventRoomToggle.checked = !!button.dataset.roomId && button.dataset.roomId !== '0';
                }
                setValue(editEventForm, 'room_id', button.dataset.roomId);
                initializeFormControls(editEventForm.parentNode);
                var recurrenceRow = widget.querySelector('[data-calsys-recurrence-toggle-row]');
                if (recurrenceRow) {
                    recurrenceRow.hidden = true;
                }
                var eventHeading = widget.querySelector('.calsys-event-editor h2');
                if (eventHeading) {
                    eventHeading.textContent = labels.editEvent || 'Edit event';
                }
                openEventDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-add-staff')) {
            var staffForm = resetStaffForm(widget);
            if (staffForm) {
                setValue(staffForm, 'starts_at', button.dataset.date + 'T09:00');
                setValue(staffForm, 'ends_at', button.dataset.date + 'T17:00');
                openStaffDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-edit-staff')) {
            var editStaffForm = resetStaffForm(widget);
            if (editStaffForm) {
                setValue(editStaffForm, 'entry_id', button.dataset.entryId);
                var staffDeleteForm = widget.querySelector('[data-calsys-delete-staff-form]');
                if (staffDeleteForm) {
                    staffDeleteForm.hidden = false;
                    setValue(staffDeleteForm, 'entry_id', button.dataset.entryId);
                }
                var staffSaveButton = editStaffForm.querySelector('[data-calsys-save-staff]');
                if (staffSaveButton) {
                    staffSaveButton.textContent = labels.saveChanges || 'Save changes';
                }
                setValue(editStaffForm, 'title', button.dataset.title);
                setEquipment(editStaffForm, button.dataset.equipment);
                setValue(editStaffForm, 'entry_type', button.dataset.entryType);
                setValue(editStaffForm, 'starts_at', button.dataset.start);
                setValue(editStaffForm, 'ends_at', button.dataset.end);
                var staffRoomToggle = editStaffForm.querySelector('[data-calsys-toggle-room]');
                if (staffRoomToggle) {
                    staffRoomToggle.checked = !!button.dataset.roomId && button.dataset.roomId !== '0';
                }
                setValue(editStaffForm, 'room_id', button.dataset.roomId);
                initializeFormControls(editStaffForm.parentNode);
                var allDay = editStaffForm.elements.namedItem('all_day');
                if (allDay) {
                    allDay.checked = button.dataset.allDay === '1';
                }
                var staffHeading = widget.querySelector('.calsys-staff-editor h2');
                if (staffHeading) {
                    staffHeading.textContent = labels.editStaffTime || 'Edit staff time';
                }
                openStaffDialog(widget);
            }
        } else if (button.hasAttribute('data-calsys-reset-event')) {
            resetEventForm(widget);
        } else if (button.hasAttribute('data-calsys-reset-staff')) {
            resetStaffForm(widget);
        } else if (button.hasAttribute('data-calsys-reset-room')) {
            resetRoomForm(widget);
        }
    }, true);

    // Updates conditional form controls after a user changes a setting.
    document.addEventListener('change', function (event) {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-calsys-select-series-events]')) {
            event.target.closest('.calsys-series-selection').querySelectorAll('input[name="series_events[]"]').forEach(function (choice) {
                choice.checked = event.target.checked;
            });
        } else if (event.target instanceof HTMLInputElement && event.target.matches('input[name="series_events[]"]')) {
            var selection = event.target.closest('.calsys-series-selection');
            var selectAll = selection.querySelector('[data-calsys-select-series-events]');
            var choices = selection.querySelectorAll('input[name="series_events[]"]');
            var checkedChoices = selection.querySelectorAll('input[name="series_events[]"]:checked');
            selectAll.checked = checkedChoices.length === choices.length;
            selectAll.indeterminate = checkedChoices.length > 0 && checkedChoices.length < choices.length;
        }
        if (event.target instanceof Element &&
            event.target.matches('.calsys-category-filter input[name="calsys_category[]"], .calsys-category-filter select[name="calsys_recurring"], .calsys-category-filter select[name="calsys_series"]')) {
            var categoryFilter = event.target.closest('[data-calsys-auto-submit]');
            if (categoryFilter) {
                categoryFilter.requestSubmit();
            }
        }
        if (event.target instanceof Element) {
            var fieldType = event.target.closest('[data-calsys-registration-field-type]');
            if (fieldType) {
                updateRegistrationFieldOptions(fieldType.closest('[data-calsys-registration-field-row]'));
            }
        }
        if (event.target instanceof Element && event.target.closest('.calsys-action-form, .calsys-admin-form')) {
            updateFormControls(event.target.closest('.calsys-action-form, .calsys-admin-form'));
        }
        if (event.target instanceof Element && event.target.matches('[name="booking_date"], [name="room_id"], [name="starts_at"], [name="ends_at"], [name="public_event"]')) {
            var roomForm = event.target.closest('[data-calsys-room-availability]');
            if (roomForm) {
                updateRoomAvailability(roomForm);
            }
        }
    });

    // Confirms destructive calendar actions before allowing their forms to submit.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.matches('[data-calsys-delete-event-form]')) {
            if (!window.confirm(labels.confirmDeleteEvent || 'Delete this event and its registrations and linked room reservation? This cannot be undone.')) {
                event.preventDefault();
            }
        } else if (form.matches('[data-calsys-delete-staff-form]')) {
            if (!window.confirm(labels.confirmDeleteStaffEntry || 'Delete this staff entry and its linked room reservation?')) {
                event.preventDefault();
            }
        } else if (form.matches('[data-calsys-delete-room-booking-form]')) {
            if (!window.confirm(labels.confirmDeleteRoomBooking || 'Delete this room booking?')) {
                event.preventDefault();
            }
        }
    });

    document.addEventListener('formdata', function (event) {
        var selectAllSeries = event.target.querySelector('[data-calsys-select-series-events]');
        if (selectAllSeries && selectAllSeries.checked) {
            event.formData.delete('series_events[]');
        }
    }, true);

    // Initializes forms rendered after the script was loaded.
    document.addEventListener('DOMContentLoaded', function () {
        initializeFormControls(document);
        document.querySelectorAll('[data-calsys-room-availability]').forEach(updateRoomAvailability);
    });
    initializeFormControls(document);
})();
