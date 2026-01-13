/**
 * NetSendo for WooCommerce - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Test Connection Button
        $('#netsendo_test_connection').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#netsendo_connection_status');

            // Get current form values
            var apiUrl = $('#netsendo_api_url').val();
            var apiKey = $('#netsendo_api_key').val();

            $button.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text(netsendoWC.strings.testing);

            $.ajax({
                url: netsendoWC.ajax_url,
                type: 'POST',
                data: {
                    action: 'netsendo_wc_test_connection',
                    nonce: netsendoWC.nonce,
                    api_url: apiUrl,
                    api_key: apiKey
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $status.removeClass('loading');

                    if (response.success) {
                        $status.addClass('success').text(response.data.message);

                        // Log user_id for debugging (WooCommerce doesn't show Pixel status)
                        if (response.data.user_id) {
                            console.log('[NetSendo WooCommerce] Pixel User ID configured:', response.data.user_id);
                        }
                    } else {
                        $status.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $status.removeClass('loading').addClass('error').text('Connection failed');
                }
            });
        });

        // Refresh Lists Button
        $('#netsendo_refresh_lists').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();

            $button.prop('disabled', true).text(netsendoWC.strings.refreshing);

            $.ajax({
                url: netsendoWC.ajax_url,
                type: 'POST',
                data: {
                    action: 'netsendo_wc_refresh_lists',
                    nonce: netsendoWC.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).text(originalText);

                    if (response.success && response.data.lists) {
                        // Update all list dropdowns
                        var lists = response.data.lists;
                        var $selects = $('select.netsendo-list-select');

                        $selects.each(function() {
                            var $select = $(this);
                            var currentValue = $select.val();

                            // Keep the first option (placeholder) and the manual option
                            $select.find('option').not(':first').not('[value="manual"]').remove();

                            // Add new options before the "manual" option
                            var $manualOption = $select.find('option[value="manual"]');
                            $.each(lists, function(index, list) {
                                var $option = $('<option></option>')
                                    .attr('value', list.id)
                                    .text(list.name)
                                    .prop('selected', list.id == currentValue);
                                if ($manualOption.length) {
                                    $manualOption.before($option);
                                } else {
                                    $select.append($option);
                                }
                            });
                        });

                        alert(response.data.message);
                    } else {
                        alert(response.data.message || 'Failed to refresh lists');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert('Failed to refresh lists');
                }
            });
        });

        // Toggle manual ID input fields when "manual" option is selected
        $('.netsendo-list-select').on('change', function() {
            var $select = $(this);
            var $wrapper = $select.closest('.netsendo-list-field');
            var $manualInput = $wrapper.next('.netsendo-manual-id');

            if ($select.val() === 'manual') {
                $manualInput.show();
            } else {
                $manualInput.hide();
            }
        });

    });

})(jQuery);
