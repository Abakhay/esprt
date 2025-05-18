jQuery(document).ready(function($) {
    'use strict';

    // Create Team Form
    const createTeamForm = $('#trm-create-team-form');
    if (createTeamForm.length) {
        createTeamForm.on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'trm_create_team');
            formData.append('nonce', trm_public.nonce);

            $.ajax({
                url: trm_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('.trm-submit-button').prop('disabled', true).text('Creating Team...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message, createTeamForm);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message, createTeamForm);
                    }
                },
                error: function() {
                    showMessage('error', 'An error occurred. Please try again.', createTeamForm);
                },
                complete: function() {
                    $('.trm-submit-button').prop('disabled', false).text('Create Team');
                }
            });
        });
    }

    // Join Team Form
    $(document).on('submit', '#trm-join-team-form', function(e) {
        e.preventDefault();
        console.log('Join Team form submitted!');

        const teamId = $(this).find('input[name="team_id"]').val();
        const teamName = $('#trm-join-team-name').val();
        const gamerTag = $('#trm-join-gamer-tag').val();

        if (teamId) {
            // If team_id is present (invitation link), use it directly
            const formData = new FormData();
            formData.append('action', 'trm_join_team');
            formData.append('nonce', trm_public.nonce);
            formData.append('team_id', teamId);
            formData.append('gamer_tag', gamerTag);

            $.ajax({
                url: trm_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('.trm-submit-button').prop('disabled', true).text('Sending Request...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message, '#trm-join-team-form');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message, '#trm-join-team-form');
                    }
                },
                error: function() {
                    showMessage('error', 'An error occurred. Please try again.', '#trm-join-team-form');
                },
                complete: function() {
                    $('.trm-submit-button').prop('disabled', false).text('Join Team');
                }
            });
        } else {
            // If no team_id, fall back to team name lookup (normal join)
        $.ajax({
            url: trm_public.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_get_team_id',
                nonce: trm_public.nonce,
                team_name: teamName
            },
            success: function(response) {
                if (response.success) {
                    // Now submit the join request with the team ID
                    const formData = new FormData();
                    formData.append('action', 'trm_join_team');
                    formData.append('nonce', trm_public.nonce);
                    formData.append('team_id', response.data.team_id);
                    formData.append('gamer_tag', gamerTag);

                    $.ajax({
                        url: trm_public.ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        beforeSend: function() {
                            $('.trm-submit-button').prop('disabled', true).text('Sending Request...');
                        },
                        success: function(response) {
                            if (response.success) {
                                showMessage('success', response.data.message, '#trm-join-team-form');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showMessage('error', response.data.message, '#trm-join-team-form');
                            }
                        },
                        error: function() {
                            showMessage('error', 'An error occurred. Please try again.', '#trm-join-team-form');
                        },
                        complete: function() {
                            $('.trm-submit-button').prop('disabled', false).text('Join Team');
                        }
                    });
                } else {
                    showMessage('error', response.data.message, '#trm-join-team-form');
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.', '#trm-join-team-form');
            }
        });
        }
    });

    // Approve Join Request
    $('.approve-join-request').on('click', function(e) {
        e.preventDefault();
        const requestId = $(this).data('id');
        const $pendingSection = $(this).closest('.trm-request-list, .trm-pending-requests');
        // Remove any previous error message
        $pendingSection.find('.trm-approve-error').remove();
        $.ajax({
            url: trm_public.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_approve_join_request',
                nonce: trm_public.nonce,
                request_id: requestId
            },
            beforeSend: function() {
                $(this).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message, '.approve-join-request');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error above Pending Requests section
                    let errorMsg = 'An error occurred.';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    } else if (response.data && typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else if (response.message) {
                        errorMsg = response.message;
                    } else if (typeof response === 'string') {
                        errorMsg = response;
                    }
                    const errorHtml = '<div class="trm-approve-error" style="margin-bottom:10px;padding:10px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;position:relative;">'
                        + '<span>' + errorMsg + '</span>'
                        + '<button type="button" style="position:absolute;top:4px;right:8px;background:none;border:none;font-size:16px;line-height:1;color:#721c24;cursor:pointer;" onclick="this.parentNode.remove();">&times;</button>'
                        + '</div>';
                    $pendingSection.prepend(errorHtml);
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.', '.approve-join-request');
            },
            complete: function() {
                $(this).prop('disabled', false);
            }
        });
    });

    // Reject Join Request
    $('.reject-join-request').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to reject this join request?')) {
            return;
        }

        const requestId = $(this).data('id');
        
        $.ajax({
            url: trm_public.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_reject_join_request',
                nonce: trm_public.nonce,
                request_id: requestId
            },
            beforeSend: function() {
                $(this).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message, '.reject-join-request');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data.message, '.reject-join-request');
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.', '.reject-join-request');
            },
            complete: function() {
                $(this).prop('disabled', false);
            }
        });
    });

    // Register for Tournament
    const registerForm = $('#register-tournament-form');
    if (registerForm.length) {
        registerForm.on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'trm_register_tournament');
            formData.append('nonce', trm_public.nonce);

            $.ajax({
                url: trm_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('.trm-submit-button').prop('disabled', true).text('Registering...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message, registerForm);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message, registerForm);
                    }
                },
                error: function() {
                    showMessage('error', 'An error occurred. Please try again.', registerForm);
                },
                complete: function() {
                    $('.trm-submit-button').prop('disabled', false).text('Register for Tournament');
                }
            });
        });
    }

    // Copy Invitation Link
    $('.copy-invitation-link').on('click', function(e) {
        e.preventDefault();
        
        const link = $(this).data('link');
        const tempInput = $('<input>');
        
        $('body').append(tempInput);
        tempInput.val(link).select();
        document.execCommand('copy');
        tempInput.remove();
        
        showMessage('success', 'Invitation link copied to clipboard!');
    });

    // Toggle Team Settings
    $('#registration-type').on('change', function() {
        const type = $(this).val();
        if (type === 'team') {
            $('.team-settings').show();
        } else {
            $('.team-settings').hide();
        }
    });

    // Helper function to show messages
    function showMessage(type, message, formSelector) {
        // Try to show in the .trm-message div inside the same modal as the form
        var $form = formSelector ? $(formSelector) : null;
        var $modalMessage = $form && $form.length ? $form.find('.trm-message') : null;
        if ($modalMessage && $modalMessage.length) {
            $modalMessage
                .removeClass('success error')
                .addClass(type)
                .text(message)
                .show();
            // Do not auto-hide modal messages
            return;
        }
        // Fallback: show as floating message
        const messageDiv = $('<div>')
            .addClass('trm-message')
            .addClass(type)
            .text(message);
        if ($('.trm-container').length) {
            $('.trm-container').prepend(messageDiv);
        } else {
            $('body').prepend(messageDiv);
        }
        setTimeout(function() {
            messageDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
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

    // Modal logic for team registration
    // Open Create Team Modal
    $('#trm-open-create-team').on('click', function() {
        if (typeof trm_public !== 'undefined' && !trm_public.is_logged_in) {
            window.location.href = trm_public.login_url;
            return;
        }
        $('#trm-create-team-modal').fadeIn(200);
    });
    // Open Join Team Modal
    $('#trm-open-join-team').on('click', function() {
        if (typeof trm_public !== 'undefined' && !trm_public.is_logged_in) {
            window.location.href = trm_public.login_url;
            return;
        }
        $('#trm-join-team-modal').fadeIn(200);
    });
    // Open Single Player Modal
    $('#trm-open-single-player').on('click', function() {
        if (typeof trm_public !== 'undefined' && !trm_public.is_logged_in) {
            window.location.href = trm_public.login_url;
            return;
        }
        $('#trm-single-player-modal').fadeIn(200);
    });
    // Close modal on close icon
    $('.trm-modal-close').on('click', function() {
        var modalId = $(this).data('modal');
        $('#' + modalId).fadeOut(200);
    });
    // Close modal when clicking outside modal content
    $('.trm-modal').on('click', function(e) {
        if ($(e.target).hasClass('trm-modal')) {
            $(this).fadeOut(200);
        }
    });

    // Single Player Registration
    $(document).on('submit', '#trm-single-player-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        if ($submitBtn.prop('disabled')) return;
        $submitBtn.prop('disabled', true).text('Registering...');

        const formData = {
            action: 'trm_register_single_player',
            nonce: trm_public.nonce,
            tournament_id: $('input[name="tournament_id"]').val(),
            gamer_tag: $('#trm-gamer-tag').val().trim()
        };

        $.ajax({
            url: trm_public.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message, $form);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data.message || 'Registration failed. Please try again.', $form);
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.', $form);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text('Register');
            }
        });
    });
}); 