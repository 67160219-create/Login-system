<?php
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

$errors = [];
$success = "";

// ถ้าส่งฟอร์มด้วย POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ตรวจ CSRF Token
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าใหม่";
    }

    // รับค่าจากฟอร์ม
    $email = trim($_POST['email'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจความถูกต้อง
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "อีเมลไม่ถูกต้อง";
    }
    if (strlen($password) < 8) {
        $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
    }

    // ตรวจว่ามี email ซ้ำหรือไม่
    if (!$errors) {
        $check = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "อีเมลนี้ถูกใช้แล้ว";
        }
        $check->close();
    }

    // ถ้าไม่มี error → บันทึกข้อมูล
    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $display_name, $password_hash);

        if ($stmt->execute()) {
            $success = "สมัครสมาชิกสำเร็จ! 🎉";
            $_SESSION['csrf'] = bin2hex(random_bytes(32)); // เปลี่ยน token ใหม่
        } else {
            $errors[] = "เกิดข้อผิดพลาด: " . $stmt->error;
        }

        $stmt->close();
    }
}

// ฟังก์ชันกัน XSS เวลาแสดงผล
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สมัครสมาชิก</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
    .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 16px; padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    h1 { text-align: center; }
    label { display: block; margin-top: 12px; font-size: 14px; }
    input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; margin-top: 4px; }
    button { margin-top: 20px; width: 100%; padding: 12px; border: none; border-radius: 8px;
      background: #2563eb; color: white; font-weight: 600; cursor: pointer; }
    .alert { margin-top: 16px; padding: 12px; border-radius: 8px; }
    .error { background: #fee2e2; color: #b91c1c; }
    .success { background: #dcfce7; color: #166534; }
  </style>
</head>
<body>
  <div class="container">
    <h1>สมัครสมาชิก</h1>

    <?php if ($errors): ?>
      <div class="alert error">
        <?php foreach ($errors as $e) echo "<div>" . e($e) . "</div>"; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label>อีเมล</label>
      <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

      <label>ชื่อที่แสดง</label>
      <input type="text" name="display_name" value="<?= e($_POST['display_name'] ?? '') ?>">

      <label>รหัสผ่าน</label>
      <input type="password" name="password" required>

      <button type="submit">สมัครสมาชิก</button>
    </form>
  </div>
</body>
</html>