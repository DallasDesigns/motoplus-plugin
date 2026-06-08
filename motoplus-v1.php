<?php
/**
 * Plugin Name: Motoplus V1
 * Description: Lightweight dealer vehicle stock system with clean listings, easy admin entry, lead capture, lookup-provider framework, and AI description placeholders.
 * Version: 1.8.0
 * Author: Motoplus / ChatGPT
 * Text Domain: motoplus-v1
 */

if (!defined('ABSPATH')) exit;

class Motoplus_V1 {
    const VERSION = '1.8.0';
    const VEHICLE_POST_TYPE = 'motoplus_vehicle';
    const LEAD_POST_TYPE = 'motoplus_lead';
    const META_PREFIX = '_motoplus_';
    const OPTION_KEY = 'motoplus_v1_settings';

    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::VEHICLE_POST_TYPE, [$this, 'save_vehicle'], 10, 2);
        add_action('save_post_' . self::LEAD_POST_TYPE, [$this, 'save_lead'], 10, 2);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_shortcode('motoplus_stock', [$this, 'stock_shortcode']);
        add_shortcode('motoplus_featured', [$this, 'featured_shortcode']);
        add_shortcode('motoplus_latest', [$this, 'latest_shortcode']);
        add_shortcode('motoplus_search', [$this, 'search_shortcode']);
        add_filter('the_content', [$this, 'single_vehicle_content']);
        add_filter('manage_' . self::VEHICLE_POST_TYPE . '_posts_columns', [$this, 'vehicle_columns']);
        add_action('manage_' . self::VEHICLE_POST_TYPE . '_posts_custom_column', [$this, 'vehicle_column_content'], 10, 2);
        add_filter('manage_' . self::LEAD_POST_TYPE . '_posts_columns', [$this, 'lead_columns']);
        add_action('manage_' . self::LEAD_POST_TYPE . '_posts_custom_column', [$this, 'lead_column_content'], 10, 2);
        add_action('wp_ajax_motoplus_lookup_vehicle', [$this, 'ajax_lookup_vehicle']);
        add_action('wp_ajax_motoplus_generate_description', [$this, 'ajax_generate_description']);
        add_action('wp_ajax_motoplus_import_usedcarsni', [$this, 'ajax_import_usedcarsni']);
        add_action('wp_ajax_motoplus_import_html', [$this, 'ajax_import_html']);
        add_action('wp_ajax_motoplus_save_lead_status', [$this, 'ajax_save_lead_status']);
        add_action('wp_ajax_nopriv_motoplus_submit_lead', [$this, 'ajax_submit_lead']);
        add_action('wp_ajax_motoplus_submit_lead', [$this, 'ajax_submit_lead']);
    }

    public static function activate() {
        $plugin = new self();
        $plugin->register_post_types();
        flush_rewrite_rules();
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public function register_post_types() {
        register_post_type(self::VEHICLE_POST_TYPE, [
            'labels' => [
                'name' => 'Vehicles', 'singular_name' => 'Vehicle', 'menu_name' => 'Motoplus',
                'add_new_item' => 'Add New Vehicle', 'edit_item' => 'Edit Vehicle', 'new_item' => 'New Vehicle',
                'view_item' => 'View Vehicle', 'search_items' => 'Search Vehicles', 'not_found' => 'No vehicles found'
            ],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'cars-for-sale', 'with_front' => false],
            'query_var' => 'vehicle',
            'menu_icon' => 'dashicons-car',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
        ]);

        register_post_type(self::LEAD_POST_TYPE, [
            'labels' => [
                'name' => 'Enquiries', 'singular_name' => 'Enquiry', 'menu_name' => 'Enquiries',
                'add_new_item' => 'Add Enquiry', 'edit_item' => 'Edit Enquiry', 'search_items' => 'Search Enquiries'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . self::VEHICLE_POST_TYPE,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    public function vehicle_fields() {
        return [
            'registration' => ['label' => 'Registration', 'type' => 'text', 'placeholder' => 'AB12 CDE', 'group' => 'Lookup'],
            'price' => ['label' => 'Price', 'type' => 'number', 'placeholder' => '18995', 'group' => 'Sale Details'],
            'previous_price' => ['label' => 'Previous Price', 'type' => 'number', 'placeholder' => '19995', 'group' => 'Sale Details'],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['In Stock'=>'In Stock','Reserved'=>'Reserved','Sold'=>'Sold','Coming Soon'=>'Coming Soon'], 'group' => 'Sale Details'],
            'featured' => ['label' => 'Featured Vehicle', 'type' => 'checkbox', 'group' => 'Sale Details'],
            'make' => ['label' => 'Make', 'type' => 'text', 'placeholder' => 'BMW', 'group' => 'Vehicle Details'],
            'model' => ['label' => 'Model', 'type' => 'text', 'placeholder' => '3 Series', 'group' => 'Vehicle Details'],
            'variant' => ['label' => 'Variant / Trim', 'type' => 'text', 'placeholder' => 'M Sport', 'group' => 'Vehicle Details'],
            'year' => ['label' => 'Year', 'type' => 'number', 'placeholder' => '2021', 'group' => 'Vehicle Details'],
            'mileage' => ['label' => 'Mileage', 'type' => 'number', 'placeholder' => '42000', 'group' => 'Vehicle Details'],
            'fuel' => ['label' => 'Fuel Type', 'type' => 'select', 'options' => [''=>'Select','Petrol'=>'Petrol','Diesel'=>'Diesel','Hybrid'=>'Hybrid','Plug-in Hybrid'=>'Plug-in Hybrid','Electric'=>'Electric'], 'group' => 'Vehicle Details'],
            'gearbox' => ['label' => 'Transmission', 'type' => 'select', 'options' => [''=>'Select','Manual'=>'Manual','Automatic'=>'Automatic','Semi-Automatic'=>'Semi-Automatic'], 'group' => 'Vehicle Details'],
            'engine' => ['label' => 'Engine Size', 'type' => 'text', 'placeholder' => '2.0L', 'group' => 'Vehicle Details'],
            'body' => ['label' => 'Body Type', 'type' => 'text', 'placeholder' => 'Hatchback', 'group' => 'Vehicle Details'],
            'colour' => ['label' => 'Colour', 'type' => 'text', 'placeholder' => 'Grey', 'group' => 'Vehicle Details'],
            'doors' => ['label' => 'Doors', 'type' => 'number', 'placeholder' => '5', 'group' => 'Vehicle Details'],
            'owners' => ['label' => 'Previous Owners', 'type' => 'number', 'placeholder' => '1', 'group' => 'Extra Details'],
            'seats' => ['label' => 'Seats', 'type' => 'number', 'placeholder' => '5', 'group' => 'Extra Details'],
            'mot_expiry' => ['label' => 'MOT Expiry', 'type' => 'text', 'placeholder' => 'March 2027', 'group' => 'Extra Details'],
            'road_tax' => ['label' => 'Road Tax', 'type' => 'text', 'placeholder' => '£190/year', 'group' => 'Extra Details'],
            'tax_band' => ['label' => 'Tax Band', 'type' => 'text', 'placeholder' => 'E', 'group' => 'Extra Details'],
            'co2' => ['label' => 'CO2 Emissions', 'type' => 'text', 'placeholder' => '135 g/km', 'group' => 'Extra Details'],
            'location' => ['label' => 'Location', 'type' => 'text', 'placeholder' => 'Belfast', 'group' => 'Extra Details'],
            'payload' => ['label' => 'Payload', 'type' => 'text', 'placeholder' => '741kg', 'group' => 'Extra Details'],
            'service_history' => ['label' => 'Service History', 'type' => 'select', 'options' => [''=>'Select','Full Service History'=>'Full Service History','Part Service History'=>'Part Service History','No Service History'=>'No Service History'], 'group' => 'Extra Details'],
            'gallery' => ['label' => 'Gallery Image IDs', 'type' => 'hidden', 'group' => 'Images'],
        ];
    }

    public function lead_fields() {
        return [
            'vehicle_id' => ['label'=>'Vehicle ID','type'=>'hidden'],
            'vehicle_title' => ['label'=>'Vehicle','type'=>'text'],
            'name' => ['label'=>'Name','type'=>'text'],
            'phone' => ['label'=>'Phone','type'=>'text'],
            'email' => ['label'=>'Email','type'=>'email'],
            'message' => ['label'=>'Message','type'=>'textarea'],
            'status' => ['label'=>'Lead Status','type'=>'select','options'=>['New'=>'New','Contacted'=>'Contacted','Appointment'=>'Appointment','Sold'=>'Sold','Lost'=>'Lost']],
        ];
    }

    public function add_meta_boxes() {
        add_meta_box('motoplus_vehicle_details', 'Vehicle Details', [$this, 'vehicle_meta_box'], self::VEHICLE_POST_TYPE, 'normal', 'high');
        add_meta_box('motoplus_vehicle_gallery', 'Vehicle Images', [$this, 'gallery_meta_box'], self::VEHICLE_POST_TYPE, 'side', 'default');
        add_meta_box('motoplus_lead_details', 'Enquiry Details', [$this, 'lead_meta_box'], self::LEAD_POST_TYPE, 'normal', 'high');
    }

    public function vehicle_meta_box($post) {
        wp_nonce_field('motoplus_save_vehicle', 'motoplus_vehicle_nonce');
        $groups = [];
        foreach ($this->vehicle_fields() as $key => $field) { if (!in_array($key, ['gallery'])) $groups[$field['group']][] = [$key, $field]; }
        echo '<div class="motoplus-admin-panel">';
        echo '<div class="motoplus-lookup-row"><div><label>Registration Lookup</label><input type="text" id="motoplus_registration_lookup" value="'.esc_attr($this->meta($post->ID,'registration')).'" placeholder="AB12 CDE"></div><button type="button" class="button button-primary" id="motoplus_lookup_vehicle">Lookup Vehicle</button><span id="motoplus_lookup_result"></span></div>';
        foreach ($groups as $group => $items) {
            echo '<h3>'.esc_html($group).'</h3><div class="motoplus-admin-grid">';
            foreach ($items as $item) { $this->render_field($post->ID, $item[0], $item[1]); }
            echo '</div>';
        }
        echo '<div class="motoplus-ai-box"><h3>AI Description</h3><p>Generate a starter vehicle description from the vehicle fields, then edit it in the main description editor.</p><button type="button" class="button" id="motoplus_generate_description">Generate Draft Description</button><span id="motoplus_ai_result"></span></div>';
        echo '</div>';
    }

    private function render_field($post_id, $key, $field) {
        $value = $this->meta($post_id, $key);
        echo '<p class="motoplus-field motoplus-field-'.$key.'"><label for="motoplus_'.$key.'">'.esc_html($field['label']).'</label>';
        $name = 'motoplus_vehicle['.esc_attr($key).']';
        if ($field['type'] === 'select') {
            echo '<select id="motoplus_'.$key.'" name="'.$name.'">';
            foreach ($field['options'] as $opt_val => $opt_label) echo '<option value="'.esc_attr($opt_val).'" '.selected($value, $opt_val, false).'>'.esc_html($opt_label).'</option>';
            echo '</select>';
        } elseif ($field['type'] === 'checkbox') {
            echo '<label class="motoplus-check"><input type="checkbox" id="motoplus_'.$key.'" name="'.$name.'" value="1" '.checked($value, '1', false).'> Yes</label>';
        } else {
            echo '<input type="'.esc_attr($field['type']).'" id="motoplus_'.$key.'" name="'.$name.'" value="'.esc_attr($value).'" placeholder="'.esc_attr($field['placeholder'] ?? '').'">';
        }
        echo '</p>';
    }

    public function gallery_meta_box($post) {
        $gallery = $this->meta($post->ID, 'gallery');
        echo '<input type="hidden" id="motoplus_gallery" name="motoplus_vehicle[gallery]" value="'.esc_attr($gallery).'">';
        echo '<div id="motoplus-gallery-preview" class="motoplus-gallery-preview">'.$this->gallery_preview_html($gallery).'</div>';
        echo '<p><button type="button" class="button button-primary" id="motoplus-add-gallery">Choose Images</button></p>';
        echo '<p><button type="button" class="button" id="motoplus-clear-gallery">Clear Images</button></p>';
        echo '<p class="description">Tip: drag images in the media picker to choose order. First image is used as the main card image.</p>';
    }

    private function gallery_preview_html($ids) {
        if (!$ids) return '<em>No gallery images selected.</em>';
        $html = '';
        foreach (array_filter(array_map('absint', explode(',', $ids))) as $id) $html .= wp_get_attachment_image($id, 'thumbnail');
        return $html;
    }

    public function lead_meta_box($post) {
        wp_nonce_field('motoplus_save_lead', 'motoplus_lead_nonce');
        echo '<div class="motoplus-admin-grid">';
        foreach ($this->lead_fields() as $key => $field) {
            $value = get_post_meta($post->ID, self::META_PREFIX . 'lead_' . $key, true);
            echo '<p class="motoplus-field"><label>'.esc_html($field['label']).'</label>';
            $name = 'motoplus_lead['.esc_attr($key).']';
            if ($field['type'] === 'textarea') echo '<textarea name="'.$name.'" rows="5">'.esc_textarea($value).'</textarea>';
            elseif ($field['type'] === 'select') { echo '<select name="'.$name.'">'; foreach($field['options'] as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected($value,$k,false).'>'.esc_html($v).'</option>'; echo '</select>'; }
            else echo '<input type="'.esc_attr($field['type']).'" name="'.$name.'" value="'.esc_attr($value).'">';
            echo '</p>';
        }
        echo '</div>';
    }

    public function save_vehicle($post_id, $post) {
        if (!isset($_POST['motoplus_vehicle_nonce']) || !wp_verify_nonce($_POST['motoplus_vehicle_nonce'], 'motoplus_save_vehicle')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $data = $_POST['motoplus_vehicle'] ?? [];
        foreach ($this->vehicle_fields() as $key => $field) {
            $value = $data[$key] ?? '';
            if ($field['type'] === 'checkbox') $value = isset($data[$key]) ? '1' : '0';
            elseif (in_array($field['type'], ['number'])) $value = $value === '' ? '' : floatval($value);
            else $value = sanitize_text_field($value);
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }
    }

    public function save_lead($post_id, $post) {
        if (!isset($_POST['motoplus_lead_nonce']) || !wp_verify_nonce($_POST['motoplus_lead_nonce'], 'motoplus_save_lead')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $data = $_POST['motoplus_lead'] ?? [];
        foreach ($this->lead_fields() as $key => $field) {
            $value = $data[$key] ?? '';
            $value = $field['type'] === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value);
            update_post_meta($post_id, self::META_PREFIX . 'lead_' . $key, $value);
        }
    }

    public function admin_menu() {
        add_submenu_page('edit.php?post_type='.self::VEHICLE_POST_TYPE, 'Motoplus Dashboard', 'Dashboard', 'edit_posts', 'motoplus-dashboard', [$this, 'dashboard_page']);
        add_submenu_page('edit.php?post_type='.self::VEHICLE_POST_TYPE, 'Motoplus Settings', 'Settings', 'manage_options', 'motoplus-settings', [$this, 'settings_page']);
        add_submenu_page('edit.php?post_type='.self::VEHICLE_POST_TYPE, 'Import Vehicle', 'Import Vehicle', 'edit_posts', 'motoplus-import', [$this, 'import_page']);
    }


    public function import_page() {
        echo '<div class="wrap motoplus-import-page"><h1>Import Vehicle</h1>';
        echo '<p>Import vehicles you own or have permission to reuse. Vehicles are saved as drafts so you can check everything before publishing.</p>';
        echo '<div class="motoplus-import-box">';
        echo '<h2>Paste HTML Source</h2>';
        echo '<p class="description">Recommended for UsedCarsNI pages that block direct server fetching. Open the listing, right click, choose <strong>View Page Source</strong>, copy all, then paste below.</p>';
        echo '<label for="motoplus_import_html"><strong>Listing HTML</strong></label>';
        echo '<textarea id="motoplus_import_html" class="large-text code" rows="14" placeholder="Paste full page source HTML here..."></textarea>';
        echo '<p><button type="button" class="button button-primary" id="motoplus_import_html_btn">Extract from HTML</button> <span id="motoplus_import_html_result"></span></p>';
        echo '</div>';
        echo '<div class="motoplus-import-box" style="margin-top:20px;">';
        echo '<h2>Import from URL</h2>';
        echo '<p class="description">This may be blocked by some sites. Use the HTML option above if you see HTTP 403.</p>';
        echo '<label for="motoplus_import_url"><strong>Listing URL</strong></label><input type="url" id="motoplus_import_url" class="large-text" placeholder="https://www.usedcarsni.com/...">';
        echo '<p><button type="button" class="button" id="motoplus_import_usedcarsni">Import from URL</button> <span id="motoplus_import_result"></span></p>';
        echo '</div>';
        echo '<div id="motoplus_import_preview"></div></div>';
    }

    public function register_settings() { register_setting('motoplus_v1_settings_group', self::OPTION_KEY, [$this, 'sanitize_settings']); }
    public function sanitize_settings($input) {
        return [
            'accent_colour' => sanitize_hex_color($input['accent_colour'] ?? '#0b5fff'),
            'button_colour' => sanitize_hex_color($input['button_colour'] ?? '#0b5fff'),
            'button_text_colour' => sanitize_hex_color($input['button_text_colour'] ?? '#ffffff'),
            'page_background' => sanitize_hex_color($input['page_background'] ?? '#ffffff'),
            'card_background' => sanitize_hex_color($input['card_background'] ?? '#ffffff'),
            'field_background' => sanitize_hex_color($input['field_background'] ?? '#ffffff'),
            'border_colour' => sanitize_hex_color($input['border_colour'] ?? '#d0d5dd'),
            'text_colour' => sanitize_hex_color($input['text_colour'] ?? '#111827'),
            'muted_text_colour' => sanitize_hex_color($input['muted_text_colour'] ?? '#667085'),
            'button_radius' => absint($input['button_radius'] ?? 12),
            'card_radius' => absint($input['card_radius'] ?? 20),
            'lead_email' => sanitize_email($input['lead_email'] ?? get_option('admin_email')),
            'dealer_phone' => sanitize_text_field($input['dealer_phone'] ?? ''),
            'stock_page_url' => esc_url_raw($input['stock_page_url'] ?? ''),
            'lookup_provider' => sanitize_text_field($input['lookup_provider'] ?? 'manual'),
            'lookup_api_key' => sanitize_text_field($input['lookup_api_key'] ?? ''),
            'openai_api_key' => sanitize_text_field($input['openai_api_key'] ?? ''),
        ];
    }
    private function settings() {
        return wp_parse_args(get_option(self::OPTION_KEY, []), [
            'accent_colour'=>'#0b5fff','button_colour'=>'#0b5fff','button_text_colour'=>'#ffffff',
            'page_background'=>'#ffffff','card_background'=>'#ffffff','field_background'=>'#ffffff','border_colour'=>'#d0d5dd',
            'text_colour'=>'#111827','muted_text_colour'=>'#667085','button_radius'=>12,'card_radius'=>20,
            'lead_email'=>get_option('admin_email'),'dealer_phone'=>'','stock_page_url'=>'','lookup_provider'=>'manual','lookup_api_key'=>'','openai_api_key'=>''
        ]);
    }

    public function dashboard_page() {
        $live = wp_count_posts(self::VEHICLE_POST_TYPE)->publish ?? 0;
        $leads = wp_count_posts(self::LEAD_POST_TYPE)->publish ?? 0;
        $featured = $this->count_meta('featured', '1');
        $reserved = $this->count_meta('status', 'Reserved');
        echo '<div class="wrap motoplus-dashboard"><h1>Motoplus Dashboard</h1><div class="motoplus-stats"><div><strong>'.intval($live).'</strong><span>Vehicles Live</span></div><div><strong>'.intval($featured).'</strong><span>Featured</span></div><div><strong>'.intval($reserved).'</strong><span>Reserved</span></div><div><strong>'.intval($leads).'</strong><span>Total Enquiries</span></div></div><h2>Shortcodes</h2><p><code>[motoplus_stock]</code> <code>[motoplus_featured]</code> <code>[motoplus_latest limit="6"]</code> <code>[motoplus_search]</code></p></div>';
    }
    private function count_meta($key, $value) {
        $q = new WP_Query(['post_type'=>self::VEHICLE_POST_TYPE,'posts_per_page'=>1,'fields'=>'ids','meta_key'=>self::META_PREFIX.$key,'meta_value'=>$value]);
        return $q->found_posts;
    }

    public function settings_page() {
        $s = $this->settings();
        $opt = self::OPTION_KEY;
        echo '<div class="wrap motoplus-settings"><h1>Motoplus Settings</h1><form method="post" action="options.php">';
        settings_fields('motoplus_v1_settings_group');
        echo '<h2>Branding & Design</h2><p>These settings control the public vehicle listings, stock page, buttons, forms and cards.</p>';
        echo '<table class="form-table">';
        $colour_fields = [
            'accent_colour'=>'Accent Colour / Badges', 'button_colour'=>'Button Colour', 'button_text_colour'=>'Button Text Colour',
            'page_background'=>'Page Background', 'card_background'=>'Card Background', 'field_background'=>'Search & Field Background',
            'border_colour'=>'Border Colour', 'text_colour'=>'Text Colour', 'muted_text_colour'=>'Muted Text Colour'
        ];
        foreach ($colour_fields as $key=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="color" name="'.$opt.'['.$key.']" value="'.esc_attr($s[$key]).'"></td></tr>';
        echo '<tr><th>Button Radius</th><td><input type="number" min="0" max="40" name="'.$opt.'[button_radius]" value="'.esc_attr($s['button_radius']).'"> px</td></tr>';
        echo '<tr><th>Card Radius</th><td><input type="number" min="0" max="40" name="'.$opt.'[card_radius]" value="'.esc_attr($s['card_radius']).'"> px</td></tr>';
        echo '</table><hr><h2>Dealer Details</h2><table class="form-table">';
        echo '<tr><th>Dealer Phone Number</th><td><input type="text" class="regular-text" name="'.$opt.'[dealer_phone]" value="'.esc_attr($s['dealer_phone']).'"><p class="description">Used for the Call button on vehicle pages. Example: 028 0000 0000 or 07700 900000.</p></td></tr>';
        echo '<tr><th>Stock Page URL</th><td><input type="url" class="regular-text" name="'.$opt.'[stock_page_url]" value="'.esc_attr($s['stock_page_url']).'" placeholder="https://example.com/stock/"><p class="description">Used by standalone search bars, for example a homepage search section. Leave blank to search the current page.</p></td></tr>';
        echo '<tr><th>Lead Notification Email</th><td><input type="email" class="regular-text" name="'.$opt.'[lead_email]" value="'.esc_attr($s['lead_email']).'"></td></tr>';
        echo '</table><hr><h2>Integrations</h2><table class="form-table">';
        echo '<tr><th>Vehicle Lookup Provider</th><td><select name="'.$opt.'[lookup_provider]"><option value="manual" '.selected($s['lookup_provider'],'manual',false).'>Manual Only</option><option value="dvla" '.selected($s['lookup_provider'],'dvla',false).'>DVLA VES</option><option value="ukvd" '.selected($s['lookup_provider'],'ukvd',false).'>UK Vehicle Data</option><option value="custom" '.selected($s['lookup_provider'],'custom',false).'>Custom API</option></select><p class="description">DVLA registrations may be closed. This framework keeps Motoplus ready for another provider.</p></td></tr>';
        echo '<tr><th>Lookup API Key</th><td><input type="password" class="regular-text" name="'.$opt.'[lookup_api_key]" value="'.esc_attr($s['lookup_api_key']).'"></td></tr>';
        echo '<tr><th>OpenAI API Key</th><td><input type="password" class="regular-text" name="'.$opt.'[openai_api_key]" value="'.esc_attr($s['openai_api_key']).'"><p class="description">Optional for future live AI description generation. V1 includes local draft generation without an API key.</p></td></tr></table>';
        submit_button(); echo '</form></div>';
    }

    public function admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [self::VEHICLE_POST_TYPE, self::LEAD_POST_TYPE]) && strpos($hook, 'motoplus') === false) return;
        wp_enqueue_media();
        wp_enqueue_script('motoplus-admin', plugin_dir_url(__FILE__) . 'assets/motoplus-admin.js', ['jquery'], self::VERSION, true);
        wp_localize_script('motoplus-admin', 'motoplusAdmin', ['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('motoplus_admin_nonce')]);
        wp_enqueue_style('motoplus-admin', plugin_dir_url(__FILE__) . 'assets/motoplus-admin.css', [], self::VERSION);
    }

    public function frontend_assets() {
        wp_enqueue_style('motoplus-front', plugin_dir_url(__FILE__) . 'assets/motoplus-front.css', [], self::VERSION);
        wp_enqueue_script('motoplus-front', plugin_dir_url(__FILE__) . 'assets/motoplus-front.js', ['jquery'], self::VERSION, true);
        wp_localize_script('motoplus-front', 'motoplusFront', ['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('motoplus_front_nonce')]);
        $s = $this->settings();
        wp_add_inline_style('motoplus-front', ':root{--motoplus-accent:'.esc_attr($s['accent_colour']).';--motoplus-button:'.esc_attr($s['button_colour']).';--motoplus-button-text:'.esc_attr($s['button_text_colour']).';--motoplus-page-bg:'.esc_attr($s['page_background']).';--motoplus-card-bg:'.esc_attr($s['card_background']).';--motoplus-field-bg:'.esc_attr($s['field_background']).';--motoplus-border:'.esc_attr($s['border_colour']).';--motoplus-text:'.esc_attr($s['text_colour']).';--motoplus-muted:'.esc_attr($s['muted_text_colour']).';--motoplus-button-radius:'.absint($s['button_radius']).'px;--motoplus-card-radius:'.absint($s['card_radius']).'px;}');
    }

    private function meta($post_id, $key) { return get_post_meta($post_id, self::META_PREFIX . $key, true); }
    private function money($value) { return ($value === '' || $value === null) ? '' : '£' . number_format((float)$value, 0); }
    private function miles($value) { return ($value === '' || $value === null) ? '' : number_format((float)$value, 0) . ' miles'; }
    private function vehicle_url($id) { return get_permalink($id); }
    private function is_new_arrival($id) { return (time() - get_post_time('U', true, $id)) <= 14 * DAY_IN_SECONDS; }
    private function spec_icon($key) { $icons = ['year'=>'📅','mileage'=>'🛣️','fuel'=>'⛽','gearbox'=>'⚙️','engine'=>'🔧','body'=>'🚗','colour'=>'🎨','doors'=>'🚪']; return $icons[$key] ?? '•'; }

    public function stock_shortcode($atts) { return $this->render_stock(shortcode_atts(['featured'=>'','limit'=>24,'show_search'=>'yes'], $atts)); }
    public function featured_shortcode($atts) { $atts = shortcode_atts(['limit'=>3], $atts); return $this->render_stock(['featured'=>'1','limit'=>$atts['limit'],'show_search'=>'no']); }
    public function latest_shortcode($atts) { $atts = shortcode_atts(['limit'=>6], $atts); return $this->render_stock(['featured'=>'','limit'=>$atts['limit'],'show_search'=>'no']); }
    public function search_shortcode($atts) { ob_start(); echo '<div class="motoplus-stock-wrap">'; $this->filter_bar(); echo '</div>'; return ob_get_clean(); }

    private function render_stock($atts) {
        $meta_query = [['key'=>self::META_PREFIX.'status','value'=>'Sold','compare'=>'!=']];
        if ($atts['featured'] === '1') $meta_query[] = ['key'=>self::META_PREFIX.'featured','value'=>'1'];
        foreach (['make','fuel','gearbox','body'] as $filter) if (!empty($_GET[$filter])) $meta_query[] = ['key'=>self::META_PREFIX.$filter,'value'=>sanitize_text_field(wp_unslash($_GET[$filter]))];
        if (!empty($_GET['max_price'])) $meta_query[] = ['key'=>self::META_PREFIX.'price','value'=>floatval($_GET['max_price']),'compare'=>'<=','type'=>'NUMERIC'];

        $query_args = [
            'post_type'=>self::VEHICLE_POST_TYPE,
            'posts_per_page'=>absint($atts['limit']),
            'meta_query'=>$meta_query,
            'orderby'=>'date',
            'order'=>'DESC'
        ];

        $keyword = sanitize_text_field(wp_unslash($_GET['vehicle_search'] ?? ''));
        if ($keyword !== '') {
            $matching_ids = $this->vehicle_keyword_ids($keyword);
            $query_args['post__in'] = $matching_ids ? $matching_ids : [0];
        }

        $q = new WP_Query($query_args);
        ob_start(); echo '<div class="motoplus-stock-wrap">'; if ($atts['show_search'] === 'yes') $this->filter_bar();
        if ($q->have_posts()) { echo '<div class="motoplus-results-count">Showing '.intval($q->found_posts).' vehicle'.($q->found_posts == 1 ? '' : 's').'</div><div class="motoplus-vehicle-grid">'; while($q->have_posts()) { $q->the_post(); $this->vehicle_card(get_the_ID()); } echo '</div>'; } else echo '<div class="motoplus-empty">No vehicles found. Try changing the filters.</div>';
        wp_reset_postdata(); echo '</div>'; return ob_get_clean();
    }

    private function vehicle_keyword_ids($keyword) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        $meta_keys = [self::META_PREFIX.'make', self::META_PREFIX.'model', self::META_PREFIX.'variant', self::META_PREFIX.'registration', self::META_PREFIX.'fuel', self::META_PREFIX.'gearbox', self::META_PREFIX.'body'];
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key IN ($placeholders) WHERE p.post_type = %s AND p.post_status = 'publish' AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)",
            array_merge($meta_keys, [self::VEHICLE_POST_TYPE, $like, $like])
        );
        return array_map('absint', $wpdb->get_col($sql));
    }

    private function get_unique_meta_values($key) { global $wpdb; return $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value != '' AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC", self::META_PREFIX.$key, self::VEHICLE_POST_TYPE)); }
    private function filter_bar() {
        $s = $this->settings();
        $action = $s['stock_page_url'] ? esc_url($s['stock_page_url']) : '';
        echo '<form class="motoplus-filter-bar" method="get" action="'.$action.'"><input type="search" name="vehicle_search" placeholder="Search make or model" value="'.esc_attr($_GET['vehicle_search'] ?? '').'">';
        foreach (['make'=>'Make','fuel'=>'Fuel','gearbox'=>'Transmission','body'=>'Body Type'] as $key=>$label) $this->select_filter($key,$label,$this->get_unique_meta_values($key));
        echo '<input type="number" name="max_price" placeholder="Max price" value="'.esc_attr($_GET['max_price'] ?? '').'"><button type="submit">Search Stock</button><a class="motoplus-reset" href="'.esc_url($action ?: remove_query_arg(['vehicle_search','make','fuel','gearbox','body','max_price'])).'">Reset</a></form>';
    }
    private function select_filter($name, $label, $options) { echo '<select name="'.esc_attr($name).'"><option value="">'.esc_html($label).'</option>'; foreach($options as $option) echo '<option value="'.esc_attr($option).'" '.selected($_GET[$name] ?? '', $option, false).'>'.esc_html($option).'</option>'; echo '</select>'; }

    private function vehicle_card($id) {
        $status = $this->meta($id,'status'); $featured = $this->meta($id,'featured'); $previous = $this->meta($id,'previous_price'); $price = $this->meta($id,'price');
        $url = $this->vehicle_url($id);
        echo '<article class="motoplus-card">';
        echo '<a class="motoplus-card-img" href="'.esc_url($url).'">'.$this->vehicle_image($id, 'large');
        if ($status && $status !== 'In Stock') echo '<span class="motoplus-badge status">'.esc_html($status).'</span>'; elseif ($previous && $price && $previous > $price) echo '<span class="motoplus-badge reduced">Just Reduced</span>'; elseif ($this->is_new_arrival($id)) echo '<span class="motoplus-badge new">New Arrival</span>'; elseif ($featured === '1') echo '<span class="motoplus-badge">Featured</span>';
        echo '</a><div class="motoplus-card-body"><h3><a href="'.esc_url($url).'">'.esc_html(get_the_title($id)).'</a></h3><div class="motoplus-price">'.esc_html($this->money($price)).'</div>';
        echo '<div class="motoplus-finance-placeholder">Finance options available</div>';
        echo '<div class="motoplus-spec-row">';
        foreach (['year'=>'Year','mileage'=>'Mileage','fuel'=>'Fuel','gearbox'=>'Trans'] as $key=>$label) { $val = $key==='mileage' ? $this->miles($this->meta($id,$key)) : $this->meta($id,$key); if ($val !== '') echo '<span><b>'.esc_html($this->spec_icon($key)).'</b> '.esc_html($val).'</span>'; }
        echo '</div>';
        echo '<div class="motoplus-actions"><a class="motoplus-btn" href="'.esc_url($url).'">View Vehicle</a><a class="motoplus-btn ghost" href="'.esc_url($url).'#motoplus-enquire">Enquire</a></div></div></article>';
    }
    private function vehicle_image($id, $size='large') { if (has_post_thumbnail($id)) return get_the_post_thumbnail($id, $size); $gallery = $this->meta($id,'gallery'); $first = absint(explode(',', $gallery)[0] ?? 0); if ($first) return wp_get_attachment_image($first, $size); return '<div class="motoplus-placeholder">Vehicle Image</div>'; }

    public function single_vehicle_content($content) {
        if (!is_singular(self::VEHICLE_POST_TYPE) || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID(); ob_start();
        $settings = $this->settings();
        $phone_raw = trim($settings['dealer_phone']);
        $phone_href = preg_replace('/[^0-9+]/', '', $phone_raw);
        echo '<div class="motoplus-single"><div class="motoplus-single-top"><div class="motoplus-gallery">'.$this->single_gallery($id).'</div><aside class="motoplus-summary"><span class="motoplus-status">'.esc_html($this->meta($id,'status') ?: 'In Stock').'</span><h1>'.esc_html(get_the_title($id)).'</h1><div class="motoplus-single-price">'.esc_html($this->money($this->meta($id,'price'))).'</div>';
        echo '<div class="motoplus-key-grid">';
        foreach (['year'=>'Year','mileage'=>'Mileage','fuel'=>'Fuel','gearbox'=>'Transmission','engine'=>'Engine','body'=>'Body','colour'=>'Colour','doors'=>'Doors'] as $key=>$label) { $val = $key==='mileage' ? $this->miles($this->meta($id,$key)) : $this->meta($id,$key); if ($val !== '') echo '<div><span class="motoplus-spec-icon">'.esc_html($this->spec_icon($key)).'</span><strong>'.esc_html($val).'</strong><span>'.esc_html($label).'</span></div>'; }
        echo '</div><div class="motoplus-contact-buttons">';
        if ($phone_href) echo '<a class="motoplus-btn wide ghost motoplus-call-btn" href="tel:'.esc_attr($phone_href).'"><span class="motoplus-btn-icon">☎</span> Call</a>';
        echo '<a class="motoplus-btn wide motoplus-mail-btn" href="#motoplus-enquire"><span class="motoplus-btn-icon">✉</span> Enquire</a></div></aside></div>';
        echo $this->vehicle_highlights($id);
        echo '<section class="motoplus-specs"><h2>Full Specification</h2><div class="motoplus-spec-table">';
        foreach ($this->vehicle_fields() as $key=>$field) { if (in_array($key,['gallery','featured','previous_price'])) continue; $val = $key==='mileage' ? $this->miles($this->meta($id,$key)) : $this->meta($id,$key); if ($val !== '') echo '<div><span>'.esc_html($field['label']).'</span><strong>'.esc_html($val).'</strong></div>'; }
        echo '</div></section>';
        echo '<section class="motoplus-description"><h2>Description</h2>'.$content.'</section>'.$this->enquiry_form($id).$this->similar_vehicles($id);
        if ($phone_href) echo '<div class="motoplus-mobile-sticky"><a href="tel:'.esc_attr($phone_href).'">☎ Call</a><a href="#motoplus-enquire">✉ Enquire</a></div>';
        echo '</div>'; return ob_get_clean();
    }
    private function vehicle_highlights($id) {
        $items = [];
        if ($this->is_new_arrival($id)) $items[] = 'New arrival';
        if ($this->meta($id,'previous_price') && $this->meta($id,'price') && $this->meta($id,'previous_price') > $this->meta($id,'price')) $items[] = 'Recently reduced';
        if (stripos($this->meta($id,'service_history'), 'Full') !== false) $items[] = 'Full service history';
        if ((int)$this->meta($id,'owners') === 1) $items[] = '1 previous owner';
        if ((int)$this->meta($id,'mileage') > 0 && (int)$this->meta($id,'mileage') < 40000) $items[] = 'Low mileage';
        if (!$items) return '';
        $html = '<section class="motoplus-highlights"><h2>Vehicle Highlights</h2><div>';
        foreach ($items as $item) $html .= '<span>✓ '.esc_html($item).'</span>';
        return $html.'</div></section>';
    }
    private function similar_vehicles($id) {
        $make = $this->meta($id,'make');
        if (!$make) return '';
        $q = new WP_Query(['post_type'=>self::VEHICLE_POST_TYPE,'posts_per_page'=>3,'post__not_in'=>[$id],'meta_query'=>[['key'=>self::META_PREFIX.'status','value'=>'Sold','compare'=>'!='],['key'=>self::META_PREFIX.'make','value'=>$make]]]);
        if (!$q->have_posts()) return '';
        ob_start(); echo '<section class="motoplus-similar"><h2>Similar Vehicles</h2><div class="motoplus-vehicle-grid compact">';
        while($q->have_posts()) { $q->the_post(); $this->vehicle_card(get_the_ID()); }
        echo '</div></section>'; wp_reset_postdata(); return ob_get_clean();
    }
    private function single_gallery($id) { $ids = array_filter(array_map('absint', explode(',', $this->meta($id,'gallery')))); if (!$ids && has_post_thumbnail($id)) return get_the_post_thumbnail($id,'large'); if (!$ids) return $this->vehicle_image($id,'large'); $html = '<div class="motoplus-main-photo">'.wp_get_attachment_image($ids[0], 'large').'</div><div class="motoplus-thumbs">'; foreach($ids as $img) $html .= wp_get_attachment_image($img, 'thumbnail'); return $html.'</div>'; }
    private function enquiry_form($id) { return '<section id="motoplus-enquire" class="motoplus-enquiry"><h2>Enquire About This Vehicle</h2><form class="motoplus-lead-form"><input type="hidden" name="vehicle_id" value="'.esc_attr($id).'"><input type="hidden" name="vehicle_title" value="'.esc_attr(get_the_title($id)).'"><div class="motoplus-form-grid"><input name="name" placeholder="Your name" required><input name="phone" placeholder="Phone number" required><input type="email" name="email" placeholder="Email address"></div><textarea name="message" placeholder="Message">Is this vehicle still available?</textarea><button class="motoplus-btn" type="submit"><span class="motoplus-btn-icon">✉</span> Enquire</button><span class="motoplus-lead-result"></span></form></section>'; }

    public function ajax_submit_lead() {
        check_ajax_referer('motoplus_front_nonce', 'nonce');
        $vehicle_id = absint($_POST['vehicle_id'] ?? 0); $name = sanitize_text_field($_POST['name'] ?? ''); $phone = sanitize_text_field($_POST['phone'] ?? ''); $email = sanitize_email($_POST['email'] ?? ''); $message = sanitize_textarea_field($_POST['message'] ?? ''); $vehicle_title = sanitize_text_field($_POST['vehicle_title'] ?? 'Vehicle Enquiry');
        if (!$name || !$phone) wp_send_json_error(['message'=>'Please enter your name and phone number.']);
        $lead_id = wp_insert_post(['post_type'=>self::LEAD_POST_TYPE,'post_status'=>'publish','post_title'=>$name.' - '.$vehicle_title]);
        foreach(['vehicle_id'=>$vehicle_id,'vehicle_title'=>$vehicle_title,'name'=>$name,'phone'=>$phone,'email'=>$email,'message'=>$message,'status'=>'New'] as $k=>$v) update_post_meta($lead_id, self::META_PREFIX.'lead_'.$k, $v);
        $s = $this->settings();
        wp_mail($s['lead_email'], 'New Vehicle Enquiry: '.$vehicle_title, "Vehicle: {$vehicle_title}\nName: {$name}\nPhone: {$phone}\nEmail: {$email}\n\nMessage:\n{$message}");
        wp_send_json_success(['message'=>'Thanks, your enquiry has been sent.']);
    }

    public function ajax_lookup_vehicle() {
        check_ajax_referer('motoplus_admin_nonce', 'nonce'); if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Permission denied.']);
        $s = $this->settings(); $reg = strtoupper(preg_replace('/\s+/', '', sanitize_text_field($_POST['registration'] ?? '')));
        if (!$reg) wp_send_json_error(['message'=>'Enter a registration first.']);
        if ($s['lookup_provider'] === 'manual') wp_send_json_error(['message'=>'Vehicle lookup is set to Manual Only in Motoplus Settings.']);
        if (empty($s['lookup_api_key'])) wp_send_json_error(['message'=>'Add your lookup API key in Motoplus Settings first.']);
        wp_send_json_error(['message'=>'Lookup provider framework is ready. Connect the selected provider endpoint once your API access is active. Manual entry still works.']);
    }

    public function ajax_generate_description() {
        check_ajax_referer('motoplus_admin_nonce', 'nonce'); if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Permission denied.']);
        $d = array_map('sanitize_text_field', $_POST['vehicle'] ?? []);
        $title = trim(($d['year'] ?? '').' '.($d['make'] ?? '').' '.($d['model'] ?? '').' '.($d['variant'] ?? ''));
        $bits = array_filter([$d['mileage'] ?? '' ? number_format((float)$d['mileage']).' miles' : '', $d['fuel'] ?? '', $d['gearbox'] ?? '', $d['engine'] ?? '', $d['service_history'] ?? '']);
        $desc = "This {$title} is a well-presented example, offering a great mix of comfort, practicality and value. Key details include ".implode(', ', $bits).".\n\nIt is available to view now and would suit anyone looking for a clean, reliable vehicle with a modern specification. Contact us today to arrange a viewing or ask any questions.";
        wp_send_json_success(['description'=>$desc]);
    }


    public function ajax_import_usedcarsni() {
        check_ajax_referer('motoplus_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Permission denied.']);
        $url = esc_url_raw(trim($_POST['url'] ?? ''));
        if (!$url) wp_send_json_error(['message'=>'Please enter a listing URL.']);
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host || stripos($host, 'usedcarsni.com') === false) wp_send_json_error(['message'=>'Please enter a valid UsedCarsNI URL.']);

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'redirection' => 5,
            'headers' => ['User-Agent' => 'Motoplus Vehicle Importer/1.3; '.home_url('/')]
        ]);
        if (is_wp_error($response)) wp_send_json_error(['message'=>$response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) wp_send_json_error(['message'=>'Could not fetch listing. HTTP '.$code]);
        $html = wp_remote_retrieve_body($response);
        if (!$html) wp_send_json_error(['message'=>'The listing returned no content.']);

        $data = $this->parse_usedcarsni_listing($html, $url);
        $this->create_imported_vehicle_response($data, $url);
    }

    public function ajax_import_html() {
        check_ajax_referer('motoplus_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Permission denied.']);
        $html = wp_unslash($_POST['html'] ?? '');
        if (!$html || strlen(trim($html)) < 500) wp_send_json_error(['message'=>'Please paste the full page source HTML.']);
        $source_url = esc_url_raw(trim($_POST['source_url'] ?? 'https://www.usedcarsni.com/'));
        $data = $this->parse_usedcarsni_listing($html, $source_url);
        $this->create_imported_vehicle_response($data, $source_url);
    }

    private function create_imported_vehicle_response($data, $source_url = '') {
        if (empty($data['title']) && empty($data['fields']['make']) && empty($data['fields']['model'])) wp_send_json_error(['message'=>'Could not find enough vehicle data in that HTML.']);

        $post_id = wp_insert_post([
            'post_type' => self::VEHICLE_POST_TYPE,
            'post_status' => 'draft',
            'post_title' => $data['title'] ?: trim(($data['fields']['make'] ?? '').' '.($data['fields']['model'] ?? '')),
            'post_content' => $data['description'] ?? '',
        ], true);
        if (is_wp_error($post_id)) wp_send_json_error(['message'=>$post_id->get_error_message()]);

        foreach (($data['fields'] ?? []) as $key => $value) {
            if (array_key_exists($key, $this->vehicle_fields())) update_post_meta($post_id, self::META_PREFIX.$key, sanitize_text_field($value));
        }
        update_post_meta($post_id, self::META_PREFIX.'status', 'In Stock');
        if ($source_url) update_post_meta($post_id, self::META_PREFIX.'import_source_url', esc_url_raw($source_url));

        $image_ids = $this->import_remote_images($data['images'] ?? [], $post_id, 20);
        if ($image_ids) {
            set_post_thumbnail($post_id, $image_ids[0]);
            update_post_meta($post_id, self::META_PREFIX.'gallery', implode(',', $image_ids));
        }

        wp_send_json_success([
            'message' => 'Imported as a draft vehicle. Please review before publishing.',
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'title' => get_the_title($post_id),
            'image_count' => count($image_ids),
        ]);
    }

    private function parse_usedcarsni_listing($html, $url) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xp = new DOMXPath($dom);
        $text = function($query) use ($xp) {
            $n = $xp->query($query);
            if ($n && $n->length) return trim(preg_replace('/\s+/', ' ', $n->item(0)->textContent));
            return '';
        };
        $attr = function($query, $name) use ($xp) {
            $n = $xp->query($query);
            if ($n && $n->length) return trim($n->item(0)->getAttribute($name));
            return '';
        };

        $jsonld_data = [];
        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            $json = trim($script->textContent);
            if (!$json) continue;
            $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5), true);
            if (!$decoded) continue;
            $items = isset($decoded['@graph']) && is_array($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $type = $item['@type'] ?? '';
                if (is_array($type)) $type = implode(' ', $type);
                if (stripos((string)$type, 'Car') !== false || isset($item['vehicleIdentificationNumber']) || isset($item['mileageFromOdometer'])) {
                    $jsonld_data = $item;
                    break 2;
                }
            }
        }

        $title = $jsonld_data['name'] ?? '';
        if (!$title) $title = $text('//h1') ?: $attr('//meta[@property="og:title"]', 'content');
        if (!$title) {
            $title_nodes = $xp->query('//title');
            if ($title_nodes && $title_nodes->length) $title = trim(preg_replace('/\s+/', ' ', $title_nodes->item(0)->textContent));
        }
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
        $title = preg_replace('/\s*For Sale\s*\|.*$/i', '', $title);
        $title = preg_replace('/^Used\s+/i', '', $title);
        $title = trim($title);

        $description = $jsonld_data['description'] ?? '';
        if (!$description) $description = $attr('//meta[@property="og:description"]', 'content') ?: $attr('//meta[@name="description"]', 'content');
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5);
        if ($description) {
            $description = preg_replace('/\s*Visit UsedCarsNI\.com.*$/i', '', $description);
            $description = preg_replace('/\s*This content (was copied|was taken) from Used\s*Cars\s*NI\.?.*$/i', '', $description);
            $description = trim($description);
        }
        if (!$description) {
            $desc_nodes = $xp->query('//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"description")]');
            if ($desc_nodes && $desc_nodes->length) $description = trim(preg_replace('/\s+/', ' ', $desc_nodes->item(0)->textContent));
        }

        $page_text = trim(preg_replace('/\s+/', ' ', $dom->textContent));
        $fields = [];
        if (!empty($jsonld_data)) {
            if (!empty($jsonld_data['brand']['name'])) $fields['make'] = $jsonld_data['brand']['name'];
            if (!empty($jsonld_data['model'])) $fields['model'] = is_array($jsonld_data['model']) ? ($jsonld_data['model']['name'] ?? '') : $jsonld_data['model'];
            if (!empty($jsonld_data['vehicleModelDate'])) $fields['year'] = $jsonld_data['vehicleModelDate'];
            if (!empty($jsonld_data['color'])) $fields['colour'] = $jsonld_data['color'];
            if (!empty($jsonld_data['bodyType'])) $fields['body'] = $jsonld_data['bodyType'];
            if (!empty($jsonld_data['fuelType'])) $fields['fuel'] = $jsonld_data['fuelType'];
            if (!empty($jsonld_data['vehicleTransmission'])) $fields['gearbox'] = $jsonld_data['vehicleTransmission'];
            if (!empty($jsonld_data['numberOfDoors'])) $fields['doors'] = $jsonld_data['numberOfDoors'];
            if (!empty($jsonld_data['offers']['price'])) $fields['price'] = $jsonld_data['offers']['price'];
            if (!empty($jsonld_data['mileageFromOdometer']['value'])) $fields['mileage'] = $jsonld_data['mileageFromOdometer']['value'];
        }

        // UsedCarsNI prints many useful details as label/value pairs. This helper captures the value after a label up to the next known label.
        $labels = ['Mileage','Location','Payload','Colour','Color','Engine Size','Fuel Type','Transmission','Doors','Seats','Body Style','Owners','MOT Expiry','Standard Tax','Tax Band','CO2 Emission','Price'];
        $find_value = function($label) use ($page_text, $labels) {
            $next = array_filter($labels, fn($l) => strcasecmp($l, $label) !== 0);
            $next_regex = implode('|', array_map(fn($l) => preg_quote($l, '/'), $next));
            if (preg_match('/'.preg_quote($label, '/').'\s+(.{1,120}?)(?=\s+(?:'.$next_regex.')\b|\s+Seller\b|\s+Features\b|$)/i', $page_text, $m)) {
                return trim($m[1]);
            }
            return '';
        };

        $label_map = [
            'Mileage'=>'mileage','Location'=>'location','Payload'=>'payload','Colour'=>'colour','Color'=>'colour','Engine Size'=>'engine',
            'Fuel Type'=>'fuel','Transmission'=>'gearbox','Doors'=>'doors','Seats'=>'seats','Body Style'=>'body','Owners'=>'owners',
            'MOT Expiry'=>'mot_expiry','Standard Tax'=>'road_tax','Tax Band'=>'tax_band','CO2 Emission'=>'co2','Price'=>'price'
        ];
        foreach ($label_map as $label=>$key) {
            $value = $find_value($label);
            if ($value !== '') $fields[$key] = $value;
        }
        if (empty($fields['price']) && preg_match('/£\s*([0-9][0-9,]*(?:\s*\+\s*VAT)?)/i', $page_text, $m)) $fields['price'] = $m[1];
        if (empty($fields['mileage']) && preg_match('/([0-9][0-9,]*)\s*miles/i', $page_text, $m)) $fields['mileage'] = $m[1];

        // Infer year, make, model and variant from the listing title, e.g. "2019 Audi A5 40 TFSI Black Edition 2dr S Tronic".
        $clean_title = preg_replace('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+/i', '', $title);
        if (preg_match('/\b(19[8-9][0-9]|20[0-3][0-9])\b/', $clean_title, $m)) $fields['year'] = $fields['year'] ?? $m[1];
        $without_year = trim(preg_replace('/\b(19[8-9][0-9]|20[0-3][0-9])\b/', '', $clean_title, 1));
        $parts = preg_split('/\s+/', $without_year);
        if (count($parts) >= 2) {
            $fields['make'] = $fields['make'] ?? $parts[0];
            $fields['model'] = $fields['model'] ?? $parts[1];
            if (empty($fields['variant']) && count($parts) > 2) $fields['variant'] = implode(' ', array_slice($parts, 2));
        }

        foreach (['price','mileage','doors','owners','seats'] as $num) if (isset($fields[$num])) $fields[$num] = preg_replace('/[^0-9.]/', '', $fields[$num]);
        if (!empty($fields['doors'])) $fields['doors'] = preg_replace('/[^0-9]/', '', $fields['doors']);
        if (!empty($fields['gearbox'])) $fields['gearbox'] = ucfirst(strtolower($fields['gearbox']));
        if (!empty($fields['fuel'])) $fields['fuel'] = ucfirst(strtolower($fields['fuel']));
        $images = [];
        if (!empty($jsonld_data['image'])) {
            if (is_array($jsonld_data['image'])) $images = array_merge($images, $jsonld_data['image']);
            else $images[] = $jsonld_data['image'];
        }
        foreach ($xp->query('//meta[@property="og:image"]') as $img) $images[] = $img->getAttribute('content');
        foreach ($xp->query('//img') as $img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy') ?: $img->getAttribute('src');
            if (!$src) continue;
            if (stripos($src, 'logo') !== false || stripos($src, 'icon') !== false) continue;
            $images[] = $src;
        }
        $base = wp_parse_url($url);
        $base_url = $base['scheme'].'://'.$base['host'];
        $images = array_values(array_unique(array_filter(array_map(function($src) use ($base_url) {
            $src = html_entity_decode(trim($src));
            if (strpos($src, '//') === 0) $src = 'https:'.$src;
            elseif (strpos($src, '/') === 0) $src = $base_url.$src;
            if (!preg_match('/^https?:\/\//i', $src)) return '';
            return $src;
        }, $images))));

        return ['title'=>$title, 'description'=>$description, 'fields'=>$fields, 'images'=>$images];
    }

    private function import_remote_images($urls, $post_id, $limit = 20) {
        if (!$urls) return [];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $ids = [];
        foreach (array_slice(array_unique($urls), 0, $limit) as $url) {
            $tmp = download_url($url, 20);
            if (is_wp_error($tmp)) continue;
            $name = basename(parse_url($url, PHP_URL_PATH));
            if (!$name || strpos($name, '.') === false) $name = 'motoplus-vehicle-image-'.time().'-'.count($ids).'.jpg';
            $file = ['name'=>sanitize_file_name($name), 'tmp_name'=>$tmp];
            $id = media_handle_sideload($file, $post_id);
            if (is_wp_error($id)) { @unlink($tmp); continue; }
            $ids[] = $id;
        }
        return $ids;
    }

    public function vehicle_columns($cols) { return ['cb'=>$cols['cb'],'image'=>'Photo','title'=>'Vehicle','price'=>'Price','status'=>'Status','featured'=>'Featured','date'=>$cols['date']]; }
    public function vehicle_column_content($col,$id) { if($col==='image') echo $this->vehicle_image($id,'thumbnail'); if($col==='price') echo esc_html($this->money($this->meta($id,'price'))); if($col==='status') echo esc_html($this->meta($id,'status')); if($col==='featured') echo $this->meta($id,'featured')==='1'?'Yes':'No'; }
    public function lead_columns($cols) { return ['cb'=>$cols['cb'],'title'=>'Lead','vehicle'=>'Vehicle','phone'=>'Phone','email'=>'Email','lead_status'=>'Status','date'=>$cols['date']]; }
    public function lead_column_content($col,$id) { $p=self::META_PREFIX.'lead_'; if($col==='vehicle') echo esc_html(get_post_meta($id,$p.'vehicle_title',true)); if($col==='phone') echo esc_html(get_post_meta($id,$p.'phone',true)); if($col==='email') echo esc_html(get_post_meta($id,$p.'email',true)); if($col==='lead_status') echo esc_html(get_post_meta($id,$p.'status',true)); }
    public function ajax_save_lead_status() { wp_send_json_success(); }
}

register_activation_hook(__FILE__, ['Motoplus_V1','activate']);
register_deactivation_hook(__FILE__, ['Motoplus_V1','deactivate']);
new Motoplus_V1();
