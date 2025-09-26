<?php
// includes/qr_handler_fixed.php
function getAllBotsStatus() {
    $api_url = 'http://localhost:3000/api/bots';
    
    // Log untuk debugging
    error_log("QR Handler: Attempting to fetch from $api_url");
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: PHP-QR-Handler/1.0'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        error_log("QR Handler: HTTP Code: $http_code");
        error_log("QR Handler: Content Type: $content_type");
        error_log("QR Handler: cURL Error: $curl_error");
        error_log("QR Handler: Response Length: " . strlen($response));
        
        if ($curl_error) {
            error_log("QR Handler: cURL Error occurred: $curl_error");
            return getFallbackBotData();
        }
        
        if ($http_code !== 200) {
            error_log("QR Handler: HTTP Error: $http_code");
            error_log("QR Handler: Response: $response");
            return getFallbackBotData();
        }
        
        if (!$response) {
            error_log("QR Handler: Empty response received");
            return getFallbackBotData();
        }
        
        // Log raw response for debugging
        error_log("QR Handler: Raw Response: " . substr($response, 0, 500) . "...");
        
        $decoded = json_decode($response, true);
        $json_error = json_last_error();
        
        if ($json_error !== JSON_ERROR_NONE) {
            error_log("QR Handler: JSON Decode Error: " . json_last_error_msg());
            error_log("QR Handler: Raw Response was: $response");
            return getFallbackBotData();
        }
        
        // Handle different response structures
        $bots_data = null;
        
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $bots_data = $decoded['data'];
                error_log("QR Handler: Using 'data' field from response");
            } else if (isset($decoded[0]) && is_array($decoded[0])) {
                $bots_data = $decoded;
                error_log("QR Handler: Using direct array response");
            } else {
                // Maybe single bot response?
                if (isset($decoded['id'])) {
                    $bots_data = [$decoded];
                    error_log("QR Handler: Converting single bot to array");
                }
            }
        }
        
        if (!$bots_data) {
            error_log("QR Handler: Could not extract bots data from response");
            error_log("QR Handler: Decoded structure: " . print_r($decoded, true));
            return getFallbackBotData();
        }
        
        error_log("QR Handler: Processing " . count($bots_data) . " bots");
        return processBotData($bots_data);
        
    } catch (Exception $e) {
        error_log("QR Handler: Exception occurred: " . $e->getMessage());
        error_log("QR Handler: Stack trace: " . $e->getTraceAsString());
        return getFallbackBotData();
    }
}

function processBotData($bots) {
    $processedBots = [];
    
    foreach ($bots as $index => $bot) {
        error_log("QR Handler: Processing bot $index: " . print_r($bot, true));
        
        // Extract status info - could be nested or direct
        $statusInfo = $bot['status'] ?? 'disconnected';
        $actualStatus = 'disconnected';
        $qrDataUrl = null;
        $reconnectAttempts = 0;
        
        if (is_array($statusInfo)) {
            // Status is nested object with detailed info
            $actualStatus = $statusInfo['status'] ?? 'disconnected';
            $qrDataUrl = $statusInfo['qrDataUrl'] ?? null;
            $reconnectAttempts = $statusInfo['reconnectAttempts'] ?? 0;
        } else {
            // Status is just a string
            $actualStatus = $statusInfo;
            $qrDataUrl = $bot['qrDataUrl'] ?? null;
            $reconnectAttempts = $bot['reconnectAttempts'] ?? 0;
        }
        
        $processedBot = [
            'id' => $bot['id'] ?? 'bot_' . $index,
            'name' => $bot['name'] ?? 'Bot ' . ($index + 1),
            'status' => $actualStatus,
            'qrDataUrl' => $qrDataUrl,
            'port' => $bot['port'] ?? (3000 + $index),
            'reconnectAttempts' => $reconnectAttempts,
            'allowedNumbers' => $bot['allowedNumbers'] ?? ['*']
        ];
        
        error_log("QR Handler: Bot {$processedBot['id']} - Status: {$processedBot['status']}, QR: " . ($processedBot['qrDataUrl'] ? 'Present (' . strlen($processedBot['qrDataUrl']) . ' chars)' : 'Absent'));
        
        $processedBots[] = $processedBot;
    }
    
    error_log("QR Handler: Processed " . count($processedBots) . " bots successfully");
    return $processedBots;
}

function getFallbackBotData() {
    error_log("QR Handler: Using fallback data");
    return [
        [
            'id' => 'bot1',
            'name' => 'Bot Utama (Fallback)',
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3001,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot2',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3002,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot3',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3003,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot4',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3004,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot5',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3005,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot6',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3006,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ],
        [
            'id' => 'bot7',
            'name' => 'Bot Kedua (Fallback)', 
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3006,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ]
    ];
}

// Enhanced AJAX handler
if (isset($_GET['action']) && isset($_GET['bot'])) {
    // Clean any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    $action = $_GET['action'];
    $botId = $_GET['bot'];
    
    error_log("QR Handler: AJAX Request - Action: $action, Bot: $botId");
    
    $api_base_url = 'http://localhost:3000/api/bots/';
    
    switch ($action) {
        case 'refresh':
        case 'restart':
            $api_url = $api_base_url . $botId . '/restart';
            break;
            
        case 'status':
            $api_url = $api_base_url . $botId;
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
            exit;
    }
    
    try {
        error_log("QR Handler: Making API call to: $api_url");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        
        if ($action === 'restart' || $action === 'refresh') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: PHP-QR-Handler/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log("QR Handler: API Response - HTTP: $http_code, Error: $curl_error");
        error_log("QR Handler: API Response Body: $response");
        
        if ($curl_error) {
            echo json_encode([
                'success' => false, 
                'error' => 'Connection error: ' . $curl_error
            ]);
        } else if ($http_code === 200) {
            echo $response;
        } else {
            echo json_encode([
                'success' => false, 
                'error' => "HTTP Error $http_code: $response"
            ]);
        }
    } catch (Exception $e) {
        error_log("QR Handler: Exception in AJAX: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Exception: ' . $e->getMessage()
        ]);
    }
    
    exit;
}
?>