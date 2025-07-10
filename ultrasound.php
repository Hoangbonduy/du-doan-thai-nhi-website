<?php
// ==================================================================
// Bắt đầu session và kiểm tra đăng nhập
// ==================================================================
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// ==================================================================
// Bao gồm cấu hình và thông tin xác thực
// ==================================================================
include('config.php');
$auth_user = $_SESSION['username'];
$auth_pass = $_SESSION['password'];

// ==================================================================
// Khai báo biến
// ==================================================================
$message = '';
$search_results = [];
// Tên gợi nhớ của máy siêu âm đã cấu hình trong orthanc.json
$modality_name = 'MAYSIEUAM_01'; 


// ==================================================================
// XỬ LÝ CÁC HÀNH ĐỘNG GỬI LÊN TỪ FORM
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ----- HÀNH ĐỘNG: KIỂM TRA KẾT NỐI (C-ECHO) -----
    if ($_POST['action'] === 'test_connection') {
        $url = $orthanc . 'modalities/' . $modality_name . '/echo';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '{}'); // Gửi một body JSON rỗng
        curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
        curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            $message = "<div class='alert alert-success'>Kết nối thành công (C-ECHO OK) đến máy siêu âm '{$modality_name}'.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Kết nối thất bại! Hãy kiểm tra lại cấu hình trong orthanc.json và đảm bảo máy siêu âm đang hoạt động.</div>";
        }
        curl_close($curl);
    }

    // ----- HÀNH ĐỘNG: TÌM KIẾM CA CHỤP (C-FIND) -----
    if ($_POST['action'] === 'search_studies') {
        $query = [];
        if (!empty($_POST['PatientName'])) $query['PatientName'] = $_POST['PatientName'];
        if (!empty($_POST['PatientID'])) $query['PatientID'] = $_POST['PatientID'];
        if (!empty($_POST['StudyDate'])) $query['StudyDate'] = $_POST['StudyDate'];

        $postData = json_encode(['Level' => 'Study', 'Query' => $query]);

        $url = $orthanc . 'modalities/' . $modality_name . '/find';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            $search_results = json_decode($response, true);
            $message = "<div class='alert alert-info'>Tìm thấy " . count($search_results) . " kết quả.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Tìm kiếm thất bại. Máy siêu âm có thể không phản hồi hoặc không tìm thấy kết quả.</div>";
        }
        curl_close($curl);
    }

    // ----- HÀNH ĐỘNG: LẤY CA CHỤP VỀ (C-MOVE) -----
    if ($_POST['action'] === 'retrieve_study') {
        $study_uid = $_POST['study_uid'] ?? '';
        $postData = json_encode(['Level' => 'Study', 'Resources' => [['StudyInstanceUID' => $study_uid]]]);

        $url = $orthanc . 'modalities/' . $modality_name . '/move';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            $message = "<div class='alert alert-success'>Đã gửi yêu cầu lấy ca chụp thành công! Quá trình này diễn ra trong nền. Vui lòng kiểm tra trong <a href='dashboard.php' class='alert-link'>Bảng điều khiển</a> sau vài phút.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Có lỗi khi gửi yêu cầu. Phản hồi: " . htmlspecialchars($response) . "</div>";
        }
        curl_close($curl);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giao tiếp với Máy siêu âm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><strong>FETAL PACS</strong></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Tải lên</a></li>
                    <li class="nav-item"><a class="nav-link" href="ultrasound.php">Lấy ảnh từ máy siêu âm</a></li>
                </ul>
                <span class="navbar-text me-3 text-white">Chào, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Giao tiếp với Máy siêu âm (<?php echo $modality_name; ?>)</h1>
        
        <!-- Hiển thị thông báo -->
        <?php if ($message) echo $message; ?>

        <!-- KHỐI 1: KIỂM TRA KẾT NỐI -->
        <div class="card mb-4">
            <div class="card-header">Kiểm tra kết nối (C-ECHO)</div>
            <div class="card-body">
                <p>Nhấn nút này để kiểm tra xem Orthanc có thể "ping" được máy siêu âm hay không.</p>
                <form action="ultrasound.php" method="post">
                    <input type="hidden" name="action" value="test_connection">
                    <button type="submit" class="btn btn-secondary">Test kết nối</button>
                </form>
            </div>
        </div>

        <!-- KHỐI 2: TÌM KIẾM -->
        <div class="card mb-4">
            <div class="card-header">Tìm kiếm ca chụp trên máy siêu âm (C-FIND)</div>
            <div class="card-body">
                <form action="ultrasound.php" method="post">
                    <input type="hidden" name="action" value="search_studies">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Tên bệnh nhân</label><input type="text" name="PatientName" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Mã bệnh nhân</label><input type="text" name="PatientID" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Ngày chụp (YYYYMMDD)</label><input type="text" name="StudyDate" class="form-control"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Tìm kiếm</button></div>
                    </div>
                </form>
            </div>
        </div>

        <!-- KHỐI 3: KẾT QUẢ TÌM KIẾM VÀ LẤY VỀ -->
        <?php if (!empty($search_results)): ?>
        <div class="card">
            <div class="card-header">Kết quả tìm kiếm</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Tên bệnh nhân</th><th>Mã bệnh nhân</th><th>Mô tả ca chụp</th><th>Ngày chụp</th><th>Hành động</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach($search_results as $study): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($study['PatientName'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($study['PatientID'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($study['StudyDescription'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($study['StudyDate'] ?? 'N/A'); ?></td>
                                <td>
                                    <form action="ultrasound.php" method="post">
                                        <input type="hidden" name="action" value="retrieve_study">
                                        <input type="hidden" name="study_uid" value="<?php echo $study['StudyInstanceUID']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Lấy về Server</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>