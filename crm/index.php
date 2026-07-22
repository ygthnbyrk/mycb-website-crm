<?php
require_once 'config.php';

// Session kontrolü - daha sıkı
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Eğer logout parametresi varsa session'ı temizle
    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit();
    }
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $stmt = $conn->prepare("SELECT id, email, password, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'E-posta veya şifre hatalı.';
            }
        } else {
            $error = 'E-posta veya şifre hatalı.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <title>Mycb Teknoloji</title>
</head>
<body>
    <div class="login-wrap">
    <div class="login-card">
        <div class="logo">
            <img src="assets/images/logo-light.png" alt="Logo">
        </div>
        <h2>Hoş Geldiniz</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">E-posta Adresi</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="ornek@mail.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn-primary">Giriş Yap</button>
        </form>

        <div class="version">Mycb Core v1.0</div>
    </div>
    </div>
    <footer style="width:100%;text-align:center;color:var(--text-muted);font-size:12.5px;padding:15px 0;">
        © 2025 <strong>MYCB Teknoloji</strong>. Tüm hakları saklıdır.
    </footer>
</body>
</html>