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
    <link rel="stylesheet" href="assets/css/responsive.css"> 
    <title>Mycb Teknoloji</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
            font-size: 38px;
            font-weight: bold;
        }
        .logo span { color: #667eea; }
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .alert {
            padding: 12px 16px;
            background: #fee;
            color: #c33;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 14px;
        }
        .version {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-card">
       <div class="logo">
    <img src="assets/images/logo-light.png" alt="Logo" style="max-width: 200px; height: auto;">
</div>
        <h2>Hoş Geldiniz</h2>
        
        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
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
            
            <button type="submit">Giriş Yap</button>
        </form>
        
        <div class="version">Mycb Core v1.0</div>
    </div>
    <footer style="
  width: 100%;
  text-align: center;
  color: #999;
  font-size: 13px;
  padding: 15px 0;
  position: absolute;
  bottom: 10px;
  left: 0;
">
  © 2025 <strong>MYCB Teknoloji</strong>. Tüm hakları saklıdır.
</footer>
</body>
</html>