<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .response { margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; }
        .error { color: #dc3545; }
        .success { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test WhatsApp API System</h1>
        
        <div class="form-group">
            <h2>Kirim Pesan ke Sistem</h2>
            <form id="sendMessageForm">
                <div class="form-group">
                    <label>Bot ID:</label>
                    <select id="botId" name="botId">
                        <option value="bot1">Bot 1</option>
                        <option value="bot2">Bot 2</option>
                        <option value="bot3">Bot 3</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Sender JID (Format: 628123456789@s.whatsapp.net):</label>
                    <input type="text" id="senderJid" name="senderJid" placeholder="628123456789@s.whatsapp.net" required>
                </div>
                
                <div class="form-group">
                    <label>Nama Pengirim:</label>
                    <input type="text" id="senderName" name="senderName" placeholder="John Doe">
                </div>
                
                <div class="form-group">
                    <label>Nomor HP:</label>
                    <input type="text" id="senderPhone" name="senderPhone" placeholder="628123456789">
                </div>
                
                <div class="form-group">
                    <label>Pesan:</label>
                    <textarea id="message" name="message" rows="4" placeholder="Tulis pesan Anda di sini..." required></textarea>
                </div>
                
                <button type="submit">Kirim ke Sistem</button>
            </form>
        </div>

        <div class="form-group">
            <h2>Status Worker</h2>
            <button onclick="checkWorkerStatus()">Cek Status Worker</button>
            <button onclick="triggerProcessing()">Trigger Manual Processing</button>
        </div>

        <div id="response" class="response" style="display: none;"></div>
    </div>

    <script>
        const API_BASE_URL = 'http://localhost:3000/api';

        document.getElementById('sendMessageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const botId = document.getElementById('botId').value;
            const senderJid = document.getElementById('senderJid').value;
            const senderName = document.getElementById('senderName').value;
            const senderPhone = document.getElementById('senderPhone').value;
            const message = document.getElementById('message').value;
            
            try {
                showLoading();
                
                const response = await fetch(`${API_BASE_URL}/${botId}/messages/receive`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        senderJid: senderJid,
                        message: message,
                        senderName: senderName,
                        senderPhone: senderPhone
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showResponse(`
                        <div class="success">
                            <h3>Pesan Berhasil Dikirim!</h3>
                            <p>Message ID: ${data.data.messageId}</p>
                            <p>Status: ${data.data.status}</p>
                            <p>${data.data.message}</p>
                        </div>
                    `);
                    
                    document.getElementById('sendMessageForm').reset();
                } else {
                    showResponse(`
                        <div class="error">
                            <h3>Error!</h3>
                            <p>${data.error}</p>
                        </div>
                    `);
                }
                
            } catch (error) {
                showResponse(`
                    <div class="error">
                        <h3>Network Error!</h3>
                        <p>${error.message}</p>
                        <p>Pastikan server API berjalan di ${API_BASE_URL}</p>
                    </div>
                `);
            }
        });

        async function checkWorkerStatus() {
            try {
                showLoading();
                
                const response = await fetch(`${API_BASE_URL}/worker/status`);
                const data = await response.json();
                
                if (data.success) {
                    showResponse(`
                        <div class="success">
                            <h3>Worker Status</h3>
                            <p>Running: ${data.data.isRunning ? 'Yes' : 'No'}</p>
                            <p>Process Interval: ${data.data.processInterval}ms</p>
                            <p>Max Retries: ${data.data.maxRetries}</p>
                            <p>Last Processed: ${data.data.lastProcessed || 'Never'}</p>
                        </div>
                    `);
                } else {
                    showResponse(`
                        <div class="error">
                            <h3>Error Getting Worker Status</h3>
                            <p>${data.error}</p>
                        </div>
                    `);
                }
                
            } catch (error) {
                showResponse(`
                    <div class="error">
                        <h3>Network Error!</h3>
                        <p>${error.message}</p>
                    </div>
                `);
            }
        }

        async function triggerProcessing() {
            try {
                showLoading();
                
                const response = await fetch(`${API_BASE_URL}/worker/process-now`, {
                    method: 'POST'
                });
                const data = await response.json();
                
                if (data.success) {
                    showResponse(`
                        <div class="success">
                            <h3>Processing Triggered</h3>
                            <p>${data.message}</p>
                        </div>
                    `);
                } else {
                    showResponse(`
                        <div class="error">
                            <h3>Error Triggering Processing</h3>
                            <p>${data.error}</p>
                        </div>
                    `);
                }
                
            } catch (error) {
                showResponse(`
                    <div class="error">
                        <h3>Network Error!</h3>
                        <p>${error.message}</p>
                    </div>
                `);
            }
        }

        function showLoading() {
            const responseDiv = document.getElementById('response');
            responseDiv.innerHTML = '<p>Loading...</p>';
            responseDiv.style.display = 'block';
        }

        function showResponse(html) {
            const responseDiv = document.getElementById('response');
            responseDiv.innerHTML = html;
            responseDiv.style.display = 'block';
        }

        document.getElementById('senderPhone').addEventListener('input', (e) => {
            const phone = e.target.value;
            if (phone && !document.getElementById('senderJid').value) {
                document.getElementById('senderJid').value = phone + '@s.whatsapp.net';
            }
        });
    </script>
</body>
</html>