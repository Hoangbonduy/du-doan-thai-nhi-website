<?php
// Bao gồm file cấu hình
include('config.php');

echo "<h1>Kiểm tra kết nối tới Orthanc Server</h1>";
echo "<p>Đang cố gắng kết nối tới: <strong>" . htmlspecialchars($orthanc) . "</strong></p>";
echo "<hr>";

// Xây dựng URL để lấy thông tin hệ thống của Orthanc
$url = $orthanc . 'system';

// Khởi tạo cURL
$curl = curl_init();

// Thiết lập các tùy chọn cho cURL
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Trả về kết quả dưới dạng chuỗi thay vì in ra
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);    // Timeout kết nối sau 5 giây
curl_setopt($curl, CURLOPT_TIMEOUT, 10);          // Timeout toàn bộ request sau 10 giây

// Thực thi request
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Lấy mã trạng thái HTTP
$error_message = curl_error($curl); // Lấy thông báo lỗi nếu có

// Đóng cURL
curl_close($curl);

// Kiểm tra kết quả
if ($error_message) {
    // Lỗi ở tầng cURL (ví dụ: không thể kết nối, timeout)
    echo "<h2><font color='red'>Lỗi cURL!</font></h2>";
    echo "<p>Không thể thực hiện yêu cầu mạng. Chi tiết lỗi:</p>";
    echo "<pre style='background-color:#f0f0f0; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($error_message) . "</pre>";
    echo "<p><strong>Gợi ý:</strong></p>";
    echo "<ul>";
    echo "<li>Kiểm tra xem Orthanc Server có đang chạy ở địa chỉ <strong>" . htmlspecialchars($orthanc) . "</strong> không.</li>";
    echo "<li>Kiểm tra xem firewall có chặn kết nối từ PHP đến cổng 8042 không.</li>";
    echo "</ul>";

} elseif ($http_code >= 400) {
    // Server Orthanc trả về lỗi (ví dụ: 404 Not Found, 401 Unauthorized, 500 Server Error)
    echo "<h2><font color='orange'>Lỗi từ Server Orthanc (Mã lỗi: " . $http_code . ")</font></h2>";
    echo "<p>Orthanc Server đã phản hồi với một lỗi. Nội dung phản hồi (nếu có):</p>";
    echo "<pre style='background-color:#f0f0f0; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($response) . "</pre>";
    echo "<p><strong>Gợi ý:</strong></p>";
    echo "<ul>";
    echo "<li>Nếu là lỗi <strong>404 Not Found</strong>, có thể Orthanc đang có vấn đề với plugins hoặc database như chúng ta đã thảo luận.</li>";
    echo "<li>Nếu là lỗi <strong>401 Unauthorized</strong>, bạn cần thêm username/password vào biến `$orthanc` trong `config.php`.</li>";
    echo "</ul>";

} else {
    // Thành công!
    echo "<h2><font color='green'>Kết nối thành công! (Mã HTTP: " . $http_code . ")</font></h2>";
    echo "<p>Thông tin hệ thống từ Orthanc:</p>";
    
    $system_info = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<pre style='background-color:#e6f9e6; padding:10px; border:1px solid #a3d9a3;'>";
        print_r($system_info);
        echo "</pre>";
    } else {
        echo "<p><font color='red'>Không thể giải mã phản hồi JSON.</font></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
?>