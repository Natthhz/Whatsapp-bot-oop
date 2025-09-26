<?php
require_once 'whatsapp-api-client.php';

$api = new WhatsAppAPIClient();
$bots_data = $api->getBots();
$bot_id = isset($_GET['bot_id']) ? $_GET['bot_id'] : ($bots_data['success'] ? $bots_data['data'][0]['id'] : 'bot1');
$qr_data = null;

if ($bots_data['success']) {
    foreach ($bots_data['data'] as $bot) {
        if ($bot['id'] === $bot_id && isset($bot['qrDataUrl'])) {
            $qr_data = $bot['qrDataUrl'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code WhatsApp</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .qr-container {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .qr-code {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .qr-code img {
            max-width: 100%;
            height: auto;
        }

        .bot-selector {
            margin: 20px 0;
        }

        select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 16px;
        }

        .instructions {
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="qr-container">
        <h2>QR Code Authentication</h2>

        <div class="bot-selector">
            <label for="bot_select">Pilih Bot:</label>
            <select id="bot_select" onchange="changeBot(this.value)">
                <?php if ($bots_data['success']): ?>
                    <?php foreach ($bots_data['data'] as $bot): ?>
                        <option value="<?php echo $bot['id']; ?>" <?php echo $bot_id == $bot['id'] ? 'selected' : ''; ?>>
                            <?php echo $bot['name']; ?> (<?php echo $bot['status']; ?>)
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="bot1">Bot Utama</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="qr-code">
            <?php if ($qr_data): ?>
                <img src="<?php echo $qr_data; ?>" alt="QR Code">
            <?php else: ?>
                <p>Tidak ada QR code yang tersedia. Bot mungkin sudah terautentikasi.</p>
            <?php endif; ?>
        </div>

        <div class="instructions">
            <p>1. Buka WhatsApp di ponsel Anda</p>
            <p>2. Ketuk Menu atau Settings dan pilih Linked Devices</p>
            <p>3. Ketuk Link a Device</p>
            <p>4. Arahkan kamera Anda ke kode QR ini</p>
        </div>
    </div>

    <script>
        function changeBot(botId) {
            const url = new URL(window.location.href);
            url.searchParams.set('bot_id', botId);
            window.location.href = url.toString();
        }

        setInterval(() => {
            window.location.reload();
        }, 5000);
    </script>
</body>

</html>