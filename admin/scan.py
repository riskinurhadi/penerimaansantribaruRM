import sys
import cv2
import numpy as np

def enhance_document(image_path):
    try:
        # 1. Baca gambar asli yang diupload
        img = cv2.imread(image_path)
        if img is None:
            return

        # 2. Konversi ke Grayscale (Abu-abu Dasar)
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # 3. PENAJAMAN OPTIK (Unsharp Masking)
        # INI KUNCI KETAJAMANNYA! Kita buat versi blur, lalu kita kurangi 
        # dari aslinya untuk sangat menonjolkan tepi (edges) setiap teks.
        gaussian_blur = cv2.GaussianBlur(gray, (0, 0), 2.0)
        sharpened = cv2.addWeighted(gray, 1.5, gaussian_blur, -0.5, 0)

        # 4. PENGHAPUS BAYANGAN (Shadow Removal)
        # Menyamaratakan pencahayaan agar kertas di ujung yang gelap tetap menjadi putih
        dilated_img = cv2.dilate(sharpened, np.ones((7,7), np.uint8))
        bg_img = cv2.medianBlur(dilated_img, 21)
        diff_img = 255 - cv2.absdiff(sharpened, bg_img)
        norm_img = cv2.normalize(diff_img, None, alpha=0, beta=255, norm_type=cv2.NORM_MINMAX, dtype=cv2.CV_8UC1)

        # 5. BINARISASI MAGIC (Hitam & Putih Murni)
        # Menggunakan Thresholding pada gambar yang sudah tajam.
        # Konstanta diset ke 10 agar tinta tipis tetap tertangkap dengan baik.
        thresh = cv2.adaptiveThreshold(norm_img, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 15, 10)

        # 6. PEMBERSIH DEBU HALUS (Micro Denoising)
        # Menggantikan metode Erosi/Dilasi yang merusak bentuk teks.
        # Filter ini hanya menghapus titik hitam tunggal (kotoran) tanpa menyentuh garis teks.
        result = cv2.medianBlur(thresh, 3)

        # 7. Simpan kembali gambar hasil akhir
        cv2.imwrite(image_path, result)
        
    except Exception as e:
        print(f"Error processing image: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        file_path = sys.argv[1]
        enhance_document(file_path)