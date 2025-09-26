<?php
// pages/debug_qrcode.php
require_once __DIR__ . '/../includes/qr_handler_debug.php';

echo "<h2>QR Code Debug Page</h2>";
echo "<hr>";

$bots = getAllBotsStatusDebug();
?>

<div class="qrcode-container">
    <div class="bots-grid">
        <?php foreach ($bots as $bot): 
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
                
                <div class="debug-info">
                    <h4>Debug Info:</h4>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>
                    <p><strong>QR Data URL:</strong> <?php echo !empty($bot['qrDataUrl']) ? 'Available' : 'Not Available'; ?></p>
                    <p><strong>Condition Check:</strong> <?php echo ($status === 'waiting_qr' && !empty($bot['qrDataUrl'])) ? 'PASS' : 'FAIL'; ?></p>
                    
                    <?php if (!empty($bot['qrDataUrl'])): ?>
                        <p><strong>QR URL Length:</strong> <?php echo strlen($bot['qrDataUrl']); ?> characters</p>
                        <p><strong>QR URL Preview:</strong> <?php echo htmlspecialchars(substr($bot['qrDataUrl'], 0, 50)) . '...'; ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="bot-qr-container">
                    <?php if ($status === 'waiting_qr' && !empty($bot['qrDataUrl'])): ?>
                        <img src="<?php echo $bot['qrDataUrl']; ?>" 
                             alt="QR Code for <?php echo htmlspecialchars($bot['name']); ?>"
                             class="qr-image">
                        <p class="qr-instruction">Scan QR code ini dengan WhatsApp</p>
                    <?php else: ?>
                        <div class="debug-placeholder">
                            <h4>QR Code Not Displayed</h4>
                            <p>Status: <?php echo htmlspecialchars($status); ?></p>
                            <p>QR Available: <?php echo !empty($bot['qrDataUrl']) ? 'Yes' : 'No'; ?></p>
                            <p>Required: status = 'waiting_qr' AND qrDataUrl not empty</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="bot-actions">
                    <button class="action-btn refresh-btn" onclick="refreshQR('<?php echo $bot['id']; ?>')">
                        🔄 Refresh QR
                    </button>
                    <button class="action-btn restart-btn" onclick="restartBot('<?php echo $bot['id']; ?>')">
                        🔄 Restart Bot
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.debug-info {
    background: #f8f9fa;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.debug-info h4 {
    margin: 0 0 10px 0;
    color: #007bff;
}

.debug-info p {
    margin: 5px 0;
    font-size: 12px;
}

.debug-placeholder {
    text-align: center;
    padding: 30px;
    background: #fff3cd;
    border-radius: 5px;
}

.debug-placeholder h4 {
    color: #856404;
    margin-bottom: 10px;
}

.debug-placeholder p {
    color: #856404;
    font-size: 12px;
    margin: 5px 0;
}

/* Existing styles */
.qrcode-container { background: rgba(255, 255, 255, 0.95); padding: 25px; }
.bots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
.bot-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1); }
.bot-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.action-btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; }
.refresh-btn { background: #3b82f6; color: white; }
.restart-btn { background: #f59e0b; color: white; }
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
            console.log('Refresh response:', data);
            if (data.success) {
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                alert('Gagal merefresh QR: ' + (data.error || 'Unknown error'));
                button.textContent = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Refresh error:', error);
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
                console.log('Restart response:', data);
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    alert('Gagal merestart bot: ' + (data.error || 'Unknown error'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Restart error:', error);
                alert('Error: ' + error.message);
                button.textContent = originalText;
                button.disabled = false;
            });
    }
}
</script>