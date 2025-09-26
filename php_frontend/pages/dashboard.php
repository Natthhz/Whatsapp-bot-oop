<?php
 require_once(__DIR__ . '/../whatsapp-api-client.php');

$api = new WhatsAppAPIClient();
$bots_data = $api->getBots();
$stats_data = [];

 $bots_list = [];
$api_error = null;

if ($bots_data['success'] && isset($bots_data['data'])) {
    $bots_list = $bots_data['data'];
    foreach ($bots_list as $bot) {
        $stats = $api->getStats($bot['id']);
        if ($stats['success']) {
            $stats_data[$bot['id']] = $stats['data'];
        }
    }
} else {
    $api_error = $bots_data['error'];
    
     $bots_list = [
        [
            'id' => 'bot1',
            'name' => 'Bot Utama',
            'status' => 'connected',
            'qrDataUrl' => null
        ]
    ];
    
    $stats_data['bot1'] = [
        'totalMessages' => 15,
        'incomingMessages' => 8,
        'outgoingMessages' => 7
    ];
}
?>

<div class="dashboard-header">
    <h2>Dashboard</h2>
    <p>Overview semua bot WhatsApp dan statistik pesan</p>
</div>

<div class="message-container">
    <h3>Status Bot WhatsApp</h3>
    
    <?php if ($api_error): ?>
    <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4>Koneksi Error</h4>
        <p>Terjadi kesalahan saat menghubungi server backend: <?php echo htmlspecialchars($api_error); ?></p>
        <p>Pastikan:</p>
        <ul>
            <li>Server Node.js berjalan di port 3000</li>
            <li>Tidak ada firewall yang memblokir koneksi</li>
            <li>URL backend di whatsapp-api-client.php benar</li>
        </ul>
        <p>Data yang ditampilkan adalah data contoh untuk keperluan development.</p>
    </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <?php foreach ($bots_list as $bot): ?>
            <?php 
             $status_color = 'gray';
            $status_text = 'Unknown';
            
             if (isset($bot['status'])) {
                if (is_array($bot['status'])) {
                     $status_text = !empty($bot['status']) ? (string)reset($bot['status']) : 'unknown';
                } else {
                    $status_text = (string)$bot['status'];
                }
                
                switch (strtolower($status_text)) {
                    case 'connected':
                        $status_color = 'green';
                        break;
                    case 'connecting':
                        $status_color = 'orange';
                        break;
                    case 'disconnected':
                        $status_color = 'red';
                        break;
                    default:
                        $status_color = 'gray';
                }
            }
            ?>
            
            <div style="background: rgba(255,255,255,0.9); padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <h4><?php echo htmlspecialchars($bot['name'] ?? 'Unknown Bot'); ?></h4>
                <p>ID: <code><?php echo htmlspecialchars($bot['id'] ?? 'N/A'); ?></code></p>
                <p>Status: 
                    <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                        <?php echo htmlspecialchars(ucfirst($status_text)); ?>
                    </span>
                </p>
                
                <?php if (isset($stats_data[$bot['id']])): ?>
                    <div style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-radius: 8px;">
                        <p><strong>Total Pesan:</strong> <?php echo $stats_data[$bot['id']]['totalMessages'] ?? 0; ?></p>
                        <p><strong>Pesan Masuk:</strong> <?php echo $stats_data[$bot['id']]['incomingMessages'] ?? 0; ?></p>
                        <p><strong>Pesan Keluar:</strong> <?php echo $stats_data[$bot['id']]['outgoingMessages'] ?? 0; ?></p>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <button class="reply-btn" onclick="window.location.href='index.php?app=whatsapp&whatsapp_type=incoming&bot_id=<?php echo urlencode($bot['id']); ?>'">
                        Lihat Pesan
                    </button>
                    
                    <?php if (isset($bot['qrDataUrl']) && $bot['qrDataUrl']): ?>
                    <button class="reply-btn" style="margin-left: 10px; background: #f59e0b;" 
                            onclick="window.open('qr-display.php?bot_id=<?php echo urlencode($bot['id']); ?>', '_blank')">
                        QR Code
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <h3>Statistik Keseluruhan</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="background: rgba(102, 126, 234, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <h4>Total Bot</h4>
            <p style="font-size: 24px; font-weight: bold; color: #667eea;">
                <?php echo count($bots_list); ?>
            </p>
        </div>
        
        <div style="background: rgba(34, 197, 94, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <h4>Bot Terhubung</h4>
            <p style="font-size: 24px; font-weight: bold; color: #22c55e;">
                <?php 
                $connected = 0;
                foreach ($bots_list as $bot) {
                    $status = $bot['status'] ?? '';
                    if (is_array($status)) {
                        $status = !empty($status) ? (string)reset($status) : '';
                    }
                    if (strtolower($status) === 'connected') {
                        $connected++;
                    }
                }
                echo $connected;
                ?>
            </p>
        </div>
        
        <div style="background: rgba(245, 158, 11, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <h4>Total Pesan</h4>
            <p style="font-size: 24px; font-weight: bold; color: #f59e0b;">
                <?php
                $totalMessages = 0;
                foreach ($stats_data as $stat) {
                    $totalMessages += $stat['totalMessages'] ?? 0;
                }
                echo $totalMessages;
                ?>
            </p>
        </div>
        
        <div style="background: rgba(236, 72, 153, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <h4>Pesan Masuk</h4>
            <p style="font-size: 24px; font-weight: bold; color: #ec4899;">
                <?php
                $incomingMessages = 0;
                foreach ($stats_data as $stat) {
                    $incomingMessages += $stat['incomingMessages'] ?? 0;
                }
                echo $incomingMessages;
                ?>
            </p>
        </div>
        
        <div style="background: rgba(139, 92, 246, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <h4>Pesan Keluar</h4>
            <p style="font-size: 24px; font-weight: bold; color: #8b5cf6;">
                <?php
                $outgoingMessages = 0;
                foreach ($stats_data as $stat) {
                    $outgoingMessages += $stat['outgoingMessages'] ?? 0;
                }
                echo $outgoingMessages;
                ?>
            </p>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    console.log('Refreshing dashboard data...');
    window.location.reload();
}

 setInterval(refreshDashboard, 30000);
</script>