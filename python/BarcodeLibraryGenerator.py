# -*- coding: utf-8 -*-
"""
Generator coduri de bare cu suport complet UTF-8 pentru caractere românești
Encoding: UTF-8
"""
import barcode
from barcode.writer import ImageWriter
from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib.units import mm
import os
import sys

class BarcodeLibraryGenerator:
    """Generare coduri de bare Code128 pentru biblioteca"""
    
    def __init__(self, output_dir='barcodes_python'):
        self.output_dir = output_dir
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
        
        # Setează encoding UTF-8 pentru output (Windows)
        if sys.platform == 'win32':
            try:
                sys.stdout.reconfigure(encoding='utf-8')
                sys.stderr.reconfigure(encoding='utf-8')
            except:
                pass
    
    def generate_barcode_image(self, code, show_text=True):
        """
        Generează cod de bare Code128 ca imagine PNG
        
        Args:
            code: Numărul codului (ex: '014016038')
            show_text: Afișează textul sub cod
            
        Returns:
            Calea către fișierul PNG generat
        """
        # Generează cod de bare Code128
        CODE128 = barcode.get_barcode_class('code128')
        
        # Opțiuni pentru cod de bare
        options = {
            'module_width': 0.3,      # Lățime modul (mm)
            'module_height': 12.0,    # Înălțime cod (mm)
            'quiet_zone': 3.0,        # Margine albă (mm)
            'font_size': 10,          # Mărime text
            'text_distance': 3.0,     # Distanță text-cod
            'background': 'white',
            'foreground': 'black',
            'write_text': show_text,  # Afișează textul
        }
        
        # Generează cod
        code_obj = CODE128(code, writer=ImageWriter())
        
        # Salvează
        filename = f'barcode_{code}'
        filepath = os.path.join(self.output_dir, filename)
        
        full_path = code_obj.save(filepath, options=options)
        
        return full_path
    
    def generate_barcode_with_header(self, code, header_text="Biblioteca Academiei Române - Iași"):
        """
        Generează cod de bare cu header personalizat
        
        Args:
            code: Numărul codului
            header_text: Textul de sus
            
        Returns:
            Calea către imaginea finală
        """
        # Generează codul de bare simplu
        barcode_path = self.generate_barcode_image(code, show_text=True)
        
        # Încarcă imaginea
        barcode_img = Image.open(barcode_path)
        
        # Creează imagine nouă mai mare (pentru header)
        new_height = barcode_img.height + 60  # +60px pentru header
        final_img = Image.new('RGB', (barcode_img.width, new_height), 'white')
        
        # Adaugă header cu font care suportă caracterele românești (ș, ț, ă, î, â)
        draw = ImageDraw.Draw(final_img)
        
        # Caută fonturi care suportă caracterele românești (ș, ț, ă, î, â)
        font = None
        font_paths = [
            "C:/Windows/Fonts/arial.ttf",        # Windows Arial (cale completă)
            "C:/Windows/Fonts/arialuni.ttf",     # Arial Unicode MS (suport complet UTF-8)
            "C:/Windows/Fonts/times.ttf",        # Times New Roman
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",  # Linux DejaVu
            "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",  # Linux Liberation
            "/System/Library/Fonts/Helvetica.ttc",  # macOS
        ]
        
        for font_path in font_paths:
            try:
                if os.path.exists(font_path):
                    font = ImageFont.truetype(font_path, 14)
                    break
            except:
                continue
        
        # Dacă nu s-a găsit niciun font TTF, folosește default
        if font is None:
            font = ImageFont.load_default()
        
        # Calculează poziția centrată pentru text
        bbox = draw.textbbox((0, 0), header_text, font=font)
        text_width = bbox[2] - bbox[0]
        text_x = (final_img.width - text_width) // 2
        
        # Python 3 suportă nativ UTF-8, deci string-ul este deja corect encodat
        draw.text((text_x, 10), header_text, fill='black', font=font)
        
        # Lipește codul de bare sub header
        final_img.paste(barcode_img, (0, 60))
        
        # Salvează
        output_path = os.path.join(self.output_dir, f'complete_{code}.png')
        final_img.save(output_path)
        
        return output_path
    
    def generate_range(self, start_code, count):
        """
        Generează serie de coduri cu counter automat
        
        Args:
            start_code: Codul de start (int, ex: 14016038)
            count: Câte coduri să genereze
            
        Returns:
            Lista cu căile către imagini
        """
        files = []
        
        for i in range(count):
            current_code = str(start_code + i).zfill(9)  # Padding cu 0
            filepath = self.generate_barcode_with_header(current_code)
            files.append(filepath)
            print(f"Generat: {current_code} -> {filepath}")
        
        return files
    
    def generate_pdf_labels(self, start_code, count, output_pdf='etichete_coduri_bare.pdf'):
        """
        Generează PDF cu etichete A4 (3 coloane × rows)
        
        Args:
            start_code: Codul de start
            count: Număr de etichete
            output_pdf: Numele fișierului PDF
        """
        c = canvas.Canvas(output_pdf, pagesize=A4)
        page_width, page_height = A4
        
        # Parametri etichetă
        label_width = 60 * mm
        label_height = 30 * mm
        cols = 3
        rows = 9
        
        margin_left = 15 * mm
        margin_top = 15 * mm
        
        labels_per_page = cols * rows
        current_label = 0
        
        for i in range(count):
            current_code = str(start_code + i).zfill(9)
            
            # Calculează poziția
            row = (current_label % labels_per_page) // cols
            col = (current_label % labels_per_page) % cols
            
            x = margin_left + col * label_width
            y = page_height - margin_top - (row + 1) * label_height
            
            # Header text - folosește font TTF care suportă caractere românești
            header_text = "Biblioteca Academiei Române - Iași"
            
            try:
                # Încearcă să folosească font TTF pentru suport UTF-8
                from reportlab.pdfbase import pdfmetrics
                from reportlab.pdfbase.ttfonts import TTFont
                
                # Caută fonturi care suportă caracterele românești
                ttf_paths = [
                    "C:/Windows/Fonts/arial.ttf",
                    "C:/Windows/Fonts/arialuni.ttf",
                    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
                    "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
                ]
                
                ttf_found = False
                for ttf_path in ttf_paths:
                    try:
                        if os.path.exists(ttf_path):
                            pdfmetrics.registerFont(TTFont('RomanianFont', ttf_path))
                            c.setFont("RomanianFont", 8)
                            ttf_found = True
                            break
                    except:
                        continue
                
                if not ttf_found:
                    # Folosește Helvetica dacă nu s-a găsit font TTF
                    c.setFont("Helvetica", 8)
            except:
                c.setFont("Helvetica", 8)
            
            # Python 3 suportă nativ UTF-8, deci putem folosi string-ul direct
            c.drawCentredString(
                x + label_width / 2,
                y + label_height - 8 * mm,
                header_text
            )
            
            # Generează cod de bare temporar
            temp_barcode = self.generate_barcode_image(current_code, show_text=False)
            
            # Desenează codul de bare
            c.drawImage(
                temp_barcode,
                x + 5 * mm,
                y + 8 * mm,
                width=50 * mm,
                height=12 * mm,
                preserveAspectRatio=True
            )
            
            # Text cod sub barcode
            c.setFont("Helvetica", 10)
            c.drawCentredString(
                x + label_width / 2,
                y + 4 * mm,
                current_code
            )
            
            current_label += 1
            
            # Pagină nouă
            if current_label % labels_per_page == 0 and i < count - 1:
                c.showPage()
        
        c.save()
        print(f"\nPDF generat: {output_pdf}")
        return output_pdf


# ==============================================================
# EXEMPLU DE FOLOSIRE - Rulează automat când se execută fișierul
# ==============================================================
if __name__ == "__main__":
    print("=" * 60)
    print("GENERATOR CODURI DE BARE - Biblioteca Academiei Române - Iași")
    print("=" * 60)
    print()
    
    try:
        generator = BarcodeLibraryGenerator()
        
        # 1. Generează UN singur cod
        print("=== 1. Generare cod simplu ===")
        single_code = generator.generate_barcode_with_header('014016038')
        print(f"✅ Cod generat: {single_code}\n")
        
        # 2. Generează serie de coduri (counter automat)
        print("=== 2. Generare serie coduri ===")
        start = 14016038
        count = 10
        
        files = generator.generate_range(start, count)
        print(f"✅ Generate {len(files)} coduri de bare în folderul {generator.output_dir}/\n")
        
        # 3. Generează PDF cu etichete A4
        print("=== 3. Generare PDF cu etichete ===")
        pdf_file = generator.generate_pdf_labels(start, 27, 'etichete_biblioteca.pdf')  # 27 = 3×9 etichete/pagină
        
        print()
        print("=" * 60)
        print("✅ TOATE CODURILE AU FOST GENERATE CU SUCCES!")
        print(f"   📁 Imagini PNG: {generator.output_dir}/")
        print(f"   📄 PDF etichete: {pdf_file}")
        print("=" * 60)
        
    except ImportError as e:
        print(f"❌ EROARE: Lipsesc biblioteci necesare!")
        print(f"   Rulând: pip install -r requirements.txt")
        print(f"   Detalii: {e}")
    except Exception as e:
        print(f"❌ EROARE: {e}")
        import traceback
        traceback.print_exc()

