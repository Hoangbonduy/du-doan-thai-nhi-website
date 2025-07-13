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

/**
 * Tải file từ Orthanc và ghi trực tiếp vào đĩa để tiết kiệm bộ nhớ.
 * @param string $url URL của file trên Orthanc.
 * @param string $user Tên người dùng Orthanc.
 * @param string $pass Mật khẩu Orthanc.
 * @param string $destination_path Đường dẫn đầy đủ để lưu file.
 * @return bool Trả về true nếu thành công, false nếu thất bại.
 */
function stream_orthanc_file_to_disk($url, $user, $pass, $destination_path) {
    // Mở một file trên đĩa ở chế độ ghi nhị phân (binary mode 'wb')
    $file_handle = fopen($destination_path, 'wb');
    if (!$file_handle) {
        return false; // Không thể tạo file để ghi
    }

    $curl = curl_init($url);
    // Yêu cầu cURL ghi output vào file handle đã mở
    curl_setopt($curl, CURLOPT_FILE, $file_handle);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 300); // Tăng timeout cho các file lớn có thể tải lâu
    curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $pass);
    
    // Thực thi, dữ liệu sẽ được stream vào file
    curl_exec($curl);
    
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    fclose($file_handle); // Luôn đóng file handle

    // Kiểm tra xem việc tải có thành công không
    if ($http_code != 200) {
        // Nếu tải lỗi, xóa file rỗng hoặc file chưa hoàn chỉnh đi
        if (file_exists($destination_path)) {
            unlink($destination_path);
        }
        return false;
    }

    return true;
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

                            <div id="prediction-result" class="mt-4">
                                <?php
                                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                    echo '<div class="alert alert-info"><strong>CẢNH BÁO:</strong> Quá trình dự đoán có thể mất nhiều thời gian. Vui lòng không tắt hoặc tải lại trang.</div>';
                                    flush(); @ob_flush();

                                    if (isset($_POST['instance_ids']) && is_array($_POST['instance_ids']) && !empty($_POST['instance_ids'])) {
                                        $selected_ids = $_POST['instance_ids'];
                                        echo "<h4 class='mb-3'>Kết quả dự đoán:</h4>";

                                        // =========================================================================
                                        // --- CẤU HÌNH ĐƯỜNG DẪN ---
                                        // =========================================================================
                                        $base_dir = __DIR__;
                                        $DS = DIRECTORY_SEPARATOR; 
                                        $tmp_dir = $base_dir . $DS . 'tmp' . $DS;
                                        
                                        $input_dicom_dir = $tmp_dir . 'input_dicom' . $DS;
                                        $input_png_dir   = $tmp_dir . 'input_png' . $DS;
                                        // Thư mục này sẽ chứa mask thô từ nnU-Net
                                        $output_mask_dir = $tmp_dir . 'output_mask' . $DS;
                                        // Thư mục này sẽ chứa ảnh kết quả cuối cùng đã được vẽ contour
                                        $visual_dir      = $tmp_dir . 'visuals' . $DS;
                                        
                                        // Đường dẫn web tương đối để thẻ <img> có thể truy cập
                                        $visual_dir_web = 'tmp/visuals/';

                                        // --- CẤU HÌNH SCRIPT VÀ MODEL ---
                                        // Đường dẫn đến các script Python của bạn
                                        $path_to_dcm_converter = $base_dir . $DS . 'dcm_to_png_first_frame.py'; // Script chỉ xuất frame đầu tiên
                                        $path_to_predictor     = $base_dir . $DS . 'run_predict.py';          // Script dự đoán và visualize mới

                                        // Đường dẫn đến thư mục chứa model nnU-Net của bạn
                                        // *** SỬA ĐỔI ĐƯỜNG DẪN NÀY CHO ĐÚNG VỚI MÁY CỦA BẠN ***
                                        $nnunet_dataset_path = 'D:' . $DS . 'Study' . $DS . 'Thai_nhi' . $DS . 'fetal-PCS' . $DS . 'model' . $DS. 'Dataset110_HC' . $DS;

                                        // Tạo các thư mục tạm nếu chưa có
                                        foreach ([$input_dicom_dir, $input_png_dir, $output_mask_dir, $visual_dir] as $dir) {
                                            if (!is_dir($dir)) mkdir($dir, 0777, true);
                                        }

                                        foreach ($selected_ids as $instance_to_predict) {
                                            $instance_safe_id = htmlspecialchars($instance_to_predict);
                                            
                                            // Sử dụng prefix không có time() để có thể cache kết quả
                                            $caching_prefix = 'dcm_' . preg_replace('/[^a-zA-Z0-9]/', '_', $instance_safe_id);

                                            echo "<div class='card mb-3'><div class='card-header'>Kết quả cho ảnh ID: <strong>{$instance_safe_id}</strong></div><div class='card-body'>";
                                            flush(); @ob_flush();

                                            // =========================================================================
                                            // === BƯỚC 1: TẢI FILE DICOM (VỚI CACHING) ===
                                            // =========================================================================
                                            $dicom_path = $input_dicom_dir . $caching_prefix . '.dcm';
                                            echo "<p>1. Chuẩn bị file DICOM... ";
                                            if (file_exists($dicom_path)) {
                                                echo "<span class='text-success'>✓ Đã tìm thấy file đã tải.</span></p>";
                                            } else {
                                                echo "<span class='spinner-border spinner-border-sm'></span> Đang tải...</p>";
                                                $success = stream_orthanc_file_to_disk($orthanc . 'instances/' . $instance_safe_id . '/file', $auth_user, $auth_pass, $dicom_path);
                                                if (!$success || !file_exists($dicom_path)) {
                                                    echo "<div class='alert alert-danger'>Lỗi: Không thể tải file DICOM.</div></div></div>";
                                                    continue;
                                                }
                                            }
                                            flush(); @ob_flush();

                                            // =========================================================================
                                            // === BƯỚC 2: CHUYỂN ĐỔI DICOM -> PNG (VỚI CACHING) ===
                                            // =========================================================================
                                            $input_png_path = $input_png_dir . $caching_prefix . '.png';
                                            echo "<p>2. Chuẩn bị ảnh PNG... ";
                                            if (file_exists($input_png_path)) {
                                                echo "<span class='text-success'>✓ Đã tìm thấy file đã chuyển đổi.</span></p>";
                                            } else {
                                                echo "<span class='spinner-border spinner-border-sm'></span> Đang chuyển đổi...</p>";
                                                $cmd_convert = sprintf('chcp 65001 > nul && python %s -i %s -o %s',
                                                    escapeshellarg($path_to_dcm_converter),
                                                    escapeshellarg($dicom_path),
                                                    escapeshellarg($input_png_path)
                                                );
                                                $convert_log = shell_exec($cmd_convert . ' 2>&1');
                                                if (!file_exists($input_png_path)) {
                                                    echo "<div class='alert alert-danger'><strong>Lỗi: Không thể chuyển đổi sang PNG.</strong><br><pre>{$convert_log}</pre></div></div></div>";
                                                    continue;
                                                }
                                            }
                                            flush(); @ob_flush();

                                            // =========================================================================
                                            // === BƯỚC 3: DỰ ĐOÁN, PHÂN TÍCH VÀ VISUALIZE (GỌI run_predict.py) ===
                                            // =========================================================================
                                            $final_result_path = $visual_dir . $caching_prefix . '_result.png';
                                            echo "<p>3. Model AI đang phân tích và đo đạc... ";

                                            // --- KIỂM TRA XEM FILE KẾT QUẢ CUỐI CÙNG ĐÃ TỒN TẠI CHƯA (CACHE) ---
                                            if (file_exists($final_result_path)) {
                                                echo "<span class='text-success'>✓ Đã tìm thấy kết quả đã xử lý trước đó.</span></p>";
                                            } else {
                                                echo "<span class='spinner-border spinner-border-sm'></span> (Bước này có thể mất vài phút)...</p>";
                                                flush(); @ob_flush();

                                                // Xây dựng câu lệnh để gọi script run_predict.py
                                                $cmd_predict = sprintf(
                                                    'python %s -d %s -id %s -od %s -ifd %s -ofd %s',
                                                    escapeshellarg($path_to_predictor),
                                                    escapeshellarg($nnunet_dataset_path),
                                                    escapeshellarg($input_png_dir),       // Thư mục chứa tất cả ảnh PNG input
                                                    escapeshellarg($output_mask_dir),     // Thư mục để lưu mask thô
                                                    escapeshellarg($input_png_path),      // File PNG cụ thể cần dự đoán
                                                    escapeshellarg($final_result_path)    // File kết quả cuối cùng
                                                );

                                                // DEBUG: In câu lệnh ra để kiểm tra
                                                // echo "<h6>Lệnh dự đoán sẽ thực thi:</h6>";
                                                // echo "<pre class='bg-light p-2 rounded small text-muted'>" . htmlspecialchars($cmd_predict) . "</pre>";
                                                flush(); @ob_flush();

                                                // Thực thi lệnh và bắt log
                                                $predict_log = shell_exec($cmd_predict . ' 2>&1');

                                                // Kiểm tra lại sau khi chạy
                                                if (!file_exists($final_result_path)) {
                                                    echo "<div class='alert alert-danger'>
                                                            <strong>Lỗi: Model AI dự đoán hoặc xử lý thất bại.</strong>
                                                            <br>Log:<pre>{$predict_log}</pre>
                                                        </div></div></div>";
                                                    continue;
                                                }
                                            }
                                            flush(); @ob_flush();
                                            
                                            // =========================================================================
                                            // === BƯỚC 4: HIỂN THỊ KẾT QUẢ ===
                                            // =========================================================================
                                            if (file_exists($final_result_path)) {
                                                // Lấy ảnh PNG gốc để so sánh
                                                $original_png_src = 'data:image/png;base64,' . base64_encode(file_get_contents($input_png_path));
                                                
                                                // Đường dẫn web đến ảnh kết quả
                                                $web_path_to_visual = $visual_dir_web . basename($final_result_path);
                                                
                                                echo "<div class='row mt-3'>";
                                                echo "  <div class='col-md-6 text-center'><h6>Ảnh Gốc (PNG)</h6><img src='{$original_png_src}' class='img-fluid border rounded'></div>";
                                                echo "  <div class='col-md-6 text-center'><h6>Kết quả Đo đạc</h6><img src='{$web_path_to_visual}?t=".time()."' class='img-fluid border rounded'></div>";
                                                echo "</div>";
                                            } else {
                                                // Trường hợp này hiếm khi xảy ra nếu logic ở trên đúng
                                                echo "<div class='alert alert-danger'>Lỗi: Không tìm thấy file kết quả để hiển thị.</div>";
                                            }
                                            
                                            // Đóng card
                                            echo "</div></div>";
                                        }

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