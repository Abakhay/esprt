<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('trm_settings', array(
    'email_notifications' => 'yes',
    'default_registration_type' => 'team',
    'max_teams_per_user' => 1,
    'max_players_per_team' => 5,
    'registration_page' => '',
    'team_management_page' => '',
    'terms_page_id' => ''
));

$pages = get_pages();
?>

<div class="wrap">
    <h1>Tournament Registration Settings</h1>
    
    <form id="trm-settings-form" method="post">
        <?php wp_nonce_field('trm_admin_nonce', 'trm_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="email_notifications">Email Notifications</label>
                </th>
                <td>
                    <select id="email_notifications" name="email_notifications">
                        <option value="yes" <?php selected($settings['email_notifications'], 'yes'); ?>>Enabled</option>
                        <option value="no" <?php selected($settings['email_notifications'], 'no'); ?>>Disabled</option>
                    </select>
                    <p class="description">Enable or disable email notifications for team join requests and approvals</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="default_registration_type">Default Registration Type</label>
                </th>
                <td>
                    <select id="default_registration_type" name="default_registration_type">
                        <option value="team" <?php selected($settings['default_registration_type'], 'team'); ?>>Team</option>
                        <option value="single" <?php selected($settings['default_registration_type'], 'single'); ?>>Single Player</option>
                    </select>
                    <p class="description">Default registration type for new tournaments</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="max_teams_per_user">Maximum Teams per User</label>
                </th>
                <td>
                    <input type="number" id="max_teams_per_user" name="max_teams_per_user" 
                           value="<?php echo esc_attr($settings['max_teams_per_user']); ?>" 
                           class="small-text" min="1">
                    <p class="description">Maximum number of teams a user can create</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="max_players_per_team">Default Maximum Players per Team</label>
                </th>
                <td>
                    <input type="number" id="max_players_per_team" name="max_players_per_team" 
                           value="<?php echo esc_attr($settings['max_players_per_team']); ?>" 
                           class="small-text" min="1">
                    <p class="description">Default maximum number of players allowed per team</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="registration_page">Registration Page</label>
                </th>
                <td>
                    <select id="registration_page" name="registration_page">
                        <option value="">Select a page...</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" 
                                    <?php selected($settings['registration_page'], $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Page where the registration form will be displayed</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="team_management_page">Team Management Page</label>
                </th>
                <td>
                    <select id="team_management_page" name="team_management_page">
                        <option value="">Select a page...</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" 
                                    <?php selected($settings['team_management_page'], $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Page where team leaders can manage their teams</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="terms_page_id">Terms of Use Page</label>
                </th>
                <td>
                    <select id="terms_page_id" name="terms_page_id">
                        <option value="">Select a page...</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected(isset($settings['terms_page_id']) ? $settings['terms_page_id'] : '', $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Select the page to use for Terms of Use. This will be linked in all registration forms.</p>
                </td>
            </tr>
        </table>
        
        <h2>Email Templates</h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="join_request_email">Join Request Email</label>
                </th>
                <td>
                    <textarea id="join_request_email" name="join_request_email" class="large-text" rows="5"><?php 
                        echo esc_textarea(get_option('trm_join_request_email', 
                            "Hello,\n\n{user_name} has requested to join your team '{team_name}'.\n\n" .
                            "Please visit your team management page to approve or reject this request.\n\n" .
                            "Team Management URL: {team_management_url}"
                        )); 
                    ?></textarea>
                    <p class="description">Available variables: {user_name}, {team_name}, {team_management_url}</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="join_approval_email">Join Approval Email</label>
                </th>
                <td>
                    <textarea id="join_approval_email" name="join_approval_email" class="large-text" rows="5"><?php 
                        echo esc_textarea(get_option('trm_join_approval_email', 
                            "Hello,\n\nYour request to join team '{team_name}' has been approved.\n\n" .
                            "You can now access your team management page.\n\n" .
                            "Team Management URL: {team_management_url}"
                        )); 
                    ?></textarea>
                    <p class="description">Available variables: {team_name}, {team_management_url}</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="join_rejection_email">Join Rejection Email</label>
                </th>
                <td>
                    <textarea id="join_rejection_email" name="join_rejection_email" class="large-text" rows="5"><?php 
                        echo esc_textarea(get_option('trm_join_rejection_email', 
                            "Hello,\n\nYour request to join team '{team_name}' has been rejected."
                        )); 
                    ?></textarea>
                    <p class="description">Available variables: {team_name}</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary trm-submit-button">Save Settings</button>
        </p>
    </form>
</div>

<div class="settings-section danger-zone" style="margin-top:40px; padding:30px; background:#fff0f0; border:1px solid #dc3232; border-radius:8px;">
    <h2 style="color:#dc3232;">Danger Zone</h2>
    <p style="color:#dc3232; font-weight:bold;">This will permanently delete <u>all tournaments, teams, registrations, and related data</u> created by this plugin. This action cannot be undone. Please back up your database first!</p>
    <button id="trm-delete-all-data" class="button button-danger" style="background:#dc3232; color:#fff; border:none;">Delete All Data</button>
</div>

<script>
jQuery(document).ready(function($) {
    $('#trm-delete-all-data').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you absolutely sure? This will permanently delete ALL plugin data and cannot be undone!')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting...');
        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_delete_all_data',
                nonce: $('#trm_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('All plugin data deleted successfully!');
                    window.location.reload();
                } else {
                    alert(response.data || 'Failed to delete data.');
                    $btn.prop('disabled', false).text('Delete All Data');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).text('Delete All Data');
            }
        });
    });
});
</script>

<style>
.form-table th {
    width: 200px;
}

.form-table input[type="text"],
.form-table input[type="number"],
.form-table select,
.form-table textarea {
    width: 100%;
    max-width: 400px;
}

.form-table textarea {
    min-height: 100px;
}

.description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

.submit {
    margin-top: 20px;
}
</style> 