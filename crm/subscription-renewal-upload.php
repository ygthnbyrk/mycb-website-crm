<?php
require_once 'config.php';
require_once 'partials/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Abonelik Yenileme - CRM</title>
</head>
<body>
    <?php $active_page = 'subscription-renewal'; include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="center-container">
            <h1 style="display:flex;align-items:center;gap:10px;font-size:20px;margin-bottom:6px;"><?php echo icon('upload'); ?> Abonelik Yenileme</h1>
            <p class="subtitle">Excel listesiyle toplu abonelik yenileme yapın</p>

            <div class="info-box">
                <h4>Beklenen Sütunlar (bu sırayla)</h4>
                <ul>
                    <li><strong>1.</strong> Müşteri</li>
                    <li><strong>2.</strong> Seri No (IMEI)</li>
                    <li><strong>3.</strong> Sahiplik Durumu</li>
                    <li><strong>4.</strong> Abonelik Süresi ("1 Yıl" / "2 Yıl")</li>
                    <li><strong>5.</strong> Liste Fiyatı</li>
                    <li><strong>6.</strong> İndirimli Fiyat (boş olabilir)</li>
                    <li><strong>7.</strong> Tarih</li>
                </ul>
            </div>

            <form id="uploadForm" method="POST" action="subscription-renewal-preview.php">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('excelFile').click()">
                    <h3>Excel Dosyanızı Buraya Sürükleyin</h3>
                    <p>veya dosya seçmek için tıklayın</p>
                    <input type="file" name="excel_file" id="excelFile" accept=".xls,.xlsx" required>
                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation(); document.getElementById('excelFile').click()">
                        Excel Dosyası Seç
                    </button>
                </div>

                <input type="hidden" name="excel_data" id="excelData">

                <div class="file-info" id="fileInfo">
                    <h4><?php echo icon('check'); ?> Dosya Başarıyla Yüklendi</h4>
                    <div class="file-info-grid">
                        <span class="file-info-label">Dosya Adı:</span>
                        <span class="file-info-value" id="fileName"></span>
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
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const excelFile = document.getElementById('excelFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const dataRows = document.getElementById('dataRows');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const excelData = document.getElementById('excelData');

        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); uploadArea.classList.add('dragover'); });
        uploadArea.addEventListener('dragleave', (e) => { e.preventDefault(); e.stopPropagation(); uploadArea.classList.remove('dragover'); });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault(); e.stopPropagation();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) { excelFile.files = files; handleFileSelect(); }
        });
        excelFile.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = excelFile.files[0];
            if (!file) return;
            const validExtensions = ['.xls', '.xlsx'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            if (!validExtensions.includes(fileExtension)) {
                alert('Lütfen sadece Excel dosyası yükleyin! (.xls veya .xlsx)');
                excelFile.value = '';
                return;
            }
            fileName.textContent = file.name;
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
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '', raw: false, dateNF: 'yyyy-mm-dd' });
                    const cleanedData = jsonData.filter(row => row.some(cell => cell !== null && cell !== undefined && cell !== ''));
                    if (cleanedData.length < 2) throw new Error('Excel dosyası boş veya sadece başlık satırı içeriyor!');
                    excelData.value = JSON.stringify(cleanedData);
                    dataRows.textContent = (cleanedData.length - 1) + ' satır (başlık hariç)';
                    progressFill.style.width = '100%';
                    progressFill.textContent = 'Hazır!';
                    setTimeout(() => {
                        progressBar.classList.remove('active');
                        fileInfo.classList.add('active');
                        uploadArea.classList.add('success');
                        submitBtn.disabled = false;
                    }, 400);
                } catch (error) {
                    alert('Excel dosyası okunamadı: ' + error.message);
                    removeFile();
                }
            };
            reader.onerror = function() { alert('Dosya okuma hatası!'); removeFile(); };
            reader.readAsArrayBuffer(file);
        }

        function removeFile() {
            excelFile.value = '';
            excelData.value = '';
            fileInfo.classList.remove('active');
            progressBar.classList.remove('active');
            submitBtn.disabled = true;
            uploadArea.classList.remove('success', 'dragover');
        }

        uploadForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.textContent = 'İşleniyor...';
        });
    </script>
</body>
</html>
