<?php
session_start();
include('config.php');

$login_error = '';

// Nếu đã đăng nhập, chuyển hướng đi
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Xử lý khi người dùng gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $login_error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        // Thử gọi một API của Orthanc để xác thực
        $url = $orthanc . 'system';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password); // Dùng thông tin người dùng gửi lên
        
        curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code == 200) {
            // Đăng nhập thành công, lưu thông tin vào session
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password; 
            header('Location: dashboard.php');
            exit();
        } else {
            $login_error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <h2 class="text-center mt-5">Đăng Nhập Hệ Thống PACS</h2>
            <div class="card my-4">
                <div class="card-body">
                    <?php if ($login_error): ?>
                        <div class="alert alert-danger"><?php echo $login_error; ?></div>
                    <?php endif; ?>
                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Đăng Nhập</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>