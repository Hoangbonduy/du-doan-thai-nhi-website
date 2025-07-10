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
// *** SỬA ĐỔI TẠI ĐÂY NẾU CẦN ***
// ==================================================================
// Đảm bảo file 'config.php' tồn tại và chứa biến $orthanc
include('config.php'); 
$auth_user = $_SESSION['username'];
$auth_pass = $_SESSION['password'];

// ==================================================================
// Lấy Study ID và chuẩn bị các biến
// ==================================================================
$study_id = $_GET['study_id'] ?? null;
if (!$study_id) {
    die("Lỗi: Không có ID ca chụp được cung cấp. Vui lòng quay lại và thử lại.");
}

$message = '';
$all_images = []; // Mảng để lưu tất cả ảnh (id và dữ liệu base64)

// ==================================================================
// Hàm trợ giúp để gọi API Orthanc
// ==================================================================
function call_orthanc_api($url, $user, $pass) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // Tăng timeout cho các file lớn
    curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $pass);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ($http_code == 200) ? $response : null;
}

// ==================================================================
// CHỈ LẤY DỮ LIỆU KHI LÀ REQUEST GET (để tránh lấy lại ảnh sau khi submit)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 1. Lấy danh sách series trong study
    $series_list_json = call_orthanc_api($orthanc . 'studies/' . $study_id . '/series', $auth_user, $auth_pass);
    $series_list = $series_list_json ? json_decode($series_list_json, true) : [];

    if (empty($series_list)) {
        $message = "<div class='alert alert-warning'>Ca chụp này không chứa series nào.</div>";
    } else {
        // 2. Lặp qua từng series để lấy instances
        foreach ($series_list as $series) {
            $series_id = $series['ID'];
            
            // 3. Lấy danh sách các ảnh (instances) trong series
            $instances_list_json = call_orthanc_api($orthanc . 'series/' . $series_id . '/instances', $auth_user, $auth_pass);
            $instances_list = $instances_list_json ? json_decode($instances_list_json, true) : [];

            // 4. Lặp qua từng instance để tạo ảnh preview
            if (!empty($instances_list)) {
                foreach ($instances_list as $instance) {
                    $instance_id = $instance['ID'];
                    
                    // 5. Lấy dữ liệu ảnh preview từ Orthanc
                    $image_data = call_orthanc_api($orthanc . 'instances/' . $instance_id . '/preview', $auth_user, $auth_pass);
                    
                    if ($image_data) {
                        // 6. Chuyển đổi sang Base64 và thêm vào mảng
                        $all_images[] = [
                            'id' => $instance_id,
                            'src' => 'data:image/png;base64,' . base64_encode($image_data)
                        ];
                    }
                }
            }
        }
        if (empty($all_images)) {
             $message = "<div class='alert alert-warning'>Không tìm thấy ảnh nào có thể hiển thị trong ca chụp này.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Dự đoán ảnh DICOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-gallery .img-container { position: relative; margin-bottom: 1rem; }
        .image-gallery .form-check { margin-top: 8px; }
        .img-thumbnail { border: 2px solid transparent; transition: border-color .2s ease-in-out; cursor: pointer; }
        .form-check-input:checked + .img-thumbnail { border-color: #0d6efd; box-shadow: 0 0 10px rgba(13, 110, 253, 0.5); }
        .spinner-border { vertical-align: middle; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><strong>FETAL PACS</strong></a>
            <a href="dashboard.php" class="btn btn-light">Quay lại Bảng điều khiển</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card">
                    <div class="card-header">
                        <h4>Chọn ảnh để dự đoán</h4>
                    </div>
                    <div class="card-body">
                        <!-- Hiển thị thông báo nếu có lỗi -->
                        <?php if ($message) echo $message; ?>

                        <!-- Form bao bọc toàn bộ gallery và nút submit -->
                        <form action="predict.php?study_id=<?php echo htmlspecialchars($study_id); ?>" method="post">
                            
                            <!-- Hiển thị bộ sưu tập ảnh nếu không phải là request POST -->
                            <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($all_images)): ?>
                                <p class="text-muted">Chọn một hoặc nhiều ảnh bạn cho là phù hợp nhất, sau đó nhấn nút "Bắt đầu Dự đoán".</p>
                                
                                <div class="row image-gallery">
                                    <?php foreach ($all_images as $image): ?>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="img-container text-center">
                                                <label class="form-check-label d-block">
                                                    <input type="checkbox" class="form-check-input visually-hidden" name="instance_ids[]" value="<?php echo htmlspecialchars($image['id']); ?>">
                                                    <img src="<?php echo $image['src']; ?>" class="img-thumbnail" alt="DICOM Preview <?php echo htmlspecialchars($image['id']); ?>">
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-4 text-center">
                                    <button type="submit" class="btn btn-lg btn-success">Bắt đầu Dự đoán cho các ảnh đã chọn</button>
                                </div>
                            <?php endif; ?>

                            <!-- Khu vực để hiển thị kết quả dự đoán (chỉ hoạt động khi là POST request) -->
                            <div id="prediction-result" class="mt-4">
                                <?php
                                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                    echo '<div class="alert alert-info"><strong>CẢNH BÁO:</strong> Quá trình dự đoán có thể mất nhiều thời gian. Vui lòng không tắt hoặc tải lại trang.</div>';

                                    if (isset($_POST['instance_ids']) && is_array($_POST['instance_ids']) && !empty($_POST['instance_ids'])) {
                                        $selected_ids = $_POST['instance_ids'];
                                        echo "<h4 class='mb-3'>Kết quả dự đoán:</h4>";

                                        // ===================================================================
                                        // CẤU HÌNH ĐƯỜNG DẪN VÀ THAM SỐ MODEL
                                        // *** SỬA ĐỔI TẠI ĐÂY ***
                                        // ===================================================================
                                        // Sử dụng __DIR__ để có đường dẫn tuyệt đối đến thư mục hiện tại, an toàn hơn
                                        $base_dir = __DIR__; 
                                        $tmp_dir = $base_dir . '/tmp/';
                                        
                                        $input_dir = $tmp_dir . 'input/';
                                        $nii_dir = $tmp_dir . 'nii/';
                                        $output_dir = $tmp_dir . 'output/';
                                        $visual_dir = $tmp_dir . 'visuals/';
                                        $visual_dir_web = 'tmp/visuals/'; // Đường dẫn web tương đối

                                        // Đường dẫn đến script Python để visualize
                                        $path_to_visualizer = $base_dir . '/visualize_result.py';
                                        
                                        // Tham số cho model nnU-Net của bạn
                                        $model_params = "-d 110 -c 2d -f 0 -tr nnUNetTrainer_100epochs -p nnUNetPlans --disable_tta";

                                        // Tạo các thư mục tạm nếu chưa có
                                        if (!is_dir($input_dir)) mkdir($input_dir, 0777, true);
                                        if (!is_dir($nii_dir)) mkdir($nii_dir, 0777, true);
                                        if (!is_dir($output_dir)) mkdir($output_dir, 0777, true);
                                        if (!is_dir($visual_dir)) mkdir($visual_dir, 0777, true);
                                        // ===================================================================

                                        foreach ($selected_ids as $instance_to_predict) {
                                            $instance_safe_id = htmlspecialchars($instance_to_predict);
                                            $unique_prefix = $instance_safe_id . '_' . time();
                                            
                                            echo "<div class='card mb-3'><div class='card-header'>Kết quả cho ảnh ID: {$instance_safe_id}</div><div class='card-body'>";
                                            
                                            // 1. LẤY FILE DICOM GỐC TỪ ORTHANC
                                            echo "<p>1. Đang tải file DICOM... <span class='spinner-border spinner-border-sm'></span></p>";
                                            flush(); @ob_flush(); // Hiển thị ngay lập tức
                                            $dicom_data = call_orthanc_api($orthanc . 'instances/' . $instance_safe_id . '/file', $auth_user, $auth_pass);
                                            if (!$dicom_data) {
                                                echo "<div class='alert alert-danger'>Lỗi: Không thể tải file DICOM.</div></div></div>";
                                                continue;
                                            }
                                            $dicom_instance_dir = $input_dir . $unique_prefix . '/';
                                            if (!is_dir($dicom_instance_dir)) mkdir($dicom_instance_dir, 0777, true);
                                            $dicom_path = $dicom_instance_dir . 'image.dcm';
                                            file_put_contents($dicom_path, $dicom_data);

                                            // 2. CHUYỂN ĐỔI DICOM -> NIFTI
                                            echo "<p>2. Đang chuyển đổi sang định dạng NIfTI... <span class='spinner-border spinner-border-sm'></span></p>";
                                            flush(); @ob_flush();
                                            $nii_filename_base = $unique_prefix . '_0000';
                                            $cmd_dcm2niix = sprintf(
                                                'dcm2niix.exe -o "%s" -f "%s" -z y "%s"',
                                                rtrim($nii_dir, '\\/'),      // Đường dẫn thư mục output
                                                $nii_filename_base,         // Tên file output (không cần ngoặc kép)
                                                rtrim($dicom_instance_dir, '\\/') // Đường dẫn thư mục input
                                            );
                                            // Sửa thành dòng này để bắt log
$                                           $dcm2niix_log = shell_exec($cmd_dcm2niix . ' 2>&1');

                                            $original_nii_path = $nii_dir . $nii_filename_base . '.nii.gz';
                                            if (!file_exists($original_nii_path)) {
                                                echo "<div class='alert alert-danger'><strong>Lỗi: Không thể chuyển đổi sang NIfTI.</strong><br>Log chi tiết từ dcm2niix:<pre>{$dcm2niix_log}</pre></div></div></div>";
                                                continue;
                                            }

                                            // 3. GỌI nnUNetV2 ĐỂ DỰ ĐOÁN
                                            echo "<p>3. Model AI đang xử lý... (bước này có thể mất vài phút) <span class='spinner-border spinner-border-sm'></span></p>";
                                            flush(); @ob_flush();
                                            $cmd_predict = "python -m nnunetv2.predict -i " . escapeshellarg($nii_dir) . " -o " . escapeshellarg($output_dir) . " " . $model_params;
                                            $predict_output_log = shell_exec($cmd_predict . ' 2>&1');
                                            $predicted_mask_path = $output_dir . $nii_filename_base . '.nii.gz';
                                            if (!file_exists($predicted_mask_path)) {
                                                echo "<div class='alert alert-danger'>Lỗi: Model AI thất bại. Log: <pre>{$predict_output_log}</pre></div></div></div>";
                                                continue;
                                            }

                                            // 4. TẠO ẢNH VISUALIZE
                                            echo "<p>4. Đang tạo ảnh kết quả... <span class='spinner-border spinner-border-sm'></span></p>";
                                            flush(); @ob_flush();
                                            $visual_png_path = $visual_dir . $unique_prefix . '.png';
                                            $cmd_visualize = "python " . escapeshellarg($path_to_visualizer) . " " . escapeshellarg($original_nii_path) . " " . escapeshellarg($predicted_mask_path) . " " . escapeshellarg($visual_png_path);
                                            shell_exec($cmd_visualize);

                                            // 5. HIỂN THỊ KẾT QUẢ
                                            if (file_exists($visual_png_path)) {
                                                $original_preview_src = 'data:image/png;base64,' . base64_encode(call_orthanc_api($orthanc . 'instances/' . $instance_safe_id . '/preview', $auth_user, $auth_pass));
                                                $web_path_to_visual = $visual_dir_web . $unique_prefix . '.png';
                                                
                                                echo "<div class='row mt-3'>";
                                                echo "  <div class='col-md-6 text-center'><h6>Ảnh Gốc</h6><img src='{$original_preview_src}' class='img-fluid border rounded'></div>";
                                                echo "  <div class='col-md-6 text-center'><h6>Kết quả Dự đoán</h6><img src='{$web_path_to_visual}?t=".time()."' class='img-fluid border rounded'></div>";
                                                echo "</div>";
                                            } else {
                                                echo "<div class='alert alert-danger'>Lỗi: Không thể tạo ảnh kết quả.</div>";
                                            }
                                            
                                            // 6. DỌN DẸP FILE TẠM
                                            unlink($dicom_path);
                                            rmdir($dicom_instance_dir);
                                            unlink($original_nii_path);
                                            unlink($predicted_mask_path);
                                            // Giữ lại file PNG kết quả để hiển thị

                                            echo "</div></div>"; // Đóng card-body và card
                                        }

                                        // Thêm nút để quay lại chọn ảnh
                                        echo '<div class="mt-4 text-center"><a href="predict.php?study_id='.htmlspecialchars($study_id).'" class="btn btn-primary">Dự đoán ảnh khác trong ca chụp này</a></div>';

                                    } else {
                                        echo "<div class='alert alert-warning'>Bạn chưa chọn ảnh nào để dự đoán.</div>";
                                        echo '<div class="mt-4 text-center"><a href="predict.php?study_id='.htmlspecialchars($study_id).'" class="btn btn-primary">Quay lại chọn ảnh</a></div>';
                                    }
                                }
                                ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>