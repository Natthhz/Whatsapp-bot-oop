<?php
// includes/qr_handler_debug.php
function getAllBotsStatusDebug() {
    $api_url = 'http://localhost:3000/api/bots';
    
    echo "<h3>Debug Information:</h3>";
    echo "<p><strong>API URL:</strong> $api_url</p>";
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>HTTP Code:</strong> $http_code</p>";
        echo "<p><strong>cURL Error:</strong> " . ($curl_error ?: 'None') . "</p>";
        echo "<p><strong>Raw Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        if ($http_code === 200 && $response) {
            $bots = json_decode($response, true);
            echo "<p><strong>Parsed JSON:</strong></p>";
            echo "<pre>" . htmlspecialchars(print_r($bots, true)) . "</pre>";
            
            if (isset($bots['data']) && is_array($bots['data'])) {
                return processBotData($bots['data']);
            } else if (is_array($bots)) {
                return processBotData($bots);
            }
        }
        
        echo "<p><strong>Using Fallback Data</strong></p>";
        return getFallbackBotData();
        
    } catch (Exception $e) {
        echo "<p><strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        return getFallbackBotData();
    }
}

function processBotData($bots) {
    $processedBots = [];
    
    foreach ($bots as $bot) {
        echo "<h4>Processing Bot: " . ($bot['id'] ?? 'unknown') . "</h4>";
        echo "<p><strong>Original Status:</strong> " . htmlspecialchars(print_r($bot['status'] ?? 'none', true)) . "</p>";
        echo "<p><strong>QR Data URL Present:</strong> " . (isset($bot['qrDataUrl']) && !empty($bot['qrDataUrl']) ? 'Yes' : 'No') . "</p>";
        
        $processedBot = [
            'id' => $bot['id'] ?? 'unknown',
            'name' => $bot['name'] ?? 'Unknown Bot',
            'status' => $bot['status'] ?? 'disconnected',
            'qrDataUrl' => $bot['qrDataUrl'] ?? null,
            'port' => $bot['port'] ?? 3000,
            'reconnectAttempts' => $bot['reconnectAttempts'] ?? 0,
            'allowedNumbers' => $bot['allowedNumbers'] ?? []
        ];
        
        if (is_array($processedBot['status'])) {
            $processedBot['status'] = $processedBot['status']['status'] ?? 'disconnected';
        }
        
        echo "<p><strong>Processed Status:</strong> " . htmlspecialchars($processedBot['status']) . "</p>";
        echo "<hr>";
        
        $processedBots[] = $processedBot;
    }
    
    return $processedBots;
}

function getFallbackBotData() {
    return [
        [
            'id' => 'bot1',
            'name' => 'Bot Utama',
            'status' => 'disconnected',
            'qrDataUrl' => null,
            'port' => 3000,
            'reconnectAttempts' => 0,
            'allowedNumbers' => ['*']
        ]
    ];
}
?>