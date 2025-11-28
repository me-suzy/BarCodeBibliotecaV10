<?php
// adauga_carte.php - Adaugă cărți noi în sistem
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Pre-completare cod dacă vine din scanare
$cod_prestabilit = isset($_GET['cod']) ? strtoupper(trim($_GET['cod'])) : '';

// Variabile pentru păstrarea datelor la eroare
$form_data = [
    'cod_bare' => $cod_prestabilit,
    'titlu' => '',
    'autor' => '',
    'isbn' => '',
    'cota' => '',
    'raft' => '',
    'nivel' => '',
    'pozitie' => '',
    'sectiune' => '',
    'observatii_locatie' => '',
    'statut' => '01' // Default: pentru împrumut acasă
];

$mesaj = '';
$tip_mesaj = '';
$cod_duplicat = false; // Flag pentru evidențiere câmp

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Salvează toate datele din formular
    $form_data = [
        'cod_bare' => strtoupper(trim($_POST['cod_bare'])),
        'titlu' => trim($_POST['titlu']),
        'autor' => trim($_POST['autor']),
        'isbn' => trim($_POST['isbn']),
        'cota' => trim($_POST['cota']),
        'raft' => trim($_POST['raft']),
        'nivel' => trim($_POST['nivel']),
        'pozitie' => trim($_POST['pozitie']),
        'sectiune' => trim($_POST['sectiune']),
        'observatii_locatie' => trim($_POST['observatii_locatie']),
        'statut' => trim($_POST['statut'] ?? '01')
    ];

    try {
        $stmt = $pdo->prepare("INSERT INTO carti (cod_bare, titlu, autor, isbn, cota, raft, nivel, pozitie, sectiune, observatii_locatie, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $form_data['cod_bare'],
            $form_data['titlu'],
            $form_data['autor'],
            $form_data['isbn'],
            $form_data['cota'],
            $form_data['raft'],
            $form_data['nivel'],
            $form_data['pozitie'],
            $form_data['sectiune'],
            $form_data['observatii_locatie'],
            $form_data['statut']
        ]);

        $mesaj = "✅ Cartea <strong>{$form_data['titlu']}</strong> a fost adăugată cu succes!";
        $tip_mesaj = "success";
        
        // Resetează formularul DOAR la succes
        $form_data = [
            'cod_bare' => '',
            'titlu' => '',
            'autor' => '',
            'isbn' => '',
            'cota' => '',
            'raft' => '',
            'nivel' => '',
            'pozitie' => '',
            'sectiune' => '',
            'observatii_locatie' => '',
            'statut' => '01'
        ];
        
    } catch (PDOException $e) {
        // Verifică dacă e eroare de cod duplicat
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $mesaj = "❌ Codul de bare <strong>{$form_data['cod_bare']}</strong> există deja în baza de date!";
            $tip_mesaj = "danger";
            $cod_duplicat = true; // Activează evidențierea
        } else {
            $mesaj = "❌ Eroare la salvare: " . $e->getMessage();
            $tip_mesaj = "danger";
        }
        // DATELE RĂMÂN PĂSTRATE în $form_data
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adaugă Carte - Sistem Bibliotecă</title>
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
            max-width: 800px;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group-full {
            grid-column: 1 / -1;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1em;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link, .home-link {
            display: inline-block;
            margin-top: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .home-link {
            background: #28a745;
            margin-right: 10px;
        }

        .home-link:hover {
            background: #218838;
        }

        .back-link {
            background: #667eea;
            color: white;
        }

        .back-link:hover {
            background: #764ba2;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .location-preview {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
        }

        .error-field {
            border-color: #dc3545 !important;
            background: #f8d7da !important;
        }

        .error-message {
            color: #dc3545;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .success-indicator {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .check-link {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }

        .check-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.1em;
        }

        .check-link a:hover {
            text-decoration: underline;
        }
		
.btn-aleph {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-aleph:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
}

.btn-aleph:disabled {
    background: #6c757d;
    cursor: not-allowed;
    opacity: 0.6;
}

.aleph-loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #17a2b8;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.field-autocomplete {
    border-color: #28a745 !important;
    background: #d4edda !important;
}

        .app-footer {
            text-align: right;
            padding: 30px 40px;
            margin-top: 40px;
            background: transparent;
        }

        .app-footer p {
            display: inline-block;
            margin: 0;
            padding: 13px 26px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(13px);
            border-radius: 22px;
            color: white;
            font-weight: 400;
            font-size: 0.9em;
            box-shadow: 0 0 18px rgba(196, 181, 253, 0.15),
                        0 4px 16px rgba(0, 0, 0, 0.1),
                        inset 0 1px 1px rgba(255, 255, 255, 0.2);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            transition: all 0.45s ease;
            position: relative;
        }

        .app-footer p::before {
            content: '💡';
            margin-right: 10px;
            font-size: 1.15em;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.6));
        }

        .app-footer p:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.08));
            box-shadow: 0 0 35px rgba(196, 181, 253, 0.3),
                        0 8px 24px rgba(0, 0, 0, 0.15),
                        inset 0 1px 1px rgba(255, 255, 255, 0.3);
            transform: translateY(-3px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.4);
        }

    </style>
</head>
<body>
    <div class="container">
        <h1>➕ Adaugă carte nouă</h1>

        <div class="info-box">
            <h3>💡 Informații utile</h3>
            <ul style="margin-left: 20px;">
                <li>Codul de bare trebuie să fie unic (ex: BOOK001, BOOK002)</li>
                <li>Locația ajută cititorii să găsească cartea rapid</li>
                <li>Cota este clasificarea bibliotecară standard</li>
            </ul>
        </div>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
            
            <?php if ($cod_duplicat): ?>
                <div class="check-link">
                    <a href="carti.php" target="_blank">🔍 Vezi lista completă de cărți pentru a verifica codurile existente</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" id="carteForm">
            <div class="form-grid">
                <!-- Informații de bază -->
                <div class="form-group">
                    <label>Cod de bare <span class="required">*</span></label>
                    <input type="text" 
                           name="cod_bare" 
                           placeholder="BOOK003" 
                           value="<?php echo htmlspecialchars($form_data['cod_bare']); ?>"
                           required
                           class="<?php echo $cod_duplicat ? 'error-field' : ''; ?>"
                           <?php echo (!empty($cod_prestabilit) && !$cod_duplicat) ? 'readonly style="background:#e9ecef;"' : ''; ?>>
                    
                    <?php if (!empty($cod_prestabilit) && !$cod_duplicat): ?>
                        <small class="success-indicator">
                            ✅ Cod scanat: <?php echo htmlspecialchars($cod_prestabilit); ?>
                        </small>
                    <?php endif; ?>
                    
                    <?php if ($cod_duplicat): ?>
                        <small class="error-message">
                            ⚠️ Acest cod există deja! Verifică lista de cărți sau folosește alt cod.
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Titlu <span class="required">*</span></label>
                    <input type="text" 
                           name="titlu" 
                           placeholder="Enigma Otiliei" 
                           value="<?php echo htmlspecialchars($form_data['titlu']); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Autor <span class="required">*</span></label>
                    <input type="text" 
                           name="autor" 
                           placeholder="George Călinescu" 
                           value="<?php echo htmlspecialchars($form_data['autor']); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" 
                           name="isbn" 
                           placeholder="9789734640560"
                           value="<?php echo htmlspecialchars($form_data['isbn']); ?>">
                </div>

<!-- Sistem de localizare -->
<div class="form-group">
    <label>Cotă bibliotecară <span style="color: #17a2b8;">🔍</span></label>
    <div style="display: flex; gap: 10px;">
        <input type="text" 
               name="cota" 
               id="inputCota"
               placeholder="IV-4659"
               value="<?php echo htmlspecialchars($form_data['cota']); ?>"
               style="flex: 1;">
        <button type="button" 
                id="btnCautaAleph" 
                class="btn-aleph"
                style="width: auto; padding: 12px 20px; margin-top: 0;">
            🔍 Caută în Aleph
        </button>
    </div>
    <small id="alephStatus" style="display: none; color: #666; margin-top: 5px;"></small>
</div>

                <div class="form-group">
                    <label>Raft</label> 
                    <select name="raft">
                        <option value="">Alege raft</option>
                        <?php for($i = 'A'; $i <= 'Z'; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $form_data['raft'] === $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nivel</label>
                    <select name="nivel">
                        <option value="">Alege nivel</option> 
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $form_data['nivel'] == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Poziție</label>
                    <input type="text" 
                           name="pozitie" 
                           placeholder="01" 
                           maxlength="2"
                           value="<?php echo htmlspecialchars($form_data['pozitie']); ?>">
                </div>

                <div class="form-group">
                    <label>Secțiune</label>
                    <select name="sectiune">
                        <option value="">Alege secțiune</option>
                        <option value="Literatură română" <?php echo $form_data['sectiune'] === 'Literatură română' ? 'selected' : ''; ?>>Literatură română</option>
                        <option value="Literatură universală" <?php echo $form_data['sectiune'] === 'Literatură universală' ? 'selected' : ''; ?>>Literatură universală</option>
                        <option value="Știință" <?php echo $form_data['sectiune'] === 'Știință' ? 'selected' : ''; ?>>Știință</option>
                        <option value="Istorie" <?php echo $form_data['sectiune'] === 'Istorie' ? 'selected' : ''; ?>>Istorie</option>
                        <option value="Filosofie" <?php echo $form_data['sectiune'] === 'Filosofie' ? 'selected' : ''; ?>>Filosofie</option>
                        <option value="Arte" <?php echo $form_data['sectiune'] === 'Arte' ? 'selected' : ''; ?>>Arte</option>
                        <option value="Drept" <?php echo $form_data['sectiune'] === 'Drept' ? 'selected' : ''; ?>>Drept</option>
                        <option value="Medicină" <?php echo $form_data['sectiune'] === 'Medicină' ? 'selected' : ''; ?>>Medicină</option>
                        <option value="Tehnică" <?php echo $form_data['sectiune'] === 'Tehnică' ? 'selected' : ''; ?>>Tehnică</option>
                        <option value="Alte" <?php echo $form_data['sectiune'] === 'Alte' ? 'selected' : ''; ?>>Alte</option>
                    </select>
                </div>

                <div class="form-group-full">
                    <label>Observații locație</label>
                    <textarea name="observatii_locatie" 
                              placeholder="Ex: Carte rară, păstrați cu grijă sau Indicatoare suplimentare pentru localizare"><?php echo htmlspecialchars($form_data['observatii_locatie']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Statut carte <span class="required">*</span></label>
                    <select name="statut" required>
                        <?php
                        require_once 'functions_statute_carti.php';
                        $stmt_statute = $pdo->query("SELECT cod_statut, nume_statut FROM statute_carti ORDER BY cod_statut");
                        $statute = $stmt_statute->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($statute as $statut): ?>
                            <option value="<?php echo htmlspecialchars($statut['cod_statut']); ?>" 
                                    <?php echo $form_data['statut'] === $statut['cod_statut'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($statut['cod_statut'] . ' - ' . $statut['nume_statut']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        ⚠️ Statutul determină dacă cartea poate fi împrumutată acasă sau doar la sală
                    </small>
                </div>
            </div>

            <button type="submit">Adaugă carte</button>
        </form>

        <a href="index.php" class="home-link">🏠 Acasă</a>
        <a href="index.php" class="back-link">← Înapoi la scanare</a>
    </div>
	



    <script>
	
// === INTEGRARE ALEPH ===
document.getElementById('btnCautaAleph').addEventListener('click', function() {
    const cota = document.getElementById('inputCota').value.trim();
    
    if (!cota) {
        alert('⚠️ Introduceți mai întâi cota bibliotecară!');
        document.getElementById('inputCota').focus();
        return;
    }
    
    cautaInAleph(cota);
});

// Enter pe câmpul cotă = căutare automată
document.getElementById('inputCota').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnCautaAleph').click();
    }
});

function cautaInAleph(cota) {
    const btn = document.getElementById('btnCautaAleph');
    const status = document.getElementById('alephStatus');
    
    // Dezactivează buton și afișează loading
    btn.disabled = true;
    btn.innerHTML = '<span class="aleph-loading"></span> Caută...';
    status.style.display = 'block';
    status.style.color = '#17a2b8';
    status.textContent = '🔍 Interogare Aleph...';
    
    // AJAX request către API
    fetch(`aleph_api.php?cota=${encodeURIComponent(cota)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // SUCCES - Completează formularul
                status.style.color = '#28a745';
                status.textContent = '✅ Date preluate din Aleph!';
                
                // Completează câmpurile
                if (data.data.barcode) {
                    document.querySelector('input[name="cod_bare"]').value = data.data.barcode;
                    document.querySelector('input[name="cod_bare"]').classList.add('field-autocomplete');
                    // Elimină readonly dacă există
                    document.querySelector('input[name="cod_bare"]').removeAttribute('readonly');
                    document.querySelector('input[name="cod_bare"]').style.background = '#d4edda';
                }
                
                if (data.data.titlu) {
                    document.querySelector('input[name="titlu"]').value = data.data.titlu;
                    document.querySelector('input[name="titlu"]').classList.add('field-autocomplete');
                }
                
                if (data.data.autor) {
                    document.querySelector('input[name="autor"]').value = data.data.autor;
                    document.querySelector('input[name="autor"]').classList.add('field-autocomplete');
                }
                
                if (data.data.isbn) {
                    document.querySelector('input[name="isbn"]').value = data.data.isbn;
                    document.querySelector('input[name="isbn"]').classList.add('field-autocomplete');
                }
                
                // ✅ NOU - Setează automat statutul din Aleph
                if (data.data.status) {
                    // Convertește statusul Aleph în cod statut
                    const statusAleph = data.data.status.toLowerCase();
                    let codStatut = '01'; // Default
                    
                    if (statusAleph.includes('pentru împrumut') || statusAleph.includes('pe raft')) {
                        codStatut = '01';
                    } else if (statusAleph.includes('se împr. numai la sală') || statusAleph.includes('numai la sală')) {
                        codStatut = '02';
                    } else if (statusAleph.includes('doar pentru sl') || statusAleph.includes('colectii speciale')) {
                        codStatut = '03';
                    } else if (statusAleph.includes('nu există') || statusAleph.includes('casat')) {
                        codStatut = '04';
                    } else if (statusAleph.includes('împrumut scurt')) {
                        codStatut = '05';
                    } else if (statusAleph.includes('regim special') || statusAleph.includes('6 luni')) {
                        codStatut = '06';
                    } else if (statusAleph.includes('ne circulat') || statusAleph.includes('nu se imprumuta')) {
                        codStatut = '08';
                    } else if (statusAleph.includes('în achiziție') || statusAleph.includes('depozit')) {
                        codStatut = '90';
                    }
                    
                    const selectStatut = document.querySelector('select[name="statut"]');
                    if (selectStatut) {
                        selectStatut.value = codStatut;
                        selectStatut.classList.add('field-autocomplete');
                    }
                }
                
                if (data.data.sectiune) {
                    const selectSectiune = document.querySelector('select[name="sectiune"]');
                    selectSectiune.value = data.data.sectiune;
                    selectSectiune.classList.add('field-autocomplete');
                }
                
                // Focus pe câmpul următor (Raft)
                setTimeout(() => {
                    document.querySelector('select[name="raft"]').focus();
                }, 500);
                
            } else {
                // EROARE
                status.style.color = '#dc3545';
                status.textContent = '❌ ' + data.mesaj;
            }
        })
        .catch(error => {
            status.style.color = '#dc3545';
            status.textContent = '❌ Eroare conexiune: ' + error.message;
        })
        .finally(() => {
            // Reactivează buton
            btn.disabled = false;
            btn.innerHTML = '🔍 Caută în Aleph';
        });
}

// Curăță highlight-ul când utilizatorul modifică manual
['cod_bare', 'titlu', 'autor', 'isbn'].forEach(field => {
    document.querySelector(`input[name="${field}"]`).addEventListener('input', function() {
        this.classList.remove('field-autocomplete');
        if (field === 'cod_bare') {
            this.style.background = '';
        }
    });
});

document.querySelector('select[name="sectiune"]').addEventListener('change', function() {
    this.classList.remove('field-autocomplete');
});
	
        // Actualizare previzualizare locație în timp real
        function updateLocationPreview() {
            const raft = document.querySelector('select[name="raft"]').value;
            const nivel = document.querySelector('select[name="nivel"]').value;
            const pozitie = document.querySelector('input[name="pozitie"]').value;

            if (raft && nivel && pozitie) {
                const locatie = `Raft ${raft} - Nivel ${nivel} - Poziția ${pozitie}`;
                let preview = document.querySelector('.location-preview');
                if (!preview) {
                    const container = document.querySelector('.form-group-full');
                    preview = document.createElement('div');
                    preview.className = 'location-preview';
                    container.insertBefore(preview, container.firstChild);
                }
                preview.textContent = `📍 Locație: ${locatie}`;
            }
        }

        // Adaugă event listeners pentru actualizare în timp real
        document.querySelector('select[name="raft"]').addEventListener('change', updateLocationPreview);
        document.querySelector('select[name="nivel"]').addEventListener('change', updateLocationPreview);
        document.querySelector('input[name="pozitie"]').addEventListener('input', updateLocationPreview);

        // Actualizare la încărcare dacă sunt valori
        window.addEventListener('load', updateLocationPreview);
    </script>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>