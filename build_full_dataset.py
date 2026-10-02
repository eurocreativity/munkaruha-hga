import io
import pypdf
import re
import glob
import json
import numpy as np
from PIL import Image
from rapidocr_onnxruntime import RapidOCR

engine = RapidOCR()

# Known employee name corrections / standardizations
EMPLOYEE_MAPPING = {
    'Klemm Zoltan': 'Klemm Zoltán',
    'Klemm Zoltán': 'Klemm Zoltán',
    'Kubis Peter': 'Kubis Péter',
    'Kubis Péter': 'Kubis Péter',
    'Gurane Szabo Agnes': 'Guráné Szabó Ágnes',
    'Guráné Szabó Ágnes': 'Guráné Szabó Ágnes',
    'Vardai David': 'Várdai Dávid',
    'Várdai Dávid': 'Várdai Dávid',
    'Takacsne Izsak Monika': 'Takácsné Izsák Mónika',
    'Takácsné Izsák Mónika': 'Takácsné Izsák Mónika',
    'Falusi Janos': 'Falusi János',
    'Falusi János': 'Falusi János',
    'Szabo Zoltan': 'Szabó Zoltán',
    'Szabó Zoltán': 'Szabó Zoltán',
    'Sudar Zoltan': 'Sudár Zoltán',
    'Sudár Zoltán': 'Sudár Zoltán',
    'Szemesi Janos': 'Szemesi János',
    'Szemesi János': 'Szemesi János',
    'Dobaine Kovacs Renata': 'Dobainé Kovács Renáta',
    'Dobainé Kovács Renáta': 'Dobainé Kovács Renáta',
    'Papvolgyi Jozsef': 'Papvölgyi József',
    'Papvölgyi József': 'Papvölgyi József',
    'Szennai Maria': 'Szennai Mária',
    'Szennai Mária': 'Szennai Mária',
    'Nemeth-Kovacs Zsanett': 'Németh-Kovács Zsanett',
    'Németh-Kovács Zsanett': 'Németh-Kovács Zsanett',
    'Vig-Levai Katalin': 'Víg-Lévai Katalin',
    'Víg-Lévai Katalin': 'Víg-Lévai Katalin',
    'Kissne Feher Andrea': 'Kissné Fehér Andrea',
    'Kissné Fehér Andrea': 'Kissné Fehér Andrea',
    'Szatmarine Gurgel Magdolna': 'Szatmáriné Gurgel Magdolna',
    'Szatmáriné Gurgel Magdolna': 'Szatmáriné Gurgel Magdolna',
    'Szabo Attilane': 'Szabó Attiláné',
    'Szabó Attiláné': 'Szabó Attiláné',
    'Manyokine Varro Veronika': 'Mányokiné Varró Veronika',
    'Mányokiné Varró Veronika': 'Mányokiné Varró Veronika',
    'Koleszar Franciska': 'Koleszár Franciska',
    'Koleszár Franciska': 'Koleszár Franciska',
    'Beko Laszlo': 'Bekő László',
    'Bekő László': 'Bekő László',
    'Torzsokne Marton Johanna Andrea': 'Törzsökné Marton Johanna Andrea',
    'Törzsökné Marton Johanna Andrea': 'Törzsökné Marton Johanna Andrea'
}

def split_hungarian_name(full_name):
    # Hungarian names: Lastname Firstname (e.g. "Klemm Zoltán", "Dobainé Kovács Renáta", "Törzsökné Marton Johanna Andrea")
    parts = full_name.strip().split()
    if len(parts) == 0:
        return '', ''
    if len(parts) == 1:
        return parts[0], ''
    if len(parts) == 2:
        return parts[0], parts[1]
    # If 3 or more parts:
    # Check if first part ends with 'né' e.g. "Dobainé Kovács Renáta" -> Vezetéknév: Dobainé Kovács, Keresztnév: Renáta
    # Or "Törzsökné Marton Johanna Andrea" -> Vezetéknév: Törzsökné Marton, Keresztnév: Johanna Andrea
    if 'né' in parts[0].lower() or '-né' in parts[0].lower():
        if len(parts) == 3:
            return f"{parts[0]} {parts[1]}", parts[2]
        elif len(parts) == 4:
            return f"{parts[0]} {parts[1]}", f"{parts[2]} {parts[3]}"
    # Default: first word is Lastname, rest is Firstname
    return parts[0], ' '.join(parts[1:])

def parse_all_pdfs():
    pdf_files = sorted(glob.glob('pdf/*.pdf'))
    print(f"Scanning {len(pdf_files)} PDFs...")
    
    pages_data = []
    current_emp = ''
    current_job = ''
    
    for idx, f in enumerate(pdf_files):
        reader = pypdf.PdfReader(f)
        raw = reader.pages[0].images[0].data
        img = Image.open(io.BytesIO(raw))
        rot_img = img.rotate(90, expand=True)
        res, _ = engine(np.array(rot_img))
        
        if not res:
            print(f"[{idx+1:02d}/66] {f}: Empty/No OCR")
            pages_data.append({'file': f, 'emp': '', 'job': '', 'items': []})
            continue
            
        lines = sorted(res, key=lambda x: (x[0][0][1], x[0][0][0]))
        
        # Check employee header
        sheet_emp = ''
        sheet_job = ''
        
        for b_box, text, score in lines:
            t = text.strip()
            if re.search(r'Dolgoz[oó]\s*neve\s*:\s*(.*)', t, re.IGNORECASE):
                m = re.search(r'Dolgoz[oó]\s*neve\s*:\s*(.*)', t, re.IGNORECASE)
                sheet_emp = m.group(1).strip()
            elif re.search(r'Munkak[oö]re\s*:\s*(.*)', t, re.IGNORECASE):
                m = re.search(r'Munkak[oö]re\s*:\s*(.*)', t, re.IGNORECASE)
                sheet_job = m.group(1).strip()

        if not sheet_emp:
            for i_l, (b_box, text, score) in enumerate(lines):
                if any(k in text.lower() for k in ['dolgozo neve', 'dolgozó neve', 'dolgoz neve']):
                    rem = re.sub(r'Dolgoz[oó\]\s*neve\s*:?', '', text, flags=re.IGNORECASE).strip()
                    if rem:
                        sheet_emp = rem
                    elif i_l + 1 < len(lines):
                        sheet_emp = lines[i_l+1][1].strip()

        sheet_emp = re.sub(r'^[^\wÁÉÍÓÖŐÚÜŰáéíóöőúüű]+', '', sheet_emp).strip()
        
        if sheet_emp:
            current_emp = EMPLOYEE_MAPPING.get(sheet_emp, sheet_emp)
            current_job = sheet_job
            
        # Extract Barcodes
        # Find signature line Y coordinate if any (to avoid OCRing notes below signatures)
        sig_y = 99999
        for b_box, txt, sc in lines:
            t = txt.strip().lower()
            if any(k in t for k in ['kelt:', 'átadó', 'atado', 'átvevő', 'atvevo']):
                if b_box[0][1] > 700:
                    sig_y = min(sig_y, b_box[0][1] + 80)
                    
        barcodes = []
        for b_box, txt, sc in lines:
            t = txt.strip()
            m = re.search(r'\b(\d{8,14})\b', t)
            if m:
                b_val = m.group(1)
                x0 = b_box[0][0]
                y0 = b_box[0][1]
                # If barcode is below signature, only include if it's within standard table range or real item
                if y0 < sig_y and y0 > 300 and (680 <= x0 <= 1050 or len(b_val) == 10):
                    if not (len(b_val) == 8 and b_val.startswith('202')):
                        barcodes.append({'barcode': b_val, 'box': b_box, 'y0': y0, 'x0': x0, 'text': t})
                        
        barcodes = sorted(barcodes, key=lambda b: b['y0'])
        
        items = []
        for i_b, b in enumerate(barcodes):
            y_b = b['y0']
            prev_y = barcodes[i_b-1]['y0'] if i_b > 0 else (y_b - 70)
            next_y = barcodes[i_b+1]['y0'] if i_b+1 < len(barcodes) else (y_b + 70)
            
            y_min = max(y_b - 50, (prev_y + y_b)/2 + 2)
            y_max = min(y_b + 45, (y_b + next_y)/2 - 2)
            
            name_tokens = []
            size_tokens = []
            sorszam = ''
            notes = ''
            
            for b_box, txt, sc in lines:
                tx = txt.strip()
                if tx == b['barcode']:
                    continue
                bx0 = b_box[0][0]
                by0 = b_box[0][1]
                
                if (y_min <= by0 <= y_max) or abs(by0 - y_b) <= 38:
                    if bx0 < 290 and re.match(r'^\d+$', tx):
                        sorszam = tx
                    elif 290 <= bx0 < 590:
                        if not any(h in tx.lower() for h in ['megnevez', 'sorsz', 'darabsz', 'mret', 'méret']):
                            name_tokens.append((by0, bx0, tx))
                    elif 580 <= bx0 < 740:
                        if not any(h in tx.lower() for h in ['méret', 'meret', 'vonalk']):
                            size_tokens.append(tx)
                    elif bx0 >= 980:
                        notes += ' ' + tx
                        
            name_tokens = sorted(name_tokens, key=lambda x: (x[0], x[1]))
            raw_name = ' '.join(t[2] for t in name_tokens).strip()
            raw_size = ' '.join(size_tokens).strip()
            
            # Use cleaner
            from clean_helpers import clean_name_and_size
            clean_name, clean_sz = clean_name_and_size(raw_name, raw_size)
            
            items.append({
                'sorszam': sorszam,
                'raw_name': raw_name,
                'name': clean_name,
                'size': clean_sz,
                'barcode': b['barcode'],
                'notes': notes.strip(),
                'y0': y_b,
                'emp': current_emp,
                'job': current_job,
                'file': f
            })
            
        print(f"[{idx+1:02d}/66] {f} | Emp: '{sheet_emp or current_emp}' | Found {len(items)} items")
        pages_data.append({
            'file': f,
            'sheet_emp': sheet_emp,
            'assigned_emp': current_emp,
            'job': current_job,
            'items': items
        })
        
    return pages_data

if __name__ == '__main__':
    data = parse_all_pdfs()
    with open('full_dataset.json', 'w', encoding='utf-8') as fp:
        json.dump(data, fp, ensure_ascii=False, indent=2)
    print("Done parsing all PDFs.")
