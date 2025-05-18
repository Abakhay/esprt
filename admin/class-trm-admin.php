<?php
/**
 * The admin-specific functionality of the plugin.
 */
class TRM_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Register AJAX actions
        add_action('wp_ajax_trm_save_tournament', array($this, 'handle_create_tournament'));
        add_action('wp_ajax_trm_update_tournament', array($this, 'handle_update_tournament'));
        add_action('wp_ajax_trm_delete_tournament', array($this, 'handle_delete_tournament'));
        add_action('wp_ajax_trm_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_trm_delete_all_data', array($this, 'delete_all_data'));
        
        // Category AJAX actions
        add_action('wp_ajax_trm_save_category', array($this, 'handle_save_category'));
        add_action('wp_ajax_trm_get_category', array($this, 'handle_get_category'));
        add_action('wp_ajax_trm_delete_category', array($this, 'handle_delete_category'));
        add_action('wp_ajax_trm_approve_join_request', array($this, 'handle_approve_join_request'));
        add_action('wp_ajax_trm_reject_join_request', array($this, 'handle_reject_join_request'));
        add_action('admin_post_trm_export_tournaments', array($this, 'export_tournaments_csv'));
        add_action('admin_post_trm_import_tournaments', array($this, 'import_tournaments_csv'));
        add_action('admin_post_trm_export_teams', array($this, 'export_teams_csv'));
        add_action('admin_post_trm_import_teams', array($this, 'import_teams_csv'));
        add_action('admin_post_trm_delete_team', array($this, 'handle_delete_team'));
        add_action('admin_post_trm_export_single_registrations', array($this, 'export_single_registrations_csv'));
        add_action('admin_post_trm_edit_single_registration', array($this, 'edit_single_registration'));
        add_action('admin_post_trm_delete_single_registration', array($this, 'delete_single_registration'));
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            TRM_PLUGIN_URL . 'admin/css/trm-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            TRM_PLUGIN_URL . 'admin/js/trm-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        wp_localize_script($this->plugin_name, 'trm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('trm_admin_nonce')
        ));
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'Tournament Registration',
            'Tournament Registration',
            'manage_tournaments',
            'tournament-registration',
            array($this, 'display_admin_page'),
            'dashicons-groups',
            6
        );
        add_submenu_page(
            'tournament-registration',
            'All Tournaments',
            'All Tournaments',
            'manage_tournaments',
            'tournament-registration',
            array($this, 'display_admin_page')
        );
        add_submenu_page(
            'tournament-registration',
            'Add New Tournament',
            'Add New',
            'manage_options',
            'tournament-registration-add',
            array($this, 'display_add_tournament_page')
        );
        add_submenu_page(
            'tournament-registration',
            'Categories',
            'Categories',
            'manage_tournaments',
            'trm_categories',
            array($this, 'display_categories_page')
        );
        add_submenu_page(
            'tournament-registration',
            'Teams',
            'Teams',
            'manage_tournaments',
            'trm_teams',
            array($this, 'display_teams_page')
        );
        add_submenu_page(
            'tournament-registration',
            'Settings',
            'Settings',
            'manage_options',
            'trm_settings',
            array($this, 'display_settings_page')
        );
        // Hidden page for registrations
        add_submenu_page(
            null,
            'View Registrations',
            'View Registrations',
            'view_tournament_reports',
            'trm_view_registrations',
            array($this, 'display_registrations_page')
        );
        add_submenu_page(
            null,
            'View Team',
            'View Team',
            'manage_tournaments',
            'trm_view_team',
            array($this, 'display_view_team_page')
        );
    }

    public function display_admin_page() {
        include_once TRM_PLUGIN_DIR . 'admin/partials/trm-admin-display.php';
    }

    public function display_add_tournament_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include_once TRM_PLUGIN_DIR . 'admin/partials/trm-admin-add.php';
    }

    public function display_teams_page() {
        include_once TRM_PLUGIN_DIR . 'admin/partials/trm-admin-teams.php';
    }

    public function display_settings_page() {
        include_once TRM_PLUGIN_DIR . 'admin/partials/trm-admin-settings.php';
    }

    public function display_registrations_page() {
        if (!current_user_can('view_tournament_reports')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include TRM_PLUGIN_DIR . 'admin/partials/trm-admin-registrations.php';
    }

    public function display_categories_page() {
        if (!current_user_can('manage_tournaments')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include TRM_PLUGIN_DIR . 'admin/partials/trm-admin-categories.php';
    }

    public function display_view_team_page() {
        if (!current_user_can('manage_tournaments')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include TRM_PLUGIN_DIR . 'admin/partials/trm-admin-view-team.php';
    }

    // AJAX handlers for admin actions
    public function handle_create_tournament() {
        // Verify nonce
        if (!check_ajax_referer('trm_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_tournaments')) {
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Validate required fields
        $required_fields = array('title', 'registration_type');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error("Missing required field: {$field}");
                return;
            }
        }

        // Make start_date and end_date optional
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;

        // Prepare data array
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'category_id' => intval($_POST['category_id']),
            'max_teams' => intval($_POST['max_teams']),
            'max_players_per_team' => intval($_POST['max_players_per_team']),
            'registration_type' => sanitize_text_field($_POST['registration_type']),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => sanitize_text_field($_POST['status']),
            'registration_page_id' => intval($_POST['registration_page_id'])
        );

        global $wpdb;
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;

        if ($tournament_id > 0) {
            // Update existing tournament
            $result = $wpdb->update(
                $wpdb->prefix . 'trm_tournaments',
                $data,
                array('id' => $tournament_id),
                array(
                    '%s', // title
                    '%s', // description
                    '%d', // category_id
                    '%d', // max_teams
                    '%d', // max_players_per_team
                    '%s', // registration_type
                    '%s', // start_date
                    '%s', // end_date
                    '%s', // status
                    '%d'  // registration_page_id
                )
            );

            if ($result !== false) {
                wp_send_json_success('Tournament updated successfully');
            } else {
                wp_send_json_error('Failed to update tournament: ' . $wpdb->last_error);
            }
        } else {
            // Check for duplicate title only for new tournaments
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_tournaments WHERE title = %s AND status = 'active'",
                $data['title']
            ));

            if ($existing) {
                wp_send_json_error('A tournament with this title already exists. Please choose a different title or update the existing tournament.');
                return;
            }

            // Insert new tournament
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $wpdb->prefix . 'trm_tournaments',
                $data,
                array(
                    '%s', // title
                    '%s', // description
                    '%d', // category_id
                    '%d', // max_teams
                    '%d', // max_players_per_team
                    '%s', // registration_type
                    '%s', // start_date
                    '%s', // end_date
                    '%s', // status
                    '%d', // registration_page_id
                    '%s'  // created_at
                )
            );

            if ($result !== false) {
                wp_send_json_success('Tournament created successfully');
            } else {
                wp_send_json_error('Failed to create tournament: ' . $wpdb->last_error);
            }
        }
    }

    public function handle_update_tournament() {
        check_ajax_referer('trm_admin_nonce', 'nonce');

        if (!current_user_can('edit_tournaments')) {
            wp_send_json_error('Unauthorized');
        }

        $tournament_id = intval($_POST['tournament_id']);
        $data = array();
        $format = array();

        // Build update data based on provided fields
        $fields = array('title', 'description', 'max_teams', 'max_players_per_team', 
                       'registration_type', 'start_date', 'end_date', 'status', 'registration_page_id');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field($_POST[$field]);
                $format[] = '%s';
            }
        }

        if (empty($data)) {
            wp_send_json_error('No data to update');
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'trm_tournaments',
            $data,
            array('id' => $tournament_id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success('Tournament updated successfully');
        } else {
            wp_send_json_error('Failed to update tournament');
        }
    }

    public function handle_delete_tournament() {
        check_ajax_referer('trm_admin_nonce', 'nonce');

        if (!current_user_can('delete_tournaments')) {
            error_log('TRM DELETE: Unauthorized user attempted to delete tournament.');
            wp_send_json_error('Unauthorized');
        }

        $tournament_id = intval($_POST['tournament_id']);
        error_log('TRM DELETE: Attempting to delete tournament ID: ' . $tournament_id);

        global $wpdb;
        // Cascade delete: get all team IDs for this tournament
        $team_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}trm_teams WHERE tournament_id = %d",
            $tournament_id
        ));
        if ($team_ids) {
            $in_team_ids = implode(',', array_map('intval', $team_ids));
            // Delete all team members for these teams
            $wpdb->query("DELETE FROM {$wpdb->prefix}trm_team_members WHERE team_id IN ($in_team_ids)");
            // Delete all check-ins for these teams (if table exists)
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'trm_check_ins'))) {
                $wpdb->query("DELETE FROM {$wpdb->prefix}trm_check_ins WHERE team_id IN ($in_team_ids)");
            }
            // Delete all teams
            $wpdb->query("DELETE FROM {$wpdb->prefix}trm_teams WHERE id IN ($in_team_ids)");
        }
        // Delete the tournament
        $result = $wpdb->delete(
            $wpdb->prefix . 'trm_tournaments',
            array('id' => $tournament_id),
            array('%d')
        );
        if ($result) {
            error_log('TRM DELETE: Tournament ID ' . $tournament_id . ' deleted successfully.');
            wp_send_json_success('Tournament and all related data deleted successfully');
        } else {
            error_log('TRM DELETE: Failed to delete tournament ID ' . $tournament_id . '. SQL Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to delete tournament');
        }
    }

    // Category management functions
    public function handle_save_category() {
        check_ajax_referer('trm_admin_nonce', 'nonce');

        if (!current_user_can('manage_tournaments')) {
            wp_send_json_error('Unauthorized');
        }

        $category_id = intval($_POST['category_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $status = sanitize_text_field($_POST['status']);

        if (empty($name)) {
            wp_send_json_error('Category name is required');
        }

        global $wpdb;
        $data = array(
            'name' => $name,
            'description' => $description,
            'status' => $status
        );

        if ($category_id > 0) {
            $result = $wpdb->update(
                $wpdb->prefix . 'trm_categories',
                $data,
                array('id' => $category_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'trm_categories',
                $data,
                array('%s', '%s', '%s')
            );
        }

        if ($result !== false) {
            wp_send_json_success('Category saved successfully');
        } else {
            wp_send_json_error('Failed to save category');
        }
    }

    public function handle_get_category() {
        check_ajax_referer('trm_admin_nonce', 'nonce');

        if (!current_user_can('manage_tournaments')) {
            wp_send_json_error('Unauthorized');
        }

        $category_id = intval($_POST['category_id']);

        global $wpdb;
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}trm_categories WHERE id = %d",
            $category_id
        ));

        if ($category) {
            wp_send_json_success($category);
        } else {
            wp_send_json_error('Category not found');
        }
    }

    public function handle_delete_category() {
        check_ajax_referer('trm_admin_nonce', 'nonce');

        if (!current_user_can('manage_tournaments')) {
            wp_send_json_error('Unauthorized');
        }

        $category_id = intval($_POST['category_id']);

        // Check if category is in use
        global $wpdb;
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}trm_tournaments WHERE category_id = %d",
            $category_id
        ));

        if ($in_use > 0) {
            wp_send_json_error('Cannot delete category that is in use by tournaments');
            return;
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'trm_categories',
            array('id' => $category_id),
            array('%d')
        );

        if ($result) {
            wp_send_json_success('Category deleted successfully');
        } else {
            wp_send_json_error('Failed to delete category');
        }
    }

    public function handle_approve_join_request() {
        check_ajax_referer('trm_admin_nonce', 'nonce');
        if (!current_user_can('manage_tournaments')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
        if (!$member_id) {
            wp_send_json_error(['message' => 'Invalid member ID']);
        }
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'trm_team_members',
            ['status' => 'active'],
            ['id' => $member_id],
            ['%s'],
            ['%d']
        );
        if ($result !== false) {
            wp_send_json_success(['message' => 'Join request approved.']);
        } else {
            wp_send_json_error(['message' => 'Failed to approve join request.']);
        }
    }

    public function handle_reject_join_request() {
        check_ajax_referer('trm_admin_nonce', 'nonce');
        if (!current_user_can('manage_tournaments')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
        if (!$member_id) {
            wp_send_json_error(['message' => 'Invalid member ID']);
        }
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'trm_team_members',
            ['id' => $member_id],
            ['%d']
        );
        if ($result) {
            wp_send_json_success(['message' => 'Join request rejected.']);
        } else {
            wp_send_json_error(['message' => 'Failed to reject join request.']);
        }
    }

    public function export_tournaments_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        global $wpdb;
        $tournaments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}trm_tournaments ORDER BY created_at DESC", ARRAY_A);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tournaments-export-'.date('Ymd-His').'.csv"');
        $output = fopen('php://output', 'w');
        if (!empty($tournaments)) {
            fputcsv($output, array_keys($tournaments[0]));
            foreach ($tournaments as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    public function import_tournaments_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (!isset($_FILES['tournaments_csv']) || !is_uploaded_file($_FILES['tournaments_csv']['tmp_name'])) {
            wp_die('No file uploaded');
        }
        $file = fopen($_FILES['tournaments_csv']['tmp_name'], 'r');
        if (!$file) {
            wp_die('Failed to open file');
        }
        global $wpdb;
        $header = fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            // Remove id if present to avoid conflicts
            if (isset($data['id'])) unset($data['id']);
            // Insert or update by title and start_date (or add your own unique logic)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_tournaments WHERE title = %s AND start_date = %s",
                $data['title'], $data['start_date']
            ));
            if ($existing) {
                $wpdb->update($wpdb->prefix.'trm_tournaments', $data, array('id' => $existing));
            } else {
                $wpdb->insert($wpdb->prefix.'trm_tournaments', $data);
            }
        }
        fclose($file);
        wp_redirect(admin_url('admin.php?page=tournament-registration'));
        exit;
    }

    public function export_teams_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        global $wpdb;
        $tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
        $where = $tournament_id ? $wpdb->prepare('WHERE t.tournament_id = %d', $tournament_id) : '';
        $teams = $wpdb->get_results("SELECT t.* FROM {$wpdb->prefix}trm_teams t $where ORDER BY t.created_at DESC", ARRAY_A);
        // Find max number of members in any team
        $max_members = 0;
        $team_members = [];
        foreach ($teams as $row) {
            $members = $wpdb->get_results($wpdb->prepare(
                "SELECT u.display_name, u.user_email, m.gamer_tag, m.status FROM {$wpdb->prefix}trm_team_members m LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID WHERE m.team_id = %d",
                $row['id']
            ));
            $team_members[$row['id']] = $members;
            if (count($members) > $max_members) {
                $max_members = count($members);
            }
        }
        // Prepare CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=teams-export-'.date('Ymd-His').'.csv');
        $output = fopen('php://output', 'w');
        if (!empty($teams)) {
            // Add member_1, member_2, ... columns
            $header = array_keys($teams[0]);
            for ($i = 1; $i <= $max_members; $i++) {
                $header[] = 'member_' . $i;
            }
            fputcsv($output, $header);
            foreach ($teams as $row) {
                $members = $team_members[$row['id']];
                $member_cols = [];
                foreach ($members as $member) {
                    $member_cols[] = sprintf('%s (%s, %s, %s)', $member->display_name ?: '-', $member->user_email ?: '-', $member->gamer_tag ?: '-', $member->status ?: '-');
                }
                // Pad with blanks if fewer than max
                while (count($member_cols) < $max_members) {
                    $member_cols[] = '';
                }
                // Set member_count to actual count
                $row['member_count'] = count($members);
                $row = array_merge($row, $member_cols);
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    public function import_teams_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (!isset($_FILES['teams_csv']) || !is_uploaded_file($_FILES['teams_csv']['tmp_name'])) {
            wp_die('No file uploaded');
        }
        $file = fopen($_FILES['teams_csv']['tmp_name'], 'r');
        if (!$file) {
            wp_die('Failed to open file');
        }
        global $wpdb;
        $header = fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            if (isset($data['id'])) unset($data['id']);
            // Insert or update by team_name and tournament_id
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_teams WHERE team_name = %s AND tournament_id = %d",
                $data['team_name'], $data['tournament_id']
            ));
            if ($existing) {
                $wpdb->update($wpdb->prefix.'trm_teams', $data, array('id' => $existing));
            } else {
                $wpdb->insert($wpdb->prefix.'trm_teams', $data);
            }
        }
        fclose($file);
        wp_redirect(admin_url('admin.php?page=trm_teams'));
        exit;
    }

    public function handle_delete_team() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        if (!$team_id || !isset($_POST['trm_delete_team_nonce']) || !wp_verify_nonce($_POST['trm_delete_team_nonce'], 'trm_delete_team_' . $team_id)) {
            wp_die('Invalid request.');
        }
        global $wpdb;
        // Delete all team members
        $wpdb->delete($wpdb->prefix . 'trm_team_members', array('team_id' => $team_id), array('%d'));
        // Delete the team
        $wpdb->delete($wpdb->prefix . 'trm_teams', array('id' => $team_id), array('%d'));
        // Optionally, delete check-ins for this team
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'trm_check_ins'))) {
            $wpdb->delete($wpdb->prefix . 'trm_check_ins', array('team_id' => $team_id), array('%d'));
        }
        // Redirect back to the teams list for this tournament
        wp_redirect(admin_url('admin.php?page=trm_teams&tournament_id=' . $tournament_id . '&deleted=1'));
        exit;
    }

    public function export_single_registrations_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        if (!$tournament_id || !isset($_POST['trm_export_single_registrations_nonce']) || !wp_verify_nonce($_POST['trm_export_single_registrations_nonce'], 'trm_export_single_registrations_' . $tournament_id)) {
            wp_die('Invalid request.');
        }
        global $wpdb;
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email FROM {$wpdb->prefix}trm_registrations r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.tournament_id = %d ORDER BY r.created_at DESC",
            $tournament_id
        ), ARRAY_A);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="single-registrations-' . $tournament_id . '-' . date('Ymd-His') . '.csv"');
        $output = fopen('php://output', 'w');
        if (!empty($players)) {
            fputcsv($output, array('Player', 'User Email', 'Gamer Tag', 'Registered', 'Status'));
            foreach ($players as $row) {
                fputcsv($output, array(
                    $row['display_name'],
                    $row['user_email'],
                    $row['gamer_tag'],
                    $row['created_at'],
                    $row['status']
                ));
            }
        }
        fclose($output);
        exit;
    }

    public function edit_single_registration() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        $gamer_tag = isset($_POST['gamer_tag']) ? sanitize_text_field($_POST['gamer_tag']) : '';
        if (!$registration_id || !$gamer_tag || !isset($_POST['trm_edit_single_registration_nonce']) || !wp_verify_nonce($_POST['trm_edit_single_registration_nonce'], 'trm_edit_single_registration_' . $registration_id)) {
            wp_die('Invalid request.');
        }
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'trm_registrations',
            array('gamer_tag' => $gamer_tag),
            array('id' => $registration_id),
            array('%s'),
            array('%d')
        );
        wp_redirect(wp_get_referer());
        exit;
    }

    public function delete_single_registration() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        if (!$registration_id || !isset($_POST['trm_delete_single_registration_nonce']) || !wp_verify_nonce($_POST['trm_delete_single_registration_nonce'], 'trm_delete_single_registration_' . $registration_id)) {
            wp_die('Invalid request.');
        }
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'trm_registrations', array('id' => $registration_id), array('%d'));
        wp_redirect(wp_get_referer());
        exit;
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // Verify nonce
        if (!check_ajax_referer('trm_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Get existing settings
        $existing_settings = get_option('trm_settings', array());

        // Prepare new settings
        $new_settings = array(
            'email_notifications' => isset($_POST['email_notifications']) ? sanitize_text_field($_POST['email_notifications']) : 'yes',
            'default_registration_type' => isset($_POST['default_registration_type']) ? sanitize_text_field($_POST['default_registration_type']) : 'team',
            'max_teams_per_user' => isset($_POST['max_teams_per_user']) ? intval($_POST['max_teams_per_user']) : 1,
            'max_players_per_team' => isset($_POST['max_players_per_team']) ? intval($_POST['max_players_per_team']) : 5,
            'registration_page' => isset($_POST['registration_page']) ? intval($_POST['registration_page']) : '',
            'team_management_page' => isset($_POST['team_management_page']) ? intval($_POST['team_management_page']) : '',
            'terms_page_id' => isset($_POST['terms_page_id']) ? intval($_POST['terms_page_id']) : 0,
            'join_request_email' => isset($_POST['join_request_email']) ? wp_kses_post($_POST['join_request_email']) : '',
            'join_approval_email' => isset($_POST['join_approval_email']) ? wp_kses_post($_POST['join_approval_email']) : '',
            'join_rejection_email' => isset($_POST['join_rejection_email']) ? wp_kses_post($_POST['join_rejection_email']) : ''
        );

        // Merge with existing settings
        $updated_settings = array_merge($existing_settings, $new_settings);

        // Save the merged settings
        update_option('trm_settings', $updated_settings);

        // Save email templates separately
        if (isset($_POST['join_request_email'])) {
            update_option('trm_join_request_email', wp_kses_post($_POST['join_request_email']));
        }
        if (isset($_POST['join_approval_email'])) {
            update_option('trm_join_approval_email', wp_kses_post($_POST['join_approval_email']));
        }
        if (isset($_POST['join_rejection_email'])) {
            update_option('trm_join_rejection_email', wp_kses_post($_POST['join_rejection_email']));
        }

        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }

    public function delete_all_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        if (!check_ajax_referer('trm_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
        }
        global $wpdb;
        // Delete all plugin data
        $wpdb->query("DELETE FROM {$wpdb->prefix}trm_team_members");
        $wpdb->query("DELETE FROM {$wpdb->prefix}trm_teams");
        $wpdb->query("DELETE FROM {$wpdb->prefix}trm_registrations");
        $wpdb->query("DELETE FROM {$wpdb->prefix}trm_tournaments");
        // Optionally, delete check-ins if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'trm_check_ins'))) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}trm_check_ins");
        }
        wp_send_json_success();
    }
} 