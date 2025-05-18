<?php
/**
 * Fired during plugin deactivation
 */
class TRM_Deactivator {
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('trm_daily_cleanup');
        
        // Optionally, you can add code here to clean up plugin data
        // Note: We're not deleting tables by default to preserve data
        // If you want to delete tables, uncomment the following code:
        /*
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}trm_teams");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}trm_team_members");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}trm_tournaments");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}trm_registrations");
        delete_option('trm_db_version');
        */
    }
} 