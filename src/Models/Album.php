<?php
namespace PhotoVault\Models;

class Album {
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pv_albums';
    }
    
    public function create($data) {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }
    
    public function get_albums($params = []) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table}");
    }
    
    public function update($id, $data) {
        global $wpdb;
        return $wpdb->update($this->table, $data, ['id' => $id]);
    }
    
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => $id]);
    }
    
    public function user_owns_album($album_id, $user_id) {
        global $wpdb;
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table} WHERE id = %d", $album_id
        ));
        return $owner == $user_id || current_user_can('manage_options');
    }
}