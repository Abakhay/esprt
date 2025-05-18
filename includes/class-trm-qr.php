<?php
namespace Tournament_Registration_Manager;

class TRM_QR {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Remove duplicate QR scan handler
        // Add hooks for scan report functionality only
        add_action('wp_ajax_trm_get_scan_report', array($this, 'get_scan_report'));
        add_action('wp_ajax_nopriv_trm_get_scan_report', array($this, 'get_scan_report'));
        add_action('wp_ajax_trm_export_scan_report', array($this, 'export_scan_report'));
        add_action('wp_ajax_nopriv_trm_export_scan_report', array($this, 'export_scan_report'));
        
        // Add shortcode
        add_shortcode('trm_scan_report', array($this, 'render_scan_report'));
    }

    public function get_scan_report() {
        // Update nonce check to use trm_public_nonce
        if (!isset($_POST['nonce']) || !check_ajax_referer('trm_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }
        
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $scan_type = isset($_POST['scan_type']) ? sanitize_text_field($_POST['scan_type']) : '';
        
        $report_data = array();
        
        // Get tournament scans
        if ($scan_type === 'tournament' || empty($scan_type)) {
            $tournament_scan_history = get_option('trm_tournament_scan_history', array());
            
            foreach ($tournament_scan_history as $key => $scans) {
                list($tournament_id, $user_id) = explode('_', $key);
                
                foreach ($scans as $scan_time) {
                    if (!empty($date_from) && $scan_time < strtotime($date_from)) {
                        continue;
                    }
                    if (!empty($date_to) && $scan_time > strtotime($date_to)) {
                        continue;
                    }
                    
                    $user = get_userdata($user_id);
                    if (!$user) continue;
                    
                    $report_data[] = array(
                        'type' => 'tournament',
                        'tournament_id' => $tournament_id,
                        'user_email' => $user->user_email,
                        'scan_time' => date('Y-m-d H:i:s', $scan_time),
                        'user_data' => array(
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'phone' => get_user_meta($user_id, 'phone', true)
                        )
                    );
                }
            }
        }
        
        // Get visitor scans
        if ($scan_type === 'visitor' || empty($scan_type)) {
            $users = get_users();
            foreach ($users as $user) {
                $scan_history = get_user_meta($user->ID, 'trm_scan_history', true);
                if (!is_array($scan_history)) continue;
                
                foreach ($scan_history as $scan) {
                    if ($scan['type'] !== 'visitor') continue;
                    
                    $scan_time = strtotime($scan['time']);
                    if (!empty($date_from) && $scan_time < strtotime($date_from)) {
                        continue;
                    }
                    if (!empty($date_to) && $scan_time > strtotime($date_to)) {
                        continue;
                    }
                    
                    $report_data[] = array(
                        'type' => 'visitor',
                        'user_email' => $user->user_email,
                        'scan_time' => $scan['time'],
                        'user_data' => array(
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'phone' => get_user_meta($user->ID, 'phone', true)
                        )
                    );
                }
            }
        }
        
        // Sort by scan time
        usort($report_data, function($a, $b) {
            return strtotime($b['scan_time']) - strtotime($a['scan_time']);
        });
        
        wp_send_json_success($report_data);
    }

    public function export_scan_report() {
        // Update nonce check to use trm_public_nonce
        if (!isset($_REQUEST['nonce']) || !check_ajax_referer('trm_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }
        
        $date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
        $date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';
        $scan_type = isset($_REQUEST['scan_type']) ? sanitize_text_field($_REQUEST['scan_type']) : '';
        
        // Get report data
        $_POST['date_from'] = $date_from;
        $_POST['date_to'] = $date_to;
        $_POST['scan_type'] = $scan_type;
        
        $report_data = $this->get_scan_report();
        $report_data = json_decode($report_data, true);
        
        if (!isset($report_data['success']) || !$report_data['success']) {
            die('Error generating report');
        }
        
        $data = $report_data['data'];
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scan_report_' . date('Y-m-d') . '.csv');
        
        // Create output
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM
        fputs($output, "\xEF\xBB\xBF");
        
        // Write headers
        fputcsv($output, array('Scan Type', 'Email', 'First Name', 'Last Name', 'Phone', 'Scan Time'));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, array(
                $row['type'],
                $row['user_email'],
                $row['user_data']['first_name'],
                $row['user_data']['last_name'],
                $row['user_data']['phone'],
                $row['scan_time']
            ));
        }
        
        fclose($output);
        exit;
    }

    public function render_scan_report() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        wp_enqueue_script(
            'trm-qr-report',
            TRM_PLUGIN_URL . 'public/js/trm-qr-report.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('trm-qr-report', 'trmQrVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('trm_public_nonce')
        ));
        
        ob_start();
        ?>
        <div class="trm-scan-report-container">
            <div class="report-container">
                <div class="report-header">
                    <h1 class="report-title">QR Scan Report</h1>
                </div>
                
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Scan Type:</label>
                            <select id="scan-type-filter">
                                <option value="">All Types</option>
                                <option value="tournament">Tournament</option>
                                <option value="visitor">Visitor</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>From:</label>
                            <div class="date-input-wrapper">
                                <input type="text" id="date-from" placeholder="mm/dd/yyyy" />
                                <span class="calendar-icon">📅</span>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>To:</label>
                            <div class="date-input-wrapper">
                                <input type="text" id="date-to" placeholder="mm/dd/yyyy" />
                                <span class="calendar-icon">📅</span>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>Items per page:</label>
                            <select id="items-per-page">
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>

                        <div class="filter-group search-filter">
                            <label>Search:</label>
                            <input type="text" id="search-input" placeholder="Search..." />
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="button" id="apply-filters" class="btn btn-dark">Apply Filters</button>
                        <button type="button" id="reset-filters" class="btn btn-light">Reset Filters</button>
                        <button type="button" id="export-excel" class="btn btn-light">Export to Excel</button>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Scan Type</th>
                                <th>Email</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Phone</th>
                                <th>Scan Time</th>
                            </tr>
                        </thead>
                        <tbody id="scan-report-data">
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-controls">
                    <button id="prev-page" class="btn btn-light">Previous</button>
                    <span class="page-info">Page <span id="current-page">1</span> of <span id="total-pages">1</span></span>
                    <button id="next-page" class="btn btn-light">Next</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 