<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$team = $wpdb->get_row($wpdb->prepare("SELECT t.*, u.display_name as leader_name FROM {$wpdb->prefix}trm_teams t LEFT JOIN {$wpdb->users} u ON t.leader_id = u.ID WHERE t.id = %d", $team_id));
$members = $wpdb->get_results($wpdb->prepare("SELECT m.*, u.display_name FROM {$wpdb->prefix}trm_team_members m LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID WHERE m.team_id = %d", $team_id));
?>
<div class="wrap">
    <h1>Team Details</h1>
    <?php if ($team): ?>
        <table class="form-table">
            <tr><th>Team Name</th><td><?php echo esc_html($team->team_name); ?></td></tr>
            <tr><th>Leader</th><td><?php echo esc_html($team->leader_name); ?></td></tr>
            <tr><th>Status</th><td><?php echo esc_html(ucfirst($team->status)); ?></td></tr>
            <tr><th>Created</th><td><?php echo esc_html($team->created_at); ?></td></tr>
        </table>
        <h2>Members</h2>
        <ul>
            <?php foreach ($members as $member): ?>
                <li><?php echo esc_html($member->display_name ?: $member->user_id); ?> (<?php echo esc_html($member->status); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Team not found.</p>
    <?php endif; ?>
    <p><a href="<?php echo admin_url('admin.php?page=trm_teams'); ?>" class="button">Back to Teams</a></p>
</div> 