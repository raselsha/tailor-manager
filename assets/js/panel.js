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
            TMRPanel.hideSkeletonModal($backdrop);
        },

        confirmDelete: function (message) {
            return window.confirm(message || 'আপনি কি নিশ্চিত এটি ডিলিট করতে চান?');
        },

        /**
         * Skeleton loading placeholders (see panel.css) — a few ready-made shapes
         * built from the same shimmering .tmr-skeleton block, one generator per
         * call site shape rather than a single generic one, since a form's field
         * rows, a paragraph of detail lines, and a table row don't share markup.
         */
        skeletonLines: function (n, widths) {
            n = n || 4;
            var html = '';
            var defaults = ['95%', '88%', '70%', '92%', '60%'];
            for (var i = 0; i < n; i++) {
                var w = (widths && widths[i]) || defaults[i % defaults.length];
                html += '<div class="tmr-skeleton tmr-skeleton-line" style="width:' + w + '"></div>';
            }
            return html;
        },

        skeletonFormRows: function (n) {
            n = n || 4;
            var html = '';
            for (var i = 0; i < n; i++) {
                html += '<div class="tmr-skeleton-form-row">' +
                    '<div class="tmr-skeleton tmr-skeleton-label"></div>' +
                    '<div class="tmr-skeleton tmr-skeleton-input"></div>' +
                    '</div>';
            }
            return html;
        },

        skeletonTableRow: function (colCount, widths) {
            var html = '<tr class="tmr-skeleton-row">';
            for (var i = 0; i < colCount; i++) {
                var w = (widths && widths[i]) || '70%';
                html += '<td><div class="tmr-skeleton tmr-skeleton-line" style="width:' + w + '"></div></td>';
            }
            return html + '</tr>';
        },

        /**
         * Opens an edit modal immediately instead of waiting for its tmr_get_*
         * fetch to resolve, showing a skeleton over the (already-in-DOM) form and
         * disabling its submit button — so a slow request never leaves the click
         * looking like it did nothing, and can't be raced by an early Save.
         * Caller still populates fields in its own AJAX success handler; call
         * hideSkeletonModal() there when done (closeModal() also does this).
         */
        showSkeletonModal: function ($modal, formRowCount) {
            var $body = $modal.find('.tmr-modal-body').first();
            var $overlay = $body.find('.tmr-skeleton-overlay');
            if (!$overlay.length) {
                $overlay = $('<div class="tmr-skeleton-overlay"></div>').appendTo($body);
            }
            $overlay.html(TMRPanel.skeletonFormRows(formRowCount)).addClass('is-active');
            $modal.find('.tmr-modal-foot button[type="submit"]').prop('disabled', true);
            TMRPanel.openModal($modal);
        },

        hideSkeletonModal: function ($modal) {
            $modal.find('.tmr-skeleton-overlay').removeClass('is-active');
            $modal.find('.tmr-modal-foot button[type="submit"]').prop('disabled', false);
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

    // "পেজে যান" jump box (TMR_Customers_Panel::render_pagination(), shared with
    // the Orders panel) — shows up once a list has more than 10 pages, where
    // scanning the windowed page numbers for a specific one by eye stops being
    // realistic (2000+ customers is 100+ pages here). data-base carries the
    // same $base_args the page links themselves were built from (jQuery parses
    // it back into a plain object automatically since it's valid JSON).
    function tmrGoToPage($jump) {
        var page = parseInt($jump.find('.tmr-page-jump-input').val(), 10);
        var max = parseInt($jump.data('max'), 10);
        if (!page || page < 1 || page > max) {
            return;
        }
        var base = $jump.data('base');
        var url;
        if (base && typeof base === 'object' && Object.keys(base).length) {
            url = new URL(window.location.origin + window.location.pathname);
            Object.keys(base).forEach(function (key) {
                url.searchParams.set(key, base[key]);
            });
        } else {
            url = new URL(window.location.href);
        }
        url.searchParams.set('paged', page);
        window.location.href = url.toString();
    }

    $(document).on('click', '.tmr-page-jump-btn', function () {
        tmrGoToPage($(this).closest('.tmr-page-jump'));
    });
    $(document).on('keydown', '.tmr-page-jump-input', function (e) {
        if ('Enter' === e.key) {
            e.preventDefault();
            tmrGoToPage($(this).closest('.tmr-page-jump'));
        }
    });

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
