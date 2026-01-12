<?php
/**
 * Timeline Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

class TimelineController {
    
    public function get_timeline() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $view = sanitize_text_field($_POST['view'] ?? 'month');
        $sort = sanitize_text_field($_POST['sort'] ?? 'desc');
        
        $sql = "SELECT DATE(i.upload_date) as date, COUNT(*) as count
                FROM {$wpdb->prefix}pv_images i
                LEFT JOIN {$wpdb->prefix}pv_shares s ON (s.item_type = 'image' AND s.item_id = i.id)
                WHERE (i.user_id = %d OR s.shared_with = %d)
                GROUP BY DATE(i.upload_date)
                ORDER BY date " . ($sort === 'asc' ? 'ASC' : 'DESC');
        
        $timeline = $wpdb->get_results($wpdb->prepare($sql, $user_id, $user_id));
        
        wp_send_json_success($timeline);
    }
}