/**
 * NetSendo for WordPress - Admin JavaScript
 *
 * @package NetSendo_WordPress
 */

(function($) {
    'use strict';

    /**
     * Test Connection Handler
     */
    $('#netsendo_test_connection').on('click', function() {
        var $button = $(this);
        var $status = $('#netsendo_connection_status');

        // Get current form values
        var apiUrl = $('#netsendo_api_url').val();
        var apiKey = $('#netsendo_api_key').val();

        $button.prop('disabled', true);
        $status
            .removeClass('success error')
            .addClass('loading')
            .text(netsendoWPAdmin.strings.testing);

        $.ajax({
            url: netsendoWPAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'netsendo_wp_test_connection',
                nonce: netsendoWPAdmin.nonce,
                api_url: apiUrl,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $status
                        .removeClass('loading error')
                        .addClass('success')
                        .text(response.data.message);

                    // Update Pixel status section if user_id was returned
                    if (response.data.user_id) {
                        var $pixelStatus = $('input[name="netsendo_wp_settings[enable_pixel]"]').closest('td').find('.description');
                        if ($pixelStatus.length) {
                            $pixelStatus
                                .css('color', '#00a32a')
                                .html('✓ Pixel configured (User ID: ' + response.data.user_id + ')');
                        }
                    }
                } else {
                    $status
                        .removeClass('loading success')
                        .addClass('error')
                        .text(response.data.message);
                }
            },
            error: function() {
                $status
                    .removeClass('loading success')
                    .addClass('error')
                    .text(netsendoWPAdmin.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    /**
     * Refresh Lists Handler
     */
    $('#netsendo_refresh_lists').on('click', function() {
        var $button = $(this);
        var $select = $('#netsendo_default_list');
        var currentValue = $select.val();

        $button.prop('disabled', true).text(netsendoWPAdmin.strings.refreshing);

        $.ajax({
            url: netsendoWPAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'netsendo_wp_refresh_lists',
                nonce: netsendoWPAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.lists) {
                    // Rebuild select options
                    var options = '<option value="">— Select List —</option>';
                    $.each(response.data.lists, function(i, list) {
                        var selected = list.id == currentValue ? ' selected' : '';
                        options += '<option value="' + list.id + '"' + selected + '>' +
                                   $('<div>').text(list.name).html() + '</option>';
                    });

                    // Update all list selects on the page
                    $('.netsendo-list-select').html(options);

                    // Show success message
                    alert(response.data.message);
                } else {
                    alert(response.data.message || 'Failed to refresh lists');
                }
            },
            error: function() {
                alert('An error occurred while refreshing lists.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Refresh Lists');
            }
        });
    });

    /**
     * Show/hide manual ID input
     */
    $(document).on('change', '.netsendo-list-select', function() {
        var $select = $(this);
        var $container = $select.closest('.netsendo-list-field, td');
        var $manualInput = $container.find('.netsendo-manual-id');

        if ($select.val() === 'manual') {
            $manualInput.slideDown(200);
        } else {
            $manualInput.slideUp(200);
        }
    });

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Trigger initial state for manual inputs
        $('.netsendo-list-select').each(function() {
            var $select = $(this);
            var $container = $select.closest('.netsendo-list-field, td');
            var $manualInput = $container.find('.netsendo-manual-id');

            if ($select.val() !== 'manual') {
                $manualInput.hide();
            }
        });
    });

})(jQuery);
