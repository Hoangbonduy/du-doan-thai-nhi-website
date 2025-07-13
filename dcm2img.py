import os
import numpy as np
import warnings
import argparse
from pydicom import dcmread
from PIL import Image

# Bỏ qua các cảnh báo không cần thiết từ pydicom
warnings.filterwarnings("ignore", category=UserWarning)

def get_args():
    """
    Định nghĩa và lấy các tham số từ dòng lệnh.
    """
    parser = argparse.ArgumentParser(
        description="Chuyển đổi frame đầu tiên của một file DICOM sang một file ảnh PNG duy nhất."
    )
    parser.add_argument(
        '-i', '--input',
        metavar='INPUT_DICOM_FILE',
        type=str,
        required=True,
        help='Đường dẫn đến file DICOM đầu vào.'
    )
    parser.add_argument(
        '-o', '--output',
        metavar='OUTPUT_PNG_FILE',
        type=str,
        required=True,
        help='Đường dẫn chính xác để lưu file PNG đầu ra.'
    )
    return parser.parse_args()

def convert_first_frame_to_png(dicom_path, output_png_path):
    """
    Chuyển đổi CHỈ FRAME ĐẦU TIÊN của một file DICOM sang một file PNG duy nhất.
    """
    try:
        if not os.path.exists(dicom_path):
            print(f"Error: Input file does not exist at '{dicom_path}'")
            return False

        ds = dcmread(dicom_path)

        if 'PixelData' not in ds:
            print(f"Error: File '{os.path.basename(dicom_path)}' contains no PixelData.")
            return False

        pixel_array = ds.pixel_array

        # =========================================================================
        # *** LOGIC CỐT LÕI ĐÃ THAY ĐỔI ***
        # =========================================================================

        # 1. Lấy frame đầu tiên, bất kể có bao nhiêu frame
        if pixel_array.ndim > 2:
            # Nếu mảng là 3D (ví dụ: frames, cao, rộng) hoặc 4D (frames, cao, rộng, kênh)
            # hoặc thậm chí nhiều hơn, chúng ta chỉ lấy phần tử đầu tiên của chiều thứ nhất.
            first_frame_data = pixel_array[0]
        else:
            # Nếu mảng đã là 2D, nó chính là frame duy nhất
            first_frame_data = pixel_array

        # 2. Xử lý Photometric Interpretation cho frame đó
        if ds.PhotometricInterpretation == "MONOCHROME1":
            first_frame_data = np.amax(first_frame_data) - first_frame_data
        
        # 3. Chuẩn hóa giá trị pixel của frame đó về 0-255
        if np.issubdtype(first_frame_data.dtype, np.floating) or first_frame_data.max() > 255:
            first_frame_data = first_frame_data.astype(float)
            min_val, max_val = first_frame_data.min(), first_frame_data.max()
            if max_val > min_val:
                first_frame_data = (first_frame_data - min_val) / (max_val - min_val) * 255.0
        
        first_frame_data = first_frame_data.astype(np.uint8)
        
        # 4. Lưu ảnh vào đúng đường dẫn output đã chỉ định
        save_as_grayscale_png(first_frame_data, output_png_path)

        print(f"Successfully converted the first frame of '{dicom_path}' to '{output_png_path}'")
        return True

    except Exception as e:
        import sys
        import traceback
        print(f"An unexpected error occurred: {e}", file=sys.stderr)
        traceback.print_exc(file=sys.stderr)
        return False

def save_as_grayscale_png(pixel_array, output_path):
    """
    Lưu một mảng pixel thành file ảnh PNG thang độ xám, và tạo thư mục nếu cần.
    """
    # Bóp lại mảng để loại bỏ các chiều không cần thiết trước khi lưu
    # Ví dụ: (1, 1, cao, rộng) -> (cao, rộng)
    if pixel_array.ndim > 2:
        pixel_array = np.squeeze(pixel_array)

    output_dir = os.path.dirname(output_path)
    if output_dir and not os.path.exists(output_dir):
        os.makedirs(output_dir)

    img = Image.fromarray(pixel_array)

    if img.mode != 'L':
        img = img.convert('L')
        
    img.save(output_path, 'PNG')

def main():
    """
    Hàm chính điều phối.
    """
    args = get_args()
    success = convert_first_frame_to_png(args.input, args.output)
    
    if not success:
        exit(1)

if __name__ == "__main__":
    main()