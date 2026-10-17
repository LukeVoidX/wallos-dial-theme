#!/usr/bin/env python3
"""Exercise the actual app endpoints against a disposable two-account database."""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import sqlite3
import struct
import urllib.parse
import urllib.request
import zlib
from urllib.error import HTTPError

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', required=True)
parser.add_argument('--database', required=True)
parser.add_argument('--demo', action='store_true')
args = parser.parse_args()
base = args.base_url.rstrip('/')

def client(username):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    opener.open(base + '/login.php').read()
    login = urllib.parse.urlencode({'username':username,'password':'dial-fixture-only'}).encode()
    opener.open(urllib.request.Request(base + '/login.php', login)).read()
    page = opener.open(base + '/').read().decode()
    token = re.search(r'window\.csrfToken\s*=\s*"([^"]+)"', page)
    assert token, f'{username} login failed'
    return opener, token.group(1)

def request(opener, path, *, body=None, headers=None):
    req = urllib.request.Request(base + path, body, headers or {})
    try: response = opener.open(req)
    except HTTPError as error: response = error
    return response.status, dict(response.headers.items()), response.read()

def post(opener, path, data, token=None):
    body = urllib.parse.urlencode(data).encode()
    headers = {'Content-Type':'application/x-www-form-urlencoded'}
    if token: headers['X-CSRF-Token'] = token
    return request(opener, path, body=body, headers=headers)

def multipart(opener, token, name, mime, content):
    boundary = 'dialfixture42192'
    body = (
        f'--{boundary}\r\nContent-Disposition: form-data; name="action"\r\n\r\nupload\r\n'
        f'--{boundary}\r\nContent-Disposition: form-data; name="label"\r\n\r\nFictional icon\r\n'
        f'--{boundary}\r\nContent-Disposition: form-data; name="icon"; filename="{name}"\r\n'
        f'Content-Type: {mime}\r\n\r\n'
    ).encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    headers = {'Content-Type':f'multipart/form-data; boundary={boundary}'}
    if token: headers['X-CSRF-Token'] = token
    return request(opener, '/endpoints/dial/icons.php', body=body, headers=headers)

def decoded(result):
    status, headers, body = result
    assert 'no-store' in headers.get('Cache-Control',''), (status,headers)
    return status, json.loads(body)

def subscription_form(db, **changes):
    currency = db.execute('SELECT id FROM currencies WHERE user_id=1 ORDER BY id LIMIT 1').fetchone()[0]
    category = db.execute('SELECT id FROM categories WHERE user_id=1 ORDER BY id LIMIT 1').fetchone()[0]
    payer = db.execute('SELECT id FROM household WHERE user_id=1 ORDER BY id LIMIT 1').fetchone()[0]
    payment = db.execute('SELECT id FROM payment_methods WHERE user_id=1 ORDER BY id LIMIT 1').fetchone()[0]
    form = {
        'name':'Fictional icon service', 'price':'18.50', 'currency_id':currency,
        'frequency':'1', 'cycle':'3', 'next_payment':'2027-02-15',
        'auto_renew':'on', 'start_date':'2026-10-01',
        'payment_method_id':payment, 'payer_user_id':payer, 'category_id':category,
        'notes':'Synthetic test', 'url':'', 'logo-url':'', 'notify_days_before':'7',
        'replacement_subscription_id':'0',
        'dial_icon_source':'catalog', 'dial_icon_catalog_key':'chatgpt',
        'dial_icon_asset_id':'', 'dial_icon_presentation':'framed',
        'dial_icon_treatment':'mono', 'dial_icon_scale':'95',
    }
    form.update(changes)
    return form

def subscription_json(result):
    return result[0], json.loads(result[2])

def clone_request(opener, token, subscription_id):
    return request(opener, '/endpoints/subscription/clone.php',
        body=json.dumps({'id':subscription_id}).encode(),
        headers={'Content-Type':'application/json','X-CSRF-Token':token})

def post_subscription_with_logo(opener, token, form, image):
    boundary = 'dialSubscriptionLogo43811'
    parts = []
    for key,value in form.items():
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n'
                      f'{value}\r\n').encode())
    parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="icon.png"\r\n'
                  'Content-Type: image/png\r\n\r\n').encode() + image + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    return request(opener, '/endpoints/subscription/add.php', body=b''.join(parts),
        headers={'Content-Type':f'multipart/form-data; boundary={boundary}', 'X-CSRF-Token':token})

one, one_token = client('demo')
two, two_token = client('second')
anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
path = '/endpoints/dial/icons.php'
quick_path = '/endpoints/dial/subscription_icon.php'
owned_subscription = sqlite3.connect(args.database).execute(
    "SELECT id FROM subscriptions WHERE user_id=1 AND name='Cinema Club'").fetchone()[0]

status, listing = decoded(request(one,path))
assert status == 200 and len(listing['assets']) == (2 if args.demo else 0), listing
def expected_usage(user_id):
    with sqlite3.connect(args.database) as usage_db:
        count, used_bytes = usage_db.execute(
            'SELECT COUNT(*),COALESCE(SUM(length(bytes)),0) FROM dial_icon_assets WHERE user_id=?',
            (user_id,)).fetchone()
    return {'count':count, 'bytes':used_bytes, 'limit_count':100, 'limit_bytes':33554432}
assert listing['usage'] == expected_usage(1), listing
status, data = decoded(request(anonymous,path))
assert status == 401 and data['code'] == 'session_expired'
status, data = decoded(post(one,path,{'action':'delete','id':'1'}))
assert status == 403 and data['code'] == 'invalid_csrf'

def png_chunk(name, payload):
    return struct.pack('>I',len(payload)) + name + payload + struct.pack('>I',zlib.crc32(name + payload))
png = (b'\x89PNG\r\n\x1a\n' +
       png_chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 6, 0, 0, 0)) +
       png_chunk(b'IDAT', zlib.compress(b'\0\xe0\x10\x20\xff')) +
       png_chunk(b'IEND', b''))
if args.demo:
    status, data = decoded(multipart(one,one_token,'sample.png','image/png',png))
    assert status == 403 and data['code'] == 'demo_read_only', data
    status, data = decoded(post(one,path,{'action':'delete','id':'1'},one_token))
    assert status == 403 and data['code'] == 'demo_read_only', data
    status, data = decoded(post(one,quick_path,{'subscription_id':owned_subscription,
        'dial_icon_source':'original'},one_token))
    assert status == 403 and data['code'] == 'demo_read_only', data
    with sqlite3.connect(args.database) as demo_db:
        demo_form = subscription_form(demo_db)
    status, data = subscription_json(post(one,'/endpoints/subscription/add.php',demo_form,one_token))
    assert status == 403 and data['status'] == 'Error', data
    status, data = subscription_json(clone_request(one,one_token,1))
    assert status == 403 and data['success'] is False, data
    assert sqlite3.connect(args.database).execute('SELECT COUNT(*) FROM dial_icon_assets').fetchone()[0] == 2
    print('Demo icon mutation guard passed.')
    raise SystemExit(0)

quick_fields = {'subscription_id':owned_subscription,'dial_icon_source':'catalog',
    'dial_icon_catalog_key':'chatgpt','dial_icon_asset_id':'',
    'dial_icon_presentation':'framed','dial_icon_treatment':'mono','dial_icon_scale':'110'}
before_row = sqlite3.connect(args.database).execute(
    'SELECT * FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()
status, data = decoded(post(one,quick_path,quick_fields,one_token))
assert status == 200 and data['code'] == 'icon_saved', data
db_check = sqlite3.connect(args.database)
assert db_check.execute('SELECT * FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone() == before_row
assert db_check.execute('SELECT source,catalog_key,presentation,treatment,scale FROM dial_subscription_icons WHERE user_id=1 AND subscription_id=?',
                        (owned_subscription,)).fetchone() == ('catalog','chatgpt','framed','mono',110)
db_check.close()
status, data = decoded(post(one,quick_path,quick_fields))
assert status == 403 and data['code'] == 'invalid_csrf', data
status, data = decoded(post(two,quick_path,quick_fields,two_token))
assert status == 404 and data['code'] == 'icon_subscription_not_owned', data

status, data = decoded(multipart(one,one_token,'sample.png','image/png',png))
assert status == 200 and data['code'] == 'icon_saved', data
icon_id = data['id']
assert icon_id > 0
svg = b'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="22" fill="#c01022"/></svg>'
status, data = decoded(multipart(one,one_token,'shape.svg','image/svg+xml',svg))
assert status == 200 and data['id'] != icon_id, data
svg_id = data['id']
status, headers, content = request(one, f'/endpoints/dial/icon.php?id={svg_id}')
assert status == 200 and headers['Content-Type'].startswith('image/png') and content.startswith(b'\x89PNG')
assert b'<svg' not in content
status, headers, content = request(one, f'/endpoints/dial/icon.php?id={icon_id}')
assert status == 200 and headers['Content-Type'].startswith('image/png') and content.startswith(b'\x89PNG')
assert 'no-store' in headers['Cache-Control'] and headers['X-Content-Type-Options'] == 'nosniff'
for invalid in ['0','-1','1x']:
    assert request(one, '/endpoints/dial/icon.php?id=' + invalid)[0] == 404
assert request(two, f'/endpoints/dial/icon.php?id={icon_id}')[0] == 404
assert request(anonymous, f'/endpoints/dial/icon.php?id={icon_id}')[0] == 404
status, listing = decoded(request(one,path))
assert status == 200 and len(listing['assets']) == 2 and listing['assets'][0]['id'] == svg_id
assert 'bytes' not in listing['assets'][0] and 'sha256' not in listing['assets'][0]
assert listing['usage'] == expected_usage(1), listing
status, listing = decoded(request(two,path))
assert status == 200 and listing['assets'] == [] and listing['usage'] == expected_usage(2), listing

status, data = decoded(multipart(one,one_token,'sample.png','image/png',png))
assert status == 200 and data['id'] == icon_id, data
status, data = decoded(multipart(two,two_token,'sample.png','image/png',png))
assert status == 200 and data['id'] != icon_id, data
second_id = data['id']

# Save the Wallos row and Dial preference together, including legacy clients
# that omit these fields, explicit Original, foreign assets, and clone.
db = sqlite3.connect(args.database)
before_subscriptions = db.execute('SELECT COUNT(*) FROM subscriptions').fetchone()[0]
form = subscription_form(db)
status, data = subscription_json(post(one,'/endpoints/subscription/add.php',form,one_token))
assert status == 200 and data['status'] == 'Success', data
owned_subscription = db.execute("SELECT id FROM subscriptions WHERE user_id=1 AND name='Fictional icon service' ORDER BY id DESC LIMIT 1").fetchone()[0]
assert db.execute('SELECT source,catalog_key,presentation,treatment,scale FROM dial_subscription_icons WHERE user_id=1 AND subscription_id=?',
    (owned_subscription,)).fetchone() == ('catalog','chatgpt','framed','mono',95)
assert db.execute('SELECT COUNT(*) FROM subscriptions').fetchone()[0] == before_subscriptions + 1

# A Logo mount can be temporarily read-only. Editing a subscription without
# uploading/fetching a Logo must still save its ordinary fields and icon choice.
logo_dir = Path(args.database).parent.parent / 'logos'
previous_logo_mode = logo_dir.stat().st_mode & 0o777
os.chmod(logo_dir, 0o555)
try:
    no_logo_edit = dict(form, id=str(owned_subscription), name='No Logo volume write needed')
    status, data = subscription_json(post(one,'/endpoints/subscription/add.php',no_logo_edit,one_token))
    assert status == 200 and data['status'] == 'Success', data
    assert db.execute('SELECT name FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()[0] == 'No Logo volume write needed'
finally:
    os.chmod(logo_dir, previous_logo_mode)

edit = dict(form, id=str(owned_subscription), name='Edited fictional service',
    dial_icon_source='original', dial_icon_catalog_key='', dial_icon_presentation='plain',
    dial_icon_treatment='color', dial_icon_scale='100')
status, data = subscription_json(post(one,'/endpoints/subscription/add.php',edit,one_token))
assert status == 200 and data['status'] == 'Success', data
assert db.execute('SELECT source FROM dial_subscription_icons WHERE user_id=1 AND subscription_id=?',
    (owned_subscription,)).fetchone()[0] == 'original'
assert db.execute('SELECT name FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()[0] == 'Edited fictional service'

foreign = dict(edit, name='Should not be written', dial_icon_source='asset',
    dial_icon_asset_id=str(second_id))
status, data = subscription_json(post(one,'/endpoints/subscription/add.php',foreign,one_token))
assert data['status'] == 'Error', data
assert db.execute('SELECT name FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()[0] == 'Edited fictional service'
assert db.execute('SELECT source FROM dial_subscription_icons WHERE subscription_id=?',(owned_subscription,)).fetchone()[0] == 'original'

# An older form can still edit without resetting a selected icon.
legacy = {key:value for key,value in edit.items() if not key.startswith('dial_icon_')}
legacy['name'] = 'Legacy edit retained icon'
status, data = subscription_json(post(one,'/endpoints/subscription/add.php',legacy,one_token))
assert status == 200 and data['status'] == 'Success', data
assert db.execute('SELECT source FROM dial_subscription_icons WHERE subscription_id=?',(owned_subscription,)).fetchone()[0] == 'original'

# Source Logo and variants must stay associated after cloning.
db.execute("UPDATE subscriptions SET logo='demo-01.svg',logo_variant='demo-02.svg',logo_text_color='dark' WHERE id=?",(owned_subscription,))
db.commit()
status, data = subscription_json(clone_request(one,one_token,owned_subscription))
assert status == 200 and data['success'] is True and data['id'] != owned_subscription, data
clone_id = data['id']
assert db.execute('SELECT logo,logo_variant,logo_text_color FROM subscriptions WHERE id=?',(clone_id,)).fetchone() == ('demo-01.svg','demo-02.svg','dark')
assert db.execute('SELECT source FROM dial_subscription_icons WHERE subscription_id=?',(clone_id,)).fetchone()[0] == 'original'

# Historical rows can carry foreign references: reject them before a clone.
db.execute('INSERT INTO categories(name,"order",user_id) VALUES(?,0,2)',('Other account',))
foreign_category = db.execute('SELECT last_insert_rowid()').fetchone()[0]
db.execute('INSERT INTO payment_methods(name,enabled,"order",user_id) VALUES(?,1,0,2)',('Other card',))
foreign_payment = db.execute('SELECT last_insert_rowid()').fetchone()[0]
db.execute('UPDATE subscriptions SET category_id=? WHERE id=?',(foreign_category,owned_subscription))
db.commit()
status, data = subscription_json(clone_request(one,one_token,owned_subscription))
assert data['success'] is False, data
db.execute('UPDATE subscriptions SET category_id=?,payment_method_id=? WHERE id=?',
    (form['category_id'],foreign_payment,owned_subscription))
db.commit()
status, data = subscription_json(clone_request(one,one_token,owned_subscription))
assert data['success'] is False, data
assert db.execute('SELECT COUNT(*) FROM subscriptions').fetchone()[0] == before_subscriptions + 2
db.execute('UPDATE subscriptions SET payment_method_id=? WHERE id=?',(form['payment_method_id'],owned_subscription))
db.commit()

# Exercise an actual Wallos Logo replacement. A preference abort must roll
# back the row and delete the freshly moved public file while leaving both
# original files available. A second request succeeds and clears staging.
logo_dir = Path(args.database).parent.parent / 'logos'
private_stage = logo_dir / ('.dial-stage-' + 'f' * 32)
private_stage.mkdir(mode=0o700)
(private_stage / 'uncommitted.png').write_bytes(b'private-staged-logo')
os.chmod(private_stage,0o700)
status, _, body = request(anonymous, '/images/uploads/logos/' + private_stage.name + '/uncommitted.png')
assert status in (403,404) and b'private-staged-logo' not in body, status
(private_stage / 'uncommitted.png').unlink()
private_stage.rmdir()
retry_journal = logo_dir / '.dial-logo-cleanup-retry.jsonl'
retry_journal.write_text('{"filename":"private-retry.png"}\n')
os.chmod(retry_journal,0o600)
status, _, body = request(anonymous, '/images/uploads/logos/.dial-logo-cleanup-retry.jsonl')
assert status in (403,404) and b'private-retry.png' not in body, status
retry_journal.unlink()
before_files = {p.name for p in logo_dir.iterdir()}
upload_png = (b'\x89PNG\r\n\x1a\n' +
    png_chunk(b'IHDR',struct.pack('>IIBBBBB',12,12,8,6,0,0,0)) +
    png_chunk(b'IDAT',zlib.compress((b'\x00' + b'\x90\x18\x2a\xff' * 12) * 12)) +
    png_chunk(b'IEND',b''))
logo_edit = dict(edit, name='New Logo accepted')
db.execute("CREATE TRIGGER reject_icon_edit BEFORE UPDATE ON dial_subscription_icons BEGIN SELECT RAISE(ABORT,'forced_icon_failure'); END")
db.commit()
status, data = subscription_json(post_subscription_with_logo(one,one_token,logo_edit,upload_png))
assert data['status'] == 'Error', data
assert db.execute('SELECT name,logo,logo_variant FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone() == ('Legacy edit retained icon','demo-01.svg','demo-02.svg')
assert {p.name for p in logo_dir.iterdir()} == before_files
assert (logo_dir / 'demo-01.svg').is_file() and (logo_dir / 'demo-02.svg').is_file()
db.execute('DROP TRIGGER reject_icon_edit')
db.commit()
status, data = subscription_json(post_subscription_with_logo(one,one_token,logo_edit,upload_png))
assert status == 200 and data['status'] == 'Success', data
current_logo = db.execute('SELECT logo FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()[0]
assert current_logo.startswith('dial-') and current_logo.endswith('.png'), current_logo
assert (logo_dir / current_logo).is_file()
assert not list(logo_dir.glob('.dial-stage-*'))

# A tiny, valid PNG whose declared width exceeds the legacy Logo limit must
# be refused before GD decodes it or replaces the committed original.
wide_png = (b'\x89PNG\r\n\x1a\n' +
    png_chunk(b'IHDR', struct.pack('>IIBBBBB', 4097, 1, 8, 6, 0, 0, 0)) +
    png_chunk(b'IDAT', zlib.compress(b'\x00' + b'\x10\x20\x30\xff' * 4097)) +
    png_chunk(b'IEND', b''))
status, data = subscription_json(post_subscription_with_logo(one, one_token, logo_edit, wide_png))
assert status == 200 and data['status'] == 'Success' and data.get('logo_warning'), data
assert db.execute('SELECT logo FROM subscriptions WHERE id=?',(owned_subscription,)).fetchone()[0] == current_logo

db.close()
status, data = decoded(multipart(one,one_token,'fake.png','image/png',b'not an image'))
assert status == 400 and data['code'] == 'icon_image_invalid', data
status, data = decoded(multipart(one,one_token,'bad.svg','image/svg+xml',b'<svg xmlns="http://www.w3.org/2000/svg" onload="x()"/>'))
assert status == 400 and data['code'] == 'unsafe_svg', data
status, data = decoded(post(two,path,{'action':'delete','id':icon_id},two_token))
assert status == 409 and data['code'] == 'icon_in_use_or_missing', data
status, data = decoded(post(two,path,{'action':'delete','id':second_id},two_token))
assert status == 200 and data['code'] == 'icon_deleted', data
status, data = decoded(multipart(one,None,'sample.png','image/png',png))
assert status == 403 and data['code'] == 'invalid_csrf', data
status, data = decoded(multipart(anonymous,None,'sample.png','image/png',png))
assert status == 401 and data['code'] == 'session_expired', data

# Nginx must reject excess multipart bodies before PHP receives them. The
# backup-restore path retains its separate 256 MiB allowance.
status, _, _ = multipart(one,one_token,'oversize.png','image/png',b'0' * (4 * 1024 * 1024))
assert status == 413, status

db = sqlite3.connect(args.database)
assert db.execute('SELECT COUNT(*) FROM dial_icon_assets').fetchone()[0] == 2
assert db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
db.close()
print('Private icon HTTP checks passed.')
