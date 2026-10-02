import io
import pypdf
import re
import glob
import json
import numpy as np
from PIL import Image
from rapidocr_onnxruntime import RapidOCR

engine = RapidOCR()

# Standard size tokens to extract if they got appended to name
KNOWN_SIZES = [
    r'\b\d{2}/\d{2,3}\b',      # e.g. 40/110, 52/105, 56/110, 48/100, 38/105, 42/100, 44/110, 46/105
    r'\b(?:XXXL|XXL|XL|L|M|S|XS)\b',
    r'\b\d{2}\b'              # e.g. 40, 42, 44, 46, 48, 50, 52, 54, 56, 58
]

def clean_name_and_size(name, size):
    name = name.strip()
    size = size.strip()
    
    # If size is empty, check if size is at the end of name
    if not size:
        # Check standard size patterns
        # 1. 2-digit/3-digit (e.g. 40/110, 52/105)
        m = re.search(r'(\d{2}/\d{2,3})$', name)
        if m:
            size = m.group(1)
            name = re.sub(r'\s*\d{2}/\d{2,3}$', '', name).strip()
            
        # 2. Letter sizes (XXXL, XXL, XL, L, M, S, XS)
        if not size:
            m = re.search(r'\b(XXXL|XXL|XL|L|M|S|XS)$', name, re.IGNORECASE)
            if m:
                size = m.group(1).upper()
                name = re.sub(r'\s*\b(XXXL|XXL|XL|L|M|S|XS)$', '', name, flags=re.IGNORECASE).strip()
                
        # 3. Simple numeric size at the end e.g. " 52"
        if not size:
            m = re.search(r'\s+(\d{2})$', name)
            if m and int(m.group(1)) in range(34, 66):
                size = m.group(1)
                name = re.sub(r'\s+\d{2}$', '', name).strip()
                
    # Clean OCR typos in item name
    name = re.sub(r'pol[oó6]', 'Póló', name, flags=re.IGNORECASE)
    name = re.sub(r'nadr\.', 'Nadrág', name, flags=re.IGNORECASE)
    name = re.sub(r'kopeny|kpeny|kopeny', 'Köpeny', name, flags=re.IGNORECASE)
    name = re.sub(r'Rovid uju|Rvid ujj|Rovid ujj', 'Rövid ujjú', name, flags=re.IGNORECASE)
    name = re.sub(r'feher|fehr', 'fehér', name, flags=re.IGNORECASE)
    name = re.sub(r'zold|zld', 'zöld', name, flags=re.IGNORECASE)
    name = re.sub(r'Ff\.|Ffi\b', 'Férfi', name, flags=re.IGNORECASE)
    name = re.sub(r'Noi|Ni', 'Női', name, flags=re.IGNORECASE)
    name = re.sub(r'Der\.', 'Derkas', name, flags=re.IGNORECASE)
    name = re.sub(r'oldalzs\.', 'oldalzsebes', name, flags=re.IGNORECASE)
    name = re.sub(r'\s*,\s*', ', ', name)
    name = re.sub(r'\s+', ' ', name).strip()
    
    return name, size

print("Module loaded.")
