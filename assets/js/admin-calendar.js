/**
 * Domilocus WP Admin Calendar JavaScript
 */

(function($) {
    'use strict';

    var DomilocusAdminCalendar = {
        apartmentId: 0,
        currentDate: new Date(),
        calendarData: {},
        viewMode: 'month', // month, week, day
        
        init: function() {
            this.apartmentId = $('#domilocus-calendar-container').data('apartment-id');
            this.viewMode = $('#domilocus-calendar-container').data('view') || 'month';
            if (this.apartmentId) {
                this.loadCalendar();
                this.bindEvents();
                this.createModal();
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Navigation buttons
            $(document).on('click', '.calendar-nav', function(e) {
                e.preventDefault();
                var direction = $(this).data('direction');
                self.navigateCalendar(direction);
            });
            
            // Day clicks
            $(document).on('click', '.calendar-day:not(.empty):not(.past)', function() {
                var date = $(this).data('date');
                if (date) {
                    self.openDayModal(date);
                }
            });
            
            // Modal events
            $(document).on('click', '.day-details-close, .day-details-cancel', function() {
                self.closeModal();
            });
            
            $(document).on('click', '.day-details-save', function() {
                self.saveDayDetails();
            });
            
            // Close modal on outside click
            $(document).on('click', '.day-details-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
        },
        
        createModal: function() {
            var i18n = domilocus_admin_vars.i18n;
            var modalHtml = `
                <div id="day-details-modal" class="day-details-modal">
                    <div class="day-details-content">
                        <button class="day-details-close">&times;</button>
                        <h3 id="modal-title">` + i18n.manage_day + `</h3>
                        <form class="day-details-form" id="day-details-form">
                            <input type="hidden" id="modal-date" name="date" value="">
                            
                            <div class="form-field">
                                <label for="modal-status">` + i18n.status + `</label>
                                <select id="modal-status" name="status">
                                    <option value="available">` + i18n.available + `</option>
                                    <option value="booked" disabled>` + i18n.booked + `</option>
                                    <option value="blocked">` + i18n.blocked + `</option>
                                    <option value="maintenance">` + i18n.maintenance + `</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="modal-price">` + i18n.price_per_night + `</label>
                                <input type="number" id="modal-price" name="price" step="0.01" min="0">
                            </div>
                            
                            <div class="form-field">
                                <label for="modal-min-stay">` + i18n.minimum_stay + `</label>
                                <input type="number" id="modal-min-stay" name="min_stay" min="1" value="1">
                            </div>
                            
                            <div class="form-field">
                                <label for="modal-notes">` + i18n.notes + `</label>
                                <textarea id="modal-notes" name="notes" rows="3" placeholder="` + i18n.notes_placeholder + `"></textarea>
                            </div>
                            
                            <div class="button-group">
                                <button type="button" class="button day-details-cancel">` + i18n.cancel + `</button>
                                <button type="button" class="button button-primary day-details-save">` + i18n.save + `</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        },
        
        loadCalendar: function() {
            var self = this;
            var i18n = domilocus_admin_vars.i18n;
            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth() + 1;
            var day = this.currentDate.getDate();
            
            $('#domilocus-calendar-container').html('<div class="calendar-loading">' + i18n.loading_calendar + '</div>');
            
            $.ajax({
                url: domilocus_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'domilocus_load_admin_calendar',
                    apartment_id: this.apartmentId,
                    year: year,
                    month: month,
                    day: day,
                    view: this.viewMode,
                    nonce: domilocus_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#domilocus-calendar-container').html(response.data.html);
                        self.calendarData = response.data.calendar_data || {};
                    } else {
                        self.showError(i18n.error_loading + ': ' + (response.data || i18n.unknown_error));
                    }
                },
                error: function() {
                    self.showError(i18n.connection_error_loading);
                }
            });
        },
        
        navigateCalendar: function(direction) {
            if (this.viewMode === 'month') {
                if (direction === 'prev') {
                    this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                } else if (direction === 'next') {
                    this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                }
            } else if (this.viewMode === 'week') {
                if (direction === 'prev') {
                    this.currentDate.setDate(this.currentDate.getDate() - 7);
                } else if (direction === 'next') {
                    this.currentDate.setDate(this.currentDate.getDate() + 7);
                }
            } else if (this.viewMode === 'day') {
                if (direction === 'prev') {
                    this.currentDate.setDate(this.currentDate.getDate() - 1);
                } else if (direction === 'next') {
                    this.currentDate.setDate(this.currentDate.getDate() + 1);
                }
            }
            this.loadCalendar();
        },
        
        openDayModal: function(date) {
            var i18n = domilocus_admin_vars.i18n;
            var dayData = this.calendarData[date] || {};
            
            $('#modal-date').val(date);
            $('#modal-title').text(i18n.manage_day + ' ' + this.formatDate(date));
            $('#modal-status').val(dayData.status || 'available');
            $('#modal-price').val(dayData.price || '');
            $('#modal-min-stay').val(dayData.min_stay || 1);
            $('#modal-notes').val(dayData.notes || '');

            // Prevent manual "booked" status edits (booked comes from bookings)
            if ((dayData.status || '') === 'booked') {
                $('#modal-status').prop('disabled', true);
            } else {
                $('#modal-status').prop('disabled', false);
            }
            
            $('#day-details-modal').fadeIn(200);
        },
        
        closeModal: function() {
            $('#day-details-modal').hide();
        },
        
        saveDayDetails: function() {
            var self = this;
            var i18n = domilocus_admin_vars.i18n;
            var statusDisabled = $('#modal-status').prop('disabled');
            var formData = {
                action: 'domilocus_save_day_details',
                apartment_id: this.apartmentId,
                date: $('#modal-date').val(),
                status: statusDisabled ? '' : $('#modal-status').val(),
                price: $('#modal-price').val(),
                min_stay: $('#modal-min-stay').val(),
                notes: $('#modal-notes').val(),
                nonce: domilocus_admin_vars.nonce
            };
            
            $('.day-details-save').prop('disabled', true).text(i18n.saving + '...');
            
            $.ajax({
                url: domilocus_admin_vars.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.loadCalendar(); // Reload calendar to reflect changes
                        self.showSuccess(i18n.data_saved);
                    } else {
                        self.showError(i18n.error_saving + ': ' + (response.data || i18n.unknown_error));
                    }
                },
                error: function() {
                    self.showError(i18n.connection_error_saving);
                },
                complete: function() {
                    $('.day-details-save').prop('disabled', false).text(i18n.save);
                }
            });
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            var options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            return date.toLocaleDateString('it-IT', options);
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showNotice: function(message, type) {
            var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
            
            // Add dismiss functionality
            notice.find('.notice-dismiss').on('click', function() {
                notice.remove();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#domilocus-calendar-container').length) {
            DomilocusAdminCalendar.init();
        }
    });

    // Bulk actions functionality
    var DomilocusBulkActions = {
        init: function() {
            this.createBulkControls();
            this.bindEvents();
        },
        
        createBulkControls: function() {
            var i18n = domilocus_admin_vars.i18n;
            var controlsHtml = `
                <div class="calendar-controls">
                    <h4>` + i18n.quick_actions + `</h4>
                    
                    <div class="control-group">
                        <label>` + i18n.set_prices_period + `</label>
                        <input type="date" id="bulk-start-date" placeholder="` + i18n.start_date + `">
                        <input type="date" id="bulk-end-date" placeholder="` + i18n.end_date + `">
                        <input type="number" id="bulk-price" placeholder="` + i18n.price_eur + `" step="0.01" min="0">
                        <button type="button" class="button" id="bulk-set-prices">` + i18n.apply_prices + `</button>
                    </div>
                    
                    <div class="control-group">
                        <label>` + i18n.block_unblock_period + `</label>
                        <input type="date" id="bulk-block-start" placeholder="` + i18n.start_date + `">
                        <input type="date" id="bulk-block-end" placeholder="` + i18n.end_date + `">
                        <select id="bulk-status">
                            <option value="available">` + i18n.available + `</option>
                            <option value="blocked">` + i18n.blocked + `</option>
                            <option value="maintenance">` + i18n.maintenance + `</option>
                        </select>
                        <button type="button" class="button" id="bulk-set-status">` + i18n.apply_status + `</button>
                    </div>
                </div>
            `;
            
            $('#domilocus-calendar-container').before(controlsHtml);
        },
        
        bindEvents: function() {
            var self = this;
            
            $('#bulk-set-prices').on('click', function() {
                self.bulkSetPrices();
            });
            
            $('#bulk-set-status').on('click', function() {
                self.bulkSetStatus();
            });
        },
        
        bulkSetPrices: function() {
            var i18n = domilocus_admin_vars.i18n;
            var startDate = $('#bulk-start-date').val();
            var endDate = $('#bulk-end-date').val();
            var price = $('#bulk-price').val();
            
            if (!startDate || !endDate || !price) {
                alert(i18n.fill_all_fields_prices);
                return;
            }
            
            this.performBulkAction('bulk_set_prices', {
                start_date: startDate,
                end_date: endDate,
                price: price
            });
        },
        
        bulkSetStatus: function() {
            var i18n = domilocus_admin_vars.i18n;
            var startDate = $('#bulk-block-start').val();
            var endDate = $('#bulk-block-end').val();
            var status = $('#bulk-status').val();
            
            if (!startDate || !endDate) {
                alert(i18n.fill_dates_status);
                return;
            }
            
            this.performBulkAction('bulk_set_status', {
                start_date: startDate,
                end_date: endDate,
                status: status
            });
        },
        
        performBulkAction: function(actionType, data) {
            var i18n = domilocus_admin_vars.i18n;
            var apartmentId = DomilocusAdminCalendar.apartmentId;
            
            $.ajax({
                url: domilocus_admin_vars.ajax_url,
                type: 'POST',
                data: $.extend({
                    action: 'domilocus_bulk_calendar_action',
                    apartment_id: apartmentId,
                    bulk_action: actionType,
                    nonce: domilocus_admin_vars.nonce
                }, data),
                success: function(response) {
                    if (response.success) {
                        DomilocusAdminCalendar.loadCalendar();
                        DomilocusAdminCalendar.showSuccess(i18n.action_completed);
                    } else {
                        DomilocusAdminCalendar.showError(i18n.error_saving + ': ' + (response.data || i18n.unknown_error));
                    }
                },
                error: function() {
                    DomilocusAdminCalendar.showError(i18n.connection_error);
                }
            });
        }
    };

    // Initialize bulk actions
    $(document).ready(function() {
        if ($('#domilocus-calendar-container').length) {
            DomilocusBulkActions.init();
        }
    });

})(jQuery);