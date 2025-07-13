import os
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
import warnings
from pydicom import dcmread
from PIL import Image
import torchvision.transforms as transforms

warnings.filterwarnings("ignore", category=UserWarning)

# === PHẦN 1: Chuyển DICOM thành PNG ===
DCM_folder_path = r"fetal-PCS\tmp\input_dicom"
png_folder_path = r"fetal-PCS\extract"

os.makedirs(png_folder_path, exist_ok=True)
images_path = os.listdir(DCM_folder_path)

for i in images_path:
    filepath = os.path.join(DCM_folder_path, i)
    ds = dcmread(filepath)

    if 'PixelData' not in ds:
        print(f" File {i} không chứa PixelData, bỏ qua.")
        continue

    pixel_array = ds.pixel_array

    if ds.PhotometricInterpretation == "MONOCHROME1":
        pixel_array = np.amax(pixel_array) - pixel_array
    elif ds.PhotometricInterpretation == "YBR_FULL":
        pixel_array = np.frombuffer(ds.PixelData, dtype=np.uint8).reshape(ds.Rows, ds.Columns, 3)

    pixel_array = pixel_array.astype(np.uint8)

    for j in range(pixel_array.shape[0]):
        slice_img = pixel_array[j]
        out_path = os.path.join(png_folder_path, f"{i.split('.')[0]}_{j}.png")
        plt.imsave(out_path, slice_img, cmap='gray')

# === Chuyển ảnh vừa tạo sang grayscale ===
for file in os.listdir(png_folder_path):
    if file.lower().endswith(('.png', '.jpg', '.jpeg')):
        file_path = os.path.join(png_folder_path, file)
        img = Image.open(file_path)
        tf = transforms.Grayscale()
        img = tf(img)
        img.save(file_path)

print(f"\n Đã chuyển xong toàn bộ ảnh sang PNG và grayscale trong: {png_folder_path}")

# === PHẦN 2: Ghi PixelSpacing & SliceThickness ra Excel ===

# def load_scan(path):
#     slices = []
#     for filename in os.listdir(path):
#         full_path = os.path.join(path, filename)
#         try:
#             dicom = dcmread(full_path)
#             if hasattr(dicom, 'ImagePositionPatient') or hasattr(dicom, 'InstanceNumber'):
#                 slices.append(dicom)
#         except Exception as e:
#             print(f"Lỗi khi đọc file {filename}: {e}")

#     if not slices:
#         raise ValueError("Không tìm thấy file DICOM hợp lệ để sắp xếp")

#     if hasattr(slices[0], 'ImagePositionPatient'):
#         slices.sort(key=lambda x: float(x.ImagePositionPatient[2]))
#         try:
#             slice_thickness = np.abs(slices[0].ImagePositionPatient[2] - slices[1].ImagePositionPatient[2])
#         except:
#             slice_thickness = 1.0
#     elif hasattr(slices[0], 'InstanceNumber'):
#         slices.sort(key=lambda x: int(x.InstanceNumber))
#         slice_thickness = 1.0
#     else:
#         raise ValueError("Không thể sắp xếp vì thiếu cả ImagePositionPatient và InstanceNumber")

#     for s in slices:
#         s.SliceThickness = slice_thickness

#     return slices

# All_DICOM_FILES = load_scan(DCM_folder_path)

# pixel_spacing_list = []
# for idx, dicom_file in enumerate(All_DICOM_FILES):
#     spacing = dicom_file.PixelSpacing if hasattr(dicom_file, 'PixelSpacing') else [None, None]
#     thickness = dicom_file.SliceThickness if hasattr(dicom_file, 'SliceThickness') else None
#     pixel_spacing_list.append({
#         "Slice Index": idx,
#         "Row Spacing (mm)": spacing[0],
#         "Column Spacing (mm)": spacing[1],
#         "Slice Thickness (mm)": thickness
#     })

# # Ghi ra file Excel
# df = pd.DataFrame(pixel_spacing_list)
# df.to_excel(excel_output_path, index=False)

# print(f"\n Đã xuất dữ liệu Pixel Spacing ra file: {excel_output_path}")
# print(f"\n Tổng số DICOM slice đã xử lý: {len(All_DICOM_FILES)}")
