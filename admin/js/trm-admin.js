jQuery(document).ready(function($) {
    'use strict';

    // Tournament Form Handling
    $('#trm-tournament-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        if ($submitBtn.prop('disabled')) return; // Prevent double submit
        
        // Basic validation
        const title = $('#title').val().trim();
        if (!title) {
            showMessage('error', 'Please enter a tournament title.');
            return;
        }

        const categoryId = $('#category_id').val();
        if (!categoryId) {
            showMessage('error', 'Please select a category.');
            return;
        }

        const registrationPageId = $('#registration_page_id').val();
        if (!registrationPageId) {
            showMessage('error', 'Please select a registration page.');
            return;
        }

        $submitBtn.prop('disabled', true).text('Saving...');
        
        // Get the nonce from the form
        const nonce = $('#trm_nonce').val();
        if (!nonce) {
            showMessage('error', 'Security token is missing. Please refresh the page and try again.');
            $submitBtn.prop('disabled', false).text('Save Tournament');
            return;
        }

        // Clear any existing messages
        $('.trm-message').remove();

        const formData = {
            action: 'trm_save_tournament',
            nonce: nonce,
            tournament_id: $('input[name="tournament_id"]').val(),
            title: title,
            description: $('#description').val().trim(),
            category_id: categoryId,
            registration_type: $('#registration_type').val(),
            max_teams: $('#max_teams').val() || 0,
            max_players_per_team: $('#max_players').val() || 0,
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            status: $('#status').val(),
            registration_page_id: registrationPageId,
            player_id_label: $('#player_id_label').val().trim()
        };

        // Log the form data for debugging
        console.log('Submitting form data:', formData);

        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    showMessage('success', response.data);
                    // Only redirect if it's a new tournament
                    if (!formData.tournament_id) {
                        setTimeout(function() {
                            window.location.href = trm_admin.admin_url + 'admin.php?page=tournament-registration';
                        }, 1500);
                    } else {
                        $submitBtn.prop('disabled', false).text('Save Tournament');
                    }
                } else {
                    showMessage('error', response.data || 'An error occurred while saving the tournament.');
                    $submitBtn.prop('disabled', false).text('Save Tournament');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                showMessage('error', 'An error occurred: ' + error);
                $submitBtn.prop('disabled', false).text('Save Tournament');
            }
        });
    });

    // Delete Tournament
    $('.delete-tournament').off('click').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this tournament?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);

        const tournamentId = $btn.data('id');
        
        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_delete_tournament',
                nonce: trm_admin.nonce,
                tournament_id: tournamentId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    // View Registrations
    $('.view-registrations').on('click', function(e) {
        e.preventDefault();
        
        const tournamentId = $(this).data('id');
        const modal = $('#registrations-modal');
        
        modal.find('.trm-modal-content').html('<div class="trm-loading"></div>');
        modal.show();

        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_get_registrations',
                nonce: trm_admin.nonce,
                tournament_id: tournamentId
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.trm-modal-content').html(response.data.html);
                } else {
                    modal.find('.trm-modal-content').html('<div class="error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                modal.find('.trm-modal-content').html('<div class="error">An error occurred. Please try again.</div>');
            }
        });
    });

    // Close Modal
    $('.trm-modal-close').on('click', function() {
        $(this).closest('.trm-modal').hide();
    });

    // Close Modal on Outside Click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('trm-modal')) {
            $('.trm-modal').hide();
        }
    });

    // Team Status Change
    $('.team-status').on('change', function() {
        const teamId = $(this).data('id');
        const newStatus = $(this).val();
        
        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_update_team_status',
                nonce: trm_admin.nonce,
                team_id: teamId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data.message);
                    // Revert select to previous value
                    $(this).val($(this).data('previous-value'));
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.');
                // Revert select to previous value
                $(this).val($(this).data('previous-value'));
            }
        });
    });

    // Settings Form Handling
    const settingsForm = $('#trm-settings-form');
    if (settingsForm.length) {
        settingsForm.on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'trm_save_settings');
            formData.append('nonce', $('#trm_nonce').val());

            $.ajax({
                url: trm_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('.trm-submit-button').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                    } else {
                        showMessage('error', response.data.message || 'Failed to save settings');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                    showMessage('error', 'An error occurred while saving settings. Please try again.');
                },
                complete: function() {
                    $('.trm-submit-button').prop('disabled', false).text('Save Settings');
                }
            });
        });
    }

    // Helper function to show messages
    function showMessage(type, message) {
        const messageDiv = $('<div>')
            .addClass('trm-message')
            .addClass(type)
            .text(message);

        $('.wrap h1').after(messageDiv);

        setTimeout(function() {
            messageDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize tooltips
    if ($.fn.tooltip) {
        $('[data-tooltip]').tooltip();
    }

    // Initialize datepickers
    if ($.fn.datepicker) {
        $('.trm-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0
        });
    }
}); 