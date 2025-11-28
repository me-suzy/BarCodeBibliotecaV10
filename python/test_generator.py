# -*- coding: utf-8 -*-
"""
Script simplu de test pentru generatorul de coduri de bare
Rulează: python test_generator.py
"""
import os
import sys

# Adaugă folderul curent la path pentru import
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

print("=" * 70)
print("TEST GENERATOR CODURI DE BARE")
print("=" * 70)
print()

try:
    from BarcodeLibraryGenerator import BarcodeLibraryGenerator
    print("✅ BarcodeLibraryGenerator importat cu succes")
except ImportError as e:
    print(f"❌ EROARE la import: {e}")
    print("\nVerifică că ai instalat dependențele:")
    print("   pip install -r requirements.txt")
    sys.exit(1)

print()

try:
    # Creează generator
    print("1. Creare generator...")
    generator = BarcodeLibraryGenerator(output_dir='barcodes_test')
    print("   ✅ Generator creat")
    print()
    
    # Test 1: Un singur cod
    print("2. Test generare cod simplu...")
    code = '014016038'
    output_file = generator.generate_barcode_with_header(code)
    if os.path.exists(output_file):
        print(f"   ✅ Cod generat: {output_file}")
        print(f"   📁 Dimensiune: {os.path.getsize(output_file)} bytes")
    else:
        print(f"   ❌ Fișierul nu a fost creat: {output_file}")
    print()
    
    # Test 2: Serie de coduri
    print("3. Test generare serie (5 coduri)...")
    start_code = 14016038
    count = 5
    files = generator.generate_range(start_code, count)
    if len(files) == count:
        print(f"   ✅ Generate {len(files)} coduri")
        for f in files:
            if os.path.exists(f):
                print(f"      ✓ {os.path.basename(f)}")
    else:
        print(f"   ❌ Așteptat {count}, generat {len(files)}")
    print()
    
    # Test 3: PDF (doar dacă toate merge bine)
    try:
        print("4. Test generare PDF (3 etichete)...")
        pdf_file = generator.generate_pdf_labels(start_code, 3, 'test_etichete.pdf')
        if os.path.exists(pdf_file):
            print(f"   ✅ PDF generat: {pdf_file}")
            print(f"   📄 Dimensiune: {os.path.getsize(pdf_file)} bytes")
        else:
            print(f"   ❌ PDF-ul nu a fost creat: {pdf_file}")
    except Exception as e:
        print(f"   ⚠️  PDF nu s-a putut genera: {e}")
    print()
    
    print("=" * 70)
    print("✅ TESTE FINALIZATE!")
    print("=" * 70)
    print(f"\nFișiere generate în: {generator.output_dir}/")
    print("\nPentru a vedea rezultatele, deschide folderul 'barcodes_test'")
    
except Exception as e:
    print()
    print("=" * 70)
    print(f"❌ EROARE: {e}")
    print("=" * 70)
    import traceback
    traceback.print_exc()
    sys.exit(1)

