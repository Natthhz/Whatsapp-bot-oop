<?php
date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../whatsapp-api-client.php');

$api = new WhatsAppAPIClient();
$whatsapp_type = isset($_GET['whatsapp_type']) ? $_GET['whatsapp_type'] : 'incoming';
$bot_id = isset($_GET['bot_id']) ? $_GET['bot_id'] : 'bot1';

$incoming_messages = [];
$outgoing_messages = [];
$bots_data = ['success' => false, 'data' => []];
$api_error = '';
$success_message = '';
$error_message = '';

$incoming_data = $api->getIncomingMessages($bot_id, 100);
$outgoing_data = $api->getOutgoingMessages($bot_id, 100);
$stats_data = $api->getStats($bot_id);
$bots_data = $api->getBots();

error_log("Incoming data structure: " . print_r($incoming_data, true));
error_log("Outgoing data structure: " . print_r($outgoing_data, true));

if (isset($incoming_data['success']) && $incoming_data['success']) {
    if (isset($incoming_data['data']['data']) && is_array($incoming_data['data']['data'])) {
        $incoming_messages = $incoming_data['data']['data'];
    } else if (isset($incoming_data['data']) && is_array($incoming_data['data'])) {
        $incoming_messages = $incoming_data['data'];
    } else {
        $api_error = 'Unexpected incoming data structure';
        error_log('Unexpected incoming data structure: ' . print_r($incoming_data, true));
        $incoming_messages = [];
    }
} else {
    $api_error = isset($incoming_data['error']) ? $incoming_data['error'] : 'Failed to fetch incoming messages';
    error_log('Incoming messages error: ' . $api_error);

    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        $incoming_messages = [
            [
                'id' => '1',
                'bot_id' => 'bot1',
                'sender_jid' => '628123456789@s.whatsapp.net',
                'sender_name' => 'John Doe',
                'sender_phone' => '628123456789',
                'message' => 'Ini adalah contoh pesan masuk (fallback)',
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    } else {
        $incoming_messages = [];
    }
}

if (isset($outgoing_data['success']) && $outgoing_data['success']) {
    if (isset($outgoing_data['data']['data']) && is_array($outgoing_data['data']['data'])) {
        $outgoing_messages = $outgoing_data['data']['data'];
    } else if (isset($outgoing_data['data']) && is_array($outgoing_data['data'])) {
        $outgoing_messages = $outgoing_data['data'];
    } else {
        $api_error = 'Unexpected outgoing data structure';
        error_log('Unexpected outgoing data structure: ' . print_r($outgoing_data, true));
        $outgoing_messages = [];
    }
} else {
    $api_error = isset($outgoing_data['error']) ? $outgoing_data['error'] : 'Failed to fetch outgoing messages';
    error_log('Outgoing messages error: ' . $api_error);

    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        $outgoing_messages = [
            [
                'id' => '2',
                'bot_id' => 'bot1',
                'target_jid' => '628987654321@s.whatsapp.net',
                'message' => 'Ini adalah contoh pesan keluar (fallback)',
                'status' => 'sent',
                'created_at' => date('Y-m-d H:i:s'),
                'sender_name' => 'John Doe',
                'sender_phone' => '628987654321'
            ]
        ];
    } else {
        $outgoing_messages = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $target_jid = $_POST['target_jid'];
        $message = $_POST['message'];
        $reply_to = $_POST['reply_to'] ?? null;

        if (!empty($target_jid) && !empty($message)) {
            $result = $api->sendMessage($bot_id, $target_jid, $message, $reply_to);

            if ($result['success']) {
                $success_message = "  Pesan berhasil dikirim!";

                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('target_jid').value = '';
                        document.getElementById('message_text').value = '';
                        document.getElementById('reply_to').value = '';
                    });
                </script>";
            } else {
                $error_message = "  Gagal mengirim pesan: " . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $error_message = "  Target JID dan pesan harus diisi";
        }
    }

    if (isset($_POST['mark_as_read'])) {
        $message_id = $_POST['message_id'];
        $sender_jid = $_POST['sender_jid'];

        $result = $api->updateMessageStatus($bot_id, $message_id, 'incoming', 'read');

        if ($result['success']) {
            $autoResponse = $api->sendAutoResponse($bot_id, $sender_jid, $message_id);

            if ($autoResponse['success']) {
                $success_message = "  Status pesan diperbarui dan notifikasi terkirim!";
            } else {
                $success_message = "  Status pesan diperbarui, tetapi gagal mengirim notifikasi: " . ($autoResponse['error'] ?? 'Unknown error');
            }

            echo "<script>setTimeout(() => { window.location.reload(); }, 1000);</script>";
        } else {
            $error_message = "  Gagal memperbarui status: " . ($result['error'] ?? 'Unknown error');
        }
    }

    if (isset($_POST['mark_as_processed'])) {
        $message_id = $_POST['message_id'];
        $target_jid = $_POST['target_jid'];

        $result = $api->updateMessageStatus($bot_id, $message_id, 'outgoing', 'processed');

        error_log("Mark as processed result: " . print_r($result, true));

        if ($result['success']) {
            $completionNotification = $api->sendCompletionNotification($bot_id, $target_jid, $message_id);

            if ($completionNotification['success']) {
                $success_message = "Status pesan diperbarui dan notifikasi selesai terkirim!";
            } else {
                $success_message = "Status pesan diperbarui, tetapi gagal mengirim notifikasi: " . ($completionNotification['error'] ?? 'Unknown error');
            }

            echo "<script>setTimeout(() => { window.location.reload(); }, 1000);</script>";
        } else {
            $error_message = "Gagal memperbarui status: " . ($result['error'] ?? 'Unknown error');
            error_log("Update status failed: " . print_r($result, true));
        }
    }
}
?>

<div class="dashboard-header">
    <h2>WhatsApp Message Manager</h2>
    <p>Kelola dan monitor pesan WhatsApp yang <?php echo $whatsapp_type == 'incoming' ? 'diterima' : 'dikirim'; ?> melalui bot</p>

    <?php if (!empty($api_error)): ?>
        <div style="background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0;">
            API Connection Issue: <?php echo htmlspecialchars($api_error); ?>
            <br><small>Showing fallback data for demonstration</small>
        </div>
    <?php endif; ?>

    <div class="bot-selector" style="margin-top: 15px;">
        <label for="bot_select">Pilih Bot:</label>
        <select id="bot_select" onchange="changeBot(this.value)">
            <?php if (is_array($bots_data) && isset($bots_data['success']) && $bots_data['success'] && isset($bots_data['data'])): ?>
                <?php foreach ($bots_data['data'] as $bot): ?>
                    <option value="<?php echo htmlspecialchars($bot['id'] ?? 'bot1'); ?>" <?php echo $bot_id == ($bot['id'] ?? 'bot1') ? 'selected' : ''; ?>>
                        <?php
                        if (is_array($bot['name'] ?? '')) {
                            echo htmlspecialchars($bot['name']['name'] ?? 'Unknown Bot');
                        } else {
                            echo htmlspecialchars($bot['name'] ?? 'Unknown Bot');
                        }
                        ?>
                        (<?php
                            if (is_array($bot['status'] ?? '')) {
                                echo htmlspecialchars($bot['status']['status'] ?? 'unknown');
                            } else {
                                echo htmlspecialchars($bot['status'] ?? 'unknown');
                            }
                            ?>)
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="bot1" <?php echo $bot_id == 'bot1' ? 'selected' : ''; ?>>Bot Utama (connected)</option>
                <option value="bot2" <?php echo $bot_id == 'bot2' ? 'selected' : ''; ?>>Bot Kedua (connected)</option>
                <option value="bot3" <?php echo $bot_id == 'bot3' ? 'selected' : ''; ?>>Bot Ketiga (connected)</option>
            <?php endif; ?>
        </select>
    </div>

    <!-- <div class="tabs" style="margin-top: 20px;">
        <button class="tab-btn <?php echo $whatsapp_type == 'incoming' ? 'active' : ''; ?>"
            onclick="changeTab('incoming')">
            Pesan Masuk (<?php echo count($incoming_messages); ?>)
        </button>
        <button class="tab-btn <?php echo $whatsapp_type == 'outgoing' ? 'active' : ''; ?>"
            onclick="changeTab('outgoing')">
            Pesan Keluar (<?php echo count($outgoing_messages); ?>)
        </button>
    </div> -->
</div>

<div class="message-container" style="margin-bottom: 20px;">
    <h3>Kirim Pesan Baru</h3>
    <form method="POST" id="sendMessageForm">
        <input type="hidden" name="send_message" value="1">
        <input type="hidden" name="reply_to" id="reply_to" value="">
        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 10px; align-items: end;">
            <div>
                <label>Target JID:</label>
                <input type="text" name="target_jid" id="target_jid" placeholder="628123456789@s.whatsapp.net" required style="width: 100%;">
            </div>
            <div>
                <label>Pesan:</label>
                <textarea name="message" id="message_text" placeholder="Tulis pesan Anda di sini..." required style="width: 100%; height: 60px;"></textarea>
            </div>
            <div>
                <button type="submit" class="reply-btn" style="width: 100%;" id="sendButton">Kirim Pesan</button>
            </div>
        </div>
    </form>

    <?php if (isset($success_message)): ?>
        <div style="color: green; margin-top: 10px;"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div style="color: red; margin-top: 10px;"><?php echo $error_message; ?></div>
    <?php endif; ?>
</div>

<div class="message-container">
    <h3>Daftar Pesan <?php echo $whatsapp_type == 'incoming' ? 'Masuk' : 'Keluar'; ?></h3>

    <div class="table-responsive">
        <table class="message-table">
            <thead>
                <tr>
                    <?php if ($whatsapp_type == 'incoming'): ?>
                        <th>ID</th>
                        <th>Bot</th>
                        <th>Pengirim</th>
                        <th>Waktu Masuk</th>
                        <th>Pesan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    <?php else: ?>
                        <th>ID</th>
                        <th>Bot</th>
                        <th>Tujuan</th>
                        <th>Waktu Keluar</th>
                        <th>Pesan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($whatsapp_type == 'incoming'): ?>
                    <?php if (!empty($incoming_messages)): ?>
                        <?php foreach ($incoming_messages as $msg): ?>
                            <?php if (is_array($msg)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($msg['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($msg['bot_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="sender-info">
                                            <span class="sender-name"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></span>
                                            <span class="sender-phone"><?php echo htmlspecialchars($msg['sender_phone'] ?? $msg['sender_jid'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $created_at = $msg['created_at'] ?? 'N/A';
                                        if ($created_at !== 'N/A') {
                                            $date = new DateTime($created_at);
                                            echo htmlspecialchars($date->format('Y-m-d H:i:s'));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['message'] ?? 'No message'); ?></td>
                                    <td>
                                        <?php
                                        $status = $msg['status'] ?? 'unread';
                                        $status_class = 'status-unread';
                                        $status_text = 'Belum Dibaca';

                                        if ($status == 'read') {
                                            $status_class = 'status-read';
                                            $status_text = 'Dibaca';
                                        } elseif ($status == 'replied') {
                                            $status_class = 'status-replied';
                                            $status_text = 'Dibalas';
                                        } elseif ($status == 'processed') {
                                            $status_class = 'status-processed';
                                            $status_text = 'Diproses';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <button class="reply-btn" onclick="replyMessage('<?php echo htmlspecialchars($msg['sender_jid'] ?? ''); ?>', '<?php echo htmlspecialchars($msg['id'] ?? ''); ?>', '<?php echo htmlspecialchars($msg['message'] ?? ''); ?>')">
                                            Balas
                                        </button>
                                        <?php if (($msg['status'] ?? 'unread') == 'unread'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="mark_as_read" value="1">
                                                <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($msg['id'] ?? ''); ?>">
                                                <input type="hidden" name="sender_jid" value="<?php echo htmlspecialchars($msg['sender_jid'] ?? ''); ?>">
                                                <button type="submit" class="read-btn">Tandai Dibaca</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                Tidak ada pesan masuk
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (!empty($outgoing_messages)): ?>
                        <?php foreach ($outgoing_messages as $msg): ?>
                            <?php if (is_array($msg)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($msg['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($msg['bot_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="sender-info">
                                            <span class="sender-name"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></span>
                                            <span class="sender-phone"><?php echo htmlspecialchars($msg['sender_phone'] ?? $msg['target_jid'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $created_at = $msg['created_at'] ?? 'N/A';
                                        if ($created_at !== 'N/A') {
                                            $date = new DateTime($created_at);
                                            echo htmlspecialchars($date->format('Y-m-d H:i:s'));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['message'] ?? 'No message'); ?></td>
                                    <td>
                                        <?php
                                        $status = $msg['status'] ?? 'pending';
                                        $status_class = 'status-pending';
                                        $status_text = 'Tertunda';

                                        if ($status == 'sent') {
                                            $status_class = 'status-sent';
                                            $status_text = 'Terkirim';
                                        } elseif ($status == 'failed') {
                                            $status_class = 'status-failed';
                                            $status_text = 'Gagal';
                                        } elseif ($status == 'processed') {
                                            $status_class = 'status-processed';
                                            $status_text = 'Selesai Diproses';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <?php if (($msg['status'] ?? 'pending') == 'sent'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="mark_as_processed" value="1">
                                                <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($msg['id'] ?? ''); ?>">
                                                <input type="hidden" name="target_jid" value="<?php echo htmlspecialchars($msg['target_jid'] ?? ''); ?>">
                                                <button type="submit" class="process-btn">Tandai Selesai</button>
                                            </form>

                                            <!-- Tampilkan delivery status (centang) -->
                                            <?php
                                            $deliveryStatus = $msg['delivery_status'] ?? null;
                                            if ($deliveryStatus): ?>
                                                <span class="delivery-status delivery-<?php echo $deliveryStatus; ?>">
                                                    <?php
                                                    if ($deliveryStatus == 'delivered') {
                                                        echo '<span class="checkmark single">✓</span>';
                                                    } elseif ($deliveryStatus == 'read') {
                                                        echo '<span class="checkmark double">✓✓</span>';
                                                    } else {
                                                        echo '<span class="checkmark sent">→</span>';
                                                    }
                                                    ?>
                                                </span>
                                            <?php endif; ?>

                                        <?php elseif (($msg['status'] ?? 'pending') == 'pending'): ?>
                                            <button class="retry-btn" onclick="retryMessage('<?php echo htmlspecialchars($msg['id'] ?? ''); ?>')">
                                                Coba Lagi
                                            </button>
                                            <span class="status-info">Menunggu...</span>

                                        <?php elseif (($msg['status'] ?? 'pending') == 'failed'): ?>
                                            <button class="retry-btn" onclick="retryMessage('<?php echo htmlspecialchars($msg['id'] ?? ''); ?>')">
                                                Coba Lagi
                                            </button>
                                            <br><small class="retry-info">Retry: <?php echo ($msg['retry_count'] ?? 0); ?>/3</small>
                                            <?php if (!empty($msg['error_message'])): ?>
                                                <br><small class="error-info" title="<?php echo htmlspecialchars($msg['error_message']); ?>">
                                                    Error: <?php echo htmlspecialchars(substr($msg['error_message'], 0, 30)); ?>...
                                                </small>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <span class="status-completed">Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                Tidak ada pesan keluar
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>



<script>
    function retryMessage(messageId) {
        if (confirm('Apakah Anda ingin mencoba mengirim ulang pesan ini?')) {
            const retryBtn = event.target;
            retryBtn.disabled = true;
            retryBtn.textContent = 'Memproses...';

            fetch(`/api/${getCurrentBotId()}/messages/${messageId}/retry`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pesan berhasil dijadwalkan ulang untuk dikirim');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert('Gagal menjadwalkan ulang pesan: ' + data.error);
                        retryBtn.disabled = false;
                        retryBtn.textContent = 'Coba Lagi';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi error saat menjadwalkan ulang pesan');
                    retryBtn.disabled = false;
                    retryBtn.textContent = 'Coba Lagi';
                });
        }
    }

    function getCurrentBotId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('bot_id') || 'bot1';
    }

    setInterval(() => {
        const hasPendingMessages = document.querySelector('.status-pending, .status-failed');
        if (hasPendingMessages) {
            console.log('Refreshing for status updates...');
            window.location.reload();
        }
    }, 10000); 
</script>

<style>
    .delivery-status {
        margin-left: 8px;
        font-size: 12px;
    }

    .checkmark {
        font-weight: bold;
    }

    .checkmark.single {
        color: #6c757d;
    }

    .checkmark.double {
        color: #007bff;
    }

    .checkmark.sent {
        color: #28a745;
    }

    .retry-info {
        color: #856404;
        font-style: italic;
    }

    .error-info {
        color: #dc3545;
        font-style: italic;
        cursor: help;
    }

    .status-info {
        color: #6c757d;
        font-size: 11px;
        font-style: italic;
    }

    .status-completed {
        color: #28a745;
        font-weight: bold;
        font-size: 12px;
    }

    .status-pending {
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }

        100% {
            opacity: 1;
        }
    }

    tr:has(.status-failed) {
        background-color: #fff5f5;
    }

    tr:has(.status-pending) {
        background-color: #fffbf0;
    }

    .retry-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<script>
    function changeBot(botId) {
        const url = new URL(window.location.href);
        url.searchParams.set('bot_id', botId);
        window.location.href = url.toString();
    }

    function changeTab(tab) {
        const url = new URL(window.location.href);
        url.searchParams.set('whatsapp_type', tab);
        window.location.href = url.toString();
    }

    function replyMessage(targetJid, messageId, messageContent) {
        document.getElementById('target_jid').value = targetJid;
        document.getElementById('reply_to').value = messageId;
        document.getElementById('message_text').value = `Re: ${messageContent}`;

        document.querySelector('.message-container').scrollIntoView({
            behavior: 'smooth'
        });
    }

    function retryMessage(messageId) {
        if (confirm('Apakah Anda ingin mencoba mengirim ulang pesan ini?')) {
            alert('Fitur mengirim ulang pesan akan segera tersedia.');
        }
    }

    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('sendMessageForm');
        const sendButton = document.getElementById('sendButton');

        if (form) {
            form.addEventListener('submit', function() {
                if (sendButton) {
                    sendButton.disabled = true;
                    sendButton.textContent = 'Mengirim...';
                }
            });
        }
    });
</script>

<style>
    .tabs {
        display: flex;
        border-bottom: 1px solid #ddd;
    }

    .tab-btn {
        padding: 10px 20px;
        background: #f5f5f5;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        margin-right: 5px;
    }

    .tab-btn.active {
        border-bottom-color: #007bff;
        background: #fff;
        font-weight: bold;
    }

    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-unread {
        background-color: #ffcccc;
        color: #d63031;
    }

    .status-read {
        background-color: #dfe6e9;
        color: #636e72;
    }

    .status-replied {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-sent {
        background-color: #d4edda;
        color: #155724;
    }

    .status-failed {
        background-color: #f8d7da;
        color: #721c24;
    }

    .status-processed {
        background-color: #4CAF50;
        color: white;
    }

    .reply-btn,
    .read-btn,
    .retry-btn,
    .process-btn {
        padding: 5px 10px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        margin: 2px;
        font-size: 12px;
    }

    .reply-btn {
        background-color: #007bff;
        color: white;
    }

    .read-btn {
        background-color: #6c757d;
        color: white;
    }

    .retry-btn {
        background-color: #fd7e14;
        color: white;
    }

    .process-btn {
        background-color: #28a745;
        color: white;
    }
</style>