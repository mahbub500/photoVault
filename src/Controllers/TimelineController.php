<?php
/**
 * Timeline Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimelineController {
    
    /**
     * Get timeline images grouped by date
     */
    public function get_timeline_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        
        $view = isset($_POST['view']) ? sanitize_text_field(wp_unslash($_POST['view'])) : 'day';
        $sort = isset($_POST['sort']) ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'desc';
        $user_id = get_current_user_id();
        
        // Determine grouping format based on view
        switch ($view) {
            case 'year':
                $date_format = '%Y';
                break;
            case 'month':
                $date_format = '%Y-%m';
                break;
            case 'day':
            default:
                $date_format = '%Y-%m-%d';
                break;
        }
        
        // Validate and sanitize sort order
        $order = ($sort === 'asc') ? 'ASC' : 'DESC';
        
        // Get all images for the current user
        $query = $wpdb->prepare(
            "SELECT 
                i.id,
                i.attachment_id,
                i.title,
                i.description,
                i.upload_date,
                DATE_FORMAT(i.upload_date, %s) as date_group,
                u.display_name as author_name
            FROM {$wpdb->prefix}pv_images i
            LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
            WHERE i.user_id = %d
            ORDER BY i.upload_date {$order}",
            $date_format,
            $user_id
        );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query properly prepared above with validated ORDER BY.
        $images = $wpdb->get_results($query);
        
        if (empty($images)) {
            wp_send_json_success([]);
            return;
        }
        
        // Group images by date
        $grouped = [];
        
        foreach ($images as $image) {
            $date_key = $image->date_group;
            
            if (!isset($grouped[$date_key])) {
                $grouped[$date_key] = [
                    'date' => $date_key,
                    'images' => []
                ];
            }
            
            // Get image URLs
            $thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $full_url = wp_get_attachment_image_url($image->attachment_id, 'full');
            
            // Fallback to attachment URL
            if (!$thumbnail_url) {
                $thumbnail_url = wp_get_attachment_url($image->attachment_id);
            }
            if (!$full_url) {
                $full_url = wp_get_attachment_url($image->attachment_id);
            }
            
            $grouped[$date_key]['images'][] = [
                'id' => $image->id,
                'attachment_id' => $image->attachment_id,
                'title' => $image->title,
                'description' => $image->description,
                'url' => $full_url,
                'thumbnail_url' => $thumbnail_url,
                'upload_date' => $image->upload_date,
                'formatted_date' => date_i18n(get_option('date_format'), strtotime($image->upload_date)),
                'author_name' => $image->author_name
            ];
        }
        
        wp_send_json_success($grouped);
    }
    
    /**
     * Get timeline statistics
     */
    public function get_timeline_stats() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Total images
        $total_images = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pv_images WHERE user_id = %d",
            $user_id
        ));
        
        // Images by year
        $by_year = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                YEAR(upload_date) as year,
                COUNT(*) as count
            FROM {$wpdb->prefix}pv_images
            WHERE user_id = %d
            GROUP BY YEAR(upload_date)
            ORDER BY year DESC",
            $user_id
        ));
        
        // Images by month (last 12 months)
        $by_month = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(upload_date, '%%Y-%%m') as month,
                COUNT(*) as count
            FROM {$wpdb->prefix}pv_images
            WHERE user_id = %d
            AND upload_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(upload_date, '%%Y-%%m')
            ORDER BY month DESC",
            $user_id
        ));
        
        // First and last upload dates
        $date_range = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                MIN(upload_date) as first_upload,
                MAX(upload_date) as last_upload
            FROM {$wpdb->prefix}pv_images
            WHERE user_id = %d",
            $user_id
        ));
        
        wp_send_json_success([
            'total_images' => $total_images,
            'by_year' => $by_year,
            'by_month' => $by_month,
            'first_upload' => $date_range->first_upload,
            'last_upload' => $date_range->last_upload
        ]);
    }
    
    /**
     * Get images for specific date range
     */
    public function get_images_by_date_range() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $user_id = get_current_user_id();
        
        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(['message' => __('Date range required', 'photovault')]);
            return;
        }
        
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                i.id,
                i.attachment_id,
                i.title,
                i.description,
                i.upload_date,
                u.display_name as author_name
            FROM {$wpdb->prefix}pv_images i
            LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
            WHERE i.user_id = %d
            AND DATE(i.upload_date) BETWEEN %s AND %s
            ORDER BY i.upload_date DESC",
            $user_id,
            $start_date,
            $end_date
        ));
        
        $formatted_images = [];
        
        foreach ($images as $image) {
            $thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $full_url = wp_get_attachment_image_url($image->attachment_id, 'full');
            
            if (!$thumbnail_url) {
                $thumbnail_url = wp_get_attachment_url($image->attachment_id);
            }
            if (!$full_url) {
                $full_url = wp_get_attachment_url($image->attachment_id);
            }
            
            $formatted_images[] = [
                'id' => $image->id,
                'attachment_id' => $image->attachment_id,
                'title' => $image->title,
                'description' => $image->description,
                'url' => $full_url,
                'thumbnail_url' => $thumbnail_url,
                'upload_date' => $image->upload_date,
                'formatted_date' => date_i18n(get_option('date_format'), strtotime($image->upload_date)),
                'author_name' => $image->author_name
            ];
        }
        
        wp_send_json_success(['images' => $formatted_images]);
    }
}