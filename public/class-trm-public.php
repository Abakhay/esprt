<?php
/**
 * The public-facing functionality of the plugin.
 */

namespace Tournament_Registration_Manager;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Tournament_Registration_Manager\TRM_Public')) {
    class TRM_Public {
        private $plugin_name;
        private $version;

        public function __construct($plugin_name, $version) {
            $this->plugin_name = $plugin_name;
            $this->version = $version;

            // Register shortcodes
            add_shortcode('tournament_registration', array($this, 'registration_form_shortcode'));
            add_shortcode('tournament_list', array($this, 'tournament_list_shortcode'));
            add_shortcode('team_leader_requests', array($this, 'team_leader_requests_shortcode'));
            add_shortcode('team_profile', array($this, 'team_profile_shortcode'));
            add_shortcode('qr_scanner', array($this, 'qr_scanner_shortcode'));

            // Register AJAX actions for team creation
            add_action('wp_ajax_trm_create_team', array($this, 'handle_create_team'));
            add_action('wp_ajax_nopriv_trm_create_team', array($this, 'handle_create_team'));
            // Register AJAX actions for joining team
            add_action('wp_ajax_trm_join_team', array($this, 'handle_join_team'));
            add_action('wp_ajax_nopriv_trm_join_team', array($this, 'handle_join_team'));
            // Register AJAX action for getting team ID by name
            add_action('wp_ajax_trm_get_team_id', array($this, 'handle_get_team_id'));
            add_action('wp_ajax_nopriv_trm_get_team_id', array($this, 'handle_get_team_id'));
            // Register AJAX actions for approving/rejecting join requests
            add_action('wp_ajax_trm_approve_join_request', array($this, 'handle_approve_join_request'));
            add_action('wp_ajax_trm_reject_join_request', array($this, 'handle_reject_join_request'));
            // Register AJAX action for scanning QR codes
            add_action('wp_ajax_trm_scan_qr', array($this, 'handle_qr_scan'));
            add_action('wp_ajax_nopriv_trm_scan_qr', array($this, 'handle_qr_scan'));
            // Register AJAX action for single player registration
            add_action('wp_ajax_trm_register_single_player', array($this, 'handle_single_player_registration'));
            add_action('wp_ajax_nopriv_trm_register_single_player', array($this, 'handle_single_player_registration'));

            // Admin: Add check-in entries management
            if (is_admin()) {
                add_action('admin_menu', array($this, 'add_checkin_entries_admin_page'));
                add_action('admin_post_trm_delete_checkin_entry', array($this, 'handle_delete_checkin_entry'));
            }
        }

        public function enqueue_styles() {
            wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'css/trm-public.css',
                array(),
                $this->version,
                'all'
            );

            wp_enqueue_style(
                $this->plugin_name . '-qr-report',
                plugin_dir_url(__FILE__) . 'css/trm-qr-report.css',
                array(),
                $this->version,
                'all'
            );
        }

        public function enqueue_scripts() {
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'js/trm-public.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_enqueue_script(
                $this->plugin_name . '-qr-report',
                plugin_dir_url(__FILE__) . 'js/trm-qr-report.js',
                array('jquery', 'jquery-ui-datepicker'),
                $this->version,
                true
            );

            // Add QR code library
            wp_enqueue_script(
                'qrcode-js',
                'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
                array(),
                '1.0.0',
                true
            );

            // Unify nonce usage: only localize once, use 'trm_public_nonce' everywhere
            wp_localize_script(
                $this->plugin_name,
                'trm_public',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('trm_public_nonce'),
                    'is_logged_in' => is_user_logged_in(),
                    'login_url' => site_url('/login/')
                )
            );
        }

        public function registration_form_shortcode($atts) {
            $atts = shortcode_atts(array(
                'id' => 0,
                'tournament_id' => 0,
            ), $atts, 'tournament_registration');

            // Use either id or tournament_id attribute
            $tournament_id = !empty($atts['id']) ? $atts['id'] : $atts['tournament_id'];

            if (!$tournament_id) {
                return '<p>Error: Tournament ID is required.</p>';
            }

            global $wpdb;
            $tournament = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE id = %d",
                $tournament_id
            ));

            if (!$tournament) {
                return '<p>Error: Tournament not found.</p>';
            }

            ob_start();
            include TRM_PLUGIN_DIR . 'public/partials/trm-registration-form.php';
            return ob_get_clean();
        }

        public function tournament_list_shortcode($atts) {
            $atts = shortcode_atts(array(
                'status' => 'active',
            ), $atts, 'tournament_list');

            global $wpdb;
            $tournaments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE status = %s ORDER BY start_date ASC",
                $atts['status']
            ));

            if (empty($tournaments)) {
                return '<p>No tournaments available at the moment.</p>';
            }

            ob_start();
            ?>
            <div class="trm-tournament-list">
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="trm-tournament-item">
                        <h3><?php echo esc_html($tournament->title); ?></h3>
                        <div class="trm-tournament-details">
                            <p><?php echo wp_kses_post($tournament->description); ?></p>
                            <ul>
                                <li><strong>Type:</strong> <?php echo esc_html(ucfirst($tournament->registration_type)); ?></li>
                                <li><strong>Start Date:</strong> <?php echo esc_html(date('F j, Y g:i a', strtotime($tournament->start_date))); ?></li>
                                <li><strong>End Date:</strong> <?php echo esc_html(date('F j, Y g:i a', strtotime($tournament->end_date))); ?></li>
                                <?php if ($tournament->registration_type === 'team'): ?>
                                    <li><strong>Max Teams:</strong> <?php echo esc_html($tournament->max_teams ?: 'Unlimited'); ?></li>
                                    <li><strong>Players per Team:</strong> <?php echo esc_html($tournament->max_players_per_team ?: 'Unlimited'); ?></li>
                                <?php endif; ?>
                            </ul>
                            <div class="trm-tournament-actions">
                                <a href="<?php echo esc_url(add_query_arg('tournament_id', $tournament->id, get_permalink())); ?>" class="trm-button">Register Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        public function team_leader_requests_shortcode($atts) {
            if (!is_user_logged_in()) {
                return '<p>You must be logged in to view join requests.</p>';
            }
            $user_id = get_current_user_id();
            global $wpdb;
            // Get all teams led by this user
            $teams = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_teams WHERE leader_id = %d",
                $user_id
            ));
            if (!$teams) {
                return '<p>You are not a leader of any team.</p>';
            }
            $output = '<div class="trm-leader-requests">';
            foreach ($teams as $team) {
                $output .= '<h3>Join Requests for Team: ' . esc_html($team->team_name) . '</h3>';
                $requests = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, u.user_email, u.display_name FROM {$wpdb->prefix}trm_team_members m
                     LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                     WHERE m.team_id = %d AND m.status = 'pending'",
                    $team->id
                ));
                if ($requests) {
                    $output .= '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                    $output .= '<th>Player</th><th>Email</th><th>Gamer Tag</th><th>Actions</th></tr></thead><tbody>';
                    foreach ($requests as $req) {
                        $output .= '<tr>';
                        $output .= '<td>' . esc_html($req->display_name ?: 'Unknown') . '</td>';
                        $output .= '<td>' . esc_html($req->user_email ?: '-') . '</td>';
                        $output .= '<td>' . esc_html($req->gamer_tag) . '</td>';
                        $output .= '<td>';
                        $output .= '<button class="trm-action-button approve approve-join-request" data-id="' . esc_attr($req->id) . '">Approve</button> ';
                        $output .= '<button class="trm-action-button reject reject-join-request" data-id="' . esc_attr($req->id) . '">Reject</button>';
                        $output .= '</td>';
                        $output .= '</tr>';
                    }
                    $output .= '</tbody></table>';
                } else {
                    $output .= '<p>No pending join requests for this team.</p>';
                }
            }
            $output .= '</div>';
            return $output;
        }

        public function team_profile_shortcode($atts) {
            if (!is_user_logged_in()) {
                return '<p>You must be logged in to view your teams.</p>';
            }
            global $wpdb;
            $user_id = get_current_user_id();

            // Get all user's team memberships
            $memberships = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*, t.team_name, t.tournament_id, tr.title as tournament_title, m.created_at as joined_at
                     FROM {$wpdb->prefix}trm_team_members m
                     JOIN {$wpdb->prefix}trm_teams t ON m.team_id = t.id
                     JOIN {$wpdb->prefix}trm_tournaments tr ON t.tournament_id = tr.id
                     WHERE m.user_id = %d
                     ORDER BY m.created_at DESC",
                    $user_id
                )
            );

            // Get all teams where user is leader
            $led_teams = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.*, tr.title as tournament_title, tr.registration_page_id,
                     (SELECT COUNT(*) FROM {$wpdb->prefix}trm_team_members WHERE team_id = t.id AND status = 'active') as active_members,
                     (SELECT COUNT(*) FROM {$wpdb->prefix}trm_team_members WHERE team_id = t.id AND status = 'pending') as pending_requests
                     FROM {$wpdb->prefix}trm_teams t
                     JOIN {$wpdb->prefix}trm_tournaments tr ON t.tournament_id = tr.id
                     WHERE t.leader_id = %d
                     ORDER BY t.created_at DESC",
                    $user_id
                )
            );

            // Get all user's single player registrations
            $single_entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, t.title as tournament_title, t.start_date, t.end_date, t.status as tournament_status, t.registration_page_id
                     FROM {$wpdb->prefix}trm_registrations r
                     JOIN {$wpdb->prefix}trm_tournaments t ON r.tournament_id = t.id
                     WHERE r.user_id = %d
                     ORDER BY r.created_at DESC",
                    $user_id
                )
            );

            ob_start();
            ?>
            <div class="trm-profile-container">
                <h2>My Activity Dashboard</h2>

                <div class="trm-tabs">
                    <div class="trm-tab-buttons">
                        <button class="trm-tab-button active" data-tab="teams-lead">Teams I Lead</button>
                        <button class="trm-tab-button" data-tab="teams-member">Teams I'm Member Of</button>
                        <button class="trm-tab-button" data-tab="single-entries">Single Player Entries</button>
                    </div>

                    <div class="trm-tab-content active" id="teams-lead">
                        <?php if ($led_teams): ?>
                            <div class="trm-teams-grid">
                                <?php foreach ($led_teams as $team): ?>
                                    <div class="trm-team-card">
                                        <div class="trm-team-header">
                                            <h4><?php echo esc_html($team->team_name); ?></h4>
                                            <span class="trm-tournament-badge"><?php echo esc_html($team->tournament_title); ?></span>
                                        </div>
                                        <?php
                                        // Generate QR code data
                                        $qr_data = array(
                                            'type' => 'tournament',
                                            'team_id' => $team->id,
                                            'tournament_id' => $team->tournament_id,
                                            'timestamp' => time()
                                        );
                                        $qr_data_json = wp_json_encode($qr_data);
                                        $qr_data_encoded = base64_encode($qr_data_json);
                                        ?>
                                        <div class="trm-qr-section">
                                            <div class="trm-qr-code" data-qr="<?php echo esc_attr($qr_data_encoded); ?>">
                                                <!-- QR code will be generated here -->
                                            </div>
                                            <div class="trm-qr-info">
                                                <p>Scan this QR code to check in for the tournament</p>
                                                <button class="trm-action-button download-qr" data-team="<?php echo esc_attr($team->id); ?>">
                                                    <i class="dashicons dashicons-download"></i> Download QR
                                                </button>
                                            </div>
                                        </div>
                                        <?php
                                        // Generate invitation link for this team using the correct registration page
                                        $registration_page_url = !empty($team->registration_page_id) ? get_permalink($team->registration_page_id) : get_permalink();
                                        $invitation_link = add_query_arg(array(
                                            'join_team' => $team->invitation_token,
                                            'tournament_id' => $team->tournament_id
                                        ), $registration_page_url);
                                        ?>
                                        <div class="trm-invitation-link" style="margin-bottom: 10px;">
                                            <input type="text" value="<?php echo esc_url($invitation_link); ?>" readonly style="width:70%;font-size:13px;padding:3px 6px;" onclick="this.select();">
                                            <button class="trm-action-button copy-invitation-link" data-link="<?php echo esc_url($invitation_link); ?>" style="font-size:13px;padding:4px 10px;">Copy Link</button>
                                        </div>
                                        <div class="trm-team-stats">
                                            <div class="trm-stat">
                                                <span class="trm-stat-label">Active Members:</span>
                                                <span class="trm-stat-value"><?php echo esc_html($team->active_members); ?></span>
                                            </div>
                                            <div class="trm-stat">
                                                <span class="trm-stat-label">Pending Requests:</span>
                                                <span class="trm-stat-value"><?php echo esc_html($team->pending_requests); ?></span>
                                            </div>
                                        </div>
                                        <?php
                                        // Query for active members
                                        $active_members = $wpdb->get_results($wpdb->prepare(
                                            "SELECT m.*, u.user_email, u.display_name 
                                             FROM {$wpdb->prefix}trm_team_members m
                                             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                                             WHERE m.team_id = %d AND m.status = 'active'",
                                            $team->id
                                        ));
                                        ?>
                                        <?php if ($active_members): ?>
                                            <div class="trm-active-members-list" style="margin-bottom: 10px;">
                                                <strong>Active Members List:</strong>
                                                <ul style="margin: 8px 0 0 0; padding-left: 18px;">
                                                    <?php foreach ($active_members as $member): ?>
                                                        <li>
                                                            <?php echo esc_html($member->display_name ?: 'Unknown'); ?>
                                                            (<?php echo esc_html($member->user_email ?: '-'); ?>)
                                                            <?php if (!empty($member->gamer_tag)): ?>
                                                                - <?php echo esc_html($member->gamer_tag); ?>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        // Query for pending requests
                                        $requests = $wpdb->get_results($wpdb->prepare(
                                            "SELECT m.*, u.user_email, u.display_name 
                                             FROM {$wpdb->prefix}trm_team_members m
                                             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                                             WHERE m.team_id = %d AND m.status = 'pending'",
                                            $team->id
                                        ));
                                        ?>
                                        <?php if ($requests): ?>
                                            <div class="trm-request-list modern-pending-requests" style="margin-top: 24px; background: #181c23; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.10); padding: 24px 20px 18px 20px;">
                                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
                                                    <span style="background:#23272f;color:#facc15;font-weight:600;padding:3px 12px;border-radius:8px;font-size:1.1em;"> <?php echo count($requests); ?> </span>
                                                    <span style="color:#fff;font-size:1.1em;font-weight:600;">Pending Requests</span>
                                                </div>
                                                <?php foreach ($requests as $req): ?>
                                                    <div class="trm-request-card" style="background:#23272f;color:#fff;border-radius:10px;padding:18px 18px 12px 18px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,0.10);border:1px solid #23272a;">
                                                        <div style="font-weight:700;font-size:1.08em; margin-bottom:2px;"> <?php echo esc_html($req->display_name ?: 'Unknown'); ?> </div>
                                                        <div style="color:#bdbdbd;font-size:0.98em; margin-bottom:2px;"> <?php echo esc_html($req->user_email ?: '-'); ?> </div>
                                                        <div style="color:#8a8a8a;font-size:0.95em;margin-bottom:14px;"> <?php echo esc_html($req->gamer_tag); ?> </div>
                                                        <div class="trm-request-actions" style="display:flex;gap:12px;">
                                                            <button class="trm-action-button approve approve-join-request" data-id="<?php echo esc_attr($req->id); ?>" style="background:#22c55e;color:#fff;font-weight:600;font-size:1.08em;display:flex;align-items:center;gap:8px;padding:12px 0;border-radius:8px;border:none;cursor:pointer;flex:1;justify-content:center;"><span style="font-size:1.2em;">&#10003;</span> Approve</button>
                                                            <button class="trm-action-button reject reject-join-request" data-id="<?php echo esc_attr($req->id); ?>" style="background:#ef4444;color:#fff;font-weight:600;font-size:1.08em;display:flex;align-items:center;gap:8px;padding:12px 0;border-radius:8px;border:none;cursor:pointer;flex:1;justify-content:center;"><span style="font-size:1.2em;">&#10005;</span> Reject</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="trm-no-teams">You are not leading any teams.</p>
                        <?php endif; ?>
                    </div>

                    <div class="trm-tab-content" id="teams-member">
                        <?php if ($memberships): ?>
                            <div class="trm-teams-grid">
                                <?php foreach ($memberships as $m): ?>
                                    <div class="trm-team-card">
                                        <div class="trm-team-header">
                                            <h4><?php echo esc_html($m->team_name); ?></h4>
                                            <span class="trm-tournament-badge"><?php echo esc_html($m->tournament_title); ?></span>
                                        </div>
                                        <?php
                                        // Generate QR code data for member
                                        $current_user = wp_get_current_user();
                                        $qr_data = array(
                                            'type' => 'member',
                                            'member_id' => $current_user->ID,
                                            'team_id' => $m->team_id,
                                            'tournament_id' => $m->tournament_id,
                                            'first_name' => $current_user->user_firstname,
                                            'last_name' => $current_user->user_lastname,
                                            'timestamp' => time()
                                        );
                                        $qr_data_json = wp_json_encode($qr_data);
                                        $qr_data_encoded = base64_encode($qr_data_json);
                                        ?>
                                        <div class="trm-qr-section">
                                            <div class="trm-qr-code" data-qr="<?php echo esc_attr($qr_data_encoded); ?>">
                                                <!-- QR code will be generated here -->
                                            </div>
                                            <div class="trm-qr-info">
                                                <p>Scan this QR code to check in as a player</p>
                                                <button class="trm-action-button download-qr" data-team="<?php echo esc_attr($m->team_id); ?>">
                                                    <i class="dashicons dashicons-download"></i> Download QR
                                             </button>
                                            </div>
                                        </div>
                                        <div class="trm-team-details">
                                            <div class="trm-status-indicator">
                                                <?php
                                                $status = strtolower($m->status);
                                                $status_class = $status === 'active' ? 'status-active' : ($status === 'pending' ? 'status-pending' : 'status-rejected');
                                                $status_label = ucfirst($status);
                                                ?>
                                                <span class="trm-status-badge <?php echo $status_class; ?>">
                                                    <span class="status-dot"></span>
                                                    <?php echo $status_label; ?>
                                                </span>
                                            </div>
                                            <div class="trm-join-date">
                                                <i class="dashicons dashicons-calendar-alt"></i>
                                                Joined: <?php echo esc_html(date('M j, Y', strtotime($m->joined_at))); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="trm-no-teams">You are not a member of any team.</p>
                        <?php endif; ?>
                    </div>

                    <div class="trm-tab-content" id="single-entries" style="display:none;">
                        <?php if ($single_entries): ?>
                            <div class="trm-teams-grid">
                                <?php foreach ($single_entries as $entry): ?>
                                    <div class="trm-team-card">
                                        <div class="trm-team-header">
                                            <h4><?php echo esc_html($entry->tournament_title); ?></h4>
                                            <span class="trm-tournament-badge">Single Player</span>
                                        </div>
                                        <div class="trm-single-flex-row">
                                            <div class="trm-single-info">
                                                <div><span class="trm-label">Gamer Tag:</span> <span class="trm-value"><?php echo esc_html($entry->gamer_tag); ?></span></div>
                                                <?php if ($user) {
                                                    $full_name = trim($user->first_name . ' ' . $user->last_name);
                                                    if ($full_name) {
                                                        echo '<div><span class="trm-label">Player:</span> <span class="trm-value">' . esc_html($full_name) . '</span></div>';
                                                    }
                                                } ?>
                                            </div>
                                            <div class="trm-single-qr">
                                                <div class="trm-qr-section">
                                                    <?php
                                                    $qr_data = array(
                                                        'type' => 'single',
                                                        'user_id' => $entry->user_id,
                                                        'tournament_id' => $entry->tournament_id,
                                                        'gamer_tag' => $entry->gamer_tag,
                                                        'first_name' => $user ? $user->first_name : '',
                                                        'last_name' => $user ? $user->last_name : '',
                                                        'timestamp' => time()
                                                    );
                                                    $qr_data_json = wp_json_encode($qr_data);
                                                    $qr_data_encoded = base64_encode($qr_data_json);
                                                    ?>
                                                    <div class="trm-qr-code" data-qr="<?php echo esc_attr($qr_data_encoded); ?>"></div>
                                                    <div class="trm-qr-info">Scan this QR code to check in as a single player</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="trm-no-teams">You have no single player entries.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <style>
                .trm-profile-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .trm-tabs {
                    margin-top: 20px;
                }

                .trm-tab-buttons {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #f0f0f0;
                    padding-bottom: 10px;
                }

                .trm-tab-button {
                    padding: 10px 20px;
                    border: none;
                    background: none;
                    cursor: pointer;
                    font-size: 1.1em;
                    color: #666;
                    position: relative;
                    transition: all 0.3s ease;
                }

                .trm-tab-button:hover {
                    color: #333;
                }

                .trm-tab-button.active {
                    color: #007bff;
                    font-weight: bold;
                }

                .trm-tab-button.active::after {
                    content: '';
                    position: absolute;
                    bottom: -12px;
                    left: 0;
                    width: 100%;
                    height: 2px;
                    background: #007bff;
                }

                .trm-tab-content {
                    display: none;
                }

                .trm-tab-content.active {
                    display: block;
                }

                .trm-teams-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                    gap: 20px;
                }

                .trm-team-card {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    padding: 20px;
                    transition: transform 0.2s;
                }

                .trm-team-card:hover {
                    transform: translateY(-2px);
                }

                .trm-team-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 15px;
                }

                .trm-team-header h4 {
                    margin: 0;
                    color: #333;
                    font-size: 1.2em;
                }

                .trm-tournament-badge {
                    background: #f0f0f0;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.9em;
                    color: #666;
                }

                .trm-team-stats {
                    display: flex;
                    gap: 15px;
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }

                .trm-stat {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .trm-stat-label {
                    font-size: 0.9em;
                    color: #666;
                }

                .trm-stat-value {
                    font-weight: bold;
                    color: #333;
                }

                .trm-team-details {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }

                .trm-status-badge {
                    display: inline-flex;
                    align-items: center;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.9em;
                }

                .status-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    margin-right: 6px;
                }

                .status-active {
                    background: #e6f4ea;
                    color: #1e7e34;
                }

                .status-active .status-dot {
                    background: #1e7e34;
                }

                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }

                .status-pending .status-dot {
                    background: #856404;
                }

                .status-rejected {
                    background: #f8d7da;
                    color: #721c24;
                }

                .status-rejected .status-dot {
                    background: #721c24;
                }

                .trm-join-date {
                    color: #666;
                    font-size: 0.9em;
                }

                .trm-request-list {
                    margin-top: 15px;
                }

                .trm-request-list h5 {
                    margin: 0 0 10px 0;
                    color: #333;
                    font-size: 1em;
                }

                .trm-request-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    margin-bottom: 8px;
                }

                .trm-request-info {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .trm-player-name {
                    font-weight: bold;
                    color: #333;
                }

                .trm-player-email {
                    color: #666;
                    font-size: 0.9em;
                }

                .trm-player-tag {
                    color: #666;
                    font-size: 0.9em;
                }

                .trm-request-actions {
                    display: flex;
                    gap: 8px;
                }

                .trm-action-button {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 6px 12px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 0.9em;
                    transition: background-color 0.2s;
                }

                .trm-action-button.approve {
                    background: #28a745;
                    color: white;
                }

                .trm-action-button.reject {
                    background: #dc3545;
                    color: white;
                }

                .trm-action-button:hover {
                    opacity: 0.9;
                }

                .trm-no-teams {
                    text-align: center;
                    color: #666;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }

                @media (max-width: 768px) {
                    .trm-teams-grid {
                        grid-template-columns: 1fr;
                    }

                    .trm-tab-buttons {
                        flex-direction: column;
                        gap: 5px;
                    }

                    .trm-tab-button {
                        width: 100%;
                        text-align: left;
                    }

                    .trm-tab-button.active::after {
                        display: none;
                    }
                }

                .trm-qr-section {
                    margin: 15px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    text-align: center;
                }

                .trm-qr-code {
                    width: 200px;
                    height: 200px;
                    margin: 0 auto 15px;
                    background: #fff;
                    padding: 10px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .trm-qr-info {
                    color: #666;
                    font-size: 0.9em;
                }

                .trm-qr-info p {
                    margin-bottom: 10px;
                }

                .download-qr {
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 0.9em;
                }

                .download-qr:hover {
                    background: #0056b3;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                function renderQRCodes(context) {
                    $(context).find('.trm-qr-code').each(function() {
                        if (!$(this).children('canvas').length) {
                            const qrData = $(this).data('qr');
                            if (typeof QRCode !== 'undefined' && qrData) {
                                new QRCode(this, {
                                    text: qrData,
                                    width: 150,
                                    height: 150,
                                    colorDark: "#000000",
                                    colorLight: "#ffffff",
                                    correctLevel: QRCode.CorrectLevel.H
                                });
                            }
                        }
                    });
                }
                // On tab switch
                $('.trm-tab-button').on('click', function() {
                    const tabId = $(this).data('tab');
                    $('.trm-tab-button').removeClass('active');
                    $(this).addClass('active');
                    $('.trm-tab-content').removeClass('active').hide();
                    $('#' + tabId).addClass('active').show();
                    renderQRCodes('#' + tabId);
                });
                // On page load
                renderQRCodes('.trm-tab-content.active');
            });
            </script>
            <style>
            .trm-single-flex-row {
                display: flex;
                gap: 32px;
                align-items: flex-start;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .trm-single-info {
                flex: 2 1 260px;
                min-width: 220px;
                padding: 10px 0 10px 0;
            }
            .trm-single-info .trm-label {
                font-weight: 700;
                color: #6b7280;
                min-width: 110px;
                display: inline-block;
            }
            .trm-single-info .trm-value {
                color: #222;
                font-weight: 500;
            }
            .trm-single-qr {
                flex: 1 1 180px;
                min-width: 180px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
            }
            .trm-single-qr .trm-qr-section {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 18px 12px 10px 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.07);
                margin-bottom: 0;
                width: 180px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .trm-single-qr .trm-qr-code {
                width: 150px;
                height: 150px;
                margin-bottom: 10px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            }
            .trm-single-qr .trm-qr-info {
                color: #666;
                font-size: 0.95em;
                text-align: center;
            }
            @media (max-width: 900px) {
                .trm-single-flex-row {
                    flex-direction: column;
                    gap: 18px;
                }
                .trm-single-qr {
                    align-items: flex-start;
                    margin-top: 10px;
                }
            }
            </style>
            <?php
            // After fetching $memberships in team_profile_shortcode, add debug output
            error_log('TRM DEBUG: User ID: ' . $user_id);
            error_log('TRM DEBUG: Memberships found: ' . count($memberships));
            error_log('TRM DEBUG: Memberships data: ' . print_r($memberships, true));
            return ob_get_clean();
        }

        public function qr_scanner_shortcode() {
            if (!is_user_logged_in()) {
                return '<p>Please log in to access the QR scanner.</p>';
            }

            // Check if user has permission to scan QR codes
            if (!current_user_can('manage_options')) {
                return '<p>You do not have permission to access the QR scanner.</p>';
            }

            ob_start();
            ?>
            <div class="trm-qr-scanner-container">
                <div class="scanner-header">
                    <h2>Tournament Check-in Scanner</h2>
                    <div class="scanner-instructions">
                        <h3>How to check in teams:</h3>
                        <ol>
                            <li>Enter the QR code in the text field below</li>
                            <li>Click "Check In" or press Enter</li>
                            <li>You'll see a confirmation message when successful</li>
                        </ol>
                    </div>
                </div>

                <div class="scanner-content">
                    <div class="scanner-section">
                        <div class="manual-entry">
                            <div class="input-group">
                                <input type="text" id="qr-code-input" placeholder="Enter QR code here..." class="qr-input">
                                <button id="check-in-button" class="check-in-btn">Check In</button>
                            </div>
                        </div>
                        <div id="qr-reader-results"></div>
                    </div>

                    <div class="scanner-section">
                        <div class="scan-history">
                            <h3>Recent Check-ins</h3>
                            <div id="scan-history-list"></div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .trm-qr-scanner-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .scanner-header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .scanner-header h2 {
                    color: #333;
                    margin-bottom: 20px;
                }

                .scanner-instructions {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 0 auto;
                    max-width: 600px;
                    text-align: left;
                }

                .scanner-instructions h3 {
                    color: #333;
                    margin-bottom: 15px;
                    font-size: 1.2em;
                }

                .scanner-instructions ol {
                    margin: 0;
                    padding-left: 20px;
                }

                .scanner-instructions li {
                    margin-bottom: 10px;
                    color: #555;
                }

                .scanner-content {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                }

                .scanner-section {
                    background: #fff;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .manual-entry {
                    margin-bottom: 20px;
                }

                .input-group {
                    display: flex;
                    gap: 10px;
                    max-width: 600px;
                    margin: 0 auto;
                }

                .qr-input {
                    flex: 1;
                    padding: 12px 15px;
                    font-size: 16px;
                    border: 2px solid #e5e7eb;
                    border-radius: 6px;
                    transition: border-color 0.2s;
                }

                .qr-input:focus {
                    outline: none;
                    border-color: #007bff;
                }

                .check-in-btn {
                    padding: 12px 24px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background-color 0.2s;
                }

                .check-in-btn:hover {
                    background: #0056b3;
                }

                #qr-reader-results {
                    margin-top: 20px;
                    padding: 15px;
                    border-radius: 4px;
                    display: none;
                    text-align: center;
                    font-weight: 500;
                }

                .scan-success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .scan-error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .scan-already {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .scan-history {
                    height: 100%;
                }

                .scan-history h3 {
                    margin-bottom: 15px;
                    color: #333;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                }

                #scan-history-list {
                    max-height: 500px;
                    overflow-y: auto;
                }

                .scan-item {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    transition: background-color 0.2s;
                }

                .scan-item:hover {
                    background-color: #f8f9fa;
                }

                .scan-item:last-child {
                    border-bottom: none;
                }

                .scan-info {
                    flex: 1;
                }

                .scan-info div {
                    margin-bottom: 5px;
                }

                .scan-team-name {
                    font-weight: 600;
                    color: #333;
                }

                .scan-tournament {
                    color: #666;
                    font-size: 0.9em;
                }

                .scan-time {
                    color: #666;
                    font-size: 0.9em;
                }

                .scan-status {
                    padding: 6px 12px;
                    border-radius: 4px;
                    font-size: 0.9em;
                    font-weight: 500;
                }

                .status-success {
                    background: #d4edda;
                    color: #155724;
                }

                .status-error {
                    background: #f8d7da;
                    color: #721c24;
                }

                @media (max-width: 768px) {
                    .scanner-content {
                        grid-template-columns: 1fr;
                    }

                    .scanner-instructions {
                        padding: 15px;
                    }

                    .input-group {
                        flex-direction: column;
                    }

                    .check-in-btn {
                        width: 100%;
                    }
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                const scanHistory = [];

                function processQRCode(qrCode) {
                    try {
                        const qrData = JSON.parse(atob(qrCode));
                        
                        // Send scan data to server
                        $.ajax({
                            url: trm_public.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'trm_scan_qr',
                                scan_data: qrCode,
                                scan_type: qrData.type,
                                nonce: trm_public.nonce
                            },
                            success: function(response) {
                                // Always use returned names for both success and error
                                const teamName = response.data && response.data.team_name ? response.data.team_name : (qrData.team_name || '');
                                const tournamentTitle = response.data && response.data.tournament_title ? response.data.tournament_title : (qrData.tournament_title || '');
                                const firstName = response.data && response.data.first_name ? response.data.first_name : '';
                                const lastName = response.data && response.data.last_name ? response.data.last_name : '';
                                const checkInTime = response.data && response.data.check_in_time ? response.data.check_in_time : '';
                                const status = response.success ? 'success' : (response.data && response.data.message === 'Already checked in' ? 'already' : 'error');
                                showScanResult(status, response.data.message);
                                addToHistory({
                                    type: qrData.type,
                                    team_id: qrData.team_id,
                                    tournament_id: qrData.tournament_id,
                                    team_name: teamName,
                                    tournament_title: tournamentTitle,
                                    first_name: firstName,
                                    last_name: lastName,
                                    check_in_time: checkInTime,
                                    status: status,
                                    time: new Date().toLocaleTimeString(),
                                    message: response.data.message
                                });
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', xhr.responseText);
                                showScanResult('error', 'Error communicating with server');
                            }
                        });
                    } catch (error) {
                        console.error('QR Processing Error:', error);
                        showScanResult('error', 'Invalid QR code format');
                    }
                }

                function showScanResult(type, message) {
                    const resultDiv = $('#qr-reader-results');
                    resultDiv.removeClass('scan-success scan-error scan-already')
                            .addClass(`scan-${type}`)
                            .html(message)
                            .show();

                    setTimeout(() => {
                        resultDiv.fadeOut();
                    }, 3000);
                }

                function addToHistory(scan) {
                    scanHistory.unshift(scan);
                    if (scanHistory.length > 20) {
                        scanHistory.pop();
                    }
                    updateHistoryDisplay();
                }

                function updateHistoryDisplay() {
                    const historyList = $('#scan-history-list');
                    historyList.empty();

                    scanHistory.forEach(scan => {
                        const item = $(`
                            <div class=\"scan-item\">
                                <div class=\"scan-info\">
                                    <div class=\"scan-tournament\">Tournament: ${scan.tournament_title || 'Tournament ID: ' + scan.tournament_id}</div>
                                    <div class=\"scan-team-name\">Team Name: ${scan.team_name || 'Team ID: ' + scan.team_id}</div>
                                    <div class=\"scan-player\">Player name: ${(scan.first_name || '') + ' ' + (scan.last_name || '')}</div>
                                    <div class=\"scan-time\">Checked in at: ${scan.check_in_time || scan.time}</div>
                                </div>
                                <span class=\"scan-status status-${scan.status}\">
                                    ${scan.status === 'success' ? 'Checked In' : (scan.message === 'Already checked in' ? 'Already checked in' : 'Error')}
                                </span>
                            </div>
                        `);
                        historyList.append(item);
                    });
                }

                // Handle manual entry
                $('#check-in-button').on('click', function() {
                    const qrCode = $('#qr-code-input').val().trim();
                    if (qrCode) {
                        processQRCode(qrCode);
                        $('#qr-code-input').val('').focus();
                    }
                });

                // Handle Enter key
                $('#qr-code-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        const qrCode = $(this).val().trim();
                        if (qrCode) {
                            processQRCode(qrCode);
                            $(this).val('').focus();
                        }
                    }
                });
            });
            </script>
            <?php
            return ob_get_clean();
        }

        // AJAX handlers for public actions
        public function handle_create_team() {
            // Debug: log received and expected nonce
            error_log('TRM DEBUG: Nonce received: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NOT SET'));
            error_log('TRM DEBUG: Expected nonce: ' . wp_create_nonce('trm_public_nonce'));
            // Use a more robust nonce check and error reporting
            if (!isset($_POST['nonce']) || !check_ajax_referer('trm_public_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
            }

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'You must be logged in to create a team.']);
            }

            // Allow admins to bypass team creation restrictions
            if (!current_user_can('manage_options')) {
                // Check if user is already a member of a team in this tournament
                global $wpdb;
                $tournament_id = intval($_POST['tournament_id']);
                $user_id = get_current_user_id();
                $existing_member = $wpdb->get_var($wpdb->prepare(
                    "SELECT m.id
                     FROM {$wpdb->prefix}trm_team_members m
                     JOIN {$wpdb->prefix}trm_teams t ON m.team_id = t.id
                     WHERE m.user_id = %d AND t.tournament_id = %d",
                    $user_id,
                    $tournament_id
                ));
                // Also check if user is a leader of any team in this tournament
                $is_leader = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}trm_teams WHERE leader_id = %d AND tournament_id = %d",
                    $user_id, $tournament_id
                ));
                if ($existing_member || $is_leader) {
                    wp_send_json_error(['message' => 'You have already joined or created a team for this tournament.']);
                }
            }

            // Debug: log all POST data
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TRM Create Team POST: ' . print_r($_POST, true));
            }

            // Check required fields
            if (empty($_POST['team_name'])) {
                wp_send_json_error(['message' => 'Team name is required.']);
            }
            if (empty($_POST['gamer_tag'])) {
                wp_send_json_error(['message' => 'Gamer tag is required.']);
            }
            if (empty($_POST['tournament_id'])) {
                wp_send_json_error(['message' => 'Tournament ID is required.']);
            }

            $team_name = sanitize_text_field($_POST['team_name']);
            $gamer_tag = sanitize_text_field($_POST['gamer_tag']);
            $tournament_id = intval($_POST['tournament_id']);

            global $wpdb;
            // Check if team name already exists
            $existing_team = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_teams WHERE team_name = %s",
                $team_name
            ));

            if ($existing_team) {
                wp_send_json_error(['message' => 'Team name already exists.']);
            }

            // Create team
            $invitation_token = wp_generate_password(32, false);
            $result = $wpdb->insert(
                $wpdb->prefix . 'trm_teams',
                array(
                    'team_name' => $team_name,
                    'leader_id' => get_current_user_id(),
                    'invitation_token' => $invitation_token,
                    'status' => 'active',
                    'tournament_id' => $tournament_id
                ),
                array('%s', '%d', '%s', '%s', '%d')
            );

            if ($result) {
                wp_send_json_success(['message' => 'Team created successfully!']);
            } else {
                global $wpdb;
                error_log('TRM Create Team DB Error: ' . $wpdb->last_error);
                wp_send_json_error(['message' => 'Failed to create team. Database error: ' . $wpdb->last_error]);
            }
        }

        public function handle_join_team() {
            check_ajax_referer('trm_public_nonce', 'nonce');

            // Get team ID either from direct input or by invitation token
            $team_id = 0;
            if (isset($_POST['team_id'])) {
                $team_id = intval($_POST['team_id']);
            } elseif (isset($_POST['invitation_token'])) {
                global $wpdb;
                $invitation_token = sanitize_text_field($_POST['invitation_token']);
                $team_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}trm_teams WHERE invitation_token = %s",
                    $invitation_token
                ));
            }

            if (!$team_id) {
                wp_send_json_error(['message' => 'Team not found. Please check the invitation link.']);
                return;
            }

            $gamer_tag = sanitize_text_field($_POST['gamer_tag']);
                global $wpdb;
            $user_id = get_current_user_id();

            // Get the tournament ID for this team
                $tournament_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT tournament_id FROM {$wpdb->prefix}trm_teams WHERE id = %d",
                    $team_id
                ));
            if (!$tournament_id) {
                wp_send_json_error(['message' => 'Tournament not found for this team.']);
                return;
            }

            // Check if user is already a member (active or pending) of ANY team in this tournament
            $existing_in_tournament = $wpdb->get_var($wpdb->prepare(
                    "SELECT m.id
                     FROM {$wpdb->prefix}trm_team_members m
                     JOIN {$wpdb->prefix}trm_teams t ON m.team_id = t.id
                     WHERE m.user_id = %d AND t.tournament_id = %d",
                $user_id,
                    $tournament_id
                ));
            // Also check if user is a leader of any team in this tournament
            $is_leader = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_teams WHERE leader_id = %d AND tournament_id = %d",
                $user_id, $tournament_id
            ));
            if ($existing_in_tournament || $is_leader) {
                wp_send_json_error(['message' => 'You have already joined or created a team for this tournament.']);
                return;
            }

            // Check if user is already a member (active or pending) of this team
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}trm_team_members WHERE team_id = %d AND user_id = %d",
                $team_id, $user_id
            ));
            if ($existing) {
                if ($existing->status === 'active') {
                    wp_send_json_error(['message' => 'You are already a member of this team.']);
                } elseif ($existing->status === 'pending') {
                    wp_send_json_error(['message' => 'You have already requested to join this team. Please wait for approval.']);
                } else {
                    wp_send_json_error(['message' => 'You already have a request for this team.']);
                }
                return;
            }

            // Check if team is full (include leader in the count)
            $max_players = $wpdb->get_var($wpdb->prepare(
                "SELECT max_players_per_team FROM {$wpdb->prefix}trm_tournaments tr
                 JOIN {$wpdb->prefix}trm_teams t ON tr.id = t.tournament_id
                 WHERE t.id = %d",
                $team_id
            ));
                $current_players = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}trm_team_members WHERE team_id = %d AND status = 'active'",
                    $team_id
                ));
            // Add 1 for the leader
            $leader_id = $wpdb->get_var($wpdb->prepare(
                "SELECT leader_id FROM {$wpdb->prefix}trm_teams WHERE id = %d",
                $team_id
            ));
            $total_players = $current_players + 1; // leader always exists
            if ($max_players && $total_players >= $max_players) {
                    wp_send_json_error(['message' => 'This team is already full.']);
                return;
            }

            // Add member to team
            $result = $wpdb->insert(
                $wpdb->prefix . 'trm_team_members',
                array(
                    'team_id' => $team_id,
                    'user_id' => $user_id,
                    'gamer_tag' => $gamer_tag,
                    'status' => 'pending'
                ),
                array('%d', '%d', '%s', '%s')
            );

            if ($result) {
                // Send notification to team leader
                $team = $wpdb->get_row($wpdb->prepare(
                    "SELECT t.*, u.user_email FROM {$wpdb->prefix}trm_teams t 
                    JOIN {$wpdb->users} u ON t.leader_id = u.ID 
                    WHERE t.id = %d",
                    $team_id
                ));
                if ($team) {
                    $this->send_join_request_notification($team->user_email, $user_id, $team_id);
                }
                wp_send_json_success(['message' => 'Join request sent successfully']);
            } else {
                global $wpdb;
                $db_error = $wpdb->last_error;
                wp_send_json_error(['message' => 'Failed to send join request. Database error: ' . $db_error]);
            }
        }

        public function handle_approve_join_request() {
            error_log('TRM: handle_approve_join_request called');
            if (!isset($_POST['nonce'])) {
                error_log('TRM: nonce not set');
            } else {
                error_log('TRM: nonce received: ' . $_POST['nonce']);
            }
            if (!check_ajax_referer('trm_public_nonce', 'nonce', false)) {
                error_log('TRM: Nonce check failed!');
                wp_send_json_error('Nonce check failed');
            }
            error_log('TRM: Nonce check passed!');
            if (!is_user_logged_in()) {
                error_log('TRM: Not logged in');
                wp_send_json_error('Please log in to approve join requests');
            }
            $request_id = intval($_POST['request_id']);
            global $wpdb;
            $request = $wpdb->get_row($wpdb->prepare(
                "SELECT m.*, t.leader_id, t.tournament_id, t.id as team_id
                FROM {$wpdb->prefix}trm_team_members m 
                JOIN {$wpdb->prefix}trm_teams t ON m.team_id = t.id 
                WHERE m.id = %d",
                $request_id
            ));
            if (!$request || $request->leader_id != get_current_user_id()) {
                error_log('TRM: Unauthorized');
                wp_send_json_error('Unauthorized');
            }
            // Enforce max_players_per_team (including leader)
            $max_players = $wpdb->get_var($wpdb->prepare(
                "SELECT max_players_per_team FROM {$wpdb->prefix}trm_tournaments WHERE id = %d",
                $request->tournament_id
            ));
            $current_players = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}trm_team_members WHERE team_id = %d AND status = 'active'",
                $request->team_id
            ));
            $total_players = $current_players + 1; // +1 for leader
            if ($max_players && $total_players >= $max_players) {
                wp_send_json_error('Cannot approve: team is already full.');
            }
            $result = $wpdb->update(
                $wpdb->prefix . 'trm_team_members',
                array('status' => 'active'),
                array('id' => $request_id),
                array('%s'),
                array('%d')
            );
            if ($result) {
                error_log('TRM: Join request approved');
                $user = get_userdata($request->user_id);
                if ($user) {
                    $this->send_join_approval_notification($user->user_email, $request->team_id);
                }
                wp_send_json_success('Join request approved');
            } else {
                error_log('TRM: Failed to approve join request');
                wp_send_json_error('Failed to approve join request');
            }
        }

        public function handle_reject_join_request() {
            error_log('TRM: handle_reject_join_request called');
            if (!isset($_POST['nonce'])) {
                error_log('TRM: nonce not set');
            } else {
                error_log('TRM: nonce received: ' . $_POST['nonce']);
            }
            if (!check_ajax_referer('trm_public_nonce', 'nonce', false)) {
                error_log('TRM: Nonce check failed!');
                wp_send_json_error('Nonce check failed');
            }
            error_log('TRM: Nonce check passed!');
            if (!is_user_logged_in()) {
                error_log('TRM: Not logged in');
                wp_send_json_error('Please log in to reject join requests');
            }
            $request_id = intval($_POST['request_id']);
            global $wpdb;
            $request = $wpdb->get_row($wpdb->prepare(
                "SELECT m.*, t.leader_id 
                FROM {$wpdb->prefix}trm_team_members m 
                JOIN {$wpdb->prefix}trm_teams t ON m.team_id = t.id 
                WHERE m.id = %d",
                $request_id
            ));
            if (!$request || $request->leader_id != get_current_user_id()) {
                error_log('TRM: Unauthorized');
                wp_send_json_error('Unauthorized');
            }
            $result = $wpdb->delete(
                $wpdb->prefix . 'trm_team_members',
                array('id' => $request_id),
                array('%d')
            );
            if ($result) {
                error_log('TRM: Join request rejected');
                $user = get_userdata($request->user_id);
                if ($user) {
                    $this->send_join_rejection_notification($user->user_email, $request->team_id);
                }
                wp_send_json_success('Join request rejected');
            } else {
                error_log('TRM: Failed to reject join request');
                wp_send_json_error('Failed to reject join request');
            }
        }

        public function handle_get_team_id() {
            check_ajax_referer('trm_public_nonce', 'nonce');
            $team_name = sanitize_text_field($_POST['team_name']);
            global $wpdb;
            $team_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_teams WHERE team_name = %s",
                $team_name
            ));
            if ($team_id) {
                wp_send_json_success(['team_id' => $team_id]);
            } else {
                wp_send_json_error(['message' => 'Team not found']);
            }
        }

        public function handle_qr_scan() {
            // Debug: log received and expected nonce
            error_log('TRM DEBUG: Nonce received: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NOT SET'));
            error_log('TRM DEBUG: Expected nonce: ' . wp_create_nonce('trm_public_nonce'));
            
            // Use a more robust nonce check and error reporting
            if (!isset($_POST['nonce']) || !check_ajax_referer('trm_public_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
            }

            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Unauthorized access'));
                return;
            }

            $scan_data = isset($_POST['scan_data']) ? sanitize_text_field($_POST['scan_data']) : '';
            $scan_type = isset($_POST['scan_type']) ? sanitize_text_field($_POST['scan_type']) : '';

            if (empty($scan_data) || empty($scan_type)) {
                wp_send_json_error(array('message' => 'Invalid scan data'));
                return;
            }

            try {
                $qr_data = json_decode(base64_decode($scan_data), true);
                if (!$qr_data) {
                    wp_send_json_error(array('message' => 'Invalid QR code data'));
                    return;
                }

                global $wpdb;
                $table_name = $wpdb->prefix . 'trm_check_ins';
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                if (!$table_exists) {
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                        id bigint(20) NOT NULL AUTO_INCREMENT,
                        team_id bigint(20) NOT NULL,
                        tournament_id bigint(20) NOT NULL,
                        member_id bigint(20) DEFAULT NULL,
                        first_name varchar(100) DEFAULT NULL,
                        last_name varchar(100) DEFAULT NULL,
                        check_in_time datetime NOT NULL,
                        PRIMARY KEY  (id),
                        KEY team_id (team_id),
                        KEY tournament_id (tournament_id),
                        KEY member_id (member_id)
                    ) $charset_collate;";
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                    dbDelta($sql);
                }

                $is_member = ($qr_data['type'] === 'member');
                $team_id = $qr_data['team_id'];
                $tournament_id = $qr_data['tournament_id'];
                $member_id = $is_member ? $qr_data['member_id'] : null;

                // Always fetch first/last name from user data if member QR
                $first_name = '';
                $last_name = '';
                if ($is_member && $member_id) {
                    $user = get_userdata($member_id);
                    if ($user) {
                        $first_name = $user->first_name;
                        $last_name = $user->last_name;
                    }
                }

                // Check for existing check-in
                $where = $is_member ?
                    $wpdb->prepare("team_id = %d AND tournament_id = %d AND member_id = %d", $team_id, $tournament_id, $member_id) :
                    $wpdb->prepare("team_id = %d AND tournament_id = %d AND member_id IS NULL", $team_id, $tournament_id);
                
                $existing_check_in = $wpdb->get_row("SELECT * FROM $table_name WHERE $where");
                
                if ($existing_check_in) {
                    // Always fetch team and tournament names from DB using IDs from the check-in record
                    $team_id = (int)$existing_check_in->team_id;
                    $tournament_id = (int)$existing_check_in->tournament_id;
                    $sql = $wpdb->prepare(
                        "SELECT t.*, tr.title as tournament_title 
                        FROM {$wpdb->prefix}trm_teams t
                        JOIN {$wpdb->prefix}trm_tournaments tr ON t.tournament_id = tr.id
                        WHERE t.id = %d AND t.tournament_id = %d",
                        $team_id,
                        $tournament_id
                    );
                    error_log('TRM DEBUG: SQL=' . $sql);
                    $team = $wpdb->get_row($sql);
                    error_log('TRM DEBUG: Team result=' . print_r($team, true));
                    if (!$team) {
                        error_log('TRM DEBUG: Team not found for team_id=' . $team_id . ' tournament_id=' . $tournament_id);
                        $team_name = 'Not found';
                        $tournament_title = 'Not found';
                    } else {
                        $team_name = $team->team_name;
                        $tournament_title = $team->tournament_title;
                    }
                    // If member QR, always fetch name from user data
                    if ($is_member && $member_id) {
                        $user = get_userdata($member_id);
                        if ($user) {
                            $first_name = $user->first_name;
                            $last_name = $user->last_name;
                        }
                    }
                    // For team QR, set player name to 'N/A'
                    if (!$is_member) {
                        $first_name = '';
                        $last_name = '';
                    }
                    wp_send_json_error(array(
                        'message' => 'Already checked in',
                        'team_name' => $team_name,
                        'tournament_title' => $tournament_title,
                        'first_name' => $is_member ? $first_name : '',
                        'last_name' => $is_member ? $last_name : '',
                        'check_in_time' => $existing_check_in->check_in_time
                    ));
                    return;
                }

                // Get team and tournament names for new check-in
                $team = $wpdb->get_row($wpdb->prepare(
                    "SELECT t.*, tr.title as tournament_title 
                    FROM {$wpdb->prefix}trm_teams t
                    JOIN {$wpdb->prefix}trm_tournaments tr ON t.tournament_id = tr.id
                    WHERE t.id = %d AND t.tournament_id = %d",
                    $team_id,
                    $tournament_id
                ));

                if (!$team) {
                    wp_send_json_error(array('message' => 'Team not found or does not belong to this tournament'));
                    return;
                }

                $check_in_data = array(
                    'team_id' => $team_id,
                    'tournament_id' => $tournament_id,
                    'member_id' => $member_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'check_in_time' => current_time('mysql')
                );

                $result = $wpdb->insert(
                    $table_name,
                    $check_in_data,
                    array('%d', '%d', '%d', '%s', '%s', '%s')
                );

                if ($result === false) {
                    error_log('TRM Check-in Error: ' . $wpdb->last_error);
                    wp_send_json_error(array('message' => 'Failed to record check-in. Database error: ' . $wpdb->last_error));
                    return;
                }

                wp_send_json_success(array(
                    'message' => 'Checked in successfully',
                    'team_name' => $team->team_name,
                    'tournament_title' => $team->tournament_title,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'check_in_time' => $check_in_data['check_in_time']
                ));

            } catch (Exception $e) {
                error_log('TRM Check-in Exception: ' . $e->getMessage());
                wp_send_json_error(array('message' => 'Error processing QR code: ' . $e->getMessage()));
            }
        }

        private function send_join_request_notification($leader_email, $user_id, $team_id) {
            $user = get_userdata($user_id);
            $subject = 'New Team Join Request';
            $message = sprintf(
                'User %s has requested to join your team. Please visit your team management page to approve or reject this request.',
                $user->display_name
            );
            
            wp_mail($leader_email, $subject, $message);
        }

        private function send_join_approval_notification($user_email, $team_id) {
            global $wpdb;
            $team = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_teams WHERE id = %d",
                $team_id
            ));

            $subject = 'Team Join Request Approved';
            $message = sprintf(
                'Your request to join team "%s" has been approved. You can now access your team management page.',
                $team->team_name
            );
            
            wp_mail($user_email, $subject, $message);
        }

        private function send_join_rejection_notification($user_email, $team_id) {
            global $wpdb;
            $team = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_teams WHERE id = %d",
                $team_id
            ));

            $subject = 'Team Join Request Rejected';
            $message = sprintf(
                'Your request to join team "%s" has been rejected.',
                $team->team_name
            );
            
            wp_mail($user_email, $subject, $message);
        }

        public function add_checkin_entries_admin_page() {
            add_menu_page(
                'Check-in Entries',
                'Check-in Entries',
                'manage_options',
                'trm_checkin_entries',
                array($this, 'render_checkin_entries_admin_page'),
                'dashicons-yes',
                56
            );
        }

        public function render_checkin_entries_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            global $wpdb;
            $table_name = $wpdb->prefix . 'trm_check_ins';

            // Filters
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : '';
            $tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : '';
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 20;
            $offset = ($paged - 1) * $per_page;

            $where = 'WHERE 1=1';
            $params = array();
            if ($search) {
                $where .= " AND (first_name LIKE %s OR last_name LIKE %s)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            if ($team_id) {
                $where .= " AND team_id = %d";
                $params[] = $team_id;
            }
            if ($tournament_id) {
                $where .= " AND tournament_id = %d";
                $params[] = $tournament_id;
            }

            $total_items = $wpdb->get_var($wpdb->prepare(
                sprintf("SELECT COUNT(*) FROM $table_name $where"),
                ...$params
            ));
            $entries = $wpdb->get_results($wpdb->prepare(
                sprintf("SELECT * FROM $table_name $where ORDER BY check_in_time DESC LIMIT %%d OFFSET %%d"),
                ...array_merge($params, array($per_page, $offset))
            ));

            // Get teams and tournaments for filters
            $teams = $wpdb->get_results("SELECT id, team_name FROM {$wpdb->prefix}trm_teams ORDER BY team_name ASC");
            $tournaments = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}trm_tournaments ORDER BY title ASC");

            $total_pages = ceil($total_items / $per_page);
            $current_url = admin_url('admin.php?page=trm_checkin_entries');
            ?>
            <div class="wrap">
                <h1>Check-in Entries</h1>
                <form method="get" action="">
                    <input type="hidden" name="page" value="trm_checkin_entries" />
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by name..." />
                    <select name="team_id">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team->id; ?>" <?php selected($team_id, $team->id); ?>><?php echo esc_html($team->team_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tournament_id">
                        <option value="">All Tournaments</option>
                        <?php foreach ($tournaments as $t): ?>
                            <option value="<?php echo $t->id; ?>" <?php selected($tournament_id, $t->id); ?>><?php echo esc_html($t->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="per_page">
                        <option value="20" <?php selected($per_page, 20); ?>>20</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    </select>
                    <button class="button">Filter</button>
                </form>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Team</th>
                            <th>Tournament</th>
                            <th>Member</th>
                            <th>Check-in Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entries): ?>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?php echo $entry->id; ?></td>
                                    <td>
                                        <?php
                                        $team = $wpdb->get_row($wpdb->prepare("SELECT team_name FROM {$wpdb->prefix}trm_teams WHERE id = %d", $entry->team_id));
                                        echo $team ? esc_html($team->team_name) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $tournament = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}trm_tournaments WHERE id = %d", $entry->tournament_id));
                                        echo $tournament ? esc_html($tournament->title) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($entry->member_id) {
                                            $user = get_userdata($entry->member_id);
                                            echo $user ? esc_html($user->display_name) : 'ID: ' . $entry->member_id;
                                        } else {
                                            echo esc_html($entry->first_name . ' ' . $entry->last_name);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($entry->check_in_time); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                            <?php wp_nonce_field('trm_delete_checkin_entry_' . $entry->id, 'trm_delete_checkin_entry_nonce'); ?>
                                            <input type="hidden" name="action" value="trm_delete_checkin_entry" />
                                            <input type="hidden" name="entry_id" value="<?php echo $entry->id; ?>" />
                                            <button class="button button-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No entries found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php if ($total_pages > 1): ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a class="button<?php if ($i == $paged) echo ' button-primary'; ?>" href="<?php echo esc_url(add_query_arg(array_merge($_GET, array('paged' => $i)), $current_url)); ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <style>
                .button-danger { background: #dc3545; color: #fff; border: none; }
                .button-danger:hover { background: #a71d2a; }
            </style>
            <?php
        }

        public function handle_delete_checkin_entry() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
            if (!$entry_id || !isset($_POST['trm_delete_checkin_entry_nonce']) || !wp_verify_nonce($_POST['trm_delete_checkin_entry_nonce'], 'trm_delete_checkin_entry_' . $entry_id)) {
                wp_die('Invalid request.');
            }
            global $wpdb;
            $table_name = $wpdb->prefix . 'trm_check_ins';
            $wpdb->delete($table_name, array('id' => $entry_id), array('%d'));
            wp_redirect(add_query_arg('deleted', '1', wp_get_referer()));
            exit;
        }

        public function handle_single_player_registration() {
            check_ajax_referer('trm_public_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'You must be logged in to register.'));
                return;
            }

            $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
            $gamer_tag = isset($_POST['gamer_tag']) ? sanitize_text_field($_POST['gamer_tag']) : '';

            if (!$tournament_id || !$gamer_tag) {
                wp_send_json_error(array('message' => 'Missing required fields.'));
                return;
            }

            global $wpdb;

            // Check if tournament exists and is active
            $tournament = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}trm_tournaments WHERE id = %d AND status = 'active'",
                $tournament_id
            ));

            if (!$tournament) {
                wp_send_json_error(array('message' => 'Tournament not found or not active.'));
                return;
            }

            // Block registration if end_date has passed
            if (!empty($tournament->end_date) && strtotime($tournament->end_date) < time()) {
                wp_send_json_error(array('message' => 'Registration is closed.'));
                return;
            }

            // Check if user is already registered
            $existing_registration = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_registrations 
                WHERE tournament_id = %d AND user_id = %d",
                $tournament_id,
                get_current_user_id()
            ));

            if ($existing_registration) {
                wp_send_json_error(array('message' => 'You are already registered for this tournament.'));
                return;
            }

            // Check if gamer tag is already taken
            $existing_gamer_tag = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}trm_registrations 
                WHERE tournament_id = %d AND gamer_tag = %s",
                $tournament_id,
                $gamer_tag
            ));

            if ($existing_gamer_tag) {
                wp_send_json_error(array('message' => 'This gamer tag is already taken. Please choose another one.'));
                return;
            }

            // Insert registration
            $result = $wpdb->insert(
                $wpdb->prefix . 'trm_registrations',
                array(
                    'tournament_id' => $tournament_id,
                    'user_id' => get_current_user_id(),
                    'gamer_tag' => $gamer_tag,
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );

            if ($result) {
                wp_send_json_success(array('message' => 'Successfully registered for the tournament!'));
            } else {
                wp_send_json_error(array('message' => 'Failed to register. Please try again.'));
            }
        }
    }
} 