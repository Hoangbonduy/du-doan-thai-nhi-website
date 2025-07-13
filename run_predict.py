import os
import argparse
import torch
import cv2
import numpy as np
import matplotlib.pyplot as plt
from batchgenerators.utilities.file_and_folder_operations import join
from nnunetv2.inference.predict_from_raw_data import nnUNetPredictor

def get_args():
    """
    Định nghĩa và lấy các tham số từ dòng lệnh.
    """
    parser = argparse.ArgumentParser(
        description="Chạy dự đoán nnU-Net trên một file ảnh, sau đó tìm contour và đo đạc."
    )
    parser.add_argument(
        '-d', '--dataset_path',
        metavar='DATASET_PATH',
        type=str,
        required=True,
        help='Đường dẫn đến thư mục chứa model đã huấn luyện của nnU-Net (ví dụ: Dataset110_HC).'
    )
    parser.add_argument(
        '-id', '--input_dir',
        metavar='INPUT_DIR',
        type=str,
        required=True,
        help='Đường dẫn đến thư mục chứa các file ảnh PNG đầu vào (dùng cho nnU-Net).'
    )
    parser.add_argument(
        '-od', '--output_dir',
        metavar='OUTPUT_DIR',
        type=str,
        required=True,
        help='Đường dẫn đến thư mục để lưu kết quả mask thô từ nnU-Net.'
    )
    parser.add_argument(
        '-ifd', '--input_file',
        metavar='INPUT_FILE_PATH',
        type=str,
        required=True,
        help='Đường dẫn đầy đủ đến file PNG cụ thể cần dự đoán (phải nằm trong --input_dir).'
    )
    parser.add_argument(
        '-ofd', '--output_file',
        metavar='OUTPUT_FILE_PATH',
        type=str,
        required=True,
        help='Đường dẫn đầy đủ để lưu ảnh PNG cuối cùng đã được vẽ contour và đo đạc.'
    )
    parser.add_argument(
        '--pixel_spacing',
        type=float,
        default=0.289551,
        help='Giá trị Pixel Spacing (mm/pixel) để chuyển đổi từ pixel sang mm.'
    )
    return parser.parse_args()

def run_prediction(predictor, input_file_path, output_dir):
    """
    Thực hiện dự đoán nnU-Net trên một file duy nhất.
    """
    
    # nnU-Net yêu cầu input là một danh sách các danh sách file
    # [[file1], [file2], ...]
    input_files_list = [[input_file_path]]

    # Tạo tên file output cho mask, không có đuôi file
    # Ví dụ: /path/to/output/mask_result
    mask_output_base = os.path.join(output_dir, os.path.splitext(os.path.basename(input_file_path))[0] + "_mask")

    # Danh sách output cũng phải tương ứng
    output_files_list = [mask_output_base]
    
    # Chạy dự đoán
    predictor.predict_from_files(
        input_files_list,
        output_files_list,
        save_probabilities=False,
        overwrite=True, # Luôn ghi đè để đảm bảo kết quả mới nhất
        num_processes_preprocessing=1,
        num_processes_segmentation_export=1,
        folder_with_segs_from_prev_stage=None,
        num_parts=1,
        part_id=0
    )
    
    # nnU-Net sẽ tự động thêm đuôi file, thường là .png hoặc .nii.gz
    # Chúng ta cần tìm chính xác tên file đã được tạo ra
    # Giả định model 2D của bạn sẽ xuất ra file .png
    predicted_mask_path = mask_output_base + ".png"
        
    return predicted_mask_path

def analyze_and_draw_contour(mask_path, final_output_path, pixel_to_mm):
    """
    Phân tích file mask, tìm ellipse, đo đạc và vẽ kết quả.
    """
    
    # Đọc ảnh mask dưới dạng grayscale
    gray_mask = cv2.imread(mask_path, cv2.IMREAD_GRAYSCALE)
    if gray_mask is None:
        print(f"Error: Unable to read mask image from '{mask_path}'")
        return

    # Chuyển ảnh 0/1 (hoặc các giá trị nhỏ) thành 0/255 để dễ xử lý và trực quan hóa
    gray_visual = (gray_mask > 0).astype(np.uint8) * 255
    
    # Tạo bản sao màu để vẽ
    image_color = cv2.cvtColor(gray_visual, cv2.COLOR_GRAY2BGR)

    # Tìm contours
    # Ngưỡng 127 là an toàn vì ảnh chỉ có giá trị 0 hoặc 255
    _, thresh = cv2.threshold(gray_visual, 127, 255, cv2.THRESH_BINARY)
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    if not contours:
        print("Error: No contours found in the mask image.")
        return

    # Chỉ xử lý contour lớn nhất để tránh nhiễu
    cnt = max(contours, key=cv2.contourArea)

    if len(cnt) < 5:
        print("Error: Contour found is too small to fit an ellipse.")
        return
        
    ellipse = cv2.fitEllipse(cnt)
    (center, axes, angle) = ellipse

    # Xác định trục lớn (MA) và trục nhỏ (ma)
    MA, ma = max(axes), min(axes)
    
    # Chuyển đổi sang mm
    MA_mm = MA * pixel_to_mm
    ma_mm = ma * pixel_to_mm

    # Tính chu vi Ramanujan
    a, b = MA_mm / 2, ma_mm / 2
    if a + b == 0: # Tránh lỗi chia cho 0
        perimeter = 0
    else:
        h = ((a - b)**2) / ((a + b)**2)
        perimeter = np.pi * (a + b) * (1 + (3 * h) / (10 + np.sqrt(4 - 3 * h)))

    # In kết quả ra console
    print("\n--- Results ---")
    print(f"Major Axis: {MA_mm:.2f} mm")
    print(f"Minor Axis: {ma_mm:.2f} mm")
    print(f"Perimeter (approx.): {perimeter:.2f} mm")

    # Vẽ kết quả lên ảnh
    cv2.ellipse(image_color, ellipse, (255, 0, 0), 2) # Vẽ ellipse màu xanh dương
    
    # Vẽ các dòng text
    text_color = (0, 255, 0) # Xanh lá
    cv2.putText(image_color, f"Major: {MA_mm:.2f} mm", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.8, text_color, 2)
    cv2.putText(image_color, f"Minor: {ma_mm:.2f} mm", (10, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.8, text_color, 2)
    cv2.putText(image_color, f"Perimeter: {perimeter:.2f} mm", (10, 90), cv2.FONT_HERSHEY_SIMPLEX, 0.8, text_color, 2)

    # Lưu ảnh kết quả cuối cùng
    output_dir = os.path.dirname(final_output_path)
    if output_dir and not os.path.exists(output_dir):
        os.makedirs(output_dir)
        
    cv2.imwrite(final_output_path, image_color)

def main():
    """
    Hàm chính điều phối toàn bộ quá trình.
    """
    args = get_args()
    
    # Khởi tạo predictor
    predictor = nnUNetPredictor(
        tile_step_size=0.5,
        use_gaussian=True,
        use_mirroring=True,
        perform_everything_on_device=True,
        device=torch.device('cuda' if torch.cuda.is_available() else 'cpu'),
        verbose=False,
        verbose_preprocessing=False,
        allow_tqdm=True
    )
    
    # Load model đã huấn luyện
    model_folder = join(args.dataset_path, 'nnUNetTrainer_100epochs__nnUNetPlans__2d')
    predictor.initialize_from_trained_model_folder(
        model_folder,
        use_folds=(0,),
        checkpoint_name='checkpoint_final.pth',
    )
    
    # Bước 1: Chạy dự đoán để tạo ra file mask
    predicted_mask_file = run_prediction(predictor, args.input_file, args.output_dir)
    
    # Bước 2: Phân tích file mask nếu nó được tạo thành công
    if predicted_mask_file:
        analyze_and_draw_contour(predicted_mask_file, args.output_file, args.pixel_spacing)

if __name__ == "__main__":
    main()