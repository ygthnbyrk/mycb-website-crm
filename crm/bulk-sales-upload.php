<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toplu Satış Yükle - CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-y: auto;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        .nav-menu { display: flex; flex-direction: column; gap: 10px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }
        .nav-item:hover { background: #f0f0f0; }
        .nav-item.active { background: #667eea; color: white; }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 40px;
        }
        h1 { color: #667eea; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 10px;
            padding: 60px 40px;
            text-align: center;
            background: #f8f9ff;
            margin: 30px 0;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            background: #f0f3ff;
            border-color: #5568d3;
        }
        .upload-area.dragover {
            background: #e3f2fd;
            border-color: #2196f3;
            transform: scale(1.02);
        }
        .upload-area.success {
            background: #d4edda;
            border-color: #28a745;
        }
        .upload-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .upload-area h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .upload-area p {
            color: #666;
            margin-bottom: 20px;
        }
        input[type="file"] {
            display: none;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover:not(:disabled) { background: #5568d3; }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover:not(:disabled) { background: #218838; }
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        .info-box h4 { color: #1976d2; margin-bottom: 10px; }
        .info-box ul { margin-left: 20px; }
        .info-box li { margin: 8px 0; color: #555; }
        .file-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: none;
            border-left: 4px solid #28a745;
        }
        .file-info.active {
            display: block;
        }
        .file-info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 20px;
            margin-top: 10px;
        }
        .file-info-label {
            font-weight: 600;
            color: #555;
        }
        .file-info-value {
            color: #333;
        }
        .progress-bar {
            width: 100%;
            height: 40px;
            background: #e0e0e0;
            border-radius: 20px;
            overflow: hidden;
            margin: 20px 0;
            display: none;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress-bar.active {
            display: block;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <div class="logo-icon">📊</div>
            <span class="logo-text">CRM Panel</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span>🏠</span> Ana Sayfa
            </a>
            <a href="customers.php" class="nav-item">
                <span>👥</span> Müşteriler
            </a>
            <a href="products.php" class="nav-item">
                <span>📦</span> Ürünler
            </a>
            <a href="simcards.php" class="nav-item">
                <span>📱</span> Sim Kartlar
            </a>
            <a href="create-sale.php" class="nav-item">
                <span>💰</span> Yeni Satış
            </a>
            <a href="sales-list.php" class="nav-item">
                <span>📋</span> Satış Listesi
            </a>
            <a href="bulk-sales-upload.php" class="nav-item active">
                <span>📤</span> Toplu Satış Yükle
            </a>
            <a href="subscriptions.php" class="nav-item">
                <span>🔄</span> Abonelikler
            </a>
        </nav>
    </div>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="container">
            <h1>📤 Toplu Satış Yükle</h1>
            <p class="subtitle">Excel dosyası ile tek seferde birden fazla satış kaydı oluşturun</p>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h4>📋 Yükleme Adımları</h4>
                <ul>
                    <li><strong>1.</strong> Aşağıdaki butona tıklayarak Excel şablonunu indirin</li>
                    <li><strong>2.</strong> Excel ile açın ve satış verilerinizi doldurun</li>
                    <li><strong>3.</strong> Dosyayı <strong>Excel formatında</strong> kaydedin (.xls veya .xlsx)</li>
                    <li><strong>4.</strong> Aşağıdaki alana sürükleyin veya tıklayarak seçin</li>
                    <li><strong>5.</strong> Önizleme sayfasında kontrol edin</li>
                    <li><strong>6.</strong> Onaylayarak kaydedin</li>
                    <li style="color: #28a745; font-weight: 600;">✅ CSV'ye çevirmenize gerek yok!</li>
                </ul>
            </div>

            <form id="uploadForm" method="POST" action="bulk-sales-preview.php">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('excelFile').click()">
                    <div class="upload-icon">📁</div>
                    <h3>Excel Dosyanızı Buraya Sürükleyin</h3>
                    <p>veya dosya seçmek için tıklayın</p>
                    <input type="file" name="excel_file" id="excelFile" accept=".xls,.xlsx" required>
                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation(); document.getElementById('excelFile').click()">
                        📂 Excel Dosyası Seç
                    </button>
                    <p style="margin-top: 15px; font-size: 12px; color: #999;">
                        Maksimum dosya boyutu: 5MB | Desteklenen formatlar: .xls, .xlsx
                    </p>
                </div>

                <input type="hidden" name="excel_data" id="excelData">

                <div class="file-info" id="fileInfo">
                    <h4 style="color: #28a745; margin-bottom: 15px;">✅ Dosya Başarıyla Yüklendi</h4>
                    <div class="file-info-grid">
                        <span class="file-info-label">📄 Dosya Adı:</span>
                        <span class="file-info-value" id="fileName"></span>
                        
                        <span class="file-info-label">💾 Boyut:</span>
                        <span class="file-info-value" id="fileSize"></span>
                        
                        <span class="file-info-label">📊 Toplam Satır:</span>
                        <span class="file-info-value" id="totalRows"></span>
                        
                        <span class="file-info-label">📋 Veri Satırı:</span>
                        <span class="file-info-value" id="dataRows"></span>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="removeFile()" style="margin-top: 15px; padding: 10px 20px; font-size: 14px;">
                        ❌ Dosyayı Kaldır ve Yeniden Yükle
                    </button>
                </div>

                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill">Yükleniyor...</div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                        🔍 Önizle ve Kontrol Et
                    </button>
                    <a href="bulk-sales-template.html" class="btn btn-primary" target="_blank">
                        📥 Excel Şablonunu İndir
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- SheetJS Kütüphanesi -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const excelFile = document.getElementById('excelFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const totalRows = document.getElementById('totalRows');
        const dataRows = document.getElementById('dataRows');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const excelData = document.getElementById('excelData');

        // Drag & Drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                excelFile.files = files;
                handleFileSelect();
            }
        });

        // Dosya seçildiğinde
        excelFile.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = excelFile.files[0];
            
            if (!file) return;
            
            // Dosya kontrolü
            const validExtensions = ['.xls', '.xlsx'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!validExtensions.includes(fileExtension)) {
                alert('❌ Lütfen sadece Excel dosyası yükleyin! (.xls veya .xlsx)');
                excelFile.value = '';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB
                alert('❌ Dosya boyutu 5MB\'dan büyük olamaz!');
                excelFile.value = '';
                return;
            }
            
            // Dosya bilgilerini göster
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            // Excel dosyasını oku
            readExcelFile(file);
        }

        function readExcelFile(file) {
            progressBar.classList.add('active');
            progressFill.style.width = '30%';
            progressFill.textContent = 'Excel okunuyor...';
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    progressFill.style.width = '60%';
                    progressFill.textContent = 'Veriler işleniyor...';
                    
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                    
                    // İlk sheet'i al
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    
                    // JSON'a çevir - boş hücreleri koruyarak
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { 
                        header: 1, 
                        defval: '',
                        raw: false // Tarihleri string olarak al
                    });
                    
                    // Boş satırları temizle
                    const cleanedData = jsonData.filter(row => {
                        return row.some(cell => cell !== null && cell !== undefined && cell !== '');
                    });
                    
                    if (cleanedData.length < 2) {
                        throw new Error('Excel dosyası boş veya sadece başlık satırı içeriyor!');
                    }
                    
                    // Hidden input'a kaydet
                    excelData.value = JSON.stringify(cleanedData);
                    
                    // İstatistikleri göster
                    totalRows.textContent = cleanedData.length + ' satır';
                    dataRows.textContent = (cleanedData.length - 1) + ' satır (başlık hariç)';
                    
                    progressFill.style.width = '100%';
                    progressFill.textContent = '✓ Hazır!';
                    
                    setTimeout(() => {
                        progressBar.classList.remove('active');
                        fileInfo.classList.add('active');
                        uploadArea.classList.add('success');
                        uploadArea.querySelector('h3').textContent = '✅ Dosya Yüklendi!';
                        uploadArea.querySelector('p').textContent = 'Önizleme için devam edebilirsiniz';
                        submitBtn.disabled = false;
                    }, 500);
                    
                    console.log('✅ Excel başarıyla okundu:', cleanedData.length, 'satır');
                    
                } catch (error) {
                    console.error('Excel okuma hatası:', error);
                    alert('❌ Excel dosyası okunamadı: ' + error.message);
                    removeFile();
                }
            };
            
            reader.onerror = function() {
                alert('❌ Dosya okuma hatası!');
                removeFile();
            };
            
            reader.readAsArrayBuffer(file);
        }

        function removeFile() {
            excelFile.value = '';
            excelData.value = '';
            fileInfo.classList.remove('active');
            progressBar.classList.remove('active');
            submitBtn.disabled = true;
            uploadArea.classList.remove('success', 'dragover');
            uploadArea.querySelector('h3').textContent = 'Excel Dosyanızı Buraya Sürükleyin';
            uploadArea.querySelector('p').textContent = 'veya dosya seçmek için tıklayın';
            progressFill.style.width = '0%';
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' Bytes';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        // Form submit - ilerleme göster
        uploadForm.addEventListener('submit', (e) => {
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ İşleniyor...';
        });
    </script>
</body>
</html>