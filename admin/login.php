<?php
$router   = new app_Libs_Router();
$db       = new app_Libs_DbConnection();
$identity = new app_Libs_UserIdentity();

// ── Redirect nếu đã đăng nhập ──────────────────────────────
if ($identity->isLogin()) {
    if ($_SESSION["role"] === "admin")       $router->adminPage();
    elseif ($_SESSION["role"] === "seller")  $router->sellerPage();
    else                                      $router->userPage();
    exit();
}

/* ═══════════════════════════════════════════════════════════
   Cấu hình SMTP Gmail — chỉnh 3 dòng này
═══════════════════════════════════════════════════════════ */
define('SMTP_USER', 'moelandrare@gmail.com');       // Gmail của bạn
define('SMTP_PASS', 'bxvh laqh mqyj yqdv');        // App Password 16 ký tự
define('SMTP_FROM_NAME', 'Manta Vietnam');

/* ═══════════════════════════════════════════════════════════
   Helpers
═══════════════════════════════════════════════════════════ */
// ── Load PHPMailer thủ công — đặt 3 file vào app/libs/PHPMailer/ ──
// Tải tại: https://github.com/PHPMailer/PHPMailer/releases
// Lấy 3 file trong thư mục src/: Exception.php, PHPMailer.php, SMTP.php
// login.php nằm ở public/ → app/Libs/PHPMailer/ cách 1 cấp
require_once __DIR__ . '/../app/Libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../app/Libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../app/Libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendOtpEmail(string $to, string $otp, string $type = 'register'): bool {

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Người gửi & nhận
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Nội dung — khác nhau theo type
        $mail->isHTML(true);
        $isReset    = ($type === 'reset');
        $subject    = $isReset ? 'Đặt lại mật khẩu tài khoản Manta' : 'Mã xác thực đăng ký tài khoản Manta';
        $title      = $isReset ? '🔐 Đặt lại mật khẩu'              : '✅ Xác thực tài khoản';
        $subtext    = $isReset ? 'Bạn vừa yêu cầu đặt lại mật khẩu. Nhập mã bên dưới để tiếp tục.'
                               : 'Chào mừng bạn đến với Manta! Nhập mã bên dưới để hoàn tất đăng ký.';
        $disclaimer = $isReset ? 'Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này ngay.'
                               : 'Nếu bạn không yêu cầu đăng ký, hãy bỏ qua email này.';
        $accent     = $isReset ? '#f59e0b' : '#fb7185';
        $accentBg   = $isReset ? 'linear-gradient(135deg,#fef3c7,#fde68a)' : 'linear-gradient(135deg,#fce7f3,#ffe4e6)';
        $headerBg   = $isReset ? 'linear-gradient(135deg,#f59e0b,#ef4444)'  : 'linear-gradient(135deg,#f9a8d4,#fb7185)';

        $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $mail->Body    = "
        <div style='font-family:\"Be Vietnam Pro\",sans-serif;max-width:480px;margin:auto;
                    background:#fff8f8;border-radius:20px;overflow:hidden;
                    box-shadow:0 8px 32px rgba(0,0,0,.08);'>
          <div style='background:$headerBg;padding:32px;text-align:center;'>
            <span style='font-size:28px;font-weight:800;color:#fff;letter-spacing:-1px;'>🐟 MANTA</span>
          </div>
          <div style='padding:36px 40px;'>
            <h2 style='color:#1e293b;font-size:20px;margin:0 0 8px;text-align:center;'>$title</h2>
            <p style='text-align:center;color:#64748b;font-size:14px;margin:0 0 8px;'>$subtext</p>
            <p style='text-align:center;color:#64748b;font-size:13px;margin:0 0 28px;'>
              Mã có hiệu lực trong <strong style='color:$accent;'>10 phút</strong>
            </p>
            <div style='background:$accentBg;border-radius:14px;padding:24px;text-align:center;
                        letter-spacing:10px;font-size:38px;font-weight:800;
                        color:#1e293b;border:2px dashed $accent;'>
              $otp
            </div>
            <p style='text-align:center;color:#111;font-size:12px;margin-top:24px;'>$disclaimer</p>
          </div>
        </div>";
        $mail->AltBody = "$subject — Mã OTP: $otp (có hiệu lực 10 phút)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateOtp(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* ═══════════════════════════════════════════════════════════
   State
═══════════════════════════════════════════════════════════ */
$activeTab  = 'login';   // 'login' | 'register' | 'forgot'
$regStep    = $_SESSION['reg_step'] ?? 'form';  // 'form' | 'verify' | 'done'
$loginError = '';
$regErrors  = [];
$regInfo    = '';

// Forgot password state
$fpStep     = $_SESSION['fp_step'] ?? 'email'; // 'email' | 'otp' | 'reset' | 'done'
$fpErrors   = [];
$fpInfo     = '';

// Chuyển sang màn forgot nếu đang trong luồng
if (isset($_SESSION['fp_step']) && $_SESSION['fp_step'] !== 'email') {
    $activeTab = 'forgot';
}
// Hoặc khi GET ?forgot=1
if (isset($_GET['forgot'])) {
    $activeTab = 'forgot';
}

/* ═══════════════════════════════════════════════════════════
   FORGOT PASSWORD — Step 1: nhập email
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_fp_email')) {
    $activeTab = 'forgot';
    $fpEmail   = trim($router->getPOST('fp_email'));

    if (!filter_var($fpEmail, FILTER_VALIDATE_EMAIL)) {
        $fpErrors[] = "Email không hợp lệ.";
    } else {
        $user = $db->query(
            "SELECT id, full_name FROM users WHERE email = :e AND is_active = 1 LIMIT 1",
            ['e' => $fpEmail]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $fpErrors[] = "Email này chưa được đăng ký hoặc tài khoản đã bị khóa.";
        } else {
            $otp = generateOtp();
            // Email nội dung reset password
            $sent = sendOtpEmail($fpEmail, $otp, 'reset');
            if ($sent) {
                $_SESSION['fp_step']     = 'otp';
                $_SESSION['fp_email']    = $fpEmail;
                $_SESSION['fp_user_id']  = $user['id'];
                $_SESSION['fp_otp']      = $otp;
                $_SESSION['fp_otp_time'] = time();
                $fpStep = 'otp';
                $fpInfo = "Mã xác thực đã gửi tới <strong>$fpEmail</strong>.";
            } else {
                $fpErrors[] = "Không thể gửi email. Vui lòng thử lại.";
            }
        }
    }
}

/* ═══════════════════════════════════════════════════════════
   FORGOT PASSWORD — Step 2: xác nhận OTP
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_fp_otp')) {
    $activeTab = 'forgot';
    $fpStep    = 'otp';
    $entered   = trim($router->getPOST('fp_otp'));

    if (!isset($_SESSION['fp_otp'], $_SESSION['fp_otp_time'])) {
        $fpErrors[] = "Phiên hết hạn. Vui lòng bắt đầu lại.";
        $fpStep = 'email';
        unset($_SESSION['fp_step']);
    } elseif (time() - $_SESSION['fp_otp_time'] > 600) {
        $fpErrors[] = "Mã OTP đã hết hạn. Vui lòng gửi lại.";
    } elseif ($entered !== $_SESSION['fp_otp']) {
        $fpErrors[] = "Mã OTP không chính xác.";
    } else {
        $_SESSION['fp_step']     = 'reset';
        $_SESSION['fp_verified'] = true;
        $fpStep = 'reset';
        $fpInfo = "Xác thực thành công! Hãy đặt mật khẩu mới.";
    }
}

/* FORGOT — gửi lại OTP */
if ($router->getPOST('resend_fp_otp')) {
    $activeTab = 'forgot';
    $fpStep    = 'otp';
    if (isset($_SESSION['fp_email'])) {
        $otp = generateOtp();
        if (sendOtpEmail($_SESSION['fp_email'], $otp, 'reset')) {
            $_SESSION['fp_otp']      = $otp;
            $_SESSION['fp_otp_time'] = time();
            $fpInfo = "Đã gửi lại mã OTP mới.";
        } else {
            $fpErrors[] = "Không thể gửi email. Vui lòng thử lại.";
        }
    } else {
        $fpErrors[] = "Phiên hết hạn.";
        $fpStep = 'email';
        unset($_SESSION['fp_step']);
    }
}

/* ═══════════════════════════════════════════════════════════
   FORGOT PASSWORD — Step 3: đặt mật khẩu mới
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_fp_reset')) {
    $activeTab = 'forgot';
    $fpStep    = 'reset';

    if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) {
        $fpErrors[] = "Phiên không hợp lệ. Vui lòng bắt đầu lại.";
        $fpStep = 'email';
        unset($_SESSION['fp_step'], $_SESSION['fp_verified']);
    } else {
        $newPass  = trim($router->getPOST('fp_new_password'));
        $confirm  = trim($router->getPOST('fp_confirm_password'));

        if (strlen($newPass) < 6)    $fpErrors[] = "Mật khẩu ít nhất 6 ký tự.";
        if ($newPass !== $confirm)   $fpErrors[] = "Mật khẩu xác nhận không khớp.";

        if (!$fpErrors) {
            $db->query(
                "UPDATE users SET password = :pw WHERE id = :id",
                ['pw' => $newPass, 'id' => $_SESSION['fp_user_id']]
            );
            unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_user_id'],
                  $_SESSION['fp_otp'], $_SESSION['fp_otp_time'], $_SESSION['fp_verified']);
            $fpStep = 'done';
        }
    }
}

/* ═══════════════════════════════════════════════════════════
   LOGIN submit
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_login')) {
    $activeTab = 'login';
    $login     = trim($router->getPOST('login'));
    $password  = trim($router->getPOST('password'));

    if (!$login || !$password) {
        $loginError = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        $sql  = "SELECT * FROM users
                 WHERE (email = :v OR phone = :v OR username = :v)
                   AND is_active = 1
                 LIMIT 1";
        $user = $db->query($sql, ["v" => $login])->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user["password"]) {
            $identity->login([
                "id"       => $user["id"],
                "username" => $user["full_name"] ?? $user["username"],
                "role"     => $user["role"],
                "avatar"   => $user["avatar"],
            ]);
            if ($user["role"] === "admin")       $router->adminPage();
            elseif ($user["role"] === "seller")  $router->sellerPage();
            else                                  $router->userPage();
            exit();
        } else {
            $loginError = "Email/SĐT/Tên đăng nhập hoặc mật khẩu không đúng!";
        }
    }
}

/* ═══════════════════════════════════════════════════════════
   REGISTER — Step 1: gửi OTP
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_register')) {
    $activeTab  = 'register';
    $full_name  = trim($router->getPOST('full_name'));
    $username   = trim($router->getPOST('username'));
    $email      = trim($router->getPOST('email'));
    $phone      = trim($router->getPOST('phone'));
    $password   = trim($router->getPOST('password'));
    $confirm    = trim($router->getPOST('confirm_password'));

    if (!$full_name)                               $regErrors[] = "Vui lòng nhập họ tên.";
    if (!$username || strlen($username) < 3)        $regErrors[] = "Tên đăng nhập ít nhất 3 ký tự.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $regErrors[] = "Email không hợp lệ.";
    if ($phone && !preg_match('/^0\d{9}$/', $phone)) $regErrors[] = "Số điện thoại không hợp lệ.";
    if (strlen($password) < 6)                     $regErrors[] = "Mật khẩu ít nhất 6 ký tự.";
    if ($password !== $confirm)                    $regErrors[] = "Mật khẩu xác nhận không khớp.";

    if (!$regErrors) {
        $exist = $db->query(
            "SELECT id FROM users WHERE email = :e OR username = :u LIMIT 1",
            ['e' => $email, 'u' => $username]
        )->fetch(PDO::FETCH_ASSOC);
        if ($exist) $regErrors[] = "Email hoặc tên đăng nhập đã tồn tại.";

        if ($phone) {
            $ep = $db->query(
                "SELECT id FROM users WHERE phone = :p LIMIT 1", ['p' => $phone]
            )->fetch(PDO::FETCH_ASSOC);
            if ($ep) $regErrors[] = "Số điện thoại đã được sử dụng.";
        }
    }

    if (!$regErrors) {
        $otp = generateOtp();
        if (sendOtpEmail($email, $otp)) {
            $_SESSION['reg_step']     = 'verify';
            $_SESSION['reg_otp']      = $otp;
            $_SESSION['reg_otp_time'] = time();
            $_SESSION['reg_data']     = compact('full_name','username','email','phone','password');
            $regStep = 'verify';
            $regInfo = "Mã OTP đã gửi tới <strong>$email</strong>. Vui lòng kiểm tra hộp thư.";
        } else {
            $regErrors[] = "Không thể gửi email. Vui lòng thử lại.";
        }
    }
}

/* ═══════════════════════════════════════════════════════════
   REGISTER — Step 2: xác nhận OTP
═══════════════════════════════════════════════════════════ */
if ($router->getPOST('submit_otp')) {
    $activeTab = 'register';
    $regStep   = 'verify';
    $entered   = trim($router->getPOST('otp'));

    if (!isset($_SESSION['reg_otp'], $_SESSION['reg_otp_time'], $_SESSION['reg_data'])) {
        $regErrors[] = "Phiên đăng ký hết hạn. Vui lòng bắt đầu lại.";
        $regStep = 'form';
    } elseif (time() - $_SESSION['reg_otp_time'] > 600) {
        $regErrors[] = "Mã OTP đã hết hạn. Vui lòng gửi lại.";
    } elseif ($entered !== $_SESSION['reg_otp']) {
        $regErrors[] = "Mã OTP không chính xác.";
    } else {
        $d = $_SESSION['reg_data'];
        $db->query(
            "INSERT INTO users (full_name, username, email, phone, password, role, is_active)
             VALUES (:fn, :un, :em, :ph, :pw, 'user', 1)",
            ['fn'=>$d['full_name'],'un'=>$d['username'],'em'=>$d['email'],
             'ph'=>$d['phone']?:null,'pw'=>$d['password']]
        );
        unset($_SESSION['reg_step'], $_SESSION['reg_otp'],
              $_SESSION['reg_otp_time'], $_SESSION['reg_data']);
        $regStep = 'done';
    }
}

/* REGISTER — gửi lại OTP */
if ($router->getPOST('resend_otp')) {
    $activeTab = 'register';
    $regStep   = 'verify';
    if (isset($_SESSION['reg_data'])) {
        $otp = generateOtp();
        if (sendOtpEmail($_SESSION['reg_data']['email'], $otp)) {
            $_SESSION['reg_otp']      = $otp;
            $_SESSION['reg_otp_time'] = time();
            $regInfo = "Đã gửi lại mã OTP mới.";
        } else {
            $regErrors[] = "Không thể gửi email. Vui lòng thử lại.";
        }
    } else {
        $regErrors[] = "Phiên đăng ký hết hạn.";
        $regStep = 'form';
    }
}

// Nếu đang ở bước verify/done thì tự động mở tab register
if ($regStep !== 'form') $activeTab = 'register';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng Nhập | Manta Việt Nam</title>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/login.css" />
  <link rel="stylesheet" href="../css/bg.css" />
  <style>
    /* ════════════════════════════
       Tab switcher
    ════════════════════════════ */
    .tab-bar {
      display: flex;
      background: rgba(255,255,255,.05);
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 24px;
      gap: 4px;
    }
    .tab-btn {
      flex: 1;
      padding: 10px;
      border: none;
      border-radius: 9px;
      background: transparent;
      color: #64748b;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background .25s, color .25s, box-shadow .25s;
    }
    .tab-btn.active {
      background: rgba(56,189,248,.18);
      color: #111;
      box-shadow: 0 0 0 1px rgba(56,189,248,.3);
    }

    /* ════════════════════════════
       Panel slide animation
    ════════════════════════════ */
    .tab-panel {
      display: none;
      animation: panelIn .3s ease;
    }
    .tab-panel.active { display: block; }
    @keyframes panelIn {
      from { opacity:0; transform:translateY(8px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* ════════════════════════════
       Register-specific extras
    ════════════════════════════ */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

    .info-box {
      background: rgba(56,189,248,.12);
      border: 1px solid rgba(56,189,248,.3);
      color: #111;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 14px;
      margin-bottom: 18px;
      line-height: 1.5;
    }

    /* ════════════════════════════
       OTP boxes
    ════════════════════════════ */
    .otp-group {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 8px 0 20px;
    }
    .otp-group input {
      width: 46px;
      height: 56px;
      text-align: center;
      font-size: 22px;
      font-weight: 700;
      border-radius: 10px;
      border: 2px solid rgba(255,255,255,1);
      background: rgba(255,255,255,.06);
      color: #111;
      transition: border-color .2s, background .2s;
      caret-color: #111;
    }
    .otp-group input:focus {
      outline: none;
      border-color: #111;
      background: rgba(56,189,248,.08);
    }
    .otp-group input.filled {
      border-color: rgba(56,189,248,.5);
    }

    .otp-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: #64748b;
      margin-bottom: 20px;
    }
    .otp-meta button {
      background: none;
      border: none;
      color: #111;
      cursor: pointer;
      font-size: 13px;
      font-family: inherit;
      padding: 0;
      transition: opacity .2s;
    }
    .otp-meta button:disabled { color: #475569; cursor: default; }

    /* step dots */
    .steps {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-bottom: 20px;
    }
    .steps span {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      transition: background .3s, width .3s;
    }
    .steps span.active { background: #111; width: 20px; border-radius: 4px; }
    .steps span.done   { background: #4ade80; }

    /* success */
    .success-card {
      text-align: center;
      padding: 16px 0 8px;
    }
    .success-icon {
      width: 68px; height: 68px;
      background: rgba(34,197,94,.15);
      border: 2px solid rgba(34,197,94,.35);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
      font-size: 30px;
      animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes popIn {
      from { transform: scale(.5); opacity:0; }
      to   { transform: scale(1);  opacity:1; }
    }
    .success-card h3 { color:#4ade80; font-size:20px; margin:0 0 8px; }
    .success-card p  { color:#111; font-size:14px; margin:0 0 24px; }

    .back-link {
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #64748b;
    }
    .back-link a { color: #111; }

    /* ════════════════════════════
       Password toggle & strength
    ════════════════════════════ */
    .pw-wrap { position: relative; }
    .pw-wrap input { width:100%; box-sizing:border-box; padding-right:44px; }
    .pw-toggle {
      position: absolute; right:12px; top:50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer; font-size:16px;
      opacity:.45; transition:opacity .2s; padding:0;
    }
    .pw-toggle:hover { opacity:1; }

    /* Forgot panel — header strip */
    #panelForgot .fp-header {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

<!-- ── Hero background ── -->
<section class="hero" id="hero">
  <div class="layer layer-bg"></div>
  <div class="layer layer-back-mountain" id="layerBack"></div>
  <div class="layer layer-front-mountain" id="layerFront"></div>
  <div class="layer layer-fish" id="layerFish">
    <img src="../img/Manta-Fish-1.svg" alt="Manta Fish">
  </div>
  <div class="layer layer-labubu" id="layerLabubu">
    <img src="../img/labubu.svg" alt="Labubu">
  </div>
  <div class="layer layer-fish2">
    <img src="../img/Manta-Fish-2.svg" alt="Manta Fish">
  </div>
</section>

<div class="auth-container">
  <a href="/MantaMarket/public/index.php" class="auth-logo">
    <img src="../img/new-logo.png" alt="Manta Logo">
  </a>

  <!-- ── Tab switcher ── -->
  <div class="tab-bar" id="mainTabBar">
    <button class="tab-btn <?= $activeTab==='login'    ? 'active' : '' ?>"
            onclick="switchTab('login')"    type="button" id="tabLogin">
      Đăng Nhập
    </button>
    <button class="tab-btn <?= $activeTab==='register' ? 'active' : '' ?>"
            onclick="switchTab('register')" type="button" id="tabRegister">
      Đăng Ký
    </button>
  </div>


  <!-- ══════════════════════════════════════════════════════
       PANEL: ĐĂNG NHẬP
  ══════════════════════════════════════════════════════ -->
  <div class="tab-panel <?= $activeTab==='login' ? 'active' : '' ?>" id="panelLogin">

    <?php if ($loginError): ?>
      <div class="error-box"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Email / Số điện thoại / Tên đăng nhập</label>
        <input type="text" name="login"
               placeholder="Nhập email, SĐT hoặc tên đăng nhập"
               value="<?= htmlspecialchars($router->getPOST('login') ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Mật khẩu</label>
        <input type="password" name="password" placeholder="Nhập mật khẩu">
      </div>
      <div class="forgot-link">
        <a href="javascript:void(0)" onclick="switchTab('forgot')">Quên mật khẩu?</a>
      </div>
      <button class="btn btn-primary" type="submit" name="submit_login" value="1">
        Đăng Nhập
      </button>
    </form>

    <div class="divider">Hoặc đăng nhập với</div>
    <div class="social-btns">
      <a href="/MantaMarket/auth-facebook.php" class="btn-social btn-facebook">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877f2">
          <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
        </svg>
        Facebook
      </a>
      <a href="/MantaMarket/auth-google.php" class="btn-social btn-google">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Google
      </a>
    </div>

    <p style="text-align:center;font-size:13px;color:#64748b;margin-top:18px;">
      Chưa có tài khoản?
      <a href="javascript:void(0)" onclick="switchTab('register')"
         style="color:#111;font-weight:600;">Đăng ký ngay</a>
    </p>
  </div><!-- /panelLogin -->


  <!-- ══════════════════════════════════════════════════════
       PANEL: ĐĂNG KÝ
  ══════════════════════════════════════════════════════ -->
  <div class="tab-panel <?= $activeTab==='register' ? 'active' : '' ?>" id="panelRegister">

    <?php if ($regErrors): ?>
      <div class="error-box"><?= implode('<br>', array_map('htmlspecialchars', $regErrors)) ?></div>
    <?php endif; ?>
    <?php if ($regInfo): ?>
      <div class="info-box"><?= $regInfo ?></div>
    <?php endif; ?>


    <!-- ── REGISTER STEP FORM ── -->
    <?php if ($regStep === 'form'): ?>

      <!-- step dots -->
      <div class="steps">
        <span class="active"></span>
        <span></span>
        <span></span>
      </div>

      <form method="POST" action="" autocomplete="off">
        <div class="form-row">
          <div class="form-group">
            <label>Họ và tên <span style="color:#f87171">*</span></label>
            <input type="text" name="full_name" placeholder="Nguyễn Văn A"
                   value="<?= htmlspecialchars($router->getPOST('full_name') ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Tên đăng nhập <span style="color:#f87171">*</span></label>
            <input type="text" name="username" placeholder="username123"
                   value="<?= htmlspecialchars($router->getPOST('username') ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Email <span style="color:#f87171">*</span></label>
          <input type="email" name="email" placeholder="example@email.com"
                 value="<?= htmlspecialchars($router->getPOST('email') ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Số điện thoại</label>
          <input type="tel" name="phone" placeholder="0912345678"
                 value="<?= htmlspecialchars($router->getPOST('phone') ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Mật khẩu <span style="color:#f87171">*</span></label>
            <input type="password" name="password" placeholder="Tối thiểu 6 ký tự">
          </div>
          <div class="form-group">
            <label>Xác nhận mật khẩu <span style="color:#f87171">*</span></label>
            <input type="password" name="confirm_password" placeholder="Nhập lại">
          </div>
        </div>
        <button class="btn btn-primary" type="submit" name="submit_register" value="1">
          Tiếp tục →
        </button>
      </form>

      <p style="text-align:center;font-size:13px;color:#64748b;margin-top:18px;">
        Đã có tài khoản?
        <a href="javascript:void(0)" onclick="switchTab('login')"
           style="color:#111;font-weight:600;">Đăng nhập</a>
      </p>


    <!-- ── REGISTER STEP VERIFY OTP ── -->
    <?php elseif ($regStep === 'verify'): ?>

      <div class="steps">
        <span class="done"></span>
        <span class="active"></span>
        <span></span>
      </div>

      <p style="text-align:center;color:#111;font-size:14px;margin:0 0 20px;">
        Nhập mã 6 số đã gửi tới<br>
        <strong style="color:#111;"><?= htmlspecialchars($_SESSION['reg_data']['email'] ?? '') ?></strong>
      </p>

      <form method="POST" action="" id="otpForm">
        <div class="otp-group" id="otpBoxes">
          <?php for ($i=0;$i<6;$i++): ?>
            <input type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" autocomplete="off" class="otp-digit">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otp_value">

        <div class="otp-meta">
          <span>Hết hạn sau <strong id="timer">10:00</strong></span>
          <button type="submit" form="resendForm" name="resend_otp"
                  value="1" id="resendBtn" disabled>Gửi lại mã</button>
        </div>

        <button class="btn btn-primary" type="submit"
                name="submit_otp" value="1" id="verifyBtn" disabled>
          Xác nhận
        </button>
      </form>

      <!-- Separate form for resend (keeps it clean) -->
      <form method="POST" action="" id="resendForm"></form>

      <div class="back-link">
        <a href="javascript:void(0)"
           onclick="clearRegSession()">← Nhập lại thông tin</a>
      </div>


    <!-- ── REGISTER STEP DONE ── -->
    <?php elseif ($regStep === 'done'): ?>

      <div class="steps">
        <span class="done"></span>
        <span class="done"></span>
        <span class="active"></span>
      </div>

      <div class="success-card">
        <div class="success-icon">✓</div>
        <h3>Đăng ký thành công!</h3>
        <p>Tài khoản đã được tạo thành công.<br>Hãy đăng nhập để bắt đầu mua sắm.</p>
        <button class="btn btn-primary" type="button"
                onclick="switchTab('login')" style="width:100%">
          Đăng nhập ngay
        </button>
      </div>

    <?php endif; ?>
  </div><!-- /panelRegister -->

  <!-- ══════════════════════════════════════════════════════
       PANEL: QUÊN MẬT KHẨU
  ══════════════════════════════════════════════════════ -->
  <div class="tab-panel <?= $activeTab==='forgot' ? 'active' : '' ?>" id="panelForgot">

    <?php if ($fpErrors): ?>
      <div class="error-box"><?= implode('<br>', array_map('htmlspecialchars', $fpErrors)) ?></div>
    <?php endif; ?>
    <?php if ($fpInfo): ?>
      <div class="info-box"><?= $fpInfo ?></div>
    <?php endif; ?>

    <!-- ── STEP 1: Nhập email ── -->
    <?php if ($fpStep === 'email'): ?>
      <div class="steps"><span class="active"></span><span></span><span></span></div>

      <p style="text-align:center;color:#64748b;font-size:14px;margin:0 0 20px;">
        Nhập email đã đăng ký để nhận mã xác thực.
      </p>
      <form method="POST" action="">
        <div class="form-group">
          <label>Email tài khoản <span style="color:#f87171">*</span></label>
          <input type="email" name="fp_email" placeholder="example@email.com"
                 value="<?= htmlspecialchars($router->getPOST('fp_email') ?? '') ?>"
                 autofocus>
        </div>
        <button class="btn btn-primary" type="submit" name="submit_fp_email" value="1">
          Gửi mã xác thực
        </button>
      </form>

      <p style="text-align:center;font-size:13px;color:#64748b;margin-top:18px;">
        <a href="javascript:void(0)" onclick="switchTab('login')"
           style="color:#111;font-weight:600;">← Quay lại đăng nhập</a>
      </p>

    <!-- ── STEP 2: Nhập OTP ── -->
    <?php elseif ($fpStep === 'otp'): ?>
      <div class="steps"><span class="done"></span><span class="active"></span><span></span></div>

      <p style="text-align:center;color:#111;font-size:14px;margin:0 0 20px;">
        Nhập mã 6 số đã gửi tới<br>
        <strong><?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?></strong>
      </p>

      <form method="POST" action="" id="fpOtpForm">
        <div class="otp-group" id="fpOtpBoxes">
          <?php for ($i=0;$i<6;$i++): ?>
            <input type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" autocomplete="off" class="fp-otp-digit">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="fp_otp" id="fp_otp_value">

        <div class="otp-meta">
          <span>Hết hạn sau <strong id="fp_timer">10:00</strong></span>
          <button type="submit" form="fpResendForm" name="resend_fp_otp"
                  value="1" id="fpResendBtn" disabled>Gửi lại mã</button>
        </div>

        <button class="btn btn-primary" type="submit"
                name="submit_fp_otp" value="1" id="fpVerifyBtn" disabled>
          Xác nhận
        </button>
      </form>
      <form method="POST" action="" id="fpResendForm"></form>

      <div class="back-link">
        <a href="javascript:void(0)" onclick="clearFpSession()">← Nhập lại email</a>
      </div>

    <!-- ── STEP 3: Đặt mật khẩu mới ── -->
    <?php elseif ($fpStep === 'reset'): ?>
      <div class="steps"><span class="done"></span><span class="done"></span><span class="active"></span></div>

      <p style="text-align:center;color:#64748b;font-size:14px;margin:0 0 20px;">
        Đặt mật khẩu mới cho tài khoản<br>
        <strong style="color:#111;"><?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?></strong>
      </p>

      <form method="POST" action="">
        <div class="form-group">
          <label>Mật khẩu mới <span style="color:#f87171">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="fp_new_password" id="fpNewPw"
                   placeholder="Tối thiểu 6 ký tự">
            <button type="button" class="pw-toggle" onclick="togglePw('fpNewPw',this)">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label>Xác nhận mật khẩu <span style="color:#f87171">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="fp_confirm_password" id="fpConfirmPw"
                   placeholder="Nhập lại mật khẩu mới">
            <button type="button" class="pw-toggle" onclick="togglePw('fpConfirmPw',this)">👁</button>
          </div>
        </div>
        <!-- Thanh độ mạnh mật khẩu -->
        <div id="pwStrengthWrap" style="margin:-8px 0 16px;">
          <div id="pwStrengthBar" style="height:4px;border-radius:2px;background:#111;transition:all .3s;">
            <div id="pwStrengthFill" style="height:100%;border-radius:2px;width:0;transition:all .4s;"></div>
          </div>
          <span id="pwStrengthLabel" style="font-size:11px;color:#111;"></span>
        </div>
        <button class="btn btn-primary" type="submit" name="submit_fp_reset" value="1">
          Cập nhật mật khẩu
        </button>
      </form>

    <!-- ── STEP DONE ── -->
    <?php elseif ($fpStep === 'done'): ?>
      <div class="steps"><span class="done"></span><span class="done"></span><span class="done"></span></div>

      <div class="success-card">
        <div class="success-icon">🔐</div>
        <h3>Đổi mật khẩu thành công!</h3>
        <p>Mật khẩu của bạn đã được cập nhật.<br>Hãy đăng nhập với mật khẩu mới.</p>
        <button class="btn btn-primary" type="button"
                onclick="switchTab('login')" style="width:100%">
          Đăng nhập ngay
        </button>
      </div>

    <?php endif; ?>
  </div><!-- /panelForgot -->

</div><!-- /auth-container -->

<script src="../js/bg.js"></script>
<script>
/* ════════════════════════════════════
   Tab switching — login | register | forgot
════════════════════════════════════ */
function switchTab(name) {
  ['login','register','forgot'].forEach(t => {
    const panel = document.getElementById('panel' + cap(t));
    const tab   = document.getElementById('tab'   + cap(t));
    if (panel) panel.classList.toggle('active', t === name);
    if (tab)   tab.classList.toggle('active',   t === name);
  });
  // Ẩn tab bar khi ở forgot, hiện lại khi login/register
  const bar = document.getElementById('mainTabBar');
  if (bar) bar.style.display = (name === 'forgot') ? 'none' : '';
}
// Ẩn tab bar ngay khi PHP render forgot active
(function(){
  <?php if ($activeTab === 'forgot'): ?>
  document.addEventListener('DOMContentLoaded', () => {
    const bar = document.getElementById('mainTabBar');
    if (bar) bar.style.display = 'none';
  });
  <?php endif; ?>
})();

function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

/* ════════════════════════════════════
   Clear sessions
════════════════════════════════════ */
function clearRegSession() {
  window.location.href = '?clear_reg=1';
}
function clearFpSession() {
  window.location.href = '?clear_fp=1';
}

/* ════════════════════════════════════
   OTP digit boxes — REGISTER
════════════════════════════════════ */
(function () {
  initOtpBoxes('.otp-digit', 'otp_value', 'verifyBtn', 'timer', 'resendBtn');
})();

/* ════════════════════════════════════
   OTP digit boxes — FORGOT PASSWORD
════════════════════════════════════ */
(function () {
  initOtpBoxes('.fp-otp-digit', 'fp_otp_value', 'fpVerifyBtn', 'fp_timer', 'fpResendBtn');
})();

/* shared OTP init */
function initOtpBoxes(selector, hiddenId, verifyId, timerId, resendId) {
  const digits    = document.querySelectorAll(selector);
  const hidden    = document.getElementById(hiddenId);
  const verifyBtn = document.getElementById(verifyId);
  if (!digits.length) return;

  function collect() { return [...digits].map(d=>d.value).join(''); }
  function sync() {
    const v = collect();
    if (hidden)    hidden.value        = v;
    if (verifyBtn) verifyBtn.disabled  = v.length < 6;
    digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
  }

  digits.forEach((box, idx) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g,'').slice(-1);
      sync();
      if (box.value && idx < digits.length-1) digits[idx+1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key==='Backspace' && !box.value && idx>0) {
        digits[idx-1].focus(); digits[idx-1].value = ''; sync();
      }
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const txt = (e.clipboardData||window.clipboardData)
                  .getData('text').replace(/\D/g,'').slice(0,6);
      txt.split('').forEach((ch,i)=>{ if(digits[i]) digits[i].value=ch; });
      digits[Math.min(txt.length, digits.length-1)].focus(); sync();
    });
  });
  digits[0]?.focus();

  // Countdown
  const timerEl   = document.getElementById(timerId);
  const resendBtn = document.getElementById(resendId);
  let   left      = 600;
  const tick = setInterval(() => {
    left--;
    if (timerEl) {
      const m = String(Math.floor(left/60)).padStart(2,'0');
      const s = String(left%60).padStart(2,'0');
      timerEl.textContent = `${m}:${s}`;
    }
    if (left <= 0) {
      clearInterval(tick);
      if (timerEl)   timerEl.textContent  = '00:00';
      if (resendBtn) resendBtn.disabled   = false;
    }
  }, 1000);
}

/* ════════════════════════════════════
   Password toggle (show/hide)
════════════════════════════════════ */
function togglePw(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type   = show ? 'text' : 'password';
  btn.textContent = show ? '🙈' : '👁';
}

/* ════════════════════════════════════
   Password strength meter
════════════════════════════════════ */
(function(){
  const inp   = document.getElementById('fpNewPw');
  const fill  = document.getElementById('pwStrengthFill');
  const label = document.getElementById('pwStrengthLabel');
  if (!inp || !fill) return;

  inp.addEventListener('input', () => {
    const v = inp.value;
    let score = 0;
    if (v.length >= 6)               score++;
    if (v.length >= 10)              score++;
    if (/[A-Z]/.test(v))            score++;
    if (/[0-9]/.test(v))            score++;
    if (/[^A-Za-z0-9]/.test(v))     score++;

    const levels = [
      { pct:'0%',   color:'#111', text:'' },
      { pct:'25%',  color:'#f87171', text:'Rất yếu' },
      { pct:'50%',  color:'#fb923c', text:'Yếu' },
      { pct:'70%',  color:'#facc15', text:'Trung bình' },
      { pct:'85%',  color:'#4ade80', text:'Mạnh' },
      { pct:'100%', color:'#22c55e', text:'Rất mạnh' },
    ];
    const lvl = levels[Math.min(score, 5)];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    if (label) { label.textContent = lvl.text; label.style.color = lvl.color; }
  });
})();
</script>

<?php
// Xử lý clear session đăng ký
if (isset($_GET['clear_reg'])) {
  unset($_SESSION['reg_step'],$_SESSION['reg_otp'],$_SESSION['reg_otp_time'],$_SESSION['reg_data']);
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit();
}
// Xử lý clear session quên mật khẩu
if (isset($_GET['clear_fp'])) {
  unset($_SESSION['fp_step'],$_SESSION['fp_email'],$_SESSION['fp_user_id'],
        $_SESSION['fp_otp'],$_SESSION['fp_otp_time'],$_SESSION['fp_verified']);
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?forgot=1');
  exit();
}
?>
</body>
</html>