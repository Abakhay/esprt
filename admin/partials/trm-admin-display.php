<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$tournaments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}trm_tournaments ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Tournaments</h1>
    <a href="<?php echo admin_url('admin.php?page=tournament-registration-add'); ?>" class="page-title-action">Add New</a>
    <a href="<?php echo admin_url('admin-post.php?action=trm_export_tournaments'); ?>" class="page-title-action">Export CSV</a>
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-left:20px;">
        <input type="hidden" name="action" value="trm_import_tournaments">
        <?php wp_nonce_field('trm_import_tournaments', 'trm_import_nonce'); ?>
        <input type="file" name="tournaments_csv" accept=".csv" required>
        <button type="submit" class="button">Import CSV</button>
    </form>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Max Teams</th>
                <th>Max Players/Team</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Shortcode</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($tournaments): ?>
                <?php foreach ($tournaments as $tournament): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($tournament->title); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=tournament-registration-add&id=' . $tournament->id); ?>">Edit</a> |
                                </span>
                                <span class="trash">
                                    <a href="#" class="delete-tournament" data-id="<?php echo esc_attr($tournament->id); ?>">Delete</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html(ucfirst($tournament->registration_type)); ?></td>
                        <td><?php echo esc_html($tournament->max_teams); ?></td>
                        <td><?php echo esc_html($tournament->max_players_per_team); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->start_date))); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($tournament->end_date))); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($tournament->status); ?>">
                                <?php echo esc_html(ucfirst($tournament->status)); ?>
                            </span>
                        </td>
                        <td>
                            <code>[tournament_registration id="<?php echo esc_attr($tournament->id); ?>"]</code>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=trm_view_registrations&tournament_id=' . $tournament->id)); ?>" class="button">View Registrations</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No tournaments found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="registrations-modal" class="trm-modal" style="display: none;">
    <div class="trm-modal-content">
        <span class="trm-modal-close">&times;</span>
        <h2>Tournament Registrations</h2>
        <div class="trm-modal-body">
            <div class="trm-loading">Loading...</div>
            <div class="trm-registrations-content"></div>
        </div>
    </div>
</div>

<style>
.trm-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.trm-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    position: relative;
}

.trm-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.trm-modal-close:hover {
    color: black;
}

.trm-loading {
    text-align: center;
    padding: 20px;
}

.status-active {
    color: #46b450;
}

.status-inactive {
    color: #dc3232;
}

.status-pending {
    color: #ffb900;
}

.row-actions {
    visibility: hidden;
}

tr:hover .row-actions {
    visibility: visible;
}
</style> 