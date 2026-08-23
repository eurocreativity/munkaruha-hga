import urllib.request
import json

base_url = 'http://127.0.0.1:3000'

def test_api():
    print('1. Testing Login...')
    login_req = urllib.request.Request(
        f'{base_url}/api/auth/login',
        data=json.dumps({'username': 'admin', 'password': 'admin123'}).encode('utf-8'),
        headers={'Content-Type': 'application/json'}
    )
    with urllib.request.urlopen(login_req) as res:
        login_data = json.loads(res.read().decode('utf-8'))
        token = login_data['token']
        print('   Login SUCCESS! User:', login_data['user']['full_name'])

    headers = {'Authorization': f'Bearer {token}', 'Content-Type': 'application/json'}

    print('2. Testing Locations...')
    req = urllib.request.Request(f'{base_url}/api/locations', headers=headers)
    with urllib.request.urlopen(req) as res:
        locs = json.loads(res.read().decode('utf-8'))
        print(f'   Locations: {len(locs["locations"])} found.')

    print('3. Testing Clothes list...')
    req = urllib.request.Request(f'{base_url}/api/clothes', headers=headers)
    with urllib.request.urlopen(req) as res:
        clothes_res = json.loads(res.read().decode('utf-8'))
        print(f'   Total clothes: {clothes_res["total"]} found in inventory.')

    print('4. Testing Laundry Scan OUT (Kiadás mosásra)...')
    test_barcode = '2077946953' # Bencze Miklos poloja
    scan_req = urllib.request.Request(
        f'{base_url}/api/laundry/scan',
        data=json.dumps({'barcode': test_barcode, 'direction': 'OUT', 'location_id': 1}).encode('utf-8'),
        headers=headers
    )
    with urllib.request.urlopen(scan_req) as res:
        scan_res = json.loads(res.read().decode('utf-8'))
        print(f'   Scan OUT result: {scan_res["message"]} (Batch: {scan_res["batch"]["batch_number"]})')
        batch_id = scan_res['batch']['id']

    print('5. Testing In-Laundry List...')
    req = urllib.request.Request(f'{base_url}/api/laundry/in-laundry', headers=headers)
    with urllib.request.urlopen(req) as res:
        in_laundry = json.loads(res.read().decode('utf-8'))
        print(f'   In-Laundry count: {in_laundry["total"]}')

    print('6. Testing Laundry Scan IN (Visszavétel mosásból)...')
    scan_in_req = urllib.request.Request(
        f'{base_url}/api/laundry/scan',
        data=json.dumps({'barcode': test_barcode, 'direction': 'IN', 'location_id': 1}).encode('utf-8'),
        headers=headers
    )
    with urllib.request.urlopen(scan_in_req) as res:
        scan_in_res = json.loads(res.read().decode('utf-8'))
        print(f'   Scan IN result: {scan_in_res["message"]}')

    print('7. Testing Batch Delivery Note...')
    req = urllib.request.Request(f'{base_url}/api/laundry/batch/{batch_id}', headers=headers)
    with urllib.request.urlopen(req) as res:
        batch_res = json.loads(res.read().decode('utf-8'))
        print(f'   Batch details retrieved: {batch_res["batch"]["batch_number"]}, items: {len(batch_res["items"])}')

    print('8. Testing CSV Export...')
    req = urllib.request.Request(f'{base_url}/api/inventory/export-csv', headers=headers)
    with urllib.request.urlopen(req) as res:
        csv_data = res.read().decode('utf-8')
        lines = csv_data.strip().split('\n')
        print(f'   CSV Export SUCCESS: {len(lines)} lines generated.')

    print('\n========================================')
    print('ALL VERIFICATION TESTS PASSED 100% OK!')
    print('========================================')

if __name__ == '__main__':
    test_api()
