import io
import pypdf
import re
import glob
import json
import numpy as np
from PIL import Image
from rapidocr_onnxruntime import RapidOCR

engine = RapidOCR()

def process_file(f):
    reader = pypdf.PdfReader(f)
    raw = reader.pages[0].images[0].data
    img = Image.open(io.BytesIO(raw))
    rot_img = img.rotate(90, expand=True)
    res, _ = engine(np.array(rot_img))
    
    if not res:
        return {'file': f, 'emp_name': '', 'job_title': '', 'records': []}
        
    lines = sorted(res, key=lambda x: (x[0][0][1], x[0][0][0]))
    
    # Extract employee name and job title
    emp_name = ''
    job_title = ''
    
    for idx, (box, text, score) in enumerate(lines):
        t = text.strip()
        if re.search(r'Dolgoz[oó]\s*neve\s*:\s*(.*)', t, re.IGNORECASE):
            m = re.search(r'Dolgoz[oó]\s*neve\s*:\s*(.*)', t, re.IGNORECASE)
            emp_name = m.group(1).strip()
        elif re.search(r'Munkak[oö]re\s*:\s*(.*)', t, re.IGNORECASE):
            m = re.search(r'Munkak[oö]re\s*:\s*(.*)', t, re.IGNORECASE)
            job_title = m.group(1).strip()

    if not emp_name:
        for idx, (box, text, score) in enumerate(lines):
            if any(k in text.lower() for k in ['dolgozo neve', 'dolgozó neve', 'dolgoz neve']):
                rem = re.sub(r'Dolgoz[oó\]\s*neve\s*:?', '', text, flags=re.IGNORECASE).strip()
                if rem:
                    emp_name = rem
                elif idx + 1 < len(lines):
                    emp_name = lines[idx+1][1].strip()

    # Clean emp_name
    emp_name = re.sub(r'^[^\wÁÉÍÓÖŐÚÜŰáéíóöőúüű]+', '', emp_name).strip()

    # Identify Barcodes (in table area: Y > 300, X ~ 700..1050 or 8-14 digits)
    barcodes = []
    for box, txt, sc in lines:
        t = txt.strip()
        # Find 8 to 14 digit sequence
        m = re.search(r'\b(\d{8,14})\b', t)
        if m:
            b_val = m.group(1)
            x0 = box[0][0]
            y0 = box[0][1]
            # Exclude header area if it matches date or phone, barcodes are in table area
            if y0 > 300 and (680 <= x0 <= 1050 or len(b_val) == 10):
                # Filter out obvious false positives like dates (20260824)
                if not (len(b_val) == 8 and b_val.startswith('202')):
                    barcodes.append({'barcode': b_val, 'box': box, 'y0': y0, 'x0': x0, 'text': t})

    barcodes = sorted(barcodes, key=lambda b: b['y0'])
    
    records = []
    for i, b in enumerate(barcodes):
        y_b = b['y0']
        prev_y = barcodes[i-1]['y0'] if i > 0 else (y_b - 70)
        next_y = barcodes[i+1]['y0'] if i+1 < len(barcodes) else (y_b + 70)
        
        y_min = max(y_b - 50, (prev_y + y_b)/2 + 2)
        y_max = min(y_b + 45, (y_b + next_y)/2 - 2)
        
        name_tokens = []
        size_tokens = []
        sorszam = ''
        notes = ''
        
        for box, txt, sc in lines:
            tx = txt.strip()
            if tx == b['barcode']:
                continue
            bx0 = box[0][0]
            by0 = box[0][1]
            
            # Check Y overlap
            if (y_min <= by0 <= y_max) or abs(by0 - y_b) <= 38:
                if bx0 < 290 and re.match(r'^\d+$', tx):
                    sorszam = tx
                elif 290 <= bx0 < 590:
                    # Ignore header text like "megnevezése"
                    if not any(h in tx.lower() for h in ['megnevez', 'sorsz', 'darabsz']):
                        name_tokens.append((by0, bx0, tx))
                elif 580 <= bx0 < 740:
                    if not any(h in tx.lower() for h in ['méret', 'meret']):
                        size_tokens.append(tx)
                elif bx0 >= 980:
                    notes += ' ' + tx
                    
        name_tokens = sorted(name_tokens, key=lambda x: (x[0], x[1]))
        full_name = ' '.join(t[2] for t in name_tokens).strip()
        full_size = ' '.join(size_tokens).strip()
        
        records.append({
            'sorszam': sorszam,
            'name': full_name,
            'size': full_size,
            'barcode': b['barcode'],
            'notes': notes.strip(),
            'y0': y_b
        })
        
    return {
        'file': f,
        'emp_name': emp_name,
        'job_title': job_title,
        'records': records
    }

def main():
    pdf_files = sorted(glob.glob('pdf/*.pdf'))
    print(f"Total PDFs: {len(pdf_files)}")
    
    all_results = []
    current_emp = ''
    current_job = ''
    
    for f in pdf_files:
        res = process_file(f)
        if res['emp_name']:
            current_emp = res['emp_name']
            current_job = res['job_title']
            
        res['assigned_emp'] = current_emp
        res['assigned_job'] = current_job
        all_results.append(res)
        
    # Analyze
    total_recs = sum(len(r['records']) for r in all_results)
    empty_names = [rec for r in all_results for rec in r['records'] if not rec['name']]
    empty_sizes = [rec for r in all_results for rec in r['records'] if not rec['size']]
    
    print(f"Total extracted garments: {total_recs}")
    print(f"Empty names: {len(empty_names)}")
    print(f"Empty sizes: {len(empty_sizes)}")
    
    if empty_names:
        print("\nEmpty names sample:")
        for en in empty_names[:10]:
            print(en)
            
    if empty_sizes:
        print("\nEmpty sizes sample:")
        for es in empty_sizes[:10]:
            print(es)
            
    with open('refined_extracted_data.json', 'w', encoding='utf-8') as fp:
        json.dump(all_results, fp, ensure_ascii=False, indent=2)
    print("Saved refined_extracted_data.json")

if __name__ == '__main__':
    main()
