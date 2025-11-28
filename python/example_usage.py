# ==============================================================
# EXEMPLU DE FOLOSIRE
# ==============================================================

from BarcodeLibraryGenerator import BarcodeLibraryGenerator

if __name__ == "__main__":
    
    generator = BarcodeLibraryGenerator()
    
    # 1. Generează UN singur cod
    print("=== Generare cod simplu ===")
    single_code = generator.generate_barcode_with_header('014016038')
    print(f"Cod generat: {single_code}\n")
    
    # 2. Generează serie de coduri (counter automat)
    print("=== Generare serie coduri ===")
    start = 14016038
    count = 10
    
    files = generator.generate_range(start, count)
    print(f"\nGenerate {len(files)} coduri de bare în folderul barcodes_python/\n")
    
    # 3. Generează PDF cu etichete A4
    print("=== Generare PDF cu etichete ===")
    pdf_file = generator.generate_pdf_labels(start, 27, 'etichete_biblioteca.pdf')  # 27 = 3×9 etichete/pagină
    
    print("\n✅ TOATE CODURILE AU FOST GENERATE CU SUCCES!")
    print(f"   - Imagini PNG: barcodes_python/")
    print(f"   - PDF etichete: {pdf_file}")

