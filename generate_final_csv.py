import os
import io
import re
import json
import csv

EMPLOYEE_CORRECTIONS = {
    'Klemm Zoltan': 'Klemm Zoltán',
    'Klemm Zoltán': 'Klemm Zoltán',
    'Kubis Peter': 'Kubis Péter',
    'Kubis Péter': 'Kubis Péter',
    'Gurane Szabo Agnes': 'Guráné Szabó Ágnes',
    'Guráné Szabó Ágnes': 'Guráné Szabó Ágnes',
    'Gurané Szab6 Agnes': 'Guráné Szabó Ágnes',
    'Guran Szab6 Agnes': 'Guráné Szabó Ágnes',
    'Vardai David': 'Várdai Dávid',
    'Várdai Dávid': 'Várdai Dávid',
    'Vrdai Dvid': 'Várdai Dávid',
    'Janko Gergely': 'Jankó Gergely',
    'Jankó Gergely': 'Jankó Gergely',
    'Torzsokne Marton Johanna Andrea': 'Törzsökné Marton Johanna Andrea',
    'Törzsökné Marton Johanna Andrea': 'Törzsökné Marton Johanna Andrea',
    'Trzskn Marton Johanna Andrea': 'Törzsökné Marton Johanna Andrea',
    'Takacsn Izsak Monika': 'Takácsné Izsák Mónika',
    'Takacsne Izsak Monika': 'Takácsné Izsák Mónika',
    'Takacsn Izsak Monika': 'Takácsné Izsák Mónika',
    'Takácsn Izsak Monika': 'Takácsné Izsák Mónika',
    'Takácsné Izsák Mónika': 'Takácsné Izsák Mónika',
    'Valko Gyorgyne': 'Valkó Györgyné',
    'Valkó Györgyné': 'Valkó Györgyné',
    'Valko Gyorgyn': 'Valkó Györgyné',
    'Valkó Gyorgyn': 'Valkó Györgyné',
    'Valko Gyorgyn': 'Valkó Györgyné',
    'Falusi Janos': 'Falusi János',
    'Falusi János': 'Falusi János',
    'Falusi Jnos': 'Falusi János',
    'Szabo Zoltan': 'Szabó Zoltán',
    'Szabó Zoltán': 'Szabó Zoltán',
    'Szab Zoltn': 'Szabó Zoltán',
    'Dr. Zsilli Gabor Barnabas': 'Dr. Zsilli Gábor Barnabás',
    'Dr. Zsilli Gábor Barnabás': 'Dr. Zsilli Gábor Barnabás',
    'Dr. Nagy Attila': 'Dr. Nagy Attila',
    'Sudar Zoltan': 'Sudár Zoltán',
    'Sudár Zoltán': 'Sudár Zoltán',
    'Sudr Zoltn': 'Sudár Zoltán',
    'Bor Kornel': 'Bor Kornél',
    'Bor Kornél': 'Bor Kornél',
    'Bor Kornl': 'Bor Kornél',
    'Bor Kornl': 'Bor Kornél',
    'Schmidtn Nemeth Beatrix': 'Schmidtné Németh Beatrix',
    'Schmidtn Nmeth Beatrix': 'Schmidtné Németh Beatrix',
    'Schmidtné Németh Beatrix': 'Schmidtné Németh Beatrix',
    'Schmidtn Nmeth Beatrix': 'Schmidtné Németh Beatrix',
    'Molnarne Pajor Krisztina': 'Molnárné Pajor Krisztina',
    'Molnárné Pajor Krisztina': 'Molnárné Pajor Krisztina',
    'Molnrn Pajor Krisztina': 'Molnárné Pajor Krisztina',
    'dr. Pragai Gabor': 'Dr. Prágai Gábor',
    'Dr. Prágai Gábor': 'Dr. Prágai Gábor',
    'Dr. Prgai Gbor': 'Dr. Prágai Gábor',
    'Kutin Druzsin Agnes': 'Kutiné Druzsin Ágnes',
    'Kutin Druzsin Agnes': 'Kutiné Druzsin Ágnes',
    'Kutin Druzsin Agnes': 'Kutiné Druzsin Ágnes',
    'Kutiné Druzsin Ágnes': 'Kutiné Druzsin Ágnes',
    'Szemesi Janos': 'Szemesi János',
    'Szemesi János': 'Szemesi János',
    'Szemesi Jnos': 'Szemesi János',
    'Dobaine Kovacs Renata': 'Dobainé Kovács Renáta',
    'Dobainé Kovács Renáta': 'Dobainé Kovács Renáta',
    'Dobain Kovacs Renata': 'Dobainé Kovács Renáta',
    'Dobain Kovacs Renata': 'Dobainé Kovács Renáta',
    'Hantal Gabor': 'Hantal Gábor',
    'Hantal Gábor': 'Hantal Gábor',
    'Papvolgyi Jozsef': 'Papvölgyi József',
    'Papvölgyi József': 'Papvölgyi József',
    'Papvlgyi Jzsef': 'Papvölgyi József',
    'Papvlgyi Jzsef': 'Papvölgyi József',
    'Szennai Maria': 'Szennai Mária',
    'Szennai Mária': 'Szennai Mária',
    'Szennai Mria': 'Szennai Mária',
    'Nemeth-Kovacs Zsanett': 'Németh-Kovács Zsanett',
    'Németh-Kovács Zsanett': 'Németh-Kovács Zsanett',
    'Nmeth-Kovacs Zsanett': 'Németh-Kovács Zsanett',
    'Nmeth-Kovacs Zsanett': 'Németh-Kovács Zsanett',
    'Vig-Levai Katalin': 'Víg-Lévai Katalin',
    'Víg-Lévai Katalin': 'Víg-Lévai Katalin',
    'Vig-Lvai Katalin': 'Víg-Lévai Katalin',
    'Vig-Lvai Katalin': 'Víg-Lévai Katalin',
    'Kissne Feher Andrea': 'Kissné Fehér Andrea',
    'Kissné Fehér Andrea': 'Kissné Fehér Andrea',
    'Kissne Fehr Andrea': 'Kissné Fehér Andrea',
    'Kissne Fehr Andrea': 'Kissné Fehér Andrea',
    'Szatmarine Gurgel Magdolna': 'Szatmáriné Gurgel Magdolna',
    'Szatmáriné Gurgel Magdolna': 'Szatmáriné Gurgel Magdolna',
    'Szatmrin Gurgel Magdolna': 'Szatmáriné Gurgel Magdolna',
    'Szabo Attilane': 'Szabó Attiláné',
    'Szabó Attiláné': 'Szabó Attiláné',
    'Szab Attiln': 'Szabó Attiláné',
    'Szab Attiln': 'Szabó Attiláné',
    'Manyokine Varro Veronika': 'Mányokiné Varró Veronika',
    'Mányokiné Varró Veronika': 'Mányokiné Varró Veronika',
    'Mnyokin Varr Veronika': 'Mányokiné Varró Veronika',
    'Mnyokin Varr Veronika': 'Mányokiné Varró Veronika',
    'Koleszar Franciska': 'Koleszár Franciska',
    'Koleszár Franciska': 'Koleszár Franciska',
    'Koleszr Franciska': 'Koleszár Franciska',
    'TARTALEK Nagygat u.': 'Tartalék Nagygát u.',
    'Tartalék Nagygát u.': 'Tartalék Nagygát u.',
    'Tartalk Nagygt u.': 'Tartalék Nagygát u.'
}

def clean_garment_name(raw_name):
    s = raw_name.strip()
    
    sz = ''
    m = re.search(r'\b(\d{2}/\d{2,3})\b', s)
    if m:
        sz = m.group(1)
        s = re.sub(r'\b\d{2}/\d{2,3}\b', '', s)
    if not sz:
        m = re.search(r'\b(XXXL|XXL|XL|L|M|S|XS)\b', s)
        if m:
            sz = m.group(1).upper()
            s = re.sub(r'\b(XXXL|XXL|XL|L|M|S|XS)\b', '', s)
    if not sz:
        m = re.search(r'\s+(\d{2})$', s)
        if m and int(m.group(1)) in range(34, 66):
            sz = m.group(1)
            s = re.sub(r'\s+\d{2}$', '', s)
            
    s = re.sub(r'\bpol[oó6]\b', 'Póló', s, flags=re.IGNORECASE)
    s = re.sub(r'\bPolo\b', 'Póló', s)
    s = re.sub(r'R[oöóő]vid\s*uj[juú]{1,3}', 'Rövid ujjú', s, flags=re.IGNORECASE)
    s = re.sub(r'Der\.?\s*nadr\.?', 'Deréknadrág ', s, flags=re.IGNORECASE)
    s = re.sub(r'Ff\.?\s*nadr\.?|Ffi\s*nadr\.?', 'Férfi nadrág ', s, flags=re.IGNORECASE)
    s = re.sub(r'Noi\s*nadr\.?|Női\s*nadr\.?|Nei\s*nadr\.?', 'Női nadrág ', s, flags=re.IGNORECASE)
    s = re.sub(r'Ff\.?\s*k[oöóő]peny|Ffi\s*k[oöóő]peny|Ff\.?\s*k[oöóő]p\.?', 'Férfi köpeny ', s, flags=re.IGNORECASE)
    s = re.sub(r'Noi\s*k[oöóő]peny|Női\s*k[oöóő]peny|Noi\s*Kop\.|Női\s*Kop\.', 'Női köpeny ', s, flags=re.IGNORECASE)
    s = re.sub(r'\bkazak\b', 'Kazak', s, flags=re.IGNORECASE)
    s = re.sub(r'\bunisex\b', 'unisex', s, flags=re.IGNORECASE)
    s = re.sub(r'\bfeh[eé]r\b', 'fehér', s, flags=re.IGNORECASE)
    s = re.sub(r'\bz[oöóő]ld\b', 'zöld', s, flags=re.IGNORECASE)
    s = re.sub(r'\bk[eé]k\b', 'kék', s, flags=re.IGNORECASE)
    s = re.sub(r'oldalzs\.?', 'oldalzsebes', s, flags=re.IGNORECASE)
    s = re.sub(r'White\s*L\b|WhiteLine', 'WhiteLine', s, flags=re.IGNORECASE)
    s = re.sub(r'SM\s*-\s*COLOR|SM\s*-\s*GOLOR|SM-C\b', 'SM-COLOR', s, flags=re.IGNORECASE)
    s = re.sub(r'bottlez[oöóő]ld|bottle\s*z[oöóő]ld', 'bottlezöld', s, flags=re.IGNORECASE)
    s = re.sub(r'\bKU\b|\bKÜ\b', 'KÜ', s)
    s = re.sub(r'[,;.\s]+$', '', s)
    s = re.sub(r'\s*,\s*', ', ', s)
    s = re.sub(r'\s+', ' ', s).strip()
    
    return s, sz

def split_name(full_name):
    parts = full_name.strip().split()
    if not parts:
        return '', ''
    if len(parts) == 1:
        return parts[0], ''
    if len(parts) == 2:
        return parts[0], parts[1]
        
    if 'né' in parts[0].lower() or '-né' in parts[0].lower():
        if len(parts) == 3:
            return f"{parts[0]} {parts[1]}", parts[2]
        else:
            return f"{parts[0]} {parts[1]}", ' '.join(parts[2:])
            
    if parts[0].lower() in ['dr', 'dr.']:
        if len(parts) == 3:
            return f"{parts[0]} {parts[1]}", parts[2]
        else:
            return f"{parts[0]} {parts[1]}", ' '.join(parts[2:])
            
    return parts[0], ' '.join(parts[1:])

def run():
    with open('full_dataset.json', 'r', encoding='utf-8') as fp:
        data = json.load(fp)

    emp_names_in_order = []
    for sheet in data:
        emp = sheet.get('assigned_emp', '').strip()
        emp_clean = EMPLOYEE_CORRECTIONS.get(emp, emp)
        if emp_clean and emp_clean not in emp_names_in_order:
            emp_names_in_order.append(emp_clean)

    emp_code_map = {}
    for idx, name in enumerate(emp_names_in_order, 1):
        if 'Tartalék' in name or 'TARTALÉK' in name:
            emp_code_map[name] = '0083'
        else:
            emp_code_map[name] = f"{idx:04d}"

    header = [
        "Költséghely",
        "SzC-megnevezés",
        "Költséghely-megnevezés",
        "Szekrény/fakk",
        "Dolgozó",
        "Vezetéknév",
        "Keresztnév",
        "Cikksz.",
        "Megnevezés",
        "Méret",
        "Vonalkód",
        "Óra/darab?",
        "StátuszMegnev",
        "Változat",
        "bevonás",
        "NévCímke",
        "Logó",
        "Módosítások",
        "Kiolvasás 1",
        "Beolvasás 1",
        "Aktuális nettó amortizációs érték"
    ]
    csv_rows = [header]

    seen_barcodes = set()
    total_items = 0
    duplicate_count = 0
    emp_item_counts = {}

    for sheet in data:
        raw_emp = sheet.get('assigned_emp', '').strip()
        emp = EMPLOYEE_CORRECTIONS.get(raw_emp, raw_emp)
        emp_code = emp_code_map.get(emp, '0001')
        
        is_nagygat = ('Nagygát' in emp or 'Tartalék' in emp or 'Szemesi János' in emp)
        loc_id = "2" if is_nagygat else "1"
        loc_name = "HGA Biomed, Kap, Nagygát utca 1" if is_nagygat else "HGA Biomed, Kap, Jutai 50."
        
        last_name, first_name = split_name(emp)
        if 'Tartalék' in emp:
            last_name = 'Tartalék (Nagygát u.)'
            first_name = ''
            
        for item in sheet.get('items', []):
            barcode = item.get('barcode', '').strip()
            if not barcode:
                continue
                
            if barcode in seen_barcodes:
                duplicate_count += 1
                continue
            seen_barcodes.add(barcode)
            
            raw_name = item.get('raw_name', '')
            raw_size = item.get('size', '')
            
            clean_name, size_from_name = clean_garment_name(raw_name)
            final_size = raw_size if raw_size else size_from_name
            
            if not clean_name:
                clean_name = "Munkaruha tétel"
                
            row = [
                loc_id,
                loc_name,
                loc_name,
                "",
                emp_code,
                last_name,
                first_name,
                "",
                clean_name,
                final_size,
                barcode,
                "1",
                "Aktív",
                "",
                "",
                "",
                "",
                item.get('notes', ''),
                "",
                "",
                "0"
            ]
            csv_rows.append(row)
            total_items += 1
            emp_item_counts[emp] = emp_item_counts.get(emp, 0) + 1

    output_csv = 'vegleges_leltar.csv'
    with open(output_csv, 'w', encoding='utf-8-sig', newline='') as fp:
        writer = csv.writer(fp, delimiter=';')
        writer.writerows(csv_rows)

    print(f"\n==========================================")
    print(f"Leltár CSV sikeresen elkészítve: {output_csv}")
    print(f"Összes kinyert tétel: {total_items} db")
    print(f"Kiszűrt duplikált vonalkód: {duplicate_count} db")
    print(f"Dolgozók száma: {len(emp_item_counts)}")
    print(f"==========================================")

if __name__ == '__main__':
    run()
