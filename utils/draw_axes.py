import cv2
import numpy as np

# Hằng số chuyển đổi
pixel_to_mm = 0.289551


# Đọc ảnh
image_path = "fetal-PCS/nnUnet_results/I0000010_0.png"
img = cv2.imread(image_path)
image_color = img.copy()
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

# Lọc trắng rõ ràng để giữ đúng ellipse trắng
_, thresh = cv2.threshold(gray, 200, 255, cv2.THRESH_BINARY)
contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

# Tìm và fit ellipse nếu đủ điểm
for cnt in contours:
    if len(cnt) >= 5:
        ellipse = cv2.fitEllipse(cnt)
        (center, axes, angle) = ellipse

        # Đảm bảo MA là trục dài hơn, ma là trục ngắn hơn
        if axes[0] >= axes[1]:
            MA, ma = axes[0], axes[1]
            angle_major = angle
        else:
            MA, ma = axes[1], axes[0]
            angle_major = angle + 90

        # Chuyển pixel sang mm
        MA_mm = MA * pixel_to_mm
        ma_mm = ma * pixel_to_mm

        # Tính chu vi Ramanujan
        a = MA_mm / 2
        b = ma_mm / 2
        h = ((a - b)**2) / ((a + b)**2)
        perimeter = np.pi * (a + b) * (1 + (3 * h) / (10 + np.sqrt(4 - 3 * h)))

        # In ra console
        print(f"Major axis length (mm): {MA_mm:.2f} mm")
        print(f"Minor axis length (mm): {ma_mm:.2f} mm")
        print(f"Ellipse perimeter (approx.): {perimeter:.2f} mm")

        # Vẽ trục chính
        center_int = tuple(map(int, center))
        angle_rad = np.deg2rad(angle_major)
        dx_major = int((MA / 2) * np.cos(angle_rad))
        dy_major = int((MA / 2) * np.sin(angle_rad))
        pt1_major = (center_int[0] - dx_major, center_int[1] - dy_major)
        pt2_major = (center_int[0] + dx_major, center_int[1] + dy_major)
        cv2.line(image_color, pt1_major, pt2_major, (0, 0, 255), 2)

        # Vẽ trục phụ
        angle_rad_minor = angle_rad + np.pi / 2
        dx_minor = int((ma / 2) * np.cos(angle_rad_minor))
        dy_minor = int((ma / 2) * np.sin(angle_rad_minor))
        pt1_minor = (center_int[0] - dx_minor, center_int[1] - dy_minor)
        pt2_minor = (center_int[0] + dx_minor, center_int[1] + dy_minor)
        cv2.line(image_color, pt1_minor, pt2_minor, (0, 255, 0), 2)

        # Ghi chú thông tin
        cv2.putText(image_color, f"Major: {MA_mm:.2f} mm", (30, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
        cv2.putText(image_color, f"Minor: {ma_mm:.2f} mm", (30, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
        cv2.putText(image_color, f"Perimeter: {perimeter:.2f} mm", (30, 90), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 0, 0), 2)

        # Xuất ảnh
        output_path = "fetal-PCS/draw/output_ellipse.png"
        cv2.imwrite(output_path, image_color)
        print(f"Ảnh đã lưu tại: {output_path}")
        break
