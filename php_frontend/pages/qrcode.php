<?php
// pages/qrcode.php
require_once __DIR__ . '/../includes/qr_handler.php';


$bots = getAllBotsStatus();
?>

<div class="dashboard-header">
    <h2>QR Code WhatsApp Bots</h2>
    <p>Scan QR code untuk menghubungkan bot WhatsApp</p>
</div>

<div class="qrcode-container">
    <div class="bots-grid">
        <?php foreach ($bots as $bot): 
            // Pastikan status adalah string
            $status = is_array($bot['status']) ? ($bot['status']['status'] ?? 'disconnected') : $bot['status'];
            $status = strtolower(trim($status));
        ?>
            <div class="bot-card">
                <div class="bot-header">
                    <h3><?php echo htmlspecialchars($bot['name']); ?></h3>
                    <span class="status-badge status-<?php echo $status; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>
                
                <div class="bot-qr-container">
                    <?php if ($status === 'waiting_qr' && !empty($bot['qrDataUrl'])): ?>
                        <img src="<?php echo $bot['qrDataUrl']; ?>" 
                             alt="QR Code for <?php echo htmlspecialchars($bot['name']); ?>"
                             class="qr-image">
                        <p class="qr-instruction">Scan QR code ini dengan WhatsApp</p>
                    <?php else: ?>
                        <div class="bot-status-placeholder">
                            <?php if ($status === 'connected'): ?>
                                <div class="status-connected">
                                    <span class="status-icon"></span>
                                    <h4>Bot Terhubung</h4>
                                    <p><?php echo htmlspecialchars($bot['name']); ?> sudah aktif dan siap digunakan</p>
                                </div>
                            <?php elseif ($status === 'connecting'): ?>
                                <div class="status-connecting">
                                    <span class="status-icon"></span>
                                    <h4>Sedang Menghubungkan...</h4>
                                    <p><?php echo htmlspecialchars($bot['name']); ?> sedang proses koneksi</p>
                                </div>
                            <?php elseif ($status === 'error'): ?>
                                <div class="status-error">
                                    <span class="status-icon"></span>
                                    <h4>Error</h4>
                                    <p>Terjadi kesalahan pada <?php echo htmlspecialchars($bot['name']); ?></p>
                                </div>
                            <?php elseif ($status === 'waiting_qr'): ?>
                                <div class="status-waiting">
                                    <span class="status-icon"></span>
                                    <h4>Menunggu QR</h4>
                                    <p>Menunggu generate QR code untuk <?php echo htmlspecialchars($bot['name']); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="status-disconnected">
                                    <span class="status-icon">🔌</span>
                                    <h4>Bot Terputus</h4>
                                    <p><?php echo htmlspecialchars($bot['name']); ?> dalam status terputus</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="bot-actions">
                    <?php if ($status === 'waiting_qr'): ?>
                        <button class="action-btn refresh-btn" onclick="refreshQR('<?php echo $bot['id']; ?>')">
                             Refresh QR
                        </button>
                    <?php endif; ?>
                    
                    <button class="action-btn restart-btn" onclick="restartBot('<?php echo $bot['id']; ?>')">
                         Restart Bot
                    </button>
                    
                    <?php if ($status === 'connected'): ?>
                        <button class="action-btn status-btn" onclick="checkBotStatus('<?php echo $bot['id']; ?>')">
                             Status
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="bot-info">
                    <div class="info-item">
                        <span class="info-label">ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($bot['id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Port:</span>
                        <span class="info-value"><?php echo htmlspecialchars($bot['port']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Percobaan Ulang:</span>
                        <span class="info-value"><?php echo $bot['reconnectAttempts']; ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* CSS yang sama seperti sebelumnya */
.qrcode-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.bots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.bot-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
}

.bot-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.bot-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.bot-qr-container {
    text-align: center;
    margin: 15px 0;
    min-height: 250px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.qr-image {
    max-width: 200px;
    max-height: 200px;
    border: 1px solid #ddd;
    padding: 10px;
    background: white;
}

.qr-instruction {
    margin-top: 10px;
    color: #666;
    font-size: 14px;
}

.bot-status-placeholder {
    text-align: center;
    padding: 30px 0;
}

.status-connected, .status-connecting, .status-error, .status-disconnected, .status-waiting {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.status-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.status-connected .status-icon {
    color: #22c55e;
}

.status-connecting .status-icon {
    color: #f59e0b;
}

.status-error .status-icon {
    color: #ef4444;
}

.status-disconnected .status-icon {
    color: #6b7280;
}

.status-waiting .status-icon {
    color: #3b82f6;
}

.bot-status-placeholder h4 {
    margin: 0;
    color: #333;
}

.bot-status-placeholder p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.bot-actions {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.action-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.refresh-btn {
    background: #3b82f6;
    color: white;
}

.refresh-btn:hover {
    background: #2563eb;
}

.restart-btn {
    background: #f59e0b;
    color: white;
}

.restart-btn:hover {
    background: #d97706;
}

.status-btn {
    background: #10b981;
    color: white;
}

.status-btn:hover {
    background: #059669;
}

.bot-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.info-item {
    display: flex;
    justify-content: between;
    margin-bottom: 5px;
}

.info-label {
    font-weight: 600;
    color: #666;
    margin-right: 8px;
    min-width: 100px;
}

.info-value {
    color: #333;
}

/* .status-waiting_qr {
    background-color: #fff3cd;
    color: #856404;
}

.status-connected {
    background-color: #d4edda;
    color: #155724;
}

.status-connecting {
    background-color: #cce7ff;
    color: #004085;
}

.status-error {
    background-color: #f8d7da;
    color: #721c24;
}

.status-disconnected {
    background-color: #f8f9fa;
    color: #6c757d;
} */

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
</style>

<script>
function refreshQR(botId) {
    const button = event.target;
    const originalText = button.textContent;
    
    button.textContent = 'Memuat...';
    button.disabled = true;
    
    fetch(`includes/qr_handler.php?action=refresh&bot=${botId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('Gagal merefresh QR: ' + (data.error || 'Unknown error'));
                button.textContent = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
            button.textContent = originalText;
            button.disabled = false;
        });
}

function restartBot(botId) {
    if (confirm(`Apakah Anda yakin ingin merestart ${botId}?`)) {
        const button = event.target;
        const originalText = button.textContent;
        
        button.textContent = 'Restarting...';
        button.disabled = true;
        
        fetch(`includes/qr_handler.php?action=restart&bot=${botId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Gagal merestart bot: ' + (data.error || 'Unknown error'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                button.textContent = originalText;
                button.disabled = false;
            });
    }
}

function checkBotStatus(botId) {
    fetch(`includes/qr_handler.php?action=status&bot=${botId}`)
        .then(response => response.json())
        .then(data => {
            alert(`Status ${botId}:\n` + 
                  `Name: ${data.name || 'N/A'}\n` +
                  `Status: ${data.status || 'N/A'}\n` +
                  `Reconnect Attempts: ${data.reconnectAttempts || 0}`);
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Auto-refresh QR codes every 30 seconds
setInterval(() => {
    const hasWaitingQR = document.querySelector('.status-waiting_qr');
    if (hasWaitingQR) {
        console.log('Auto-refreshing QR codes...');
        window.location.reload();
    }
}, 30000);
</script>