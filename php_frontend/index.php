<?php
$page_title = "Message Hub - Admin Dashboard";
$active_app = isset($_GET['app']) ? $_GET['app'] : 'whatsapp';
$whatsapp_submenu = isset($_GET['whatsapp_type']) ? $_GET['whatsapp_type'] : 'incoming';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-left: 60px;
            transition: margin-left 0.3s ease;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #667eea;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: scale(1.1);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 280px;
            height: calc(100vh - 70px);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateX(0);
            transition: transform 0.3s ease;
            z-index: 999;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            transform: translateX(-280px);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            color: #666;
            font-size: 14px;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(102, 126, 234, 0.1);
            border-left-color: #667eea;
        }

        .nav-item.active {
            background: rgba(102, 126, 234, 0.15);
            border-left-color: #667eea;
            color: #667eea;
        }

        .nav-item i {
            font-size: 20px;
            margin-right: 15px;
            width: 24px;
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.03);
        }

        .submenu.expanded {
            max-height: 200px;
        }

        .submenu-item {
            padding: 12px 20px 12px 60px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .submenu-item:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .submenu-item.active {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            font-weight: 500;
        }

        .submenu-item i {
            margin-right: 10px;
            font-size: 16px;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            width: calc(100% - 280px);
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: #666;
            font-size: 16px;
        }

        .message-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .message-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .message-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: none;
        }

        .message-table th:first-child {
            border-radius: 8px 0 0 0;
        }

        .message-table th:last-child {
            border-radius: 0 8px 0 0;
        }

        .message-table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: middle;
        }

        .message-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .sender-info {
            display: flex;
            flex-direction: column;
        }

        .sender-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .sender-phone {
            font-size: 12px;
            color: #666;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
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

        .retry-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .retry-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .reply-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .read-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-left: 5px;
        }

        .read-btn:hover {
            background: #5a6268;
        }

        .checkmark {
            color: #22c55e;
            font-size: 16px;
        }

        .checkmark-double {
            color: #3b82f6;
        }

        .checkmark-read {
            color: #9333ea;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-top: 15px;
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
            }

            .header h1 {
                margin-left: 15px;
            }

            .message-table {
                font-size: 14px;
            }

            .message-table th,
            .message-table td {
                padding: 10px 8px;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-btn {
                margin-bottom: 5px;
            }
        }

        .icon-whatsapp::before {
            content: "";
        }

        .icon-email::before {
            content: "";
        }

        .icon-telegram::before {
            content: "";
        }

        .icon-dashboard::before {
            content: "";
        }

        .icon-settings::before {
            content: "";
        }

        .icon-menu::before {
            content: "☰";
        }

        .icon-check::before {
            content: "✓";
        }

        .icon-check-double::before {
            content: "✓✓";
        }

        .icon-incoming::before {
            content: "";
        }

        .icon-outgoing::before {
            content: "";
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <span class="icon-menu"></span>
            </button>
            <h1>Message Flow</h1>
        </header>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <p>Kelola pesan multi-platform</p>
            </div>

            <nav class="nav-menu">
                <div class="nav-item <?php echo $active_app == 'dashboard' ? 'active' : ''; ?>" onclick="selectApp('dashboard')">
                    <span class="icon-dashboard"></span>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item <?php echo $active_app == 'whatsapp' ? 'active' : ''; ?>" onclick="toggleWhatsAppSubmenu()">
                    <span class="icon-whatsapp"></span>
                    <span>WhatsApp</span>
                </div>
                <div class="submenu <?php echo $active_app == 'whatsapp' ? 'expanded' : ''; ?>" id="whatsappSubmenu">
                    <div class="submenu-item <?php echo ($active_app == 'whatsapp' && $whatsapp_submenu == 'incoming') ? 'active' : ''; ?>" onclick="selectWhatsAppType('incoming')">
                        <span class="icon-incoming"></span>
                        <span>Pesan Masuk</span>
                    </div>
                    <div class="submenu-item <?php echo ($active_app == 'whatsapp' && $whatsapp_submenu == 'outgoing') ? 'active' : ''; ?>" onclick="selectWhatsAppType('outgoing')">
                        <span class="icon-outgoing"></span>
                        <span>Pesan Keluar</span>
                    </div>
                </div>

                <div class="nav-item <?php echo $active_app == 'email' ? 'active' : ''; ?>" onclick="selectApp('email')">
                    <span class="icon-email"></span>
                    <span>Email</span>
                </div>
                <div class="nav-item <?php echo $active_app == 'telegram' ? 'active' : ''; ?>" onclick="selectApp('telegram')">
                    <span class="icon-telegram"></span>
                    <span>Telegram</span>
                </div>
                <div class="nav-item <?php echo $active_app == 'settings' ? 'active' : ''; ?>" onclick="selectApp('settings')">
                    <span class="icon-settings"></span>
                    <span>Pengaturan</span>
                </div>
                <div class="nav-item <?php echo $active_app == 'qrcode' ? 'active' : ''; ?>" onclick="selectApp('qrcode')">
                    <span class="icon-qrcode"></span>
                    <span>QR Whatsapp</span>
                </div>
                 <!-- <div class="nav-item <?php echo $active_app == 'debug_qrcode' ? 'active' : ''; ?>" onclick="selectApp('debug_qrcode')">
                    <span class="icon-qrcode"></span>
                    <span>QR WhatsApp</span>
                </div> -->
            </nav>
        </aside>

        <main class="main-content" id="mainContent">
            <?php
            switch ($active_app) {
                case 'whatsapp':
                    include __DIR__ . '/pages/whatsapp.php';
                    break;
                case 'email':
                    include __DIR__ . '/pages/email.php';
                    break;
                case 'telegram':
                    include __DIR__ . '/pages/telegram.php';
                    break;
                case 'dashboard':
                    include __DIR__ . '/pages/dashboard.php';
                    break;
                case 'settings':
                    include __DIR__ . '/pages/settings.php';
                    break;
                case 'qrcode':
                    include __DIR__ . '/pages/qrcode.php';
                    break;
                default:
                    include __DIR__ . '/pages/whatsapp.php';
                    break;
            }
            ?>
        </main>
    </div>

    <script>
        let sidebarCollapsed = false;
        let whatsappSubmenuExpanded = <?php echo $active_app == 'whatsapp' ? 'true' : 'false'; ?>;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            sidebarCollapsed = !sidebarCollapsed;

            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        }

        function toggleWhatsAppSubmenu() {
            const submenu = document.getElementById('whatsappSubmenu');
            whatsappSubmenuExpanded = !whatsappSubmenuExpanded;

            if (whatsappSubmenuExpanded) {
                submenu.classList.add('expanded');
                selectApp('whatsapp');
            } else {
                submenu.classList.remove('expanded');
            }
        }

        function selectApp(app) {
            if (app === 'whatsapp') {
                document.getElementById('whatsappSubmenu').classList.add('expanded');
                whatsappSubmenuExpanded = true;

                window.location.href = 'index.php?app=' + app + '&whatsapp_type=incoming';
            } else {
                window.location.href = 'index.php?app=' + app;
            }
        }

        function selectWhatsAppType(type) {
            window.location.href = 'index.php?app=whatsapp&whatsapp_type=' + type;
        }

        function retryMessage(messageId) {
            if (confirm('Apakah Anda yakin ingin mengirim ulang pesan ini?')) {
                const button = event.target;
                const originalText = button.textContent;

                button.textContent = 'Mengirim...';
                button.disabled = true;

                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                    alert('Pesan berhasil dikirim ulang!');

                    const row = button.closest('tr');
                    const statusCell = row.querySelector('td:nth-child(6)');

                    statusCell.innerHTML = '<span class="status-badge status-sent">Terkirim</span>';
                }, 2000);
            }
        }

        function replyMessage(targetJid, messageId, messageContent) {
            document.getElementById('target_jid').value = targetJid;
            document.getElementById('reply_to').value = messageId;
            document.getElementById('message_text').value = `Re: ${messageContent}`;

            document.querySelector('.message-container').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Auto-refresh data setiap 30 detik
        // setInterval(() => {
        //     console.log('Refreshing message data...');
        //     window.location.reload();
        // }, 30000);

        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('mainContent').classList.add('expanded');
                sidebarCollapsed = true;
            }
        });

        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.add('collapsed');
            document.getElementById('mainContent').classList.add('expanded');
            sidebarCollapsed = true;
        }
    </script>
</body>

</html>