<?php
/**
 * Fired during plugin activation
 */
class TRM_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        // Create custom roles
        add_role(
            'tournament_manager',
            'Tournament Manager',
            array(
                'read' => true,
                'manage_tournaments' => true,
                'edit_tournaments' => true,
                'delete_tournaments' => true,
                'view_tournament_reports' => true
            )
        );

        add_role(
            'tournament_organizer',
            'Tournament Organizer',
            array(
                'read' => true,
                'manage_tournaments' => true,
                'edit_tournaments' => true,
                'view_tournament_reports' => true
            )
        );

        // Add capabilities to administrator
        $admin = get_role('administrator');
        $admin->add_cap('manage_tournaments');
        $admin->add_cap('edit_tournaments');
        $admin->add_cap('delete_tournaments');
        $admin->add_cap('view_tournament_reports');

        // Table for tournament categories
        $sql_categories = "CREATE TABLE {$prefix}trm_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Table for tournaments
        $sql_tournaments = "CREATE TABLE {$prefix}trm_tournaments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INT(11) DEFAULT 0,
            max_teams INT(11) DEFAULT 0,
            max_players_per_team INT(11) DEFAULT 0,
            registration_type VARCHAR(20) NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            registration_deadline DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id)
        ) $charset_collate;";

        // Table for teams (with tournament_id)
        $sql_teams = "CREATE TABLE {$prefix}trm_teams (
            id INT(11) NOT NULL AUTO_INCREMENT,
            team_name VARCHAR(255) NOT NULL,
            leader_id INT(11) NOT NULL,
            invitation_token VARCHAR(64) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            tournament_id INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY team_name (team_name)
        ) $charset_collate;";

        // Table for team members
        $sql_team_members = "CREATE TABLE {$prefix}trm_team_members (
            id INT(11) NOT NULL AUTO_INCREMENT,
            team_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            gamer_tag VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY team_user (team_id, user_id)
        ) $charset_collate;";

        // Table for single player registrations
        $sql_registrations = "CREATE TABLE {$prefix}trm_registrations (
            id INT(11) NOT NULL AUTO_INCREMENT,
            tournament_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            gamer_tag VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tournament_user (tournament_id, user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_categories);
        dbDelta($sql_tournaments);
        dbDelta($sql_teams);
        dbDelta($sql_team_members);
        dbDelta($sql_registrations);

        // Set plugin DB version for future upgrades
        add_option('trm_db_version', '1.2');
    }

    // Optional: Add an upgrade routine for future schema changes
    public static function maybe_upgrade() {
        $current_version = get_option('trm_db_version');
        if ($current_version !== '1.2') {
            self::activate();
            update_option('trm_db_version', '1.2');
        }
    }
} 