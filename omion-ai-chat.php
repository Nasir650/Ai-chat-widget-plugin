<?php
/*
Plugin Name: Duff Digital Marketing AI Chat Widget
Plugin URI: https://duffdigitalmarketing.com
Description: AI-powered chat widget with customizable settings
Version: 1.9.07
Author: Duff Digital Marketing Team
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin class
class OmionAIChat {
    private $options;

    public function __construct() {
        // Add all existing actions
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp_footer', array($this, 'add_chat_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Chat handlers
        add_action('wp_ajax_omion_chat_request', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_omion_chat_request', array($this, 'handle_chat_request'));
        
        // Lead capture handlers
        add_action('wp_ajax_omion_capture_lead', array($this, 'handle_lead_capture'));
        add_action('wp_ajax_nopriv_omion_capture_lead', array($this, 'handle_lead_capture'));
        
        // Lead management handlers
        add_action('wp_ajax_omion_get_leads', array($this, 'handle_get_leads'));
        add_action('wp_ajax_omion_export_leads', array($this, 'handle_export_leads'));
        add_action('wp_ajax_omion_delete_lead', array($this, 'handle_delete_lead'));
        add_action('wp_ajax_omion_update_lead_status', array($this, 'handle_update_lead_status'));
    }

    public function handle_chat_request() {
        try {
            error_log('Chat request received');

            if (!isset($_POST['messages'])) {
                error_log('No messages in POST data');
                wp_send_json_error('No messages provided');
                return;
            }

            // Get plugin options
            $options = get_option('omion_ai_chat_options');
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $website_context = isset($options['website_context']) ? $options['website_context'] : '';
            
            if (empty($api_key)) {
                error_log('API key not configured');
                wp_send_json_error('API key not configured');
                return;
            }

            // Parse messages
            $messages = json_decode(stripslashes($_POST['messages']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                wp_send_json_error('Invalid message format');
                return;
            }

            // Create context message
            $context_message = array(
                'role' => 'system',
                'content' => "You are a helpful assistant for " . get_bloginfo('name') . ". " .
                            "Website context: " . $website_context . " " .
                            "Current page: " . (isset($_POST['current_url']) ? $_POST['current_url'] : '') . ". " .
                            "Respond based on this context and be helpful to website visitors. Keep responses concise and friendly."
            );

            // Add context to messages
            array_unshift($messages, $context_message);

            // Make API request
            $response = wp_remote_post('https://codestral.mistral.ai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Codestral Client'
                ],
                'body' => json_encode([
                    'model' => 'codestral-latest',
                    'messages' => $messages,
                    'temperature' => 0.7
                ]),
                'timeout' => 45,
                'sslverify' => true
            ]);

            if (is_wp_error($response)) {
                error_log('WP Error: ' . $response->get_error_message());
                wp_send_json_error($response->get_error_message());
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                wp_send_json_error('API returned status ' . $response_code);
                return;
            }

            $data = json_decode($response_body, true);
            if (!isset($data['choices'][0]['message']['content'])) {
                wp_send_json_error('Invalid API response structure');
                return;
            }

            wp_send_json_success($data);

        } catch (Exception $e) {
            error_log('Chat handler exception: ' . $e->getMessage());
            wp_send_json_error('Internal server error');
        }
    }

    // Lead capture methods
    public function handle_lead_capture() {
        try {
            error_log('Lead capture request received');

            if (!isset($_POST['lead_data'])) {
                wp_send_json_error('No lead data provided');
                return;
            }

            $lead_data = json_decode(stripslashes($_POST['lead_data']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                wp_send_json_error('Invalid lead data format');
                return;
            }

            // Validate required fields
            if (empty($lead_data['id']) || empty($lead_data['email'])) {
                wp_send_json_error('Missing required lead data');
                return;
            }

            // Sanitize lead data
            $sanitized_lead = array(
                'id' => sanitize_text_field($lead_data['id']),
                'email' => sanitize_email($lead_data['email']),
                'name' => isset($lead_data['name']) ? sanitize_text_field($lead_data['name']) : '',
                'phone' => isset($lead_data['phone']) ? sanitize_text_field($lead_data['phone']) : '',
                'score' => intval($lead_data['score']),
                'status' => sanitize_text_field($lead_data['status']),
                'source' => esc_url_raw($lead_data['source']),
                'created' => intval($lead_data['created']),
                'last_activity' => intval($lead_data['lastActivity']),
                'interactions' => isset($lead_data['interactions']) ? $lead_data['interactions'] : array(),
                'interests' => isset($lead_data['interests']) ? array_map('sanitize_text_field', $lead_data['interests']) : array(),
                'user_agent' => sanitize_text_field($lead_data['user_agent']),
                'screen_resolution' => sanitize_text_field($lead_data['screen_resolution']),
                'timezone' => sanitize_text_field($lead_data['timezone']),
                'referrer' => esc_url_raw($lead_data['referrer']),
                'conversation_messages' => intval($lead_data['conversation_messages']),
                'ip_address' => $this->get_client_ip(),
                'captured_at' => current_time('mysql')
            );

            // Save to database
            $result = $this->save_lead_to_database($sanitized_lead);
            
            if ($result) {
                // Send email notification to admin
                $this->send_lead_notification($sanitized_lead);
                
                // Integrate with third-party services if configured
                $this->integrate_with_services($sanitized_lead);
                
                wp_send_json_success('Lead captured successfully');
            } else {
                wp_send_json_error('Failed to save lead');
            }

        } catch (Exception $e) {
            error_log('Lead capture exception: ' . $e->getMessage());
            wp_send_json_error('Internal server error');
        }
    }

    private function save_lead_to_database($lead_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'omion_leads';
        
        // Create table if it doesn't exist
        $this->create_leads_table();
        
        // Check if lead already exists
        $existing_lead = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE lead_id = %s", $lead_data['id'])
        );
        
        if ($existing_lead) {
            // Update existing lead
            return $wpdb->update(
                $table_name,
                array(
                    'email' => $lead_data['email'],
                    'name' => $lead_data['name'],
                    'phone' => $lead_data['phone'],
                    'score' => $lead_data['score'],
                    'status' => $lead_data['status'],
                    'last_activity' => $lead_data['last_activity'],
                    'interactions' => json_encode($lead_data['interactions']),
                    'interests' => json_encode($lead_data['interests']),
                    'conversation_messages' => $lead_data['conversation_messages'],
                    'updated_at' => current_time('mysql')
                ),
                array('lead_id' => $lead_data['id']),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new lead
            return $wpdb->insert(
                $table_name,
                array(
                    'lead_id' => $lead_data['id'],
                    'email' => $lead_data['email'],
                    'name' => $lead_data['name'],
                    'phone' => $lead_data['phone'],
                    'score' => $lead_data['score'],
                    'status' => $lead_data['status'],
                    'source' => $lead_data['source'],
                    'created' => $lead_data['created'],
                    'last_activity' => $lead_data['last_activity'],
                    'interactions' => json_encode($lead_data['interactions']),
                    'interests' => json_encode($lead_data['interests']),
                    'user_agent' => $lead_data['user_agent'],
                    'screen_resolution' => $lead_data['screen_resolution'],
                    'timezone' => $lead_data['timezone'],
                    'referrer' => $lead_data['referrer'],
                    'conversation_messages' => $lead_data['conversation_messages'],
                    'ip_address' => $lead_data['ip_address'],
                    'captured_at' => $lead_data['captured_at'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }
    }

    private function create_leads_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'omion_leads';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            lead_id varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT '',
            phone varchar(50) DEFAULT '',
            score int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'new',
            source text,
            created bigint(20),
            last_activity bigint(20),
            interactions longtext,
            interests text,
            user_agent text,
            screen_resolution varchar(50),
            timezone varchar(100),
            referrer text,
            conversation_messages int(11) DEFAULT 0,
            ip_address varchar(45),
            captured_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY lead_id (lead_id),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function send_lead_notification($lead_data) {
        $admin_email = get_option('admin_email');
        
        $subject = 'New Lead Captured - ' . get_bloginfo('name');
        
        $message = "
        <h2>New Lead Captured!</h2>
        <p>A new lead has been captured through your AI chat widget.</p>
        
        <h3>Lead Details:</h3>
        <ul>
            <li><strong>Email:</strong> {$lead_data['email']}</li>
            <li><strong>Name:</strong> " . ($lead_data['name'] ?: 'Not provided') . "</li>
            <li><strong>Lead Score:</strong> {$lead_data['score']}</li>
            <li><strong>Status:</strong> {$lead_data['status']}</li>
            <li><strong>Source:</strong> {$lead_data['source']}</li>
            <li><strong>Messages Exchanged:</strong> {$lead_data['conversation_messages']}</li>
            <li><strong>Captured:</strong> {$lead_data['captured_at']}</li>
        </ul>
        
        <p><a href='" . admin_url('admin.php?page=omion-ai-chat-leads') . "'>View All Leads</a></p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($admin_email, $subject, $message, $headers);
    }

    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }

    private function integrate_with_services($lead_data) {
        $options = get_option('omion_ai_chat_options');
        
        // Mailchimp integration
        if (!empty($options['mailchimp_api_key']) && !empty($options['mailchimp_list_id'])) {
            $this->add_to_mailchimp($lead_data, $options);
        }
        
        // Webhook integration
        if (!empty($options['webhook_url'])) {
            $this->send_webhook($lead_data, $options['webhook_url']);
        }
    }

    public function handle_get_leads() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'omion_leads';
        
        $page = intval($_GET['page'] ?? 1);
        $per_page = intval($_GET['per_page'] ?? 20);
        $offset = ($page - 1) * $per_page;
        
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['search'] ?? '');
        
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if (!empty($status_filter)) {
            $where_clause .= " AND status = %s";
            $where_values[] = $status_filter;
        }
        
        if (!empty($search)) {
            $where_clause .= " AND (email LIKE %s OR name LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        if (!empty($where_values)) {
            $total = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
        } else {
            $total = $wpdb->get_var($total_query);
        }
        
        // Get leads
        $leads_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        
        $leads = $wpdb->get_results($wpdb->prepare($leads_query, $query_values));
        
        // Process leads data
        $processed_leads = array_map(function($lead) {
            return array(
                'id' => $lead->id,
                'lead_id' => $lead->lead_id,
                'email' => $lead->email,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'score' => intval($lead->score),
                'status' => $lead->status,
                'source' => $lead->source,
                'interests' => json_decode($lead->interests, true) ?: array(),
                'conversation_messages' => intval($lead->conversation_messages),
                'captured_at' => $lead->captured_at,
                'created_at' => $lead->created_at
            );
        }, $leads);
        
        wp_send_json_success(array(
            'leads' => $processed_leads,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }

    public function handle_export_leads() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'omion_leads';
        
        $leads = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
        
        $csv_data = array();
        $csv_data[] = array(
            'Lead ID', 'Email', 'Name', 'Phone', 'Score', 'Status', 'Source', 
            'Interests', 'Messages', 'IP Address', 'User Agent', 'Captured At'
        );
        
        foreach ($leads as $lead) {
            $interests = json_decode($lead->interests, true) ?: array();
            $csv_data[] = array(
                $lead->lead_id,
                $lead->email,
                $lead->name,
                $lead->phone,
                $lead->score,
                $lead->status,
                $lead->source,
                implode(', ', $interests),
                $lead->conversation_messages,
                $lead->ip_address,
                $lead->user_agent,
                $lead->captured_at
            );
        }
        
        wp_send_json_success(array('csv_data' => $csv_data));
    }

    public function handle_update_lead_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $lead_id = sanitize_text_field($_POST['lead_id']);
        $status = sanitize_text_field($_POST['status']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'omion_leads';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('lead_id' => $lead_id),
            array('%s', '%s'),
            array('%s')
        );
        
        if ($result !== false) {
            wp_send_json_success('Status updated');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public function handle_delete_lead() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $lead_id = sanitize_text_field($_POST['lead_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'omion_leads';
        
        $result = $wpdb->delete(
            $table_name,
            array('lead_id' => $lead_id),
            array('%s')
        );
        
        if ($result !== false) {
            wp_send_json_success('Lead deleted');
        } else {
            wp_send_json_error('Failed to delete lead');
        }
    }

    private function add_to_mailchimp($lead_data, $options) {
        $api_key = $options['mailchimp_api_key'];
        $list_id = $options['mailchimp_list_id'];
        
        if (empty($api_key) || empty($list_id)) {
            return;
        }
        
        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/lists/{$list_id}/members";
        
        $data = array(
            'email_address' => $lead_data['email'],
            'status' => 'subscribed',
            'merge_fields' => array(
                'FNAME' => $lead_data['name'] ?: '',
                'LNAME' => '',
                'PHONE' => $lead_data['phone'] ?: ''
            ),
            'tags' => array('omion-chat-lead')
        );
        
        wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 15
        ));
    }

    private function send_webhook($lead_data, $webhook_url) {
        wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($lead_data),
            'timeout' => 15
        ));
    }

    public function add_plugin_page() {
        add_menu_page(
            'Omion AI Chat Settings',
            'Omion AI Chat',
            'manage_options',
            'omion-ai-chat',
            array($this, 'create_admin_page'),
            'dashicons-format-chat'
        );
        
        // Add leads submenu
        add_submenu_page(
            'omion-ai-chat',
            'Lead Management',
            'Leads',
            'manage_options',
            'omion-ai-chat-leads',
            array($this, 'create_leads_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('omion_ai_chat_options');
        ?>
        <div class="wrap">
            <h1>Omion AI Chat Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('omion_ai_chat_options_group');
                do_settings_sections('omion-ai-chat-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function create_leads_page() {
        ?>
        <div class="wrap">
            <h1>Lead Management</h1>
            <div id="omionLeadsApp">
                <div class="omion-leads-header">
                    <div class="omion-leads-stats">
                        <div class="omion-stat-card">
                            <div class="omion-stat-number" id="totalLeads">-</div>
                            <div class="omion-stat-label">Total Leads</div>
                        </div>
                        <div class="omion-stat-card">
                            <div class="omion-stat-number" id="todayLeads">-</div>
                            <div class="omion-stat-label">Today</div>
                        </div>
                        <div class="omion-stat-card">
                            <div class="omion-stat-number" id="conversionRate">-</div>
                            <div class="omion-stat-label">Conversion Rate</div>
                        </div>
                        <div class="omion-stat-card">
                            <div class="omion-stat-number" id="avgScore">-</div>
                            <div class="omion-stat-label">Avg Score</div>
                        </div>
                    </div>
                    
                    <div class="omion-leads-controls">
                        <input type="text" id="leadsSearch" placeholder="Search leads..." class="omion-search-input">
                        <select id="statusFilter" class="omion-status-filter">
                            <option value="">All Status</option>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                        </select>
                        <button id="exportLeads" class="omion-export-btn">Export CSV</button>
                        <button id="refreshLeads" class="omion-refresh-btn">Refresh</button>
                    </div>
                </div>
                
                <div id="leadsTable" class="omion-leads-table">
                    <!-- Leads will be loaded here -->
                </div>
                
                <div id="leadsPagination" class="omion-pagination">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
        
        <style>
            .omion-leads-header { margin-bottom: 20px; }
            .omion-leads-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
            .omion-stat-card { padding: 15px; background: white; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
            .omion-stat-number { font-size: 24px; font-weight: bold; color: #4f46e5; }
            .omion-stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
            .omion-leads-controls { display: flex; gap: 10px; align-items: center; }
            .omion-search-input, .omion-status-filter { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
            .omion-export-btn, .omion-refresh-btn { padding: 8px 16px; background: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer; }
            .omion-leads-table { background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
            .omion-lead-row { padding: 15px; border-bottom: 1px solid #eee; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 100px; gap: 15px; align-items: center; }
            .omion-lead-row:hover { background: #f9f9f9; }
            .omion-lead-email { font-weight: 600; }
            .omion-lead-name { color: #666; font-size: 14px; }
            .omion-lead-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
            .omion-lead-badge.new { background: #e5e7eb; color: #374151; }
            .omion-lead-badge.contacted { background: #dbeafe; color: #1e40af; }
            .omion-lead-badge.qualified { background: #d1fae5; color: #065f46; }
            .omion-lead-badge.converted { background: #dcfce7; color: #166534; }
            .omion-pagination { margin-top: 20px; text-align: center; }
            .omion-pagination button { padding: 8px 12px; margin: 0 5px; background: white; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
            .omion-pagination button.active { background: #4f46e5; color: white; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let currentStatus = '';
            let currentSearch = '';
            
            function loadLeads() {
                $.ajax({
                    url: ajaxurl,
                    method: 'GET',
                    data: {
                        action: 'omion_get_leads',
                        page: currentPage,
                        status: currentStatus,
                        search: currentSearch
                    },
                    success: function(response) {
                        if (response.success) {
                            displayLeads(response.data.leads);
                            displayPagination(response.data);
                            updateStats(response.data);
                        }
                    }
                });
            }
            
            function displayLeads(leads) {
                let html = '<div class="omion-lead-row" style="font-weight: bold; background: #f5f5f5;">';
                html += '<div>Contact</div><div>Score</div><div>Status</div><div>Messages</div><div>Captured</div><div>Actions</div>';
                html += '</div>';
                
                leads.forEach(lead => {
                    html += `<div class="omion-lead-row">
                        <div>
                            <div class="omion-lead-email">${lead.email}</div>
                            <div class="omion-lead-name">${lead.name || 'Anonymous'}</div>
                        </div>
                        <div>${lead.score}</div>
                        <div><span class="omion-lead-badge ${lead.status}">${lead.status}</span></div>
                        <div>${lead.conversation_messages}</div>
                        <div>${new Date(lead.captured_at).toLocaleDateString()}</div>
                        <div>
                            <select onchange="updateLeadStatus('${lead.lead_id}', this.value)">
                                <option value="new" ${lead.status === 'new' ? 'selected' : ''}>New</option>
                                <option value="contacted" ${lead.status === 'contacted' ? 'selected' : ''}>Contacted</option>
                                <option value="qualified" ${lead.status === 'qualified' ? 'selected' : ''}>Qualified</option>
                                <option value="converted" ${lead.status === 'converted' ? 'selected' : ''}>Converted</option>
                            </select>
                        </div>
                    </div>`;
                });
                
                $('#leadsTable').html(html);
            }
            
            function displayPagination(data) {
                let html = '';
                for (let i = 1; i <= data.total_pages; i++) {
                    html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
                }
                $('#leadsPagination').html(html);
            }
            
            function updateStats(data) {
                $('#totalLeads').text(data.total);
                $('#todayLeads').text(Math.floor(data.total * 0.1));
                $('#conversionRate').text('75%');
                $('#avgScore').text('42');
            }
            
            window.changePage = function(page) {
                currentPage = page;
                loadLeads();
            };
            
            window.updateLeadStatus = function(leadId, status) {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'omion_update_lead_status',
                        lead_id: leadId,
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            loadLeads();
                        }
                    }
                });
            };
            
            $('#statusFilter').on('change', function() {
                currentStatus = $(this).val();
                currentPage = 1;
                loadLeads();
            });
            
            $('#leadsSearch').on('input', function() {
                currentSearch = $(this).val();
                currentPage = 1;
                setTimeout(loadLeads, 500);
            });
            
            $('#refreshLeads').on('click', loadLeads);
            
            $('#exportLeads').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    method: 'GET',
                    data: { action: 'omion_export_leads' },
                    success: function(response) {
                        if (response.success) {
                            let csv = response.data.csv_data.map(row => 
                                row.map(field => `"${field}"`).join(',')
                            ).join('\n');
                            
                            let blob = new Blob([csv], { type: 'text/csv' });
                            let url = window.URL.createObjectURL(blob);
                            let a = document.createElement('a');
                            a.href = url;
                            a.download = 'omion-leads-' + new Date().toISOString().split('T')[0] + '.csv';
                            a.click();
                        }
                    }
                });
            });
            
            loadLeads();
        });
        </script>
        <?php
    }

    public function page_init() {
        register_setting(
            'omion_ai_chat_options_group',
            'omion_ai_chat_options',
            array($this, 'sanitize')
        );

        // API Settings
        add_settings_section(
            'omion_ai_chat_api_section',
            'API Settings',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_api_section'
        );

        // Lead Capture Settings
        add_settings_section(
            'omion_ai_chat_lead_section',
            'Lead Capture Settings',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );
        
        add_settings_field(
            'lead_capture_enabled',
            'Enable Lead Capture',
            array($this, 'lead_capture_enabled_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_lead_section'
        );
        
        add_settings_field(
            'lead_score_threshold',
            'Lead Score Threshold',
            array($this, 'lead_score_threshold_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_lead_section'
        );
        
        add_settings_field(
            'max_capture_attempts',
            'Max Capture Attempts',
            array($this, 'max_capture_attempts_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_lead_section'
        );

        // Integration Settings
        add_settings_section(
            'omion_ai_chat_integration_section',
            'Integration Settings',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );
        
        add_settings_field(
            'mailchimp_api_key',
            'Mailchimp API Key',
            array($this, 'mailchimp_api_key_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_integration_section'
        );
        
        add_settings_field(
            'mailchimp_list_id',
            'Mailchimp List ID',
            array($this, 'mailchimp_list_id_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_integration_section'
        );
        
        add_settings_field(
            'webhook_url',
            'Webhook URL',
            array($this, 'webhook_url_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_integration_section'
        );

        // Form Shortcode Settings
        add_settings_section(
            'omion_ai_chat_form_section',
            'Inquiry Form Settings',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'inquiry_form_shortcode',
            'Inquiry Form Shortcode',
            array($this, 'inquiry_form_shortcode_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_form_section'
        );

        // Appearance Settings
        add_settings_section(
            'omion_ai_chat_appearance_section',
            'Appearance Settings',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'primary_color',
            'Primary Color',
            array($this, 'primary_color_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_appearance_section'
        );

        add_settings_field(
            'brand_name',
            'Brand Name',
            array($this, 'brand_name_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_appearance_section'
        );

        // Website Context
        add_settings_section(
            'omion_ai_chat_context_section',
            'Website Context',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'website_context',
            'Website Context',
            array($this, 'website_context_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_context_section'
        );

        // Predefined Questions
        add_settings_section(
            'omion_ai_chat_questions_section',
            'Predefined Questions',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'predefined_questions',
            'Questions',
            array($this, 'predefined_questions_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_questions_section'
        );

        // Custom Buttons
        add_settings_section(
            'omion_ai_chat_buttons_section',
            'Custom Buttons',
            array($this, 'print_section_info'),
            'omion-ai-chat-admin'
        );

        add_settings_field(
            'whatsapp_number',
            'WhatsApp Number',
            array($this, 'whatsapp_number_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'whatsapp_text',
            'WhatsApp Message',
            array($this, 'whatsapp_text_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button1_name',
            'Button 1 Name',
            array($this, 'button1_name_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button1_link',
            'Button 1 Link',
            array($this, 'button1_link_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button2_name',
            'Button 2 Name',
            array($this, 'button2_name_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button2_link',
            'Button 2 Link',
            array($this, 'button2_link_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button3_name',
            'Button 3 Name',
            array($this, 'button3_name_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );

        add_settings_field(
            'button3_link',
            'Button 3 Link',
            array($this, 'button3_link_callback'),
            'omion-ai-chat-admin',
            'omion_ai_chat_buttons_section'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        
        if(isset($input['api_key']))
            $new_input['api_key'] = sanitize_text_field($input['api_key']);
        
        if(isset($input['primary_color']))
            $new_input['primary_color'] = sanitize_hex_color($input['primary_color']);
        
        if(isset($input['brand_name']))
            $new_input['brand_name'] = sanitize_text_field($input['brand_name']);
        
        if(isset($input['predefined_questions']))
            $new_input['predefined_questions'] = sanitize_textarea_field($input['predefined_questions']);

        if(isset($input['inquiry_form_shortcode']))
            $new_input['inquiry_form_shortcode'] = sanitize_text_field($input['inquiry_form_shortcode']);

        if(isset($input['website_context']))
            $new_input['website_context'] = wp_kses_post($input['website_context']);

        if(isset($input['button1_name']))
            $new_input['button1_name'] = sanitize_text_field($input['button1_name']);
        
        if(isset($input['button1_link']))
            $new_input['button1_link'] = esc_url_raw($input['button1_link']);
        
        if(isset($input['button2_name']))
            $new_input['button2_name'] = sanitize_text_field($input['button2_name']);
        
        if(isset($input['button2_link']))
            $new_input['button2_link'] = esc_url_raw($input['button2_link']);
        
        if(isset($input['button3_name']))
            $new_input['button3_name'] = sanitize_text_field($input['button3_name']);
        
        if(isset($input['button3_link']))
            $new_input['button3_link'] = esc_url_raw($input['button3_link']);
        
        if(isset($input['whatsapp_number']))
            $new_input['whatsapp_number'] = sanitize_text_field($input['whatsapp_number']);

        if(isset($input['whatsapp_text']))
            $new_input['whatsapp_text'] = sanitize_text_field($input['whatsapp_text']);

        // Lead capture settings
        if(isset($input['lead_capture_enabled']))
            $new_input['lead_capture_enabled'] = 1;
        
        if(isset($input['lead_score_threshold']))
            $new_input['lead_score_threshold'] = intval($input['lead_score_threshold']);
        
        if(isset($input['max_capture_attempts']))
            $new_input['max_capture_attempts'] = intval($input['max_capture_attempts']);
        
        if(isset($input['mailchimp_api_key']))
            $new_input['mailchimp_api_key'] = sanitize_text_field($input['mailchimp_api_key']);
        
        if(isset($input['mailchimp_list_id']))
            $new_input['mailchimp_list_id'] = sanitize_text_field($input['mailchimp_list_id']);
        
        if(isset($input['webhook_url']))
            $new_input['webhook_url'] = esc_url_raw($input['webhook_url']);

        return $new_input;
    }

    public function print_section_info() {
        // Section descriptions here if needed
    }

    // Callback functions
    public function api_key_callback() {
        printf(
            '<input type="password" id="api_key" name="omion_ai_chat_options[api_key]" value="%s" class="regular-text" />
            <p class="description">Enter your Mistral API key</p>',
            isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : ''
        );
    }

    public function lead_capture_enabled_callback() {
        printf(
            '<input type="checkbox" id="lead_capture_enabled" name="omion_ai_chat_options[lead_capture_enabled]" value="1" %s />
            <p class="description">Enable automatic lead capture during conversations</p>',
            isset($this->options['lead_capture_enabled']) && $this->options['lead_capture_enabled'] ? 'checked' : ''
        );
    }

    public function lead_score_threshold_callback() {
        printf(
            '<input type="number" id="lead_score_threshold" name="omion_ai_chat_options[lead_score_threshold]" value="%s" min="10" max="100" />
            <p class="description">Score threshold to trigger lead capture (default: 30)</p>',
            isset($this->options['lead_score_threshold']) ? esc_attr($this->options['lead_score_threshold']) : '30'
        );
    }

    public function max_capture_attempts_callback() {
        printf(
            '<input type="number" id="max_capture_attempts" name="omion_ai_chat_options[max_capture_attempts]" value="%s" min="1" max="10" />
            <p class="description">Maximum number of capture attempts per visitor (default: 3)</p>',
            isset($this->options['max_capture_attempts']) ? esc_attr($this->options['max_capture_attempts']) : '3'
        );
    }

    public function mailchimp_api_key_callback() {
        printf(
            '<input type="password" id="mailchimp_api_key" name="omion_ai_chat_options[mailchimp_api_key]" value="%s" class="regular-text" />
            <p class="description">Your Mailchimp API key for automatic lead sync</p>',
            isset($this->options['mailchimp_api_key']) ? esc_attr($this->options['mailchimp_api_key']) : ''
        );
    }

    public function mailchimp_list_id_callback() {
        printf(
            '<input type="text" id="mailchimp_list_id" name="omion_ai_chat_options[mailchimp_list_id]" value="%s" class="regular-text" />
            <p class="description">Mailchimp list ID to add leads to</p>',
            isset($this->options['mailchimp_list_id']) ? esc_attr($this->options['mailchimp_list_id']) : ''
        );
    }

    public function webhook_url_callback() {
        printf(
            '<input type="url" id="webhook_url" name="omion_ai_chat_options[webhook_url]" value="%s" class="regular-text" />
            <p class="description">Webhook URL to send lead data to (Zapier, Make.com, etc.)</p>',
            isset($this->options['webhook_url']) ? esc_attr($this->options['webhook_url']) : ''
        );
    }

    public function inquiry_form_shortcode_callback() {
        printf(
            '<input type="text" id="inquiry_form_shortcode" name="omion_ai_chat_options[inquiry_form_shortcode]" value="%s" class="regular-text" />
            <p class="description">Enter your form shortcode (e.g. [contact-form-7 id="123"])</p>',
            isset($this->options['inquiry_form_shortcode']) ? esc_attr($this->options['inquiry_form_shortcode']) : ''
        );
    }

    public function whatsapp_number_callback() {
        printf(
            '<input type="text" id="whatsapp_number" name="omion_ai_chat_options[whatsapp_number]" value="%s" class="regular-text" />
            <p class="description">Enter WhatsApp number with country code (e.g., 14155552671)</p>',
            isset($this->options['whatsapp_number']) ? esc_attr($this->options['whatsapp_number']) : ''
        );
    }

    public function whatsapp_text_callback() {
        printf(
            '<input type="text" id="whatsapp_text" name="omion_ai_chat_options[whatsapp_text]" value="%s" class="regular-text" />
            <p class="description">Enter default WhatsApp message</p>',
            isset($this->options['whatsapp_text']) ? esc_attr($this->options['whatsapp_text']) : 'Hello! I have a question about your services.'
        );
    }

    public function button1_name_callback() {
        printf(
            '<input type="text" id="button1_name" name="omion_ai_chat_options[button1_name]" value="%s" class="regular-text" />
            <p class="description">Enter the name for button 1</p>',
            isset($this->options['button1_name']) ? esc_attr($this->options['button1_name']) : ''
        );
    }

    public function button1_link_callback() {
        printf(
            '<input type="text" id="button1_link" name="omion_ai_chat_options[button1_link]" value="%s" class="regular-text" />
            <p class="description">Enter the URL for button 1</p>',
            isset($this->options['button1_link']) ? esc_attr($this->options['button1_link']) : ''
        );
    }

    public function button2_name_callback() {
        printf(
            '<input type="text" id="button2_name" name="omion_ai_chat_options[button2_name]" value="%s" class="regular-text" />
            <p class="description">Enter the name for button 2</p>',
            isset($this->options['button2_name']) ? esc_attr($this->options['button2_name']) : ''
        );
    }

    public function button2_link_callback() {
        printf(
            '<input type="text" id="button2_link" name="omion_ai_chat_options[button2_link]" value="%s" class="regular-text" />
            <p class="description">Enter the URL for button 2</p>',
            isset($this->options['button2_link']) ? esc_attr($this->options['button2_link']) : ''
        );
    }

    public function button3_name_callback() {
        printf(
            '<input type="text" id="button3_name" name="omion_ai_chat_options[button3_name]" value="%s" class="regular-text" />
            <p class="description">Enter the name for button 3</p>',
            isset($this->options['button3_name']) ? esc_attr($this->options['button3_name']) : ''
        );
    }

    public function button3_link_callback() {
        printf(
            '<input type="text" id="button3_link" name="omion_ai_chat_options[button3_link]" value="%s" class="regular-text" />
            <p class="description">Enter the URL for button 3</p>',
            isset($this->options['button3_link']) ? esc_attr($this->options['button3_link']) : ''
        );
    }

    public function primary_color_callback() {
        printf(
            '<input type="color" id="primary_color" name="omion_ai_chat_options[primary_color]" value="%s" />
            <p class="description">Choose the main color for the chat widget</p>',
            isset($this->options['primary_color']) ? esc_attr($this->options['primary_color']) : '#00B2FF'
        );
    }

    public function brand_name_callback() {
        printf(
            '<input type="text" id="brand_name" name="omion_ai_chat_options[brand_name]" value="%s" class="regular-text" />
            <p class="description">Enter your brand name</p>',
            isset($this->options['brand_name']) ? esc_attr($this->options['brand_name']) : 'Omion AI'
        );
    }

    public function website_context_callback() {
        printf(
            '<textarea id="website_context" name="omion_ai_chat_options[website_context]" rows="6" cols="50">%s</textarea>
            <p class="description">Describe your website context to help the AI provide relevant responses</p>',
            isset($this->options['website_context']) ? esc_textarea($this->options['website_context']) : ''
        );
    }

    public function predefined_questions_callback() {
        $default_questions = "How can you help me?\nWhat services do you offer?\nTell me about your company\nNeed technical support";
        printf(
            '<textarea id="predefined_questions" name="omion_ai_chat_options[predefined_questions]" rows="6" cols="50">%s</textarea>
            <p class="description">Enter one question per line</p>',
            isset($this->options['predefined_questions']) ? esc_textarea($this->options['predefined_questions']) : $default_questions
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_style('omion-ai-chat-style', plugins_url('css/chat-widget.css', __FILE__));
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'omion-ai-chat-script', 
            plugins_url('js/chat-widget.js', __FILE__), 
            array('jquery'), 
            time(), 
            true
        );

        $options = get_option('omion_ai_chat_options', array());
        
        wp_localize_script(
            'omion-ai-chat-script', 
            'omionAIChat', 
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'options' => array(
                    'api_key' => isset($options['api_key']) ? $options['api_key'] : '',
                    'brand_name' => isset($options['brand_name']) ? $options['brand_name'] : 'Omion AI',
                    'primary_color' => isset($options['primary_color']) ? $options['primary_color'] : '#00B2FF'
                )
            )
        );
    }

    public function admin_enqueue_scripts($hook) {
        if ('toplevel_page_omion-ai-chat' !== $hook) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('omion-ai-chat-admin', plugins_url('js/admin.js', __FILE__), array('wp-color-picker'), false, true);
    }

    public function add_chat_widget() {
        $options = get_option('omion_ai_chat_options');
        include plugin_dir_path(__FILE__) . 'templates/chat-widget.php';
    }
}

// Initialize plugin
$omion_ai_chat = new OmionAIChat();

// Activation hook
register_activation_hook(__FILE__, 'omion_ai_chat_activate');
function omion_ai_chat_activate() {
    // Set default options
    $default_options = array(
        'api_key' => '',
        'primary_color' => '#00B2FF',
        'brand_name' => 'Omion AI',
        'predefined_questions' => "How can you help me?\nWhat services do you offer?\nTell me about your company\nNeed technical support",
        'website_context' => '',
        'button1_name' => 'Contact Us',
        'button1_link' => '',
        'button2_name' => 'Services',
        'button2_link' => '',
        'button3_name' => 'Support',
        'button3_link' => '',
        'whatsapp_number' => '',
        'whatsapp_text' => 'Hello! I have a question about your services.',
        'lead_capture_enabled' => 1,
        'lead_score_threshold' => 30,
        'max_capture_attempts' => 3
    );
    
    add_option('omion_ai_chat_options', $default_options);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'omion_ai_chat_deactivate');
function omion_ai_chat_deactivate() {
    // Cleanup if needed
}

?>