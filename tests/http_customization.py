#!/usr/bin/env python3
"""Disposable loopback instance only. Server and PHP CLI must share SUPERAPP_* config.
Temporarily changes instance options, restores them, and cleans up generated accounts.
"""
import http.cookiejar, json, os, re, secrets, subprocess, sys, urllib.request, urllib.error, urllib.parse
from pathlib import Path
BASE=sys.argv[1].rstrip('/')
if urllib.parse.urlparse(BASE).hostname not in ('127.0.0.1','localhost'):raise SystemExit('Use a disposable loopback test instance.')
ROOT=Path(__file__).resolve().parents[1];PHP=os.environ.get('SUPERAPP_TEST_PHP','php');CHECKS=0
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,headers=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data,doseq=True).encode()
  try:r=self.opener.open(urllib.request.Request(BASE+path,data=data,headers=headers or {}))
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read().decode(),r.headers
 def post(self,path,data,form='/settings'):
  _,body,_=self.request(form);token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)
  assert token,('missing csrf',form,body[:200]);return self.request(path,dict(data,csrf=token.group(1)))
def check(value,label):
 global CHECKS
 assert value,label
 CHECKS+=1;print(f'ok {CHECKS} - {label}',flush=True)
def cli(code,data=None):return subprocess.check_output([PHP,'-r',"require 'app/bootstrap.php';"+code],input=json.dumps(data).encode(),cwd=ROOT).decode()
def export(c):return json.loads(c.request('/export')[1])
anon=Client();a=Client();b=Client();admin=Client();prefix='ht'+secrets.token_hex(4);names=[]
original=json.loads(cli("echo json_encode(one('SELECT * FROM instance_settings WHERE name=?',['site']));"))
all_features=json.loads(cli('echo json_encode(array_keys(features()));'))
def configure(enabled,theme='cedar'):
 _,body,_=admin.request('/admin/features');revision=re.search(r'name="revision" value="(\d+)"',body).group(1)
 return admin.post('/admin/features/save',{'enabled[]':enabled,'theme':theme,'welcome':'A quiet welcome','revision':revision})
try:
 for c,suffix,role in [(a,'a','member'),(b,'b','member'),(admin,'m','admin')]:
  name=prefix+suffix;pw=secrets.token_hex(16);cli("$d=json_decode(stream_get_contents(STDIN),true);create_user($d,$d['role']);",dict(username=name,email=name+'@example.invalid',password=pw,role=role));names.append(name)
  check(c.post('/login',dict(username=name,password=pw),'/login')[0]==200,'fixture sign-in succeeds')
 check(anon.request('/admin/features')[0]==401,'instance dashboard requires sign-in')
 check(a.request('/admin/features')[0]==403,'instance dashboard rejects members')
 check(a.post('/admin/features/save',{'enabled[]':[],'revision':'0'})[0]==403,'member cannot change site features')
 check(admin.request('/admin/features/save',{'enabled[]':[]})[0]==403,'instance changes require CSRF')
 check(configure(all_features)[0]==200,'administrator can enable features')
 check('Make this corner yours' in a.request('/welcome')[1],'optional welcome setup is available')
 prefs={'theme':'midnight','font':'mono','density':'compact','banner':'grid','avatar':'compass','landing':'start','favourites[]':['start','lab','pocket'],'widgets[]':['note','pocket']}
 status,body,_=a.post('/customize/save',prefs)
 check(status==200 and 'theme-midnight font-mono density-compact' in body,'theme, font and density save and render')
 check('banner-grid' in body,'profile pattern preview reflects saved preference')
 body=anon.request('/@'+names[0])[1]
 check('theme-midnight font-mono' in body and 'banner-grid' in body,'public profile uses member appearance')
 data=export(a);check(data['preferences']['favourites']==['start','lab','pocket'],'favourites persist in export')
 check(data['preferences']['widgets']==['note','pocket'],'widget selection persists')
 body=a.request('/start')[1]
 check('In your pocket' in body and 'Your latest notes' not in body and 'Add a shortcut' not in body,'start page respects hidden widgets')
 check(a.post('/customize/save',dict(prefs,theme='evil'))[0]==422,'invalid theme denied')
 check(a.post('/customize/save',dict(prefs,font='javascript'))[0]==422,'invalid font denied')
 check(a.request('/customize/save',prefs)[0]==403,'member customization requires CSRF')
 check(a.post('/pocket/save',dict(title='<script>Pocket task</script>'))[0]==200,'pocket task creates')
 body=a.request('/pocket')[1];task=re.search(r'/pocket\?edit=(\d+)',body).group(1)
 check('&lt;script&gt;Pocket task' in body and '<script>Pocket task' not in body,'pocket title escapes markup')
 check('Pocket task' not in b.request('/pocket')[1],'other member cannot see pocket contents')
 for route,payload in [('/pocket/save',dict(id=task,title='stolen')),('/pocket/toggle',dict(id=task,completed='1')),('/pocket/delete',dict(id=task))]:
  check(b.post(route,payload)[0]==404,'cross-account action denied: '+route)
 check(b.request('/pocket?edit='+task)[0]==404,'cross-account pocket editor denied')
 a.post('/pocket/toggle',dict(id=task,completed='1'))
 check(int(export(a)['pocket_tasks'][0]['completed'])==1,'task can be completed')
 a.post('/pocket/toggle',dict(id=task,completed='0'));a.post('/pocket/save',dict(id=task,title='Test a restore'))
 check(export(a)['pocket_tasks'][0]['title']=='Test a restore','task can be reopened and edited')
 status,body,_=a.post('/ideas/draft',dict(id='0'))
 check(status==200 and 'A little refinement' in body,'idea opens editable draft')
 check(export(a)['entries'][0]['visibility']=='private','idea draft starts private')
 check(a.post('/ideas/draft',dict(id='999'))[0]==404,'unknown idea rejected')
 for theme in ['cedar','maple','terminal','coast','prairie','midnight','paper']:
  status,body,_=anon.request('/themes?theme='+theme);check(status==200 and 'theme-'+theme in body,'public theme preview: '+theme)
 check(anon.request('/themes?theme=invalid')[0]==422,'unknown public theme rejected')
 check(anon.request('/focus')[0]==200,'focus room opens without an account')
 for kind in ['post','paste']:
  a.post('/entry/save',dict(kind=kind,title='Flagged '+kind+' '+prefix,slug='flagged-'+kind,visibility='public',body='Test content'))
 entries=export(a)['entries'];post=next(e for e in entries if e['kind']=='post')
 status,body,_=a.post('/token/create',{});token=re.search(r'<code class="token">([a-f0-9]{64})</code>',body).group(1);headers={'Authorization':'Bearer '+token}
 check(anon.request('/api/entries',headers=headers)[0]==200,'enabled API accepts token')
 disabled=['post','pocket','projects','rings','directory','guestbook','garden','uses','funding','support','feeds','focus','ideas','start']
 check(configure([f for f in all_features if f not in disabled])[0]==200,'administrator can turn individual tools off')
 for path in ['/start','/workbench','/projects','/rings','/directory','/guestbook','/garden','/funding','/support','/feed.xml','/focus','/ideas','/pocket','/@'+names[0]+'/now','/@'+names[0]+'/uses','/@'+names[0]+'/feed.xml','/@'+names[0]+'/e/flagged-post','/workspace?kind=post','/compose?kind=post']:
  check(a.request(path)[0]==404,'disabled route blocked: '+path)
 for path,payload in [('/pocket/save',dict(title='blocked')),('/ideas/draft',dict(id=0)),('/projects/save',dict(title='blocked')),('/rings/save',dict(name='blocked',description='blocked')),('/entry/save',dict(kind='post',title='blocked'))]:
  check(a.post(path,payload)[0]==404,'disabled write blocked: '+path)
 body=anon.request('/commons')[1]
 check('Flagged post '+prefix not in body and 'Flagged paste '+prefix in body,'commons filters disabled kinds without hiding enabled kinds')
 body=anon.request('/@'+names[0])[1]
 check('Flagged post '+prefix not in body and 'Leave a little hello' not in body,'profile filters disabled entries and guestbook')
 body=a.request('/apps')[1]
 check('href="/pocket"' not in body and 'href="/rings"' not in body,'catalogue hides disabled tools')
 body=anon.request('/')[1]
 check('href="/directory"' not in body and 'href="/support"' not in body,'home navigation hides disabled services')
 api=json.loads(anon.request('/api/entries',headers=headers)[1])
 check(all(e['kind']!='post' for e in api['entries']),'API listing filters disabled entry kinds')
 check(any(e['kind']=='post' for e in export(a)['entries']),'export preserves disabled entries')
 check(len(export(a)['pocket_tasks'])==1,'export preserves disabled pocket records')
 check(configure([])[0]==200,'core-only mode saves')
 check(anon.request('/api/me',headers=headers)[0]==404,'disabled API blocks existing tokens')
 for path in ['/admin/features','/customize','/profile','/workspace','/settings','/export']:
  check((admin if path.startswith('/admin') else a).request(path)[0]==200,'core route still works: '+path)
 check(a.post('/token/revoke',{})[0]==200,'tokens can still be revoked while API disabled')
 check(configure(all_features,'coast')[0]==200,'features can be re-enabled')
 check('theme-coast' in anon.request('/')[1],'operator default theme applies to anonymous pages')
 check('theme-midnight' in a.request('/workspace')[1],'operator default does not overwrite member theme')
 check(anon.request('/@'+names[0]+'/e/flagged-post')[0]==200,'reenabling restores original public entry')
 check('Test a restore' in a.request('/pocket')[1],'reenabling restores original private task')
 # Reject a stale dashboard write using a real prior revision.
 _,body,_=admin.request('/admin/features');rev=re.search(r'name="revision" value="(\d+)"',body).group(1)
 configure(all_features)
 check(admin.post('/admin/features/save',{'enabled[]':all_features,'theme':'cedar','revision':rev})[0]==409,'stale admin save is rejected')
 a.post('/pocket/delete',dict(id=task));check(export(a)['pocket_tasks']==[],'pocket deletion removes record')
 print(f'PASS: {CHECKS} customization HTTP checks')
finally:
 cli("$d=json_decode(stream_get_contents(STDIN),true);query('DELETE FROM instance_settings WHERE name=?',['site']);if($d)query('INSERT INTO instance_settings (name,value,revision) VALUES (?,?,?)',[$d['name'],$d['value'],$d['revision']]);",original)
 cli("foreach(json_decode(stream_get_contents(STDIN),true) as $n)query('DELETE FROM users WHERE username=?',[$n]);",names)
