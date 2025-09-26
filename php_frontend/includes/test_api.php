<?php
// test_api.php - Test file terpisah untuk debug API
echo "<h2>API Endpoint Test</h2>";

// Test endpoint /api/bots
$api_url = 'http://localhost:3000/api/bots';

echo "<h3>Testing: $api_url</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);
curl_close($ch);

echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h4>cURL Info:</h4>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";
echo "<p><strong>cURL Error:</strong> " . ($curl_error ?: 'None') . "</p>";
echo "<p><strong>Content Type:</strong> " . ($curl_info['content_type'] ?? 'Unknown') . "</p>";
echo "<p><strong>Total Time:</strong> " . ($curl_info['total_time'] ?? 'Unknown') . " seconds</p>";
echo "</div>";

echo "<div style='background: #fff; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px;'>";
echo "<h4>Raw Response:</h4>";
echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
echo htmlspecialchars($response);
echo "</pre>";
echo "</div>";

if ($response) {
    $decoded = json_decode($response, true);
    $json_error = json_last_error();
    
    echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>JSON Decode Result:</h4>";
    
    if ($json_error === JSON_ERROR_NONE) {
        echo "<p><strong>JSON Valid:</strong> Yes</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
        echo htmlspecialchars(print_r($decoded, true));
        echo "</pre>";
        
        // Test structure
        echo "<h5>Data Structure Analysis:</h5>";
        if (is_array($decoded)) {
            echo "<p>Response is an array with " . count($decoded) . " items</p>";
            
            foreach ($decoded as $index => $item) {
                echo "<p><strong>Item $index:</strong></p>";
                if (isset($item['id'])) echo "<p>- ID: " . htmlspecialchars($item['id']) . "</p>";
                if (isset($item['name'])) echo "<p>- Name: " . htmlspecialchars($item['name']) . "</p>";
                if (isset($item['status'])) echo "<p>- Status: " . htmlspecialchars(print_r($item['status'], true)) . "</p>";
                if (isset($item['qrDataUrl'])) echo "<p>- QR Data: " . (empty($item['qrDataUrl']) ? 'Empty' : 'Present (' . strlen($item['qrDataUrl']) . ' chars)') . "</p>";
                echo "<hr>";
            }
        } else {
            echo "<p>Response is not an array</p>";
            if (isset($decoded['data'])) {
                echo "<p>Found 'data' key in response</p>";
                echo "<pre>" . htmlspecialchars(print_r($decoded['data'], true)) . "</pre>";
            }
        }
    } else {
        echo "<p><strong>JSON Valid:</strong> No</p>";
        echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
    }
    echo "</div>";
}

// Test individual bot endpoint if we know bot IDs
$common_bot_ids = ['bot1', 'bot2', 'main', 'primary'];

foreach ($common_bot_ids as $botId) {
    $bot_url = "http://localhost:3000/api/bots/$botId";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $bot_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $bot_response = curl_exec($ch);
    $bot_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($bot_http_code === 200 && $bot_response) {
        echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>Individual Bot Test: $botId</h4>";
        echo "<p><strong>URL:</strong> $bot_url</p>";
        echo "<p><strong>HTTP Code:</strong> $bot_http_code</p>";
        echo "<pre>" . htmlspecialchars($bot_response) . "</pre>";
        echo "</div>";
        break; // Just show first successful one
    }
}

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

h2, h3, h4, h5 {
    color: #333;
}
</style>