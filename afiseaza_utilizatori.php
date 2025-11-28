<?php
/**
 * Script pentru afișarea utilizatorilor din baza de date
 * 
 * IMPORTANT: Parolele sunt hash-uite cu BCRYPT și NU pot fi afișate în plain text!
 */

session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Obține toți utilizatorii
$stmt = $pdo->query("
    SELECT 
        id, 
        username, 
        password_hash, 
        nume, 
        email, 
        activ, 
        data_creare, 
        ultima_autentificare 
    FROM utilizatori 
    ORDER BY id
");
$utilizatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilizatori - Biblioteca</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .warning-box p {
            margin: 5px 0;
            color: #856404;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .hash {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #666;
            word-break: break-all;
            max-width: 400px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #0c5460;
        }
        
        .count {
            background: #667eea;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Utilizatori din Baza de Date <span class="count"><?php echo count($utilizatori); ?> utilizatori</span></h1>
        
        <div class="warning-box">
            <p><strong>⚠️ ATENȚIE:</strong></p>
            <p>• Parolele sunt hash-uite cu BCRYPT (one-way encryption)</p>
            <p>• Hash-urile NU pot fi "decriptate" înapoi la parola originală</p>
            <p>• Pentru a modifica o parolă, folosește <a href="modifica_utilizator.php">Gestionare Utilizatori</a></p>
        </div>
        
        <div class="info-box">
            <p><strong>💡 Informații:</strong></p>
            <p>• Hash-ul BCRYPT are formatul: <code>$2y$10$...</code></p>
            <p>• Fiecare hash este unic, chiar dacă parola este aceeași</p>
            <p>• Verificarea parolei se face cu <code>password_verify()</code>, nu prin comparație directă</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Password Hash (BCRYPT)</th>
                    <th>Nume</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Data Creare</th>
                    <th>Ultima Autentificare</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($utilizatori)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                            Nu există utilizatori în baza de date.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($utilizatori as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['id']); ?></strong></td>
                            <td><strong style="color: #667eea;"><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td>
                                <div class="hash" title="Hash BCRYPT - Nu poate fi decriptat">
                                    <?php echo htmlspecialchars($user['password_hash']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['nume'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $user['activ'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $user['activ'] ? '✅ ACTIV' : '❌ DEZACTIVAT'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['data_creare'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['ultima_autentificare'] ?? 'Niciodată'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px;">
            <a href="modifica_utilizator.php" class="btn btn-primary">✏️ Gestionare Utilizatori</a>
            <a href="index.php" class="btn btn-primary" style="background: #6c757d;">← Înapoi la Index</a>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3 style="color: #667eea; margin-bottom: 10px;">📊 Detalii Tehnice:</h3>
            <p style="color: #666; line-height: 1.8;">
                <strong>Structura Hash-ului BCRYPT:</strong><br>
                Format: <code>$2y$10$[salt][hash]</code><br>
                • <code>$2y$</code> = Versiunea algoritmului BCRYPT<br>
                • <code>10</code> = Cost factor (2^10 = 1024 iterații)<br>
                • <code>[salt]</code> = Salt aleator (22 caractere)<br>
                • <code>[hash]</code> = Hash-ul propriu-zis (31 caractere)<br>
                <br>
                <strong>Exemplu:</strong> <code>$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi</code><br>
                <br>
                <strong>De ce nu poți vedea parola?</strong><br>
                BCRYPT este un algoritm "one-way" - poți genera hash din parolă, dar nu poți genera parola din hash.
                Singura metodă de "resetare" este generarea unui hash nou pentru o parolă nouă.
            </p>
        </div>
    </div>
</body>
</html>

