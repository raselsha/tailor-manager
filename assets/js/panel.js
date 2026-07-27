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
                            : 'কিছু একটা সমস্যা হয়েছে।';
                        if (typeof onError === 'function') {
                            onError(message);
                        } else {
                            window.alert(message);
                        }
                    }
                })
                .fail(function () {
                    if (typeof onError === 'function') {
                        onError('রিকোয়েস্ট ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
                    } else {
                        window.alert('রিকোয়েস্ট ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
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
            return window.confirm(message || 'আপনি কি নিশ্চিত এটি ডিলিট করতে চান?');
        },

        /**
         * Keeps a .tmr-status-toggle checkbox's adjacent .tmr-status-toggle-label text in
         * sync with its checked state (সক্রিয়/নিষ্ক্রিয়) — called both on the toggle's own
         * `change` event and directly whenever JS sets `.prop('checked', ...)` itself
         * (programmatic changes don't fire `change`, e.g. populating an Edit modal).
         */
        syncStatusToggle: function ($checkbox) {
            $checkbox.siblings('.tmr-status-toggle-label').text($checkbox.is(':checked') ? 'সক্রিয়' : 'নিষ্ক্রিয়');
        }
    };

    $(document).on('change', '.tmr-status-toggle', function () {
        TMRPanel.syncStatusToggle($(this));
    });

    $(document).on('click', '.tmr-modal', function (e) {
        if (e.target === this) {
            TMRPanel.closeModal($(this));
        }
    });

    $(document).on('click', '.tmr-modal-close, [data-tmr-close-modal]', function (e) {
        e.preventDefault();
        $(this).closest('.tmr-modal').removeClass('is-open');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.tmr-modal.is-open').removeClass('is-open');
        }
    });

}(jQuery));
