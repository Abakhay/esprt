<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE id = %d", $tournament_id));
if (!$tournament) {
    echo '<div class="notice notice-error"><p>Tournament not found.</p></div>';
    return;
}
?>
<div class="wrap">
    <h1>Registrations for: <?php echo esc_html($tournament->title); ?></h1>
    <p>
        <strong>Type:</strong> <?php echo esc_html(ucfirst($tournament->registration_type)); ?> &nbsp;|
        <strong>Start:</strong> <?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->start_date))); ?> &nbsp;|
        <strong>End:</strong> <?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->end_date))); ?>
    </p>
    <a href="<?php echo esc_url(admin_url('admin.php?page=tournament-registration')); ?>" class="button">&larr; Back to Tournaments</a>
    <hr>
    <?php if ($tournament->registration_type === 'team'): ?>
        <h2>Teams</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Leader</th>
                    <th>Player Count</th>
                    <th>Members</th>
                    <th>Registered</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $teams = $wpdb->get_results($wpdb->prepare(
                "SELECT t.*, u.display_name as leader_name FROM {$wpdb->prefix}trm_teams t
                 LEFT JOIN {$wpdb->users} u ON t.leader_id = u.ID
                 WHERE t.tournament_id = %d ORDER BY t.created_at DESC",
                $tournament_id
            ));
            if ($teams) {
                foreach ($teams as $team) {
                    $members = $wpdb->get_results($wpdb->prepare(
                        "SELECT m.*, u.display_name FROM {$wpdb->prefix}trm_team_members m
                         LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                         WHERE m.team_id = %d",
                        $team->id
                    ));
                    $player_count = $members ? count($members) : 0;
                    echo '<tr>';
                    echo '<td>' . esc_html($team->team_name) . '</td>';
                    echo '<td>' . esc_html($team->leader_name) . '</td>';
                    echo '<td>' . esc_html($player_count) . '</td>';
                    echo '<td>';
                    if ($members) {
                        echo '<ul style="margin:0; padding-left:18px;">';
                        foreach ($members as $member) {
                            echo '<li>' . esc_html($member->display_name ?: $member->gamer_tag) .
                                ' <span style="color:#888;">(' . esc_html($member->gamer_tag) . ')</span>' .
                                ' <span class="trm-member-status ' . esc_attr($member->status) . '">' . esc_html(ucfirst($member->status)) . '</span></li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No members</em>';
                    }
                    echo '</td>';
                    echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($team->created_at))) . '</td>';
                    echo '<td>' . esc_html(ucfirst($team->status)) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6"><em>No teams registered yet.</em></td></tr>';
            }
            ?>
            </tbody>
        </table>
    <?php else: ?>
        <h2>Players</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-bottom:10px;">
            <?php wp_nonce_field('trm_export_single_registrations_' . $tournament_id, 'trm_export_single_registrations_nonce'); ?>
            <input type="hidden" name="action" value="trm_export_single_registrations" />
            <input type="hidden" name="tournament_id" value="<?php echo esc_attr($tournament_id); ?>" />
            <button class="button button-primary" type="submit">Export Single Player Entries (CSV)</button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Gamer Tag</th>
                    <th>Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $players = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, u.display_name FROM {$wpdb->prefix}trm_registrations r
                 LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.tournament_id = %d ORDER BY r.created_at DESC",
                $tournament_id
            ));
            if ($players) {
                foreach ($players as $player) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player->display_name) . '</td>';
                    echo '<td>';
                    echo '<span class="gamer-tag-display">' . esc_html($player->gamer_tag) . '</span>';
                    echo '<form class="edit-gamer-tag-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:none;margin:0;">';
                    wp_nonce_field('trm_edit_single_registration_' . $player->id, 'trm_edit_single_registration_nonce');
                    echo '<input type="hidden" name="action" value="trm_edit_single_registration" />';
                    echo '<input type="hidden" name="registration_id" value="' . esc_attr($player->id) . '" />';
                    echo '<input type="text" name="gamer_tag" value="' . esc_attr($player->gamer_tag) . '" style="width:120px;" />';
                    echo '<button type="submit" class="button">Save</button>';
                    echo '<button type="button" class="button cancel-edit">Cancel</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($player->created_at))) . '</td>';
                    echo '<td>' . esc_html(ucfirst($player->status)) . '</td>';
                    echo '<td>';
                    echo '<button class="button edit-gamer-tag">Edit</button> ';
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                    wp_nonce_field('trm_delete_single_registration_' . $player->id, 'trm_delete_single_registration_nonce');
                    echo '<input type="hidden" name="action" value="trm_delete_single_registration" />';
                    echo '<input type="hidden" name="registration_id" value="' . esc_attr($player->id) . '" />';
                    echo '<button type="submit" class="button button-danger" onclick="return confirm(\'Delete this registration?\');">Delete</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5"><em>No players registered yet.</em></td></tr>';
            }
            ?>
            </tbody>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('.edit-gamer-tag').on('click', function() {
                var $row = $(this).closest('tr');
                $row.find('.gamer-tag-display').hide();
                $row.find('.edit-gamer-tag-form').show();
            });
            $('.cancel-edit').on('click', function(e) {
                e.preventDefault();
                var $row = $(this).closest('tr');
                $row.find('.edit-gamer-tag-form').hide();
                $row.find('.gamer-tag-display').show();
            });
        });
        </script>
    <?php endif; ?>
</div> 