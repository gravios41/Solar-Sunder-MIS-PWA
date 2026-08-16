<?php
// config/database.php
// Database abstraction layer for Supabase

class Database {
    private $url;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->url = SUPABASE_URL . '/rest/v1';
        $this->apiKey = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
        $this->headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // Make API request to Supabase
    private function request($method, $table, $params = [], $data = null) {
        $url = $this->url . '/' . $table;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        
        switch ($method) {
            case 'GET':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    // Get all records
    public function getAll($table, $filters = []) {
        return $this->request('GET', $table, $filters);
    }
    
    // Get single record by ID
    public function getById($table, $id) {
        $result = $this->request('GET', $table, ['id' => 'eq.' . $id]);
        return $result ? $result[0] : null;
    }
    
    // Insert record
    public function insert($table, $data) {
        return $this->request('POST', $table, [], $data);
    }
    
    // Update record
    public function update($table, $id, $data) {
        return $this->request('PUT', $table, ['id' => 'eq.' . $id], $data);
    }
    
    // Delete record
    public function delete($table, $id) {
        return $this->request('DELETE', $table, ['id' => 'eq.' . $id]);
    }
    
    // Query with custom conditions
    public function query($table, $conditions) {
        $params = [];
        
        if (isset($conditions['select'])) {
            $params['select'] = $conditions['select'];
        }
        
        if (isset($conditions['where'])) {
            foreach ($conditions['where'] as $key => $value) {
                $params[$key] = 'eq.' . $value;
            }
        }
        
        if (isset($conditions['order'])) {
            $params['order'] = $conditions['order'];
        }
        
        if (isset($conditions['limit'])) {
            $params['limit'] = $conditions['limit'];
        }
        
        return $this->request('GET', $table, $params);
    }
}

// Initialize database connection
$db = new Database();
?>