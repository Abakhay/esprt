<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check for invitation link
$join_token = isset($_GET['join_team']) ? sanitize_text_field($_GET['join_team']) : '';
$join_team = null;
if ($join_token) {
    global $wpdb;
    error_log('TRM INVITE DEBUG: Token: ' . $join_token . ' Tournament: ' . $tournament->id);
    $join_team = $wpdb->get_row($wpdb->prepare(
        "SELECT t.id, t.team_name FROM {$wpdb->prefix}trm_teams t WHERE t.invitation_token = %s AND t.tournament_id = %d",
        $join_token,
        $tournament->id
    ));
    if (!$join_team) {
        error_log('TRM INVITE DEBUG: No team found for token and tournament');
    } else {
        error_log('TRM INVITE DEBUG: Found team: ' . print_r($join_team, true));
    }
}

$current_time = current_time('mysql');
$end_date = isset($tournament->end_date) ? $tournament->end_date : '';
if ($end_date && strtotime($end_date) < strtotime($current_time)) {
    echo '<div class="trm-registration-closed"><strong>Registration is closed.</strong></div>';
    return;
}

// Get Terms of Use page link from plugin settings
$trm_settings = get_option('trm_settings', []);
$terms_page_id = isset($trm_settings['terms_page_id']) ? intval($trm_settings['terms_page_id']) : 0;
$terms_url = $terms_page_id ? get_permalink($terms_page_id) : '';
$terms_label = $terms_url ? '<a href="' . esc_url($terms_url) . '" target="_blank">Terms of Use</a>' : 'Terms of Use';
?>

<div class="trm-container">
    <?php if (!is_user_logged_in()): ?>
        <div class="trm-login-required">
            <p>You must be logged in to register for this tournament.</p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="trm-login-button">Login</a>
        </div>
    <?php else: ?>
        <?php if ($tournament->registration_type === 'team'): ?>
            <?php if ($join_team): ?>
                <div class="trm-join-direct" style="max-width:500px;margin:0 auto;">
                    <h3>Join Team: <span style="color:#0073aa;"><?php echo esc_html($join_team->team_name); ?></span></h3>
                    <form id="trm-join-team-form">
                        <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament->id); ?>" />
                        <input type="hidden" name="team_id" value="<?php echo esc_attr($join_team->id); ?>" />
                        <div class="trm-form-group">
                            <label for="trm-join-gamer-tag">Your Gamer Tag</label>
                            <input type="text" id="trm-join-gamer-tag" name="gamer_tag" required />
                        </div>
                        <div class="trm-form-group"><label><input type="checkbox" class="trm-terms-checkbox" required> I agree to the <?php echo $terms_label; ?></label></div>
                        <button type="submit" class="trm-submit-button">Join Team</button>
                        <div class="trm-message" style="display:none;"></div>
                    </form>
                </div>
            <?php else: ?>
                <div class="trm-team-choice-buttons" style="text-align:center; margin-bottom:30px;">
                    <button id="trm-open-create-team" class="trm-button" type="button">Create Team</button>
                    <button id="trm-open-join-team" class="trm-button" type="button">Join Team</button>
                </div>

                <!-- Modal for Create Team -->
                <div id="trm-create-team-modal" class="trm-modal" style="display:none;">
                    <div class="trm-modal-content">
                        <span class="trm-modal-close" data-modal="trm-create-team-modal">&times;</span>
                        <h3>Create a New Team</h3>
                        <form id="trm-create-team-form">
                            <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament->id); ?>" />
                            <div class="trm-form-group">
                                <label for="trm-team-name">Team Name</label>
                                <input type="text" id="trm-team-name" name="team_name" required />
                            </div>
                            <div class="trm-form-group">
                                <label for="trm-gamer-tag">Your Gamer Tag</label>
                                <input type="text" id="trm-gamer-tag" name="gamer_tag" required />
                            </div>
                            <div class="trm-form-group"><label><input type="checkbox" class="trm-terms-checkbox" required> I agree to the <?php echo $terms_label; ?></label></div>
                            <button type="submit" class="trm-submit-button">Create Team</button>
                            <div class="trm-message" style="display:none;"></div>
                        </form>
                    </div>
                </div>

                <!-- Modal for Join Team -->
                <div id="trm-join-team-modal" class="trm-modal" style="display:none;">
                    <div class="trm-modal-content">
                        <span class="trm-modal-close" data-modal="trm-join-team-modal">&times;</span>
                        <h3>Join Existing Team</h3>
                        <form id="trm-join-team-form">
                            <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament->id); ?>" />
                            <div class="trm-form-group">
                                <label for="trm-join-team-name">Team Name</label>
                                <input type="text" id="trm-join-team-name" name="team_name" required />
                            </div>
                            <div class="trm-form-group">
                                <label for="trm-join-gamer-tag">Your Gamer Tag</label>
                                <input type="text" id="trm-join-gamer-tag" name="gamer_tag" required />
                            </div>
                            <div class="trm-form-group"><label><input type="checkbox" class="trm-terms-checkbox" required> I agree to the <?php echo $terms_label; ?></label></div>
                            <button type="submit" class="trm-submit-button">Join Team</button>
                            <div class="trm-message" style="display:none;"></div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="trm-team-choice-buttons" style="text-align:center; margin-bottom:30px;">
                <button id="trm-open-single-player" class="trm-button" type="button">Register as Single Player</button>
            </div>
            <!-- Modal for Single Player Registration -->
            <div id="trm-single-player-modal" class="trm-modal" style="display:none;">
                <div class="trm-modal-content">
                    <span class="trm-modal-close" data-modal="trm-single-player-modal">&times;</span>
                    <h3>Single Player Registration</h3>
                    <form id="trm-single-player-form" class="trm-registration-form">
                        <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament->id); ?>" />
                        <div class="trm-form-group">
                            <label for="trm-gamer-tag">Your Gamer Tag</label>
                            <input type="text" id="trm-gamer-tag" name="gamer_tag" required />
                        </div>
                        <div class="trm-form-group"><label><input type="checkbox" class="trm-terms-checkbox" required> I agree to the <?php echo $terms_label; ?></label></div>
                        <button type="submit" class="trm-submit-button">Register</button>
                        <div class="trm-message" style="display:none;"></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.trm-registration-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.trm-tournament-description {
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.button-primary {
    background-color: #0073aa;
    color: white;
}

.button-primary:hover {
    background-color: #005177;
}

.trm-team-info {
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.trm-team-members ul {
    list-style: none;
    padding: 0;
}

.trm-team-members li {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.status.pending {
    color: #f0ad4e;
    font-size: 0.9em;
    margin-left: 10px;
}

.trm-pending-requests ul {
    list-style: none;
    padding: 0;
}

.trm-pending-requests li {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}

.trm-pending-requests button {
    padding: 5px 10px;
    font-size: 0.9em;
}

.approve-request {
    background-color: #5cb85c;
    color: white;
}

.reject-request {
    background-color: #d9534f;
    color: white;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open Single Player Modal
    $('#trm-open-single-player').on('click', function() {
        $('#trm-single-player-modal .trm-modal-content h3').text('<?php echo esc_js($tournament->title); ?>');
        $('#trm-single-player-modal').fadeIn(200);
    });
    // Open Create Team Modal
    $('#trm-open-create-team').on('click', function() {
        $('#trm-create-team-modal .trm-modal-content h3').text('<?php echo esc_js($tournament->title); ?>');
        $('#trm-create-team-modal').fadeIn(200);
    });
    // Open Join Team Modal
    $('#trm-open-join-team').on('click', function() {
        $('#trm-join-team-modal .trm-modal-content h3').text('<?php echo esc_js($tournament->title); ?>');
        $('#trm-join-team-modal').fadeIn(200);
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
    // Terms of Use validation for all forms
    $(document).on('submit', '#trm-single-player-form, #trm-create-team-form, #trm-join-team-form', function(e) {
        var $form = $(this);
        var $checkbox = $form.find('.trm-terms-checkbox');
        if (!$checkbox.is(':checked')) {
            e.preventDefault();
            var msg = $form.find('.trm-message');
            if (!msg.length) {
                msg = $('<div class="trm-message error"></div>').insertBefore($form.find('button[type=submit]'));
            }
            msg.text('You must agree to the Terms of Use to continue.').show();
            return false;
        }
    });
});
</script> 