<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
include('config.php');

$auth_user = $_SESSION['username'];
$auth_pass = $_SESSION['password'];

// Lấy study ID từ URL
$study_id = $_GET['study'] ?? null;
if (!$study_id) {
    die("Không có study ID được cung cấp.");
}

// Hàm trợ giúp để lấy dữ liệu từ Orthanc
function get_orthanc_data_viewer($url, $user, $pass) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $pass);
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

// Lấy thông tin chi tiết của study, patient và danh sách series
$study = get_orthanc_data_viewer($orthanc . 'studies/' . $study_id, $auth_user, $auth_pass);
if (!$study) die("Không tìm thấy study.");

$patient = get_orthanc_data_viewer($orthanc . 'patients/' . $study['ParentPatient'], $auth_user, $auth_pass);
$series_list = get_orthanc_data_viewer($orthanc . 'studies/' . $study_id . '/series', $auth_user, $auth_pass);

// Lấy ID của series đầu tiên để hiển thị mặc định
$default_series_id = $series_list[0]['ID'] ?? null;
$viewer_url = $default_series_id ? $orthanc . "web-viewer/app/viewer.html?series=" . $default_series_id : "";

// Thêm username:password vào URL nếu Orthanc yêu cầu xác thực cho cả trang viewer
// Điều này giúp iframe có thể tự đăng nhập
$viewer_url_with_auth = str_replace("://", "://" . urlencode($auth_user) . ":" . urlencode($auth_pass) . "@", $viewer_url);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xem ảnh - <?php echo htmlspecialchars($patient['MainDicomTags']['PatientName']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        .main-container { display: flex; height: 100vh; }
        .sidebar { width: 280px; background-color: #212529; color: white; padding: 15px; overflow-y: auto; flex-shrink: 0; }
        .viewer-container { flex-grow: 1; }
        #viewerFrame { width: 100%; height: 100%; border: none; }
        .series-link { display: block; padding: 8px 12px; color: #adb5bd; text-decoration: none; border-radius: 5px; margin-bottom: 5px; }
        .series-link:hover, .series-link.active { background-color: #495057; color: white; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sidebar (Cột bên trái) -->
        <div class="sidebar">
            <h5><?php echo htmlspecialchars($patient['MainDicomTags']['PatientName']); ?></h5>
            <p class="text-muted small"><?php echo htmlspecialchars($patient['MainDicomTags']['PatientID']); ?></p>
            <hr class="bg-secondary">
            <strong><?php echo htmlspecialchars($study['MainDicomTags']['StudyDescription']); ?></strong>
            <p class="text-muted small"><?php echo htmlspecialchars($study['MainDicomTags']['StudyDate']); ?></p>
            <hr class="bg-secondary">
            <h6>Series:</h6>
            <div id="series-list">
                <?php foreach ($series_list as $index => $series): 
                    $series_viewer_url = $orthanc . "web-viewer/app/viewer.html?series=" . $series['ID'];
                    $series_viewer_url_with_auth = str_replace("://", "://" . urlencode($auth_user) . ":" . urlencode($auth_pass) . "@", $series_viewer_url);
                ?>
                    <a href="<?php echo $series_viewer_url_with_auth; ?>" 
                       target="viewerFrame" 
                       class="series-link <?php if ($index == 0) echo 'active'; ?>"
                       onclick="setActive(this)">
                        <?php echo htmlspecialchars($series['MainDicomTags']['SeriesDescription'] ?: 'Series ' . ($index + 1)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Viewer (Cột bên phải) -->
        <div class="viewer-container">
            <iframe id="viewerFrame" name="viewerFrame" src="<?php echo $viewer_url_with_auth; ?>"></iframe>
        </div>
    </div>

<script>
    // JavaScript để đổi màu link series đang được chọn
    function setActive(element) {
        document.querySelectorAll('.series-link').forEach(link => link.classList.remove('active'));
        element.classList.add('active');
    }
</script>

</body>
</html>