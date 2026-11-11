/* Initializes dynamic settings controls on the calendar settings page. */
(function ($) {
    'use strict';

    // Binds category and staff-type controls after the settings DOM is ready.
    $(function () {
        var $types = $('#calsys-staff-types');
        if (!$types.length) {
            return;
        }

        var nextIndex = $types.find('.calsys-type-row').length;
        var typeTemplate = wp.template('calsys-staff-type');
        var $categories = $('#calsys-event-categories');
        var nextCategoryIndex = $categories.find('.calsys-category-row').length;
        var categoryTemplate = wp.template('calsys-event-category');

        // Activates WordPress color pickers within the supplied settings section.
        function initializeColorPicker($scope) {
            $scope.find('.calsys-color-picker').wpColorPicker();
        }

        initializeColorPicker($(document));

        // Adds a staff booking type row and initializes its color picker.
        $('#calsys-add-type').on('click', function () {
            if ($types.find('.calsys-type-row').length >= 20) {
                return;
            }
            $types.append(typeTemplate({ index: nextIndex }));
            nextIndex += 1;
            initializeColorPicker($types.find('.calsys-type-row').last());
        });

        // Confirms removal of a staff booking type row.
        $types.on('click', '.calsys-remove-type', function () {
            if (window.confirm(calsysSettings.confirmRemoveType)) {
                $(this).closest('.calsys-type-row').remove();
            }
        });

        // Adds a new event-category row to the settings form.
        $('#calsys-add-category').on('click', function () {
            if ($categories.find('.calsys-category-row').length >= 40) {
                return;
            }
            $categories.append(categoryTemplate({ index: nextCategoryIndex }));
            nextCategoryIndex += 1;
        });

        // Removes the selected event-category row from the settings form.
        $categories.on('click', '.calsys-remove-category', function () {
            $(this).closest('.calsys-category-row').remove();
        });

        var $holidays = $('#calsys-holiday-hours');
        var nextHolidayIndex = $holidays.find('.calsys-holiday-row').length;
        $('#calsys-add-holiday').on('click', function () {
            if ($holidays.find('.calsys-holiday-row').length >= 100) {
                return;
            }
            var template = document.getElementById('calsys-holiday-template');
            $holidays.append(template.innerHTML.replace(/INDEX/g, String(nextHolidayIndex)));
            nextHolidayIndex += 1;
        });
        $holidays.on('click', '.calsys-remove-holiday', function () {
            $(this).closest('.calsys-holiday-row').remove();
        });
    });
})(jQuery);
