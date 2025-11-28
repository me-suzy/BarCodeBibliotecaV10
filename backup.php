<?php
/**
 * Pagină Web pentru Backup Manual
 */
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$backup_log_file = __DIR__ . DIRECTORY_SEPARATOR . 'BackUp' . DIRECTORY_SEPARATOR . 'backup_log.txt';
$last_backup_time = null;
if (file_exists($backup_log_file)) {
    $last_backup_time = filemtime($backup_log_file);
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Sistem - Biblioteca</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        h1 {
            color: #667eea;
            margin-bottom: 30px;
            font-size: 2.2em;
            text-align: center;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .backup-section {
            text-align: center;
            margin: 30px 0;
        }

        .btn-backup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px 50px;
            font-size: 1.3em;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-backup:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-backup:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loader {
            display: none;
            margin: 30px auto;
            text-align: center;
        }

        .loader.active {
            display: block;
        }

        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-text {
            color: #667eea;
            font-size: 1.1em;
            font-weight: 600;
            margin-top: 15px;
        }

        .backup-status {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }

        .backup-status.active {
            display: block;
        }

        .backup-status.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }

        .backup-status.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }

        .backup-status h3 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        .backup-files {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .backup-files h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .backup-files ul {
            list-style: none;
            margin-left: 0;
        }

        .backup-files li {
            padding: 8px;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-size {
            color: #666;
            font-size: 0.9em;
        }

        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.85em;
            max-height: 400px;
            overflow-y: auto;
        }

        .debug-info pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .last-backup {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .last-backup p {
            margin: 5px 0;
            color: #856404;
        }

        .home-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .home-link:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .app-footer {
            text-align: right;
            padding: 30px 40px;
            margin-top: 40px;
        }

        .app-footer p {
            display: inline-block;
            padding: 13px 26px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 22px;
            color: white;
            font-weight: 400;
            font-size: 0.9em;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Backup Sistem</h1>

        <div class="info-box">
            <h3>Informatii Backup</h3>
            <ul>
                <li><strong>Backup automat:</strong> Zilnic la 05:30</li>
                <li><strong>Backup manual:</strong> Foloseste butonul de mai jos</li>
                <li><strong>Continut:</strong> Baza de date MySQL + Toate fisierele (ZIP)</li>
                <li><strong>Locatie:</strong> Folderul <code>BackUp/</code></li>
                <li><strong>Retentie:</strong> 30 zile</li>
            </ul>
        </div>

        <?php if ($last_backup_time): ?>
        <div class="last-backup">
            <p><strong>Ultimul backup:</strong> <?php echo date('d.m.Y H:i:s', $last_backup_time); ?></p>
            <p><small>Acum: <?php echo date('d.m.Y H:i:s'); ?></small></p>
        </div>
        <?php endif; ?>

        <div class="backup-section">
            <button id="btnBackup" class="btn-backup" onclick="startBackup()">
                Porneste Backup Complet
            </button>

            <div id="loader" class="loader">
                <div class="spinner"></div>
                <div class="progress-text" id="progressText">Pregatire backup...</div>
            </div>

            <div id="backupStatus" class="backup-status">
                <h3 id="statusTitle"></h3>
                <p id="statusMessage"></p>
                <div id="backupFiles" class="backup-files" style="display: none;">
                    <h4>Fisiere create:</h4>
                    <ul id="filesList"></ul>
                </div>
                <div id="debugInfo" class="debug-info" style="display: none;">
                    <h4>Informatii Debug:</h4>
                    <pre id="debugContent"></pre>
                </div>
            </div>
        </div>

        <a href="index.php" class="home-link">Inapoi la Pagina Principala</a>
    </div>

    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>

    <script>
        let backupInProgress = false;

        function startBackup() {
            if (backupInProgress) {
                return;
            }

            backupInProgress = true;
            const btn = document.getElementById('btnBackup');
            const loader = document.getElementById('loader');
            const status = document.getElementById('backupStatus');
            const progressText = document.getElementById('progressText');

            // Reset UI
            btn.disabled = true;
            btn.textContent = 'Backup in progres...';
            loader.classList.add('active');
            status.classList.remove('active', 'success', 'error');
            status.style.display = 'none';

            // Actualizeaza progresul
            const steps = [
                'Pregatire backup...',
                'Verificare sistem...',
                'Backup baza de date...',
                'Creare arhiva ZIP...',
                'Finalizare...'
            ];
            let stepIndex = 0;

            const progressInterval = setInterval(() => {
                if (stepIndex < steps.length - 1) {
                    stepIndex++;
                    progressText.textContent = steps[stepIndex];
                }
            }, 2000);

            console.log('Trimitere cerere backup...');

            // Trimite cererea AJAX
            fetch('backup_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'backup' })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                clearInterval(progressInterval);
                loader.classList.remove('active');
                btn.disabled = false;
                btn.textContent = 'Porneste Backup Complet';
                backupInProgress = false;

                status.classList.add('active');
                status.style.display = 'block';

                if (data.success) {
                    status.classList.add('success');
                    document.getElementById('statusTitle').textContent = 'Backup realizat cu succes!';
                    document.getElementById('statusMessage').textContent = data.message || 'Backup-ul a fost creat cu succes.';

                    // Afiseaza fisierele create
                    if (data.files && data.files.length > 0) {
                        const filesList = document.getElementById('filesList');
                        const backupFiles = document.getElementById('backupFiles');
                        filesList.innerHTML = '';
                        
                        data.files.forEach(file => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <span>${file.name}</span>
                                <span class="file-size">${file.size}</span>
                            `;
                            filesList.appendChild(li);
                        });
                        
                        backupFiles.style.display = 'block';
                    }
                } else {
                    status.classList.add('error');
                    document.getElementById('statusTitle').textContent = 'Eroare la backup';
                    document.getElementById('statusMessage').textContent = data.message || 'A aparut o eroare la crearea backup-ului.';
                }

                // Afiseaza info debug
                if (data.debug) {
                    const debugInfo = document.getElementById('debugInfo');
                    const debugContent = document.getElementById('debugContent');
                    debugContent.textContent = JSON.stringify(data.debug, null, 2);
                    debugInfo.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                
                clearInterval(progressInterval);
                loader.classList.remove('active');
                btn.disabled = false;
                btn.textContent = 'Porneste Backup Complet';
                backupInProgress = false;

                status.classList.add('active', 'error');
                status.style.display = 'block';
                document.getElementById('statusTitle').textContent = 'Eroare de comunicare';
                document.getElementById('statusMessage').textContent = 'Nu s-a putut comunica cu serverul: ' + error.message;
            });
        }
    </script>
</body>
</html>
