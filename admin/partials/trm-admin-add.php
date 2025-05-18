<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$tournament_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tournament = $tournament_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE id = %d",
    $tournament_id
)) : null;

$title = $tournament ? $tournament->title : '';
$description = $tournament ? $tournament->description : '';
$max_teams = $tournament ? $tournament->max_teams : 0;
$max_players = $tournament ? $tournament->max_players_per_team : 0;
$registration_type = $tournament ? $tournament->registration_type : 'team';
$start_date = $tournament ? date('Y-m-d\TH:i', strtotime($tournament->start_date)) : '';
$end_date = $tournament ? date('Y-m-d\TH:i', strtotime($tournament->end_date)) : '';
$status = $tournament ? $tournament->status : 'active';
$category_id = $tournament ? $tournament->category_id : '';
$player_id_label = $tournament && isset($tournament->player_id_label) ? $tournament->player_id_label : 'Gamer Tag';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $tournament_id ? 'Edit Tournament' : 'Add New Tournament'; ?></h1>
    
    <form id="trm-tournament-form" method="post">
        <?php wp_nonce_field('trm_admin_nonce', 'trm_nonce'); ?>
        <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament_id); ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="title">Title</label>
                </th>
                <td>
                    <input type="text" id="title" name="title" class="regular-text" 
                           value="<?php echo esc_attr($title); ?>" required>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="category_id">Category</label>
                </th>
                <td>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php
                        $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}trm_categories WHERE status = 'active' ORDER BY name ASC");
                        foreach ($categories as $cat) {
                            printf(
                                '<option value="%d" %s>%s</option>',
                                $cat->id,
                                selected($category_id, $cat->id, false),
                                esc_html($cat->name)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="description">Description</label>
                </th>
                <td>
                    <textarea id="description" name="description" class="large-text" rows="5"><?php 
                        echo esc_textarea($description); 
                    ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="registration_type">Registration Type</label>
                </th>
                <td>
                    <select id="registration_type" name="registration_type">
                        <option value="team" <?php selected($registration_type, 'team'); ?>>Team</option>
                        <option value="single" <?php selected($registration_type, 'single'); ?>>Single Player</option>
                    </select>
                </td>
            </tr>
            
            <tr class="team-fields" style="display: <?php echo $registration_type === 'team' ? 'table-row' : 'none'; ?>;">
                <th scope="row">
                    <label for="max_teams">Maximum Teams</label>
                </th>
                <td>
                    <input type="number" id="max_teams" name="max_teams" class="small-text" 
                           value="<?php echo esc_attr($max_teams); ?>" min="0">
                    <p class="description">Leave empty for unlimited teams</p>
                </td>
            </tr>
            
            <tr class="team-fields" style="display: <?php echo $registration_type === 'team' ? 'table-row' : 'none'; ?>;">
                <th scope="row">
                    <label for="max_players">Maximum Players per Team</label>
                </th>
                <td>
                    <input type="number" id="max_players" name="max_players_per_team" class="small-text" 
                           value="<?php echo esc_attr($max_players); ?>" min="0">
                    <p class="description">Leave empty for unlimited players</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="start_date">Start Date</label>
                </th>
                <td>
                    <input type="datetime-local" id="start_date" name="start_date" 
                           value="<?php echo esc_attr($start_date); ?>" required>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="end_date">End Date</label>
                </th>
                <td>
                    <input type="datetime-local" id="end_date" name="end_date" 
                           value="<?php echo esc_attr($end_date); ?>" required>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="status">Status</label>
                </th>
                <td>
                    <select id="status" name="status">
                        <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                        <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="player_id_label">Player ID Field Label</label>
                </th>
                <td>
                    <input type="text" id="player_id_label" name="player_id_label" class="regular-text" value="<?php echo esc_attr($player_id_label); ?>" required>
                    <p class="description">This label will be shown on the registration form (e.g., 'Gamer Tag', 'Riot ID', 'Epic ID').</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="registration_page_id">Registration Page</label>
                </th>
                <td>
                    <select id="registration_page_id" name="registration_page_id" required>
                        <option value="">Select Page</option>
                        <?php
                        $pages = get_pages(['post_status' => 'publish']);
                        $selected_page = $tournament && isset($tournament->registration_page_id) ? $tournament->registration_page_id : '';
                        foreach ($pages as $page) {
                            printf(
                                '<option value="%d" %s>%s</option>',
                                $page->ID,
                                selected($selected_page, $page->ID, false),
                                esc_html($page->post_title)
                            );
                        }
                        ?>
                    </select>
                    <p class="description">Select the page where the registration form is placed.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary">Save Tournament</button>
            <a href="<?php echo admin_url('admin.php?page=tournament-registration'); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

<style>
.form-table th {
    width: 200px;
}

.form-table input[type="text"],
.form-table input[type="number"],
.form-table input[type="datetime-local"],
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

.submit .button {
    margin-right: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle team fields based on registration type
    $('#registration_type').on('change', function() {
        if ($(this).val() === 'team') {
            $('.team-fields').show();
        } else {
            $('.team-fields').hide();
        }
    });

    // Form submission
    $('#trm-tournament-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'trm_save_tournament',
            nonce: $('#trm_nonce').val(),
            tournament_id: $('input[name="tournament_id"]').val(),
            title: $('#title').val(),
            category_id: $('#category_id').val(),
            description: $('#description').val(),
            registration_type: $('#registration_type').val(),
            max_teams: $('#max_teams').val(),
            max_players_per_team: $('#max_players').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            status: $('#status').val(),
            registration_page_id: $('#registration_page_id').val()
        };

        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    window.location.href = '<?php echo admin_url('admin.php?page=tournament-registration'); ?>';
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Validate dates
    function validateDates() {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        var now = new Date();
        
        if (startDate <= now) {
            alert('Start date must be in the future');
            return false;
        }
        
        if (endDate <= startDate) {
            alert('End date must be after start date');
            return false;
        }
        
        return true;
    }

    $('#start_date, #end_date').on('change', function() {
        validateDates();
    });
});
</script> 