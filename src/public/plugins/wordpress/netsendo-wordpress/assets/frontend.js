/**
 * NetSendo for WordPress - Frontend JavaScript
 *
 * @package NetSendo_WordPress
 */

(function($) {
    'use strict';

    /**
     * Form Handler
     */
    var FormHandler = {
        init: function() {
            $(document).on('submit', '.netsendo-form__form', this.handleSubmit.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $container = $form.closest('.netsendo-form');
            var $button = $form.find('.netsendo-form__button');
            var $buttonText = $button.find('.netsendo-form__button-text');
            var $buttonLoading = $button.find('.netsendo-form__button-loading');
            var $message = $form.find('.netsendo-form__message');
            var $success = $container.find('.netsendo-form__success');

            // Get form data
            var email = $form.find('input[name="email"]').val();
            var name = $form.find('input[name="name"]').val() || '';
            var consent = $form.find('input[name="consent"]');
            var listId = $container.data('list-id');

            // Validate email
            if (!this.isValidEmail(email)) {
                this.showMessage($message, netsendoWP.strings.invalid_email, 'error');
                return;
            }

            // Validate consent if present
            if (consent.length && !consent.is(':checked')) {
                this.showMessage($message, netsendoWP.strings.consent_required, 'error');
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $buttonText.hide();
            $buttonLoading.show();
            $message.hide();

            // Send AJAX request
            $.ajax({
                url: netsendoWP.ajax_url,
                type: 'POST',
                data: {
                    action: 'netsendo_wp_subscribe',
                    nonce: netsendoWP.nonce,
                    email: email,
                    name: name,
                    list_id: listId,
                    consent: consent.is(':checked') ? 1 : 0,
                    page_url: window.location.href,
                    form_id: $container.attr('id')
                },
                success: function(response) {
                    if (response.success) {
                        // Show success state
                        $form.hide();
                        $success.fadeIn();

                        // Set unlock cookie
                        if (response.data && response.data.unlock_key) {
                            ContentGateHandler.unlockGates();
                        }
                    } else {
                        FormHandler.showMessage($message, response.data.message || netsendoWP.strings.error, 'error');
                    }
                },
                error: function() {
                    FormHandler.showMessage($message, netsendoWP.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $buttonText.show();
                    $buttonLoading.hide();
                }
            });
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        showMessage: function($message, text, type) {
            $message
                .removeClass('netsendo-form__message--error netsendo-form__message--success')
                .addClass('netsendo-form__message--' + type)
                .text(text)
                .fadeIn();
        }
    };

    /**
     * Content Gate Handler
     */
    var ContentGateHandler = {
        init: function() {
            $(document).on('submit', '.netsendo-content-gate__form', this.handleSubmit.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $gate = $form.closest('.netsendo-content-gate');
            var $button = $form.find('.netsendo-content-gate__submit');
            var $buttonText = $button.find('.netsendo-content-gate__submit-text');
            var $buttonLoading = $button.find('.netsendo-content-gate__submit-loading');
            var $message = $form.find('.netsendo-content-gate__message');

            // Get form data
            var email = $form.find('input[name="email"]').val();
            var consent = $form.find('input[name="consent"]');
            var listId = $form.data('list-id');

            // Validate email
            if (!FormHandler.isValidEmail(email)) {
                this.showMessage($message, netsendoWP.strings.invalid_email, 'error');
                return;
            }

            // Validate consent if present
            if (consent.length && !consent.is(':checked')) {
                this.showMessage($message, netsendoWP.strings.consent_required, 'error');
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $buttonText.hide();
            $buttonLoading.show();
            $message.hide();

            // Send AJAX request
            $.ajax({
                url: netsendoWP.ajax_url,
                type: 'POST',
                data: {
                    action: 'netsendo_wp_subscribe',
                    nonce: netsendoWP.nonce,
                    email: email,
                    list_id: listId,
                    consent: consent.is(':checked') ? 1 : 0,
                    page_url: window.location.href,
                    form_id: $form.data('gate-id')
                },
                success: function(response) {
                    if (response.success) {
                        ContentGateHandler.unlockGate($gate);
                    } else {
                        ContentGateHandler.showMessage($message, response.data.message || netsendoWP.strings.error, 'error');
                    }
                },
                error: function() {
                    ContentGateHandler.showMessage($message, netsendoWP.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $buttonText.show();
                    $buttonLoading.hide();
                }
            });
        },

        unlockGate: function($gate) {
            var $overlay = $gate.find('.netsendo-content-gate__overlay, .netsendo-content-gate__locked');
            var $hidden = $gate.find('.netsendo-content-gate__hidden');

            // Animate unlock
            $overlay.fadeOut(400, function() {
                $hidden.fadeIn(400);
                $gate.addClass('netsendo-content-gate--unlocked');
            });
        },

        unlockGates: function() {
            $('.netsendo-content-gate').each(function() {
                ContentGateHandler.unlockGate($(this));
            });
        },

        showMessage: function($message, text, type) {
            $message
                .removeClass('netsendo-content-gate__message--error netsendo-content-gate__message--success')
                .addClass('netsendo-content-gate__message--' + type)
                .text(text)
                .fadeIn();
        }
    };

    /**
     * Initialize on document ready
     */
    $(function() {
        FormHandler.init();
        ContentGateHandler.init();
    });

})(jQuery);
