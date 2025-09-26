<?php
// Include API client
require_once(__DIR__ . '/../api/whatsapp-api-client.php');

$api = new WhatsAppAPIClient();
$whatsapp_type = isset($_GET['whatsapp_type']) ? $_GET['whatsapp_type'] : 'incoming';
$bot_id = isset($_GET['bot_id']) ? $_GET['bot_id'] : 'bot1';

// Test koneksi API terlebih dahulu
$api_connected = $api->testConnection();

if (!$api_connected) {
    echo "<div class='error'>⚠️ Tidak dapat terhubung ke API Server. Pastikan Node.js backend berjalan di port 3000.</div>";
    
    // Fallback data untuk development
    $incoming_messages = [
        [
            'id' => '1',
            'bot' => 'Bot Utama',
            'sender_name' => 'John Doe',
            'sender_phone' => '628123456789',
            'in_time' => date('Y-m-d H:i:s'),
            'message' => 'Ini adalah contoh pesan masuk (fallback data)',
            'read_status' => 'Dibaca',
            'action' => 'reply'
        ]
    ];
    
    $outgoing_messages = [
        [
            'id' => '2',
            'bot' => 'Bot Utama',
            'target_phone' => '628987654321',
            'out_time' => date('Y-m-d H:i:s'),
            'message' => 'Ini adalah contoh pesan keluar (fallback data)',
            'status' => 'Terkirim',
            'action' => '-'
        ]
    ];
    
} else {
    // Get data dari Node.js API
    $messages_data = $api->getMessages($bot_id, 100, $whatsapp_type);
    $stats_data = $api->getStats($bot_id);
    $bots_data = $api->getBots();

    // Process data untuk display
    $incoming_messages = [];
    $outgoing_messages = [];

    if ($messages_data['success']) {
        // Proses data dari API
        foreach ($messages_data['data'] as $msg) {
            if ($whatsapp_type == 'incoming') {
                $incoming_messages[] = [
                    'id' => $msg['message_id'] ?? $msg['id'] ?? uniqid(),
                    'bot' => 'Bot Utama',
                    'sender_name' => $msg['sender_name'] ?? 'Unknown',
                    'sender_phone' => $msg['sender_phone'] ?? 'Unknown',
                    'in_time' => $msg['created_at'] ?? date('Y-m-d H:i:s'),
                    'message' => $msg['message'] ?? 'No message',
                    'read_status' => 'Dibaca',
                    'action' => 'reply'
                ];
            } else {
                $outgoing_messages[] = [
                    'id' => $msg['message_id'] ?? $msg['id'] ?? uniqid(),
                    'bot' => 'Bot Utama',
                    'target_phone' => $msg['sender_phone'] ?? 'Unknown',
                    'out_time' => $msg['created_at'] ?? date('Y-m-d H:i:s'),
                    'message' => $msg['message'] ?? 'No message',
                    'status' => 'Terkirim',
                    'action' => '-'
                ];
            }
        }
    } else {
        echo "<div class='error'>❌ Error API: " . htmlspecialchars($messages_data['error']) . "</div>";
        
        // Fallback data
        if ($whatsapp_type == 'incoming') {
            $incoming_messages = [
                [
                    'id' => '1',
                    'bot' => 'Bot Utama',
                    'sender_name' => 'John Doe',
                    'sender_phone' => '628123456789',
                    'in_time' => date('Y-m-d H:i:s'),
                    'message' => 'Ini adalah contoh pesan masuk (fallback)',
                    'read_status' => 'Dibaca',
                    'action' => 'reply'
                ]
            ];
        } else {
            $outgoing_messages = [
                [
                    'id' => '2',
                    'bot' => 'Bot Utama',
                    'target_phone' => '628987654321',
                    'out_time' => date('Y-m-d H:i:s'),
                    'message' => 'Ini adalah contoh pesan keluar (fallback)',
                    'status' => 'Terkirim',
                    'action' => '-'
                ]
            ];
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $chat_jid = $_POST['chat_jid'];
        $message = $_POST['message'];
        
        if (!empty($chat_jid) && !empty($message)) {
            $result = $api->sendMessage($bot_id, $chat_jid, $message);
            
            if ($result['success']) {
                $success_message = "✅ Pesan berhasil dikirim!";
                // Refresh halaman setelah 2 detik
                echo "<script>setTimeout(() => { window.location.reload(); }, 2000);</script>";
            } else {
                $error_message = "❌ Gagal mengirim pesan: " . $result['error'];
            }
        } else {
            $error_message = "❌ Chat JID dan pesan harus diisi";
        }
    }
}
?>

<!-- Kode HTML Anda tetap sama, tapi tambahkan error handling -->
<?php if (isset($error_message)): ?>
    <div class="error-message" style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red; border-radius: 5px;">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($success_message)): ?>
    <div class="success-message" style="color: green; padding: 10px; margin: 10px 0; border: 1px solid green; border-radius: 5px;">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Sisanya kode HTML Anda tetap sama -->