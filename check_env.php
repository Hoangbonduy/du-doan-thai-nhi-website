<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kiểm tra Môi trường Python</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { padding: 20px; } pre { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; }</style>
</head>
<body>
    <div class="container">
        <h1>Kiểm tra Môi trường Python từ PHP</h1>
        <hr>

        <?php
        echo "<h2>1. Đường dẫn đến Python mà PHP đang thấy:</h2>";
        $python_path = shell_exec('where python 2>&1');
        echo "<pre>" . htmlspecialchars($python_path) . "</pre>";
        
        // Lấy dòng đầu tiên nếu có nhiều đường dẫn
        $first_python_path = strtok($python_path, "\n");
        // Bọc trong ngoặc kép để xử lý khoảng trắng
        $executable_python = '"' . trim($first_python_path) . '"';

        if (strpos($first_python_path, 'python.exe') === false) {
            echo "<div class='alert alert-danger'>Lỗi: PHP không tìm thấy 'python.exe'. Hãy đảm bảo Python đã được thêm vào biến PATH của hệ thống và khởi động lại Apache.</div>";
        } else {
            echo "<h2>2. Kiểm tra `pip show nnunetv2`:</h2>";
            $command_check = $executable_python . ' -m pip show nnunetv2 2>&1';
            echo "<h4>Lệnh đã chạy:</h4>";
            echo "<pre>" . htmlspecialchars($command_check) . "</pre>";

            echo "<h4>Kết quả:</h4>";
            $pip_show_result = shell_exec($command_check);
            echo "<pre>" . htmlspecialchars($pip_show_result) . "</pre>";

            if (stripos($pip_show_result, 'Package(s) not found') !== false || empty($pip_show_result)) {
                echo "<div class='alert alert-danger'><strong>KẾT LUẬN:</strong> Thư viện `nnunetv2` CHƯA được cài đặt trong môi trường Python mà Apache đang sử dụng.</div>";
                echo "<p><strong>Hành động:</strong> Mở Anaconda Prompt, đảm bảo bạn đang ở môi trường `(base)`, và chạy lệnh `pip install nnunetv2`.</p>";
            } else {
                echo "<div class='alert alert-success'><strong>KẾT LUẬN:</strong> Thư viện `nnunetv2` ĐÃ được cài đặt chính xác! Vấn đề có thể nằm ở chỗ khác.</div>";
                echo "<h4>Kiểm tra import:</h4>";
                $command_import = $executable_python . ' -c "import nnunetv2" 2>&1';
                $import_result = shell_exec($command_import);
                 if (empty($import_result)) {
                    echo "<div class='alert alert-success'>Import `nnunetv2` thành công!</div>";
                } else {
                    echo "<div class='alert alert-warning'><strong>Lỗi khi import:</strong><pre>" . htmlspecialchars($import_result) . "</pre></div>";
                }
            }
        }
        ?>
    </div>
</body>
</html>