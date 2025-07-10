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
// Khai báo biến thông báo
// ==================================================================
$message = '';

// ==================================================================
// BƯỚC 1: XỬ LÝ CÁC HÀNH ĐỘNG POST (CHỈ CÒN LẠI HÀNH ĐỘNG XÓA)
// ==================================================================

// ---- XỬ LÝ HÀNH ĐỘNG XÓA ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_study') {
    $study_id_to_delete = $_POST['study_id_to_delete'] ?? null;
    if ($study_id_to_delete) {
        $url = $orthanc . 'studies/' . $study_id_to_delete;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
        curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            $message = "<div class='alert alert-success'>Đã xóa ca chụp thành công.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Có lỗi khi xóa ca chụp.</div>";
        }
        curl_close($curl);
    }
}

// ---- LOGIC TẢI LÊN ĐÃ ĐƯỢC CHUYỂN HOÀN TOÀN SANG upload_handler.php ----

// ==================================================================
// BƯỚC 2: LẤY DỮ LIỆU ĐỂ HIỂN THỊ
// ==================================================================
$all_studies = [];
$postData = json_encode([
    'Level' => 'Study',
    'Query' => new stdClass(),
    'Expand' => true
], JSON_UNESCAPED_UNICODE);

$curl_find = curl_init($orthanc . 'tools/find');
curl_setopt($curl_find, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_find, CURLOPT_POST, true);
curl_setopt($curl_find, CURLOPT_POSTFIELDS, $postData);
curl_setopt($curl_find, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
curl_setopt($curl_find, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
$response = curl_exec($curl_find);
if (curl_getinfo($curl_find, CURLINFO_HTTP_CODE) == 200) {
    $all_studies = json_decode($response, true);
}
curl_close($curl_find);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng điều khiển - PACS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .action-buttons .btn, .action-buttons .form-control { margin-right: 5px; margin-bottom: 5px; }
        /* Thêm hiệu ứng chuyển động mượt mà cho progress bar */
        .progress-bar { transition: width .4s ease; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><strong>FETAL PACS</strong></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Bảng điều khiển</a></li>
                    <li class="nav-item"><a class="nav-link" href="ultrasound.php">Máy siêu âm</a></li>
                </ul>
                <span class="navbar-text me-3 text-white">Chào, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <!-- Nội dung chính -->
    <div class="container-fluid mt-4">
        <!-- Vùng hiển thị thông báo động -->
        <div id="status-message">
             <?php if ($message) echo $message; // Hiển thị thông báo từ PHP (ví dụ: xóa thành công) ?>
        </div>
       
        <div class="row">
            <!-- CỘT BÊN TRÁI: UPLOAD -->
            <div class="col-lg-4 col-xl-3">
                <div class="card">
                    <div class="card-header bg-light"><strong>Tải file DICOM</strong></div>
                    <div class="card-body">
                        <!-- Form đã được sửa, không cần action/method, thêm id -->
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="dicomFiles" class="form-label small">Chọn file (.dcm):</label>
                                <input class="form-control" type="file" id="dicomFiles" name="dicomFiles[]" multiple required>
                            </div>
                            <!-- Nút submit sẽ được JavaScript lắng nghe sự kiện -->
                            <div class="d-grid"><button type="submit" class="btn btn-primary" id="uploadButton">Tải lên</button></div>
                        </form>
                        
                        <!-- THANH PROGRESS BAR MỚI -->
                        <div id="upload-progress-container" class="mt-3" style="display: none;">
                             <div class="progress">
                                <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CỘT BÊN PHẢI: DANH SÁCH CA CHỤP -->
            <div class="col-lg-8 col-xl-9">
                <div class="card">
                     <div class="card-header bg-light"><strong>Danh sách ca chụp trong hệ thống</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Tên bệnh nhân</th>
                                        <th>Mã bệnh nhân</th>
                                        <th>Mô tả ca chụp</th>
                                        <th>Ngày chụp</th>
                                        <th style="min-width: 280px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($all_studies)): ?>
                                    <?php foreach ($all_studies as $study): 
                                        $patient = $study['PatientMainDicomTags'];
                                    ?>
                                        <tr>
                                            <td class="ps-3"><?php echo htmlspecialchars($patient['PatientName'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($patient['PatientID'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($study['MainDicomTags']['StudyDescription'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($study['MainDicomTags']['StudyDate'] ?? 'N/A'); ?></td>
                                            <td class="action-buttons">
                                                <a href="<?php echo $orthanc . 'stone-webviewer/index.html?study=' . ($study['MainDicomTags']['StudyInstanceUID'] ?? ''); ?>" class="btn btn-info btn-sm" target="_blank" title="Xem ảnh">Xem</a>
                                                <a href="predict.php?study_id=<?php echo $study['ID']; ?>" class="btn btn-success btn-sm" title="Dự đoán ảnh">Dự đoán</a>
                                                <a href="edit_study.php?study_id=<?php echo $study['ID']; ?>" class="btn btn-warning btn-sm" title="Sửa thông tin">Sửa</a>
                                                <form action="dashboard.php" method="post" class="d-inline-block">
                                                    <input type="hidden" name="action" value="delete_study">
                                                    <input type="hidden" name="study_id_to_delete" value="<?php echo $study['ID']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn ca chụp này không?');">Xóa</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center p-4">Chưa có ca chụp nào trong hệ thống.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- *** ĐOẠN JAVASCRIPT XỬ LÝ UPLOAD VÀ PROGRESS BAR *** -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadForm = document.getElementById('uploadForm');
        const uploadButton = document.getElementById('uploadButton');
        const fileInput = document.getElementById('dicomFiles');
        const progressContainer = document.getElementById('upload-progress-container');
        const progressBar = document.getElementById('upload-progress-bar');
        const statusMessage = document.getElementById('status-message');

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Ngăn form gửi đi theo cách truyền thống

            if (fileInput.files.length === 0) {
                statusMessage.innerHTML = `<div class='alert alert-warning'>Vui lòng chọn file để tải lên.</div>`;
                return;
            }

            // Vô hiệu hóa nút bấm và hiển thị thanh progress
            uploadButton.disabled = true;
            uploadButton.innerHTML = 'Đang tải lên...';
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            statusMessage.innerHTML = ''; // Xóa thông báo cũ

            const formData = new FormData();
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('dicomFiles[]', fileInput.files[i]);
            }
            
            const xhr = new XMLHttpRequest();

            // Theo dõi tiến trình tải lên
            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            });

            // Xử lý khi tải lên hoàn tất
            xhr.addEventListener('load', function() {
                // Kích hoạt lại nút bấm
                uploadButton.disabled = false;
                uploadButton.innerHTML = 'Tải lên';
                
                // Ẩn thanh progress sau 2 giây
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                }, 2000);

                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    let alertClass = response.status === 'success' ? 'alert-success' : 'alert-danger';
                    statusMessage.innerHTML = `<div class='alert ${alertClass}'>${response.message}</div>`;
                    
                    // Nếu thành công, tải lại trang để cập nhật danh sách
                    if(response.status === 'success') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500); // Chờ 1.5 giây để người dùng đọc thông báo
                    }
                } else {
                    statusMessage.innerHTML = `<div class='alert alert-danger'>Lỗi server: ${xhr.statusText}. Vui lòng thử lại.</div>`;
                }
            });

            // Xử lý khi có lỗi mạng
            xhr.addEventListener('error', function() {
                uploadButton.disabled = false;
                uploadButton.innerHTML = 'Tải lên';
                progressContainer.style.display = 'none';
                statusMessage.innerHTML = `<div class='alert alert-danger'>Lỗi kết nối mạng. Không thể tải file lên.</div>`;
            });
            
            // Bắt đầu gửi yêu cầu
            xhr.open('POST', 'upload_handler.php', true);
            xhr.send(formData);
        });
    });
    </script>

</body>
</html>