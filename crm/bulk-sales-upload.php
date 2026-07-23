<?php
require_once 'config.php';
require_once 'partials/icons.php';

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
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Toplu Satış Yükle - CRM</title>
</head>
<body>
    <?php $active_page = 'bulk-sales-upload'; include 'partials/sidebar.php'; ?>

    <!-- Ana İçerik -->
    <div class="main-content">
        <div class="center-container">
            <h1 style="display:flex;align-items:center;gap:10px;font-size:20px;margin-bottom:6px;"><?php echo icon('upload'); ?> Toplu Satış Yükle</h1>
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
                <h4>Yükleme Adımları</h4>
                <ul>
                    <li><strong>1.</strong> Aşağıdaki butona tıklayarak Excel şablonunu indirin</li>
                    <li><strong>2.</strong> Excel ile açın ve satış verilerinizi doldurun</li>
                    <li><strong>3.</strong> Dosyayı <strong>Excel formatında</strong> kaydedin (.xls veya .xlsx)</li>
                    <li><strong>4.</strong> Aşağıdaki alana sürükleyin veya tıklayarak seçin</li>
                    <li><strong>5.</strong> Önizleme sayfasında kontrol edin</li>
                    <li><strong>6.</strong> Onaylayarak kaydedin</li>
                    <li style="color: var(--success); font-weight: 600;">CSV'ye çevirmenize gerek yok!</li>
                </ul>
            </div>

            <form id="uploadForm" method="POST" action="bulk-sales-preview.php">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('excelFile').click()">
                    <h3>Excel Dosyanızı Buraya Sürükleyin</h3>
                    <p>veya dosya seçmek için tıklayın</p>
                    <input type="file" name="excel_file" id="excelFile" accept=".xls,.xlsx" required>
                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation(); document.getElementById('excelFile').click()">
                        Excel Dosyası Seç
                    </button>
                    <p style="margin-top: 15px; font-size: 12px; color: var(--text-muted);">
                        Maksimum dosya boyutu: 5MB | Desteklenen formatlar: .xls, .xlsx
                    </p>
                </div>

                <input type="hidden" name="excel_data" id="excelData">

                <div class="file-info" id="fileInfo">
                    <h4><?php echo icon('check'); ?> Dosya Başarıyla Yüklendi</h4>
                    <div class="file-info-grid">
                        <span class="file-info-label">Dosya Adı:</span>
                        <span class="file-info-value" id="fileName"></span>

                        <span class="file-info-label">Boyut:</span>
                        <span class="file-info-value" id="fileSize"></span>

                        <span class="file-info-label">Toplam Satır:</span>
                        <span class="file-info-value" id="totalRows"></span>

                        <span class="file-info-label">Veri Satırı:</span>
                        <span class="file-info-value" id="dataRows"></span>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="removeFile()" style="margin-top: 15px;">
                        <?php echo icon('x'); ?> Dosyayı Kaldır ve Yeniden Yükle
                    </button>
                </div>

                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill">Yükleniyor...</div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <?php echo icon('search'); ?> Önizle ve Kontrol Et
                    </button>
                    <a href="bulk-sales-template.html" class="btn btn-secondary" target="_blank">
                        Excel Şablonunu İndir
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
                alert('Lütfen sadece Excel dosyası yükleyin! (.xls veya .xlsx)');
                excelFile.value = '';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB
                alert('Dosya boyutu 5MB\'dan büyük olamaz!');
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
                    progressFill.textContent = 'Hazır!';
                    
                    setTimeout(() => {
                        progressBar.classList.remove('active');
                        fileInfo.classList.add('active');
                        uploadArea.classList.add('success');
                        uploadArea.querySelector('h3').textContent = 'Dosya Yüklendi!';
                        uploadArea.querySelector('p').textContent = 'Önizleme için devam edebilirsiniz';
                        submitBtn.disabled = false;
                    }, 500);
                    
                    console.log('Excel başarıyla okundu:', cleanedData.length, 'satır');
                    
                } catch (error) {
                    console.error('Excel okuma hatası:', error);
                    alert('Excel dosyası okunamadı: ' + error.message);
                    removeFile();
                }
            };
            
            reader.onerror = function() {
                alert('Dosya okuma hatası!');
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
            submitBtn.textContent = 'İşleniyor...';
        });
    </script>
</body>
</html>