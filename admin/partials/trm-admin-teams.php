<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;

if (!$tournament_id) {
    // Show list of tournaments
    $tournaments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}trm_tournaments ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>Tournaments</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tournaments): ?>
                    <?php foreach ($tournaments as $tournament): ?>
                        <tr>
                            <td><?php echo esc_html($tournament->title); ?></td>
                            <td><?php echo esc_html(ucfirst($tournament->registration_type)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->start_date))); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->end_date))); ?></td>
                            <td><?php echo esc_html(ucfirst($tournament->status)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=trm_teams&tournament_id=' . $tournament->id); ?>" class="button">View Teams</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No tournaments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return;
}

// Show teams for the selected tournament
$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE id = %d", $tournament_id));
$teams = $wpdb->get_results($wpdb->prepare("SELECT t.*, u.display_name as leader_name, COUNT(m.id) as member_count FROM {$wpdb->prefix}trm_teams t LEFT JOIN {$wpdb->users} u ON t.leader_id = u.ID LEFT JOIN {$wpdb->prefix}trm_team_members m ON t.id = m.team_id WHERE t.tournament_id = %d GROUP BY t.id ORDER BY t.created_at DESC", $tournament_id));
?>
<div class="wrap">
    <h1>Teams for Tournament: <?php echo esc_html($tournament->title); ?></h1>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="notice notice-success is-dismissible"><p>Team deleted successfully.</p></div>
    <?php endif; ?>
    <a href="<?php echo admin_url('admin.php?page=trm_teams'); ?>" class="button">Back to Tournaments</a>
    <a href="<?php echo admin_url('admin-post.php?action=trm_export_teams&tournament_id=' . $tournament_id); ?>" class="page-title-action">Export CSV</a>
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php?tournament_id=' . $tournament_id); ?>" style="display:inline-block; margin-left:20px;">
        <input type="hidden" name="action" value="trm_import_teams">
        <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament_id); ?>">
        <?php wp_nonce_field('trm_import_teams', 'trm_import_teams_nonce'); ?>
        <input type="file" name="teams_csv" accept=".csv" required>
        <button type="submit" class="button">Import CSV</button>
    </form>
    <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
        <thead>
            <tr>
                <th>Team Name</th>
                <th>Leader</th>
                <th>Members</th>
                <th>Created</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($teams): ?>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><strong><?php echo esc_html($team->team_name); ?></strong></td>
                        <td><?php echo esc_html($team->leader_name); ?></td>
                        <td><?php echo esc_html($team->member_count); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($team->created_at))); ?></td>
                        <td><span class="status-<?php echo esc_attr($team->status); ?>"><?php echo esc_html(ucfirst($team->status)); ?></span></td>
                        <td>
                            <select class="team-status" data-id="<?php echo esc_attr($team->id); ?>">
                                <option value="active" <?php selected($team->status, 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($team->status, 'inactive'); ?>>Inactive</option>
                            </select>
                            <div class="row-actions">
                                <span class="view"><a href="<?php echo admin_url('admin.php?page=trm_view_team&team_id=' . $team->id); ?>">View Details</a></span>
                                <?php if (current_user_can('manage_options')): ?>
                                    <span class="delete">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this team and all its members?');">
                                            <?php wp_nonce_field('trm_delete_team_' . $team->id, 'trm_delete_team_nonce'); ?>
                                            <input type="hidden" name="action" value="trm_delete_team" />
                                            <input type="hidden" name="team_id" value="<?php echo esc_attr($team->id); ?>" />
                                            <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament_id); ?>" />
                                            <button class="button button-danger" style="margin-left:8px;">Delete</button>
                                        </form>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No teams found for this tournament.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-active {
    color: #46b450;
}

.status-inactive {
    color: #dc3232;
}

.row-actions {
    visibility: hidden;
}

tr:hover .row-actions {
    visibility: visible;
}

.team-status {
    width: 100px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update team status
    $('.team-status').on('change', function() {
        var teamId = $(this).data('id');
        var status = $(this).val();
        
        var formData = {
            action: 'trm_update_team_status',
            nonce: trm_admin.nonce,
            team_id: teamId,
            status: status
        };

        $.post(trm_admin.ajax_url, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
});
</script> 