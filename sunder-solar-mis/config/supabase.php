<?php
// config/supabase.php
// Supabase API wrapper for PHP

class SupabaseClient {
    private $url;
    private $apiKey;
    private $headers;
    
    public function __construct($url, $apiKey) {
        $this->url = rtrim($url, '/') . '/rest/v1';
        $this->apiKey = $apiKey;
        $this->headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // Make HTTP request to Supabase
    public function request($method, $endpoint, $params = [], $data = null) {
        $url = $this->url . '/' . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Initialize curl
        $ch = curl_init();
        
        if ($ch === false) {
            throw new Exception("Failed to initialize curl");
        }
        
        // Set curl options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set request method
        switch ($method) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        // Execute request
        $response = curl_exec($ch);
        
        // Check for curl errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Error: " . $error);
        }
        
        // Get HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Close curl connection (only once)
        curl_close($ch);
        
        // Handle response based on status code
        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return $decoded;
        }

        // Return empty array for true 404 (table/route not found at REST level)
        if ($httpCode == 404) {
            return [];
        }

        // Surface the actual Supabase error message so callers can show it
        $errBody = json_decode($response, true);
        $errMsg  = $errBody['message'] ?? ($errBody['hint'] ?? "Supabase HTTP $httpCode on /$endpoint");
        throw new Exception($errMsg);
    }
    
    // Get all records
    public function getAll($table, $filters = []) {
        return $this->request('GET', $table, $filters);
    }
    
    // Get single record by ID
    public function getById($table, $id) {
        $result = $this->request('GET', $table, ['id' => 'eq.' . $id]);
        if ($result && is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return null;
    }
    
    // Insert record
    public function insert($table, $data) {
        return $this->request('POST', $table, [], $data);
    }
    
    // Update record — Supabase REST requires PATCH for partial updates
    public function update($table, $id, $data) {
        return $this->request('PATCH', $table, ['id' => 'eq.' . $id], $data);
    }
    
    // Delete record
    public function delete($table, $id) {
        return $this->request('DELETE', $table, ['id' => 'eq.' . $id]);
    }
    
    // Query builder
    public function from($table) {
        return new SupabaseQueryBuilder($this, $table);
    }
}

class SupabaseQueryBuilder {
    private $client;
    private $table;
    private $filters = [];
    private $selectFields = '*';
    private $orderField = null;
    private $orderAscending = true;
    private $limitValue = null;
    private $offsetValue = null;
    
    public function __construct($client, $table) {
        $this->client = $client;
        $this->table = $table;
    }
    
    public function select($fields) {
        $this->selectFields = $fields;
        return $this;
    }
    
    public function eq($column, $value) {
        $this->filters[$column] = 'eq.' . $value;
        return $this;
    }
    
    public function neq($column, $value) {
        $this->filters[$column] = 'neq.' . $value;
        return $this;
    }
    
    public function gt($column, $value) {
        $this->filters[$column] = 'gt.' . $value;
        return $this;
    }
    
    public function lt($column, $value) {
        $this->filters[$column] = 'lt.' . $value;
        return $this;
    }
    
    public function gte($column, $value) {
        $this->filters[$column] = 'gte.' . $value;
        return $this;
    }
    
    public function lte($column, $value) {
        $this->filters[$column] = 'lte.' . $value;
        return $this;
    }
    
    public function like($column, $value) {
        $this->filters[$column] = 'like.' . $value;
        return $this;
    }
    
    public function ilike($column, $value) {
        $this->filters[$column] = 'ilike.' . $value;
        return $this;
    }
    
    public function order($column, $ascending = true) {
        if (is_array($ascending)) {
            $ascending = $ascending['ascending'] ?? true;
        }

        $this->orderField = $column;
        $this->orderAscending = (bool) $ascending;
        return $this;
    }
    
    public function limit($value) {
        $this->limitValue = $value;
        return $this;
    }
    
    public function offset($value) {
        $this->offsetValue = $value;
        return $this;
    }
    
    public function execute() {
        $params = ['select' => $this->selectFields];
        
        foreach ($this->filters as $key => $value) {
            $params[$key] = $value;
        }
        
        if ($this->orderField) {
            $params['order'] = $this->orderField . '.' . ($this->orderAscending ? 'asc' : 'desc');
        }
        
        if ($this->limitValue) {
            $params['limit'] = $this->limitValue;
        }
        
        if ($this->offsetValue) {
            $params['offset'] = $this->offsetValue;
        }
        
        $result = $this->client->request('GET', $this->table, $params);
        
        // Return empty array if no results
        if ($result === null) {
            return [];
        }
        
        return $result;
    }
}
?>