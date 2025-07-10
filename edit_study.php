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
// Khai báo biến và lấy Study ID từ URL
// ==================================================================
$message = '';
$study_id = $_GET['study_id'] ?? null;
$study_data = null;
$patient_data = null;

if (!$study_id) {
    die("Lỗi: Không có ID ca chụp được cung cấp. Vui lòng quay lại trang dashboard.");
}

// ==================================================================
// XỬ LÝ KHI NGƯỜI DÙNG LƯU THAY ĐỔI (POST REQUEST)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_study_id = $_GET['study_id']; // ID của ca chụp cũ cần xóa
    
    // Lấy dữ liệu mới từ form
    $new_patient_name = $_POST['PatientName'] ?? '';
    $new_patient_id = $_POST['PatientID'] ?? '';
    $new_study_desc = $_POST['StudyDescription'] ?? '';
    $new_study_date = $_POST['StudyDate'] ?? '';

    // --- BƯỚC 1: TẠO BẢN SAO VỚI THÔNG TIN MỚI (ANONYMIZE) ---
    $anonymize_payload = [
        'Replace' => [
            'PatientName' => $new_patient_name,
            'PatientID' => $new_patient_id,
            'StudyDescription' => $new_study_desc,
            'StudyDate' => $new_study_date
            // Bạn có thể thêm các tag khác cần sửa ở đây
        ],
        // "Keep" sẽ giữ lại các tag không được chỉ định trong Replace
        "Keep" => ["SeriesInstanceUID", "SOPInstanceUID"],
        "Force" => true
    ];
    $anonymize_postData = json_encode($anonymize_payload, JSON_UNESCAPED_UNICODE);

    $url_anonymize = $orthanc . 'studies/' . $original_study_id . '/anonymize';
    $curl_anonymize = curl_init($url_anonymize);
    curl_setopt($curl_anonymize, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_anonymize, CURLOPT_POST, true);
    curl_setopt($curl_anonymize, CURLOPT_POSTFIELDS, $anonymize_postData);
    curl_setopt($curl_anonymize, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($curl_anonymize, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
    
    $response_anonymize = curl_exec($curl_anonymize);
    $http_code_anonymize = curl_getinfo($curl_anonymize, CURLINFO_HTTP_CODE);
    curl_close($curl_anonymize);

    // Kiểm tra xem bước 1 có thành công không
    if ($http_code_anonymize == 200) {
        $new_study_data = json_decode($response_anonymize, true);
        $new_study_id = $new_study_data['ID'] ?? null;

        if ($new_study_id) {
            // --- BƯỚC 2: XÓA CA CHỤP GỐC ---
            $url_delete = $orthanc . 'studies/' . $original_study_id;
            $curl_delete = curl_init($url_delete);
            curl_setopt($curl_delete, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_delete, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl_delete, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
            curl_exec($curl_delete);
            $http_code_delete = curl_getinfo($curl_delete, CURLINFO_HTTP_CODE);
            curl_close($curl_delete);

            if ($http_code_delete == 200) {
                // Tất cả thành công, chuyển hướng về dashboard để thấy kết quả
                // Thêm một tham số vào URL để dashboard có thể hiển thị thông báo
                header('Location: dashboard.php?status=edited');
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Tạo bản sao mới thành công, nhưng không thể xóa ca chụp gốc. Mã lỗi: {$http_code_delete}. Vui lòng xóa thủ công.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Tạo bản sao mới thành công, nhưng không nhận được ID mới từ Orthanc.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Có lỗi khi tạo bản sao với thông tin mới. Mã lỗi: {$http_code_anonymize}<br>Phản hồi: " . htmlspecialchars($response_anonymize) . "</div>";
    }
}

// ==================================================================
// LẤY DỮ LIỆU HIỆN TẠI ĐỂ ĐIỀN VÀO FORM (LUÔN CHẠY)
// ==================================================================
// Lấy thông tin ca chụp
$url = $orthanc . 'studies/' . $study_id;
$curl_get_study = curl_init($url);
curl_setopt($curl_get_study, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_get_study, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
$response_study = curl_exec($curl_get_study);
curl_close($curl_get_study);
$study_data = json_decode($response_study, true);

// Nếu không tìm thấy study, dừng lại
if (!$study_data) {
     die("Lỗi: Không tìm thấy thông tin ca chụp với ID này.");
}

// Lấy thông tin bệnh nhân tương ứng
$patient_url = $orthanc . 'patients/' . $study_data['ParentPatient'];
$curl_patient = curl_init($patient_url);
curl_setopt($curl_patient, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_patient, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
$response_patient = curl_exec($curl_patient);
curl_close($curl_patient);
$patient_data = json_decode($response_patient, true);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa thông tin ca chụp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-warning shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 text-dark"><strong>CHỈNH SỬA THÔNG TIN</strong></span>
            <a href="dashboard.php" class="btn btn-light">Quay lại Bảng điều khiển</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Ca chụp của bệnh nhân: <?php echo htmlspecialchars($patient_data['MainDicomTags']['PatientName'] ?? 'N/A'); ?></h4>
                    </div>
                    <div class="card-body">
                        <!-- Hiển thị thông báo thành công hoặc thất bại -->
                        <?php if ($message) echo $message; ?>

                        <form action="edit_study.php?study_id=<?php echo htmlspecialchars($study_id); ?>" method="post">
                            <h5 class="mt-2">Thông tin Bệnh nhân</h5>
                            <hr>
                            <div class="mb-3">
                                <label for="PatientName" class="form-label">Tên bệnh nhân</label>
                                <input type="text" class="form-control" id="PatientName" name="PatientName" value="<?php echo htmlspecialchars($patient_data['MainDicomTags']['PatientName'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="PatientID" class="form-label">Mã bệnh nhân</label>
                                <input type="text" class="form-control" id="PatientID" name="PatientID" value="<?php echo htmlspecialchars($patient_data['MainDicomTags']['PatientID'] ?? ''); ?>">
                            </div>
                            
                            <h5 class="mt-4">Thông tin Ca chụp</h5>
                            <hr>
                            <div class="mb-3">
                                <label for="StudyDescription" class="form-label">Mô tả ca chụp</label>
                                <input type="text" class="form-control" id="StudyDescription" name="StudyDescription" value="<?php echo htmlspecialchars($study_data['MainDicomTags']['StudyDescription'] ?? ''); ?>">
                            </div>
                             <div class="mb-3">
                                <label for="StudyDate" class="form-label">Ngày chụp (Định dạng: YYYYMMDD)</label>
                                <input type="text" class="form-control" id="StudyDate" name="StudyDate" value="<?php echo htmlspecialchars($study_data['MainDicomTags']['StudyDate'] ?? ''); ?>" pattern="[0-9]{8}" title="Vui lòng nhập 8 chữ số, ví dụ: 20240715">
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-success">Lưu thay đổi</button>
                                <a href="dashboard.php" class="btn btn-secondary">Hủy</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>