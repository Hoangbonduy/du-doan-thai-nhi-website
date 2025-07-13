import os
import torchvision.transforms as transforms
from PIL import Image


folder = 'fetal-PCS/convert_extract'  

for fname in os.listdir(folder):
    if fname.endswith('.png') and not fname.endswith('_0000.png'):
        old_path = os.path.join(folder, fname)
        name_wo_ext = os.path.splitext(fname)[0]
        new_name = name_wo_ext + '_0000.png'
        new_path = os.path.join(folder, new_name)
        os.rename(old_path, new_path)
        print(f'Renamed: {fname} ➜ {new_name}')

for file in os.listdir(folder):
    if file.lower().endswith(('.png', '.jpg', '.jpeg')):
        file_path = os.path.join(folder, file)
        img = Image.open(file_path)
        tf = transforms.Grayscale()
        img = tf(img)
        img.save(file_path)
    