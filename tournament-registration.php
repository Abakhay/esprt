<?php
/**
 * Plugin Name: Tournament Registration Manager
 * Plugin URI: https://yourwebsite.com/tournament-registration
 * Description: A comprehensive tournament registration system for managing single-player and team registrations
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: tournament-registration
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TRM_VERSION', '1.0.0');
define('TRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once TRM_PLUGIN_DIR . 'includes/class-trm-loader.php';
require_once TRM_PLUGIN_DIR . 'includes/class-trm-activator.php';
require_once TRM_PLUGIN_DIR . 'includes/class-trm-deactivator.php';
require_once TRM_PLUGIN_DIR . 'admin/class-trm-admin.php';
require_once TRM_PLUGIN_DIR . 'public/class-trm-public.php';
require_once TRM_PLUGIN_DIR . 'includes/class-trm-qr.php';

// Always instantiate the public class to register AJAX handlers
$trm_public_instance = new \Tournament_Registration_Manager\TRM_Public('tournament-registration', TRM_VERSION);
$trm_qr_instance = new \Tournament_Registration_Manager\TRM_QR('tournament-registration', TRM_VERSION);

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('TRM_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('TRM_Deactivator', 'deactivate'));

use Tournament_Registration_Manager\TRM_Public;

/**
 * Main plugin class
 */
class Tournament_Registration_Manager {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->version = TRM_VERSION;
        $this->plugin_name = 'tournament-registration';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        $this->loader = new TRM_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new TRM_Admin($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
    }

    private function define_public_hooks() {
        $plugin_public = new TRM_Public($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_shortcode('tournament_registration', $plugin_public, 'registration_form_shortcode');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}

// Initialize the plugin
function run_tournament_registration_manager() {
    $plugin = new Tournament_Registration_Manager();
    $plugin->run();
}
run_tournament_registration_manager(); 