<?php
// ==================================================================
// Xử lý tải lên file DICOM qua AJAX
// ==================================================================

// Bắt đầu session và kiểm tra đăng nhập
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Trả về lỗi nếu không được xác thực
    header('Content-Type: application/json');
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

// Bao gồm cấu hình và thông tin xác thực
include('config.php');
$auth_user = $_SESSION['username'];
$auth_pass = $_SESSION['password'];

// Chuẩn bị phản hồi mặc định
$response = [
    'status' => 'error',
    'message' => 'Không có file nào được tải lên.',
    'success_count' => 0,
    'total_files' => 0
];

if (isset($_FILES["dicomFiles"]) && !empty($_FILES["dicomFiles"]["name"][0])) {
    $success_count = 0;
    $error_messages = [];
    $total_files = count($_FILES["dicomFiles"]["name"]);
    $response['total_files'] = $total_files;

    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES["dicomFiles"]["error"][$i] == UPLOAD_ERR_OK) {
            $file_data = file_get_contents($_FILES["dicomFiles"]["tmp_name"][$i]);
            
            $curl = curl_init($orthanc . 'instances');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $file_data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/dicom']);
            curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ":" . $auth_pass);
            
            curl_exec($curl);
            
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($http_code == 200) {
                $success_count++;
            } else {
                $error_messages[] = "Lỗi khi tải lên file '" . htmlspecialchars($_FILES["dicomFiles"]["name"][$i]) . "' (Code: $http_code)";
            }
            curl_close($curl);
        } else {
             $error_messages[] = "Lỗi upload file '" . htmlspecialchars($_FILES["dicomFiles"]["name"][$i]) . "'";
        }
    }
    
    $response['success_count'] = $success_count;

    if ($success_count > 0) {
        $response['status'] = 'success';
        $response['message'] = "Đã tải lên thành công {$success_count}/{$total_files} file.";
        if (!empty($error_messages)) {
            $response['message'] .= " Chi tiết lỗi: " . implode(', ', $error_messages);
        }
    } else {
        $response['message'] = "Tất cả các file đều không tải lên được. Chi tiết: " . implode(', ', $error_messages);
    }

} else {
    $response['message'] = 'Vui lòng chọn file để tải lên.';
}

// Trả về kết quả dưới dạng JSON cho JavaScript xử lý
header('Content-Type: application/json');
echo json_encode($response);
exit;