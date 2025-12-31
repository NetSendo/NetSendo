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

            $button.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text(netsendoWC.strings.testing);

            $.ajax({
                url: netsendoWC.ajax_url,
                type: 'POST',
                data: {
                    action: 'netsendo_wc_test_connection',
                    nonce: netsendoWC.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $status.removeClass('loading');

                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
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
                        var $selects = $('select[name*="list_id"]');

                        $selects.each(function() {
                            var $select = $(this);
                            var currentValue = $select.val();

                            // Keep the first option (placeholder)
                            $select.find('option:not(:first)').remove();

                            // Add new options
                            $.each(lists, function(index, list) {
                                $select.append(
                                    $('<option></option>')
                                        .attr('value', list.id)
                                        .text(list.name)
                                        .prop('selected', list.id == currentValue)
                                );
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

    });

})(jQuery);
