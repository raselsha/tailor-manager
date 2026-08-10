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
        },

        /**
         * Wires drag-and-drop card reordering via SortableJS (window.Sortable,
         * vendored — see the enqueue comment in TMR_Panel_Shell::enqueue_assets()
         * for why it replaced jQuery UI Sortable), one independent instance per
         * matched container (e.g. every category's own .tmr-dress-grid, so
         * dragging never crosses from one category's group into another's — this
         * is display-order only, not a way to reassign category/parent). Each
         * container saves its own order the moment a drag finishes, sending every
         * card's data-id in its new top-to-bottom/left-to-right order.
         * @param {string} gridSelector e.g. '.tmr-dress-grid'
         * @param {string} action       tmr_ AJAX action that persists the order
         */
        initSortableGrids: function (gridSelector, action) {
            $(gridSelector).each(function () {
                window.Sortable.create(this, {
                    animation: 150,
                    handle: '.tmr-drag-handle',
                    draggable: '.tmr-dress-card:not(.tmr-dress-card-add)',
                    ghostClass: 'tmr-drag-ghost',
                    chosenClass: 'tmr-drag-chosen',
                    // Touch only (mouse stays instant): a short press-and-hold
                    // before the drag actually engages, so a finger that's really
                    // trying to scroll the page (and moves a few px during that
                    // hold) cancels the drag instead of yanking a card sideways —
                    // without this, a touch grab and an attempted scroll compete
                    // for the same gesture and the drag "doesn't take."
                    delay: 150,
                    delayOnTouchOnly: true,
                    touchStartThreshold: 5,
                    onEnd: function (evt) {
                        // evt.to (not evt.target, which SortableJS doesn't document/
                        // guarantee) is the container the drag actually ended in —
                        // always this same grid here since cross-grid dragging isn't
                        // enabled (no `group` option set), but reading the documented
                        // property is one less thing to get wrong.
                        var ids = $(evt.to).find('.tmr-dress-card:not(.tmr-dress-card-add)').map(function () {
                            return $(this).data('id');
                        }).get();
                        if (ids.length) {
                            TMRPanel.call(action, { order: ids });
                        }
                    }
                });
            });
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

    // Mobile sidebar drawer — toggled by the hamburger button that only renders
    // (and is only visible) under the panel's own 900px mobile breakpoint.
    $(document).on('click', '#tmr-mobile-menu-toggle', function () {
        $('.tmr-admin-wrapper').toggleClass('tmr-sidebar-open');
    });
    $(document).on('click', '#tmr-sidebar-backdrop, .tmr-sidebar-menu a, .tmr-sidebar-footer', function () {
        $('.tmr-admin-wrapper').removeClass('tmr-sidebar-open');
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.tmr-admin-wrapper').removeClass('tmr-sidebar-open');
        }
    });

}(jQuery));
