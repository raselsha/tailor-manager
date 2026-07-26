/* global TMR, jQuery */
(function ($) {
    'use strict';

    window.TMRPanel = {
        /**
         * Shared AJAX helper. action is appended with the tmr_ prefix by callers.
         */
        call: function (action, data, onSuccess, onError) {
            var payload;

            // `data` is either a plain object (e.g. { id: 5 }) or an already-serialized
            // query string (e.g. from $form.serialize() / $.param()). $.extend() only
            // merges objects — feeding it a string silently corrupts the request, so the
            // two cases must be built into the request body differently.
            if (typeof data === 'string') {
                payload = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(TMR.nonce);
                if (data) {
                    payload += '&' + data;
                }
            } else {
                payload = $.extend({ action: action, nonce: TMR.nonce }, data || {});
            }

            $.post(TMR.ajaxUrl, payload)
                .done(function (response) {
                    if (response && response.success) {
                        if (typeof onSuccess === 'function') {
                            onSuccess(response.data);
                        }
                    } else {
                        var message = response && response.data && response.data.message
                            ? response.data.message
                            : 'Something went wrong.';
                        if (typeof onError === 'function') {
                            onError(message);
                        } else {
                            window.alert(message);
                        }
                    }
                })
                .fail(function () {
                    if (typeof onError === 'function') {
                        onError('Request failed. Please try again.');
                    } else {
                        window.alert('Request failed. Please try again.');
                    }
                });
        },

        openModal: function ($backdrop) {
            $backdrop.addClass('is-open');
        },

        closeModal: function ($backdrop) {
            $backdrop.removeClass('is-open');
        },

        confirmDelete: function (message) {
            return window.confirm(message || 'Are you sure you want to delete this?');
        }
    };

    $(document).on('click', '.tmr-modal-backdrop', function (e) {
        if (e.target === this) {
            TMRPanel.closeModal($(this));
        }
    });

    $(document).on('click', '.tmr-modal__close, [data-tmr-close-modal]', function (e) {
        e.preventDefault();
        $(this).closest('.tmr-modal-backdrop').removeClass('is-open');
    });

}(jQuery));
