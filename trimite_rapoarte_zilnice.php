<?php
/**
 * trimite_rapoarte_zilnice.php
 * Script pentru trimiterea rapoartelor zilnice de împrumuturi la ora 18:00
 * Rulează automat prin cron job sau manual pentru testare
 */

header('Content-Type: text/html; charset=UTF-8');

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

require_once 'config.php';
require_once 'send_email.php';
require_once 'functions_email_templates.php';

// Configurare email
$config_email = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'YOUR-EMAIL@gmail.com',
    'smtp_pass' => 'GOOGLE SECRET PASSWORD',
    'from_email' => 'YOUR-EMAIL@gmail.com',
    'from_name' => 'Biblioteca Academiei Române - Iași'
];

// Data de azi
$azi = date('Y-m-d');
$ora = date('H:i');

// Dacă se rulează din browser, afișează HTML
$is_browser = (php_sapi_name() !== 'cli');

if ($is_browser) {
    echo "<!DOCTYPE html>
    <html lang='ro'>
    <head>
        <meta charset='UTF-8'>
        <title>Rapoarte Zilnice - Biblioteca</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                   padding: 20px; margin: 0; }
            .container { max-width: 900px; margin: 0 auto; background: white; 
                        border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
            h1 { color: #667eea; margin-top: 0; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 8px; 
                   margin: 15px 0; border-left: 4px solid #2196F3; }
            .success { background: #d4edda; color: #155724; padding: 15px; 
                      border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; 
                    border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; 
                      border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107; }
            .cititor-box { background: #f8f9fa; padding: 15px; margin: 10px 0; 
                          border-radius: 8px; border: 1px solid #dee2e6; }
            .btn { display: inline-block; padding: 12px 30px; background: #667eea; 
                  color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>📧 Rapoarte Zilnice de Împrumuturi</h1>";
}

try {
    // Setează encoding-ul conexiunii MySQL la UTF-8
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    
    if ($is_browser) {
        echo "<div class='info'><strong>📅 Data:</strong> $azi<br><strong>⏰ Ora:</strong> $ora</div>";
    } else {
        echo "📅 Data: $azi\n⏰ Ora: $ora\n\n";
    }
    
    // Găsește toți cititorii care au avut activitate azi
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            c.id,
            c.cod_bare,
            c.nume,
            c.prenume,
            c.email
        FROM cititori c
        INNER JOIN imprumuturi i ON c.cod_bare = i.cod_cititor
        WHERE DATE(i.data_imprumut) = ?
        AND c.email IS NOT NULL
        AND c.email != ''
        ORDER BY c.nume, c.prenume
    ");
    $stmt->execute([$azi]);
    $cititori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_cititori = count($cititori);
    
    if ($is_browser) {
        echo "<div class='info'><strong>👥 Găsiți:</strong> $total_cititori cititori cu activitate astăzi.</div>";
    } else {
        echo "👥 Găsiți: $total_cititori cititori cu activitate astăzi.\n\n";
    }
    
    if ($total_cititori === 0) {
        if ($is_browser) {
            echo "<div class='warning'><strong>ℹ️ Nu există activitate astăzi.</strong><br>Nu se vor trimite email-uri.</div>";
        } else {
            echo "ℹ️ Nu există activitate astăzi. Nu se vor trimite email-uri.\n";
        }
    } else {
        $emailuri_trimise = 0;
        $emailuri_erori = 0;
        
        foreach ($cititori as $cititor) {
            $nume_complet = trim($cititor['nume'] . ' ' . $cititor['prenume']);
            
            if ($is_browser) {
                echo "<div class='cititor-box'>";
                echo "<h3>👤 {$nume_complet} ({$cititor['email']})</h3>";
            } else {
                echo "👤 Procesare: {$nume_complet} ({$cititor['email']})\n";
            }
            
            // CĂRȚI ÎMPRUMUTATE AZI (și încă active)
            $stmt_imp = $pdo->prepare("
                SELECT 
                    car.titlu,
                    car.autor,
                    car.cod_bare,
                    car.cota,
                    car.locatie_completa,
                    i.data_imprumut,
                    i.data_scadenta,
                    i.status,
                    i.data_returnare
                FROM imprumuturi i
                INNER JOIN carti car ON i.cod_carte = car.cod_bare
                WHERE i.cod_cititor = ?
                AND DATE(i.data_imprumut) = ?
                ORDER BY i.data_imprumut DESC
            ");
            $stmt_imp->execute([$cititor['cod_bare'], $azi]);
            $activitate = $stmt_imp->fetchAll(PDO::FETCH_ASSOC);
            
            // Separă împrumuturile active de cele returnate în aceeași zi
            $imprumutate_active = [];
            $imprumutate_si_returnate = [];
            
            foreach ($activitate as $carte) {
                if ($carte['status'] === 'returnat' && $carte['data_returnare'] && 
                    date('Y-m-d', strtotime($carte['data_returnare'])) === $azi) {
                    // Împrumutată și returnată în aceeași zi
                    $imprumutate_si_returnate[] = [
                        'titlu' => $carte['titlu'],
                        'autor' => $carte['autor'] ?? '',
                        'cod_bare' => $carte['cod_bare'],
                        'cota' => $carte['cota'] ?? '',
                        'data_imprumut' => $carte['data_imprumut'],
                        'data_returnare' => $carte['data_returnare'],
                        'locatie_completa' => $carte['locatie_completa'] ?? ''
                    ];
                } elseif ($carte['status'] === 'activ' || $carte['status'] === 'anulat') {
                    // Împrumutată și încă activă (sau anulată)
                    $imprumutate_active[] = [
                        'titlu' => $carte['titlu'],
                        'autor' => $carte['autor'] ?? '',
                        'cod_bare' => $carte['cod_bare'],
                        'cota' => $carte['cota'] ?? '',
                        'data_imprumut' => $carte['data_imprumut'],
                        'data_scadenta' => $carte['data_scadenta'],
                        'locatie_completa' => $carte['locatie_completa'] ?? ''
                    ];
                }
            }
            
            $total_imprumutate = count($imprumutate_active);
            $total_returnate_azi = count($imprumutate_si_returnate);
            
            if ($is_browser) {
                echo "<p><strong>📚 Împrumuturi active:</strong> $total_imprumutate</p>";
                echo "<p><strong>🔄 Împrumutate și returnate azi:</strong> $total_returnate_azi</p>";
            } else {
                echo "  📚 Împrumuturi active: $total_imprumutate\n";
                echo "  🔄 Împrumutate și returnate azi: $total_returnate_azi\n";
            }
            
            // Trimite email doar dacă există activitate
            if ($total_imprumutate > 0 || $total_returnate_azi > 0) {
                try {
                    $email_content = genereazaEmailRaportZilnic(
                        $cititor,
                        $imprumutate_active,
                        $imprumutate_si_returnate,
                        $azi
                    );
                    
                    $subiect = mb_encode_mimeheader("📚 Raport împrumuturi - " . date('d.m.Y', strtotime($azi)), 'UTF-8', 'B', "\r\n", 0);
                    
                    $rezultat = trimiteEmailSMTP(
                        $cititor['email'],
                        $subiect,
                        $email_content,
                        $config_email
                    );
                    
                    if ($rezultat['success']) {
                        $emailuri_trimise++;
                        if ($is_browser) {
                            echo "<div class='success'>✅ Email trimis cu succes!</div>";
                        } else {
                            echo "  ✅ Email trimis cu succes!\n";
                        }
                    } else {
                        $emailuri_erori++;
                        if ($is_browser) {
                            echo "<div class='error'>❌ Eroare trimitere email: " . htmlspecialchars($rezultat['message'], ENT_QUOTES, 'UTF-8') . "</div>";
                        } else {
                            echo "  ❌ Eroare trimitere email: " . $rezultat['message'] . "\n";
                        }
                    }
                } catch (Exception $e) {
                    $emailuri_erori++;
                    if ($is_browser) {
                        echo "<div class='error'>❌ Eroare: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
                    } else {
                        echo "  ❌ Eroare: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                if ($is_browser) {
                    echo "<div class='warning'>⏭️  Fără activitate relevantă, email omis.</div>";
                } else {
                    echo "  ⏭️  Fără activitate relevantă, email omis.\n";
                }
            }
            
            if ($is_browser) {
                echo "</div>";
            } else {
                echo "\n";
            }
        }
        
        // Rezumat final
        if ($is_browser) {
            echo "<div class='success' style='margin-top: 20px;'><strong>✅ REZUMAT:</strong><br>";
            echo "📧 Email-uri trimise cu succes: <strong>$emailuri_trimise</strong><br>";
            if ($emailuri_erori > 0) {
                echo "❌ Erori: <strong>$emailuri_erori</strong>";
            }
            echo "</div>";
        } else {
            echo "\n✅ REZUMAT:\n";
            echo "📧 Email-uri trimise cu succes: $emailuri_trimise\n";
            if ($emailuri_erori > 0) {
                echo "❌ Erori: $emailuri_erori\n";
            }
        }
    }
    
    if ($is_browser) {
        echo "<a href='index.php' class='btn'>← Înapoi la Pagina Principală</a>";
        echo "<a href='trimite_rapoarte_zilnice.php' class='btn' style='margin-left: 10px;'>🔄 Reîncarcă</a>";
    }
    
} catch (PDOException $e) {
    $error_msg = "Eroare bază de date: " . $e->getMessage();
    if ($is_browser) {
        echo "<div class='error'><strong>❌ EROARE:</strong><br>" . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . "</div>";
    } else {
        echo "❌ EROARE: $error_msg\n";
    }
} catch (Exception $e) {
    $error_msg = "Eroare generală: " . $e->getMessage();
    if ($is_browser) {
        echo "<div class='error'><strong>❌ EROARE:</strong><br>" . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . "</div>";
    } else {
        echo "❌ EROARE: $error_msg\n";
    }
}

if ($is_browser) {
    echo "    </div>
    </body>
    </html>";
} else {
    echo "\n✅ Rapoarte zilnice procesate!\n";
}

/**
 * Generează email personalizat pentru raport zilnic
 * 
 * @param array $cititor Date cititor (nume, prenume, email)
 * @param array $imprumutate_active Cărți împrumutate și încă active
 * @param array $imprumutate_returnate Cărți împrumutate și returnate în aceeași zi
 * @param string $data Data (Y-m-d)
 * @return string HTML formatat
 */
function genereazaEmailRaportZilnic($cititor, $imprumutate_active, $imprumutate_returnate, $data) {
    $nume_complet = trim($cititor['nume'] . ' ' . $cititor['prenume']);
    $data_frumoasa = date('d.m.Y', strtotime($data));
    
    $html = '<!DOCTYPE html>
    <html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; 
                   line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
            .email-container { max-width: 600px; margin: 20px auto; background: white; 
                              border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                     color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px 20px; }
            .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }
            .section-title { color: #667eea; font-size: 18px; margin-top: 30px; 
                           margin-bottom: 15px; font-weight: bold; }
            .book-details { background: #f8f9fa; border-left: 4px solid #667eea; 
                          padding: 15px; margin: 20px 0; border-radius: 5px; }
            .book-item { margin: 15px 0; padding: 15px; background: white; 
                        border-radius: 5px; border-left: 4px solid #667eea; }
            .book-item.returned { border-left-color: #28a745; background: #f0f9ff; }
            .book-title { font-weight: bold; color: #667eea; font-size: 16px; margin-bottom: 8px; }
            .book-title.returned { color: #28a745; }
            .book-info { color: #666; font-size: 14px; margin-top: 5px; }
            .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; 
                       padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-box strong { color: #1976D2; }
            .important { background: #fff3cd; padding: 15px; border-radius: 5px; 
                        border-left: 4px solid #ffc107; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; 
                     color: #666; font-size: 12px; border-top: 1px solid #ddd; }
            .thank-you { background: #d4edda; padding: 15px; border-radius: 5px; 
                        border-left: 4px solid #28a745; margin: 20px 0; text-align: center; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>📚 Biblioteca Academiei Române - Iași</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Raport zilnic - ' . htmlspecialchars($data_frumoasa, ENT_QUOTES, 'UTF-8') . '</p>
            </div>
            
            <div class="content">
                <div class="greeting">
                    Bună ziua <strong>' . htmlspecialchars($nume_complet, ENT_QUOTES, 'UTF-8') . '</strong>,
                </div>
                
                <p>Vă mulțumim că sunteți cititor fidel al bibliotecii noastre și vă prezentăm un rezumat al activității dumneavoastră de astăzi:</p>';
    
    // CĂRȚI ÎMPRUMUTATE ACTIVE
    if (count($imprumutate_active) > 0) {
        $html .= '<div class="section-title">📖 Cărți împrumutate astăzi (' . count($imprumutate_active) . ')</div>';
        
        foreach ($imprumutate_active as $carte) {
            $scadenta = date('d.m.Y', strtotime($carte['data_scadenta']));
            $data_imprumut = date('d.m.Y H:i', strtotime($carte['data_imprumut']));
            
            $html .= '<div class="book-item">
                <div class="book-title">📚 ' . htmlspecialchars($carte['titlu'], ENT_QUOTES, 'UTF-8') . '</div>';
            
            if (!empty($carte['autor'])) {
                $html .= '<div class="book-info">👤 Autor: ' . htmlspecialchars($carte['autor'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            
            if (!empty($carte['cota'])) {
                $html .= '<div class="book-info">📍 Cota: ' . htmlspecialchars($carte['cota'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            
            if (!empty($carte['locatie_completa'])) {
                $html .= '<div class="book-info">📍 Locație: ' . htmlspecialchars($carte['locatie_completa'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            
            $html .= '<div class="book-info">📅 <strong>Împrumutată:</strong> ' . htmlspecialchars($data_imprumut, ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '<div class="book-info">⏰ <strong>Termen returnare:</strong> ' . htmlspecialchars($scadenta, ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '</div>';
        }
        
        $html .= '<div class="important">
            ⏰ <strong>Important:</strong> Aveți 14 zile pentru a returna cărțile. 
            Vă rugăm să respectați termenul de returnare pentru a evita penalizările și pentru a permite și altor cititori să beneficieze de aceste cărți.
        </div>';
    }
    
    // CĂRȚI ÎMPRUMUTATE ȘI RETURNATE ÎN ACEEAȘI ZI
    if (count($imprumutate_returnate) > 0) {
        $html .= '<div class="section-title">✅ Cărți împrumutate și returnate astăzi (' . count($imprumutate_returnate) . ')</div>';
        $html .= '<p>Mulțumim pentru promptitudinea cu care ați returnat următoarele cărți:</p>';
        
        foreach ($imprumutate_returnate as $carte) {
            $data_imprumut = date('d.m.Y H:i', strtotime($carte['data_imprumut']));
            $data_returnare = date('d.m.Y H:i', strtotime($carte['data_returnare']));
            
            $html .= '<div class="book-item returned">
                <div class="book-title returned">📚 ' . htmlspecialchars($carte['titlu'], ENT_QUOTES, 'UTF-8') . '</div>';
            
            if (!empty($carte['autor'])) {
                $html .= '<div class="book-info">👤 Autor: ' . htmlspecialchars($carte['autor'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            
            $html .= '<div class="book-info">📅 <strong>Împrumutată:</strong> ' . htmlspecialchars($data_imprumut, ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '<div class="book-info">✅ <strong>Returnată:</strong> ' . htmlspecialchars($data_returnare, ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '</div>';
        }
        
        $html .= '<div class="thank-you">
            🙏 <strong>Vă mulțumim pentru corectitudinea dumneavoastră!</strong>
        </div>';
    }
    
    $html .= '<div class="info-box">
        <p><strong>📍 Locație bibliotecă:</strong> Biblioteca Academiei Române - Iași</p>
        <p><strong>⏰ Program:</strong> Luni - Vineri: 09:00 - 17:00</p>
        <p><strong>📞 Contact:</strong> Pentru orice întrebări sau nelămuriri, ne puteți contacta.</p>
    </div>
    
    <p style="margin-top: 30px;"><strong>Lectură plăcută!</strong><br>
    Cu respect,<br>
    <strong>Echipa Bibliotecii</strong><br>
    Biblioteca Academiei Române - Iași</p>
            </div>
            
            <div class="footer">
                <p>Acest email a fost generat automat de sistemul de notificări al bibliotecii.</p>
                <p>Pentru întrebări: bib.acadiasi@gmail.com</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>

