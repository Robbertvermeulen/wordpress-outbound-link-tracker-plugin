<?php 
use EPICWP\Outbound_Link_Tracking\Tracked_Link;

class Link_Tracking {

    private static $instance;

    private function __construct() {
        add_action('template_redirect', [$this, 'handle_link_redirect'], 1);
        add_action('init', [$this, 'register_post_types']);
        add_action('after_setup_theme', [$this, 'register_custom_fields']);
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('admin_init', [$this, 'handle_submit_analytics_date']);
    }

    public function handle_link_redirect() {
        if (!is_singular('tracked_link')) return;
        $link_id = get_the_ID();
        $link = new Tracked_Link($link_id);
        $link->handle_visit();
    }

    public function register_post_types() {
        register_post_type('tracked_link', [
            'labels' => [
                'name' => __('Links', 'usercentrics-data-shield'),
                'singular_name' => __('Link', 'usercentrics-data-shield'),
                'menu_name' => __('Links', 'usercentrics-data-shield'),
                'add_new' => __('Add New', 'usercentrics-data-shield'),
                'add_new_item' => __('Add New Link', 'usercentrics-data-shield'),
                'edit' => __('Edit', 'usercentrics-data-shield'),
                'edit_item' => __('Edit Link', 'usercentrics-data-shield'),
                'new_item' => __('New Link', 'usercentrics-data-shield'),
                'view' => __('View', 'usercentrics-data-shield'),
                'view_item' => __('View Link', 'usercentrics-data-shield'),
                'search_items' => __('Search Links', 'usercentrics-data-shield'),
                'not_found' => __('No Links found', 'usercentrics-data-shield'),
                'not_found_in_trash' => __('No Links found in Trash', 'usercentrics-data-shield'),
                'parent' => __('Parent Link', 'usercentrics-data-shield')
            ],
            'public' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'link'],
            'supports' => ['title'],
            'menu_icon' => 'dashicons-admin-links',
        ]);
    }

    public function get_tracked_links_callback() {
        $page = $_GET['page'] ?? 1;
        $per_page = $_GET['per_page'] ?? 1000;
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        $data = $this->get_tracked_links($page, $per_page, $date_from, $date_to);
        $response = [
            'status' => 'success',
            'data' => $data
        ];
        return $response;
    }

    public function get_tracked_links_stats_callback() {
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        $data = $this->get_tracked_links_stats($date_from, $date_to);
        $response = [
            'status' => 'success',
            'data' => $data
        ];
        return $response;
    }

    public function get_tracked_links(int $page = 1, int $per_page = 500, $date_from = null, $date_to = null) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'tracked_link_redirects';
        $offset = ($page - 1) * $per_page;

        // Set default dates to today if not set
        if (empty($date_from)) {
            $date_from = date('Y-m-d');
        }
        if (empty($date_to)) {
            $date_to = $date_from;
        }

        // Add time to dates
        $date_from = $date_from . ' 00:00:00';
        $date_to = $date_to . ' 23:59:59';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE date >= %s AND date <= %s ORDER BY date DESC LIMIT %d, %d",
            $date_from,
            $date_to,
            $offset,
            $per_page
        );
        $results = $wpdb->get_results($query, ARRAY_A);
        return $results;
    }

    public function get_tracked_links_stats($date_from = null, $date_to = null) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'tracked_link_redirects';
    
        // Set default dates to today if not set
        if (empty($date_from)) {
            $date_from = date('Y-m-d');
        }
        if (empty($date_to)) {
            $date_to = $date_from;
        }
    
        // Add time to dates
        $date_from = $date_from . ' 00:00:00';
        $date_to = $date_to . ' 23:59:59';
        
        $query = $wpdb->prepare(
            "SELECT 
                tlr.link_id, 
                p.post_title AS link_name, 
                p.guid AS link_url, 
                tlr.browser AS browser, 
                tlr.os AS os
            FROM $table_name AS tlr
            INNER JOIN
                wp_posts AS p ON tlr.link_id = p.ID
            WHERE date >= %s AND date <= %s;",
            $date_from,
            $date_to
        );
        $results = $wpdb->get_results($query, ARRAY_A);
    
        $grouped_results = [];
        foreach ($results as $result) {
            $link_id = $result['link_id'];
            $browser = $result['browser'] ? $result['browser'] : 'unknown';
            $os = $result['os'] ? $result['os'] : 'unknown';
    
            if (!isset($grouped_results[$link_id])) {
                $grouped_results[$link_id] = [
                    'link_id' => $link_id,
                    'link_name' => $result['link_name'],
                    'link_url' => get_permalink($link_id),
                    'visit_count' => 0,
                    'system' => []
                ];
            }
    
            $grouped_results[$link_id]['visit_count']++;
    
            $system_key = $browser . '_' . $os;
            if (!isset($grouped_results[$link_id]['system'][$system_key])) {
                $grouped_results[$link_id]['system'][$system_key] = [
                    'browser' => $browser ? $browser : 'unknown',
                    'os' => $os,
                    'visit_count' => 0
                ];
            }
            $grouped_results[$link_id]['system'][$system_key]['visit_count']++;
        }
    
        // Convert 'system' arrays to indexed arrays
        foreach ($grouped_results as $link_id => $result) {
            $grouped_results[$link_id]['system'] = array_values($result['system']);
        }
        return array_values($grouped_results);
    }    

    public function api_permissions_check() {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            return false;
        }
        list($user, $pass) = explode(':', base64_decode(substr($headers['Authorization'], 6)));

        $user = wp_authenticate($user, $pass);
        if (is_wp_error($user) || !user_can($user, 'manage_options')) {
            return false;
        }
        return true;
    }

    public function admin_enqueue_scripts() {
        $theme_version = wp_get_theme()->get('Version');
        wp_enqueue_style('olt-admin', OLT_PLUGIN_URL . '/assets/css/admin.css', [], $theme_version);
    }

    public function register_admin_menus() {

        add_submenu_page(
            'edit.php?post_type=tracked_link',
            __('Analytics', 'usercentrics-data-shield'),
            __('Analytics', 'usercentrics-data-shield'),
            'manage_options',
            'tracked-link-analytics',
            [$this, 'tracked_link_analytics_page']
        );
    }

    public function tracked_link_analytics_page() {

        $date_from = $_GET['date_from'] ?? date('Y-m-d');
        $date_to = $_GET['date_to'] ?? $date_from;

        $stats = $this->get_tracked_links_stats($date_from, $date_to);

        include OLT_PLUGIN_DIR . '/templates/admin/analytics.php';
    }

    public function handle_submit_analytics_date() {

        if (!isset($_POST['action']) || $_POST['action'] !== 'ucds_submit_analytics_data') return;
        
        $date_from = $_POST['date_from'];
        $date_to = $_POST['date_to'];
        $url = admin_url('edit.php?post_type=tracked_link&page=tracked-link-analytics');
        $url = add_query_arg(['date_from' => $date_from, 'date_to' => $date_to], $url);
        wp_redirect($url);
        exit;
    }

    public function register_custom_fields() {

        if( function_exists('acf_add_local_field_group') ):

            acf_add_local_field_group(array(
                'key' => 'group_65cad5be12a0b',
                'title' => 'Links',
                'fields' => array(
                    array(
                        'key' => 'field_65cad5be211ae',
                        'label' => 'Link redirects',
                        'name' => 'link_redirects',
                        'aria-label' => '',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'layout' => 'table',
                        'pagination' => 0,
                        'min' => 0,
                        'max' => 0,
                        'collapsed' => '',
                        'button_label' => 'Add Row',
                        'rows_per_page' => 20,
                        'sub_fields' => array(
                            array(
                                'key' => 'field_65cad720211b0',
                                'label' => 'URL',
                                'name' => 'url',
                                'aria-label' => '',
                                'type' => 'url',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'default_value' => '',
                                'placeholder' => '',
                                'parent_repeater' => 'field_65cad5be211ae',
                            ),
                            array(
                                'key' => 'field_65cad73b211b1',
                                'label' => 'Browser',
                                'name' => 'browser',
                                'aria-label' => '',
                                'type' => 'select',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'choices' => array(
                                    'safari' => 'Safari',
                                    'chrome' => 'Chrome',
                                    'firefox' => 'Firefox',
                                    'edge' => 'Edge',
                                ),
                                'default_value' => false,
                                'return_format' => 'value',
                                'multiple' => 0,
                                'allow_null' => 1,
                                'ui' => 0,
                                'ajax' => 0,
                                'placeholder' => '',
                                'parent_repeater' => 'field_65cad5be211ae',
                            ),
                            array(
                                'key' => 'field_65cad622211af',
                                'label' => 'Operating system',
                                'name' => 'operating_system',
                                'aria-label' => '',
                                'type' => 'select',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'choices' => array(
                                    'ios' => 'iOS',
                                    'android' => 'Android',
                                ),
                                'default_value' => false,
                                'return_format' => 'value',
                                'multiple' => 0,
                                'allow_null' => 1,
                                'ui' => 0,
                                'ajax' => 0,
                                'placeholder' => '',
                                'parent_repeater' => 'field_65cad5be211ae',
                            ),
                        ),
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'tracked_link',
                        ),
                    ),
                ),
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
            ));
            endif;		
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    } 

}

Link_Tracking::get_instance();