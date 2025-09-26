<?php
class WhatsAppAPIClient
{
    private $baseUrl;
    private $timeout;

    public function __construct($baseUrl = 'http://localhost:3000', $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getIncomingMessages($botId = 'bot1', $limit = 100, $status = null)
    {
        $url = $this->baseUrl . "/api/{$botId}/messages/incoming?limit={$limit}";
        if ($status) {
            $url .= "&status={$status}";
        }
        return $this->makeRequest($url);
    }

    public function getOutgoingMessages($botId = 'bot1', $limit = 100, $status = null)
    {
        $url = $this->baseUrl . "/api/{$botId}/messages/outgoing?limit={$limit}";
        if ($status) {
            $url .= "&status={$status}";
        }
        return $this->makeRequest($url);
    }

    public function sendMessage($botId, $targetJid, $message, $replyTo = null)
    {
        $url = $this->baseUrl . "/api/{$botId}/messages/send";
        $data = [
            'targetJid' => $targetJid,
            'message' => $message,
            'replyTo' => $replyTo
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

public function updateMessageStatus($botId, $messageId, $type, $status)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/{$messageId}/status";
    $data = [
        'type' => $type,
        'status' => $status
    ];

    error_log("Update Status Request: " . json_encode([
        'url' => $url,
        'data' => $data
    ]));

    return $this->makeRequest($url, 'PUT', $data);
}

    public function getMessages($botId = 'bot1', $limit = 100, $type = null)
    {
        if ($type === 'incoming') {
            return $this->getIncomingMessages($botId, $limit);
        } else if ($type === 'outgoing') {
            return $this->getOutgoingMessages($botId, $limit);
        }

        return $this->getIncomingMessages($botId, $limit);
    }

    public function getFiles($botId = 'bot1', $limit = 50)
    {
        $url = $this->baseUrl . "/api/{$botId}/files?limit={$limit}";
        return $this->makeRequest($url);
    }

    public function getStats($botId = 'bot1')
    {
        $url = $this->baseUrl . "/api/{$botId}/stats";
        return $this->makeRequest($url);
    }

    public function getBots()
    {
        $url = $this->baseUrl . "/api/bots";
        $result = $this->makeRequest($url);

        if ($result['success'] && isset($result['data'])) {
            return $result;
        } else {
            return [
                'success' => true,
                'data' => [
                    [
                        'id' => 'bot1',
                        'name' => 'Bot Utama',
                        'status' => 'connected',
                        'allowedNumbers' => ["628123456789", "628987654321"]
                    ]
                ]
            ];
        }
    }

    public function sendMessageOld($botId, $chatJid, $message)
    {
        return $this->sendMessage($botId, $chatJid, $message);
    }

    public function sendFile($botId, $chatJid, $filePath, $caption = '')
    {
        $url = $this->baseUrl . "/api/{$botId}/send-file";
        $data = [
            'chatJid' => $chatJid,
            'filePath' => $filePath,
            'caption' => $caption
        ];

        return $this->makeRequest($url, 'POST', $data);
    }


// Method untuk mendapatkan delivery status
public function getMessageDeliveryStatus($botId, $messageId)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/{$messageId}/delivery-status";
    return $this->makeRequest($url);
}

// Method untuk update delivery status
public function updateDeliveryStatus($botId, $messageId, $status)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/{$messageId}/delivery-status";
    $data = [
        'status' => $status
    ];

    return $this->makeRequest($url, 'PUT', $data);
}

// Method untuk kirim pesan via API eksternal (simulasi dari APK)
public function sendMessageViaAPI($botId, $senderJid, $message, $senderName = null, $senderPhone = null)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/receive";
    $data = [
        'senderJid' => $senderJid,
        'message' => $message,
        'senderName' => $senderName,
        'senderPhone' => $senderPhone
    ];

    return $this->makeRequest($url, 'POST', $data);
}

// Method untuk mendapatkan pesan dengan pagination
public function getMessagesWithPagination($botId, $type = 'incoming', $page = 1, $limit = 20, $status = null)
{
    $offset = ($page - 1) * $limit;
    $url = $this->baseUrl . "/api/{$botId}/messages/{$type}?limit={$limit}&offset={$offset}";
    
    if ($status) {
        $url .= "&status={$status}";
    }
    
    return $this->makeRequest($url);
}

// Method untuk mendapatkan statistik detail
public function getDetailedStats($botId)
{
    $url = $this->baseUrl . "/api/{$botId}/stats/detailed";
    return $this->makeRequest($url);
}

// Method untuk clear messages berdasarkan status
public function clearMessagesByStatus($botId, $type, $status)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/{$type}/clear";
    $data = [
        'status' => $status
    ];

    return $this->makeRequest($url, 'POST', $data);
}

// Method untuk bulk retry failed messages
public function retryAllFailedMessages($botId)
{
    $url = $this->baseUrl . "/api/{$botId}/messages/retry-all-failed";
    return $this->makeRequest($url, 'POST');
}

    public function getHealth()
    {
        $url = $this->baseUrl . "/api/health";
        return $this->makeRequest($url);
    }

    // Fungsi baru untuk mengirim auto-respon
    public function sendAutoResponse($botId, $targetJid, $originalMessageId)
    {
        $responseMessage = "Pesan Anda telah kami terima dan sedang kami proses. Terima kasih telah menghubungi kami.";
        
        return $this->sendMessage($botId, $targetJid, $responseMessage, $originalMessageId);
    }

    // Fungsi baru untuk mengirim notifikasi proses selesai
    // public function sendCompletionNotification($botId, $targetJid, $originalMessageId)
    // {
    //     $completionMessage = "Pesan Anda telah selesai kami proses. Apakah ada yang else yang bisa kami bantu?";
        
    //     return $this->sendMessage($botId, $targetJid, $completionMessage, $originalMessageId);
    // }

    private function makeRequest($url, $method = 'GET', $data = [])
    {
        error_log("API Request: {$method} {$url}");

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        error_log("API Response: HTTP {$httpCode} - " . substr($response, 0, 200));

        if ($httpCode !== 200 || $error) {
            error_log("API Error: {$error}, HTTP Code: {$httpCode}, URL: {$url}");
            return [
                'success' => false,
                'error' => $error ?: 'HTTP error: ' . $httpCode,
                'http_code' => $httpCode,
                'data' => null
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'data' => null
            ];
        }

        return [
            'success' => true,
            'data' => $decoded,
            'http_code' => $httpCode
        ];
    }

    public function testConnection()
    {
        $health = $this->getHealth();
        return $health['success'];
    }

    // Fungsi baru untuk mengirim notifikasi proses selesai
    public function sendCompletionNotification($botId, $targetJid, $originalMessageId)
    {
        $completionMessage = "Pesan Anda telah selesai kami proses. Apakah ada yang else yang bisa kami bantu?";
        
        return $this->sendMessage($botId, $targetJid, $completionMessage, $originalMessageId);
    }

    // Method untuk retry pesan yang gagal
    public function retryMessage($botId, $messageId)
    {
        $url = $this->baseUrl . "/api/{$botId}/messages/{$messageId}/retry";
        return $this->makeRequest($url, 'POST');
    }

    // Method untuk mengirim pesan via API (untuk aplikasi eksternal)
    public function receiveExternalMessage($botId, $senderJid, $message, $senderName = null, $senderPhone = null)
    {
        $url = $this->baseUrl . "/api/{$botId}/messages/receive";
        $data = [
            'senderJid' => $senderJid,
            'message' => $message,
            'senderName' => $senderName,
            'senderPhone' => $senderPhone
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    // Method untuk mendapatkan status worker
    public function getWorkerStatus()
    {
        $url = $this->baseUrl . "/api/worker/status";
        return $this->makeRequest($url);
    }

    // Method untuk trigger manual processing
    public function processMessagesNow()
    {
        $url = $this->baseUrl . "/api/worker/process-now";
        return $this->makeRequest($url, 'POST');
    }
}

