#!/usr/bin/env python3
"""Community integration checks. Disposable local DB only; PHP CLI and server must share SUPERAPP_* config.
Usage: python3 tests/http_community.py http://127.0.0.1:8080
Creates temporary members/admin through the local CLI, then deletes their records in finally.
"""
import http.cookiejar, json, os, re, secrets, subprocess, sys, urllib.request, urllib.error, urllib.parse
from pathlib import Path
BASE=sys.argv[1].rstrip('/')
if urllib.parse.urlparse(BASE).hostname not in ('127.0.0.1','localhost'):
    raise SystemExit('Use a disposable loopback test instance.')
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('SUPERAPP_TEST_PHP','php')
CHECKS=0
class Client:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None):
        if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
        try:r=self.opener.open(urllib.request.Request(BASE+path,data=data))
        except urllib.error.HTTPError as e:r=e
        return r.status,r.read().decode(),r.headers
    def post(self,path,data,form='/settings'):
        _,body,_=self.request(form)
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)
        assert token,('missing CSRF',form,body[:150])
        return self.request(path,dict(data,csrf=token.group(1)))
def check(value,label):
    global CHECKS
    assert value,label
    CHECKS+=1;print(f'ok {CHECKS} - {label}',flush=True)
def cli(code,data):
    return subprocess.check_output([PHP,'-r',"require 'app/bootstrap.php';"+code],input=json.dumps(data).encode(),cwd=ROOT).decode()
def export(c):return json.loads(c.request('/export')[1])
anon=Client();a=Client();b=Client();admin=Client();prefix='hc'+secrets.token_hex(4);names=[]
try:
    for c,suffix,role in [(a,'a','member'),(b,'b','member'),(admin,'m','admin')]:
        name=prefix+suffix;pw=secrets.token_hex(16)
        cli("$d=json_decode(stream_get_contents(STDIN),true);create_user($d,$d['role']);",dict(username=name,email=name+'@example.invalid',password=pw,role=role));names.append(name)
        status,_,_=c.post('/login',dict(username=name,password=pw),'/login');check(status==200,'fixture account signs in')
    for path in ['/start','/workbench']:
        check(anon.request(path)[0]==401,path+' requires sign-in')
    check(a.request('/start/link/save',dict(title='x'))[0]==403,'new shortcut endpoint requires CSRF')
    status,html,_=a.post('/start/link/save',dict(title='PRIVATE-NAS-'+prefix,target_url='http://192.168.1.40/',group_name='Lab',position='2'))
    check(status==200 and 'PRIVATE-NAS-'+prefix in html,'private shortcut creates and renders')
    link=re.search(r'/start\?edit=(\d+)',html).group(1)
    check(b.request('/start?edit='+link)[0]==404,'shortcut editor denies another member')
    check(b.post('/start/link/save',dict(id=link,title='stolen',target_url='https://example.org'))[0]==404,'shortcut update denies another member')
    check(b.post('/start/link/delete',dict(id=link))[0]==404,'shortcut deletion denies another member')
    check('PRIVATE-NAS-'+prefix not in b.request('/start')[1],'other start page does not expose shortcut')
    a.post('/start/note',dict(body='<script>PRIVATE NOTE</script>'))
    html=a.request('/start')[1];check('&lt;script&gt;PRIVATE NOTE' in html and '<script>PRIVATE NOTE' not in html,'private note escapes HTML')
    check('PRIVATE NOTE' not in b.request('/start')[1],'private note isolated by owner')
    status,html,_=a.post('/projects/save',dict(title='Build '+prefix,summary='<script>build</script>',source_url='https://example.org/repo'))
    check(status==200 and '&lt;script&gt;build' in html,'project creates and escapes summary')
    project=str(export(a)['projects'][0]['id'])
    check(anon.request('/projects/'+project)[0]==404,'anonymous cannot read private project')
    check(b.request('/projects/'+project)[0]==404,'other member cannot read private project')
    check(b.post('/projects/save',dict(id=project,title='stolen'))[0]==404,'cross-account project update denied')
    check(b.post('/projects/delete',dict(id=project))[0]==404,'cross-account project deletion denied')
    status,html,_=a.post('/projects/update/save',dict(project_id=project,kind='part',title='Memory',cost='19.95',body='Used DIMM'))
    check(status==200 and 'CA$19.95' in html,'part cost contributes to project total')
    part=str(export(a)['projects'][0]['updates'][0]['id'])
    check(b.post('/projects/update/save',dict(project_id=project,kind='log',title='intrusion'))[0]==404,'cross-account project log denied')
    check(b.post('/projects/update/delete',dict(project_id=project,id=part))[0]==404,'cross-account log deletion denied')
    a.post('/projects/update/save',dict(project_id=project,kind='milestone',title='Boots Linux',completed='1'))
    check('1 / 1' in a.request('/projects/'+project)[1],'completed milestone counted')
    a.post('/projects/save',dict(id=project,visibility='unlisted'))
    check(anon.request('/projects/'+project)[0]==200,'unlisted project direct link opens')
    check('Build '+prefix not in anon.request('/projects?q='+prefix)[1],'unlisted project absent from discovery')
    a.post('/projects/save',dict(id=project,visibility='public'))
    check('Build '+prefix in anon.request('/projects?q='+prefix)[1],'public project appears in discovery')
    check('Build '+prefix in anon.request('/@'+names[0])[1],'public project appears on profile')
    profile=dict(display_name=names[0],theme='cedar')
    a.post('/profile/save',profile)
    check('Build '+prefix not in anon.request('/projects?q='+prefix)[1],'profile opt-out removes project discovery')
    a.post('/profile/save',dict(profile,listed='1'))
    check(b.post('/report',dict(path='/projects/'+project,reason='HTTP test report'))[0]==200,'project can be reported')
    status,html,_=a.post('/rings/save',dict(name='Ring '+prefix,description='<script>ring</script>'))
    check(status==200 and '&lt;script&gt;ring' in html,'ring creates with escaped description')
    ring=str(export(a)['webrings_owned'][0]['id']);origin=str(export(a)['webring_memberships'][0]['id'])
    check(b.post('/rings/save',dict(id=ring,name='stolen',description='bad'))[0]==403,'non-owner ring edit denied')
    check(b.post('/rings/delete',dict(id=ring))[0]==403,'non-owner ring deletion denied')
    check(b.post('/rings/join',dict(id=ring,website='https://example.org/'))[0]==200,'ring membership request succeeds')
    member=str(export(b)['webring_memberships'][0]['id'])
    check(names[1] not in anon.request('/rings/'+ring)[1],'pending member not shown publicly')
    check(b.post('/rings/member',dict(id=ring,member_id=member,decision='approve'))[0]==403,'member cannot approve own request')
    a.post('/rings/member',dict(id=ring,member_id=member,decision='approve'))
    check(names[1] in anon.request('/rings/'+ring)[1],'owner approval publishes member')
    check('&lt;nav aria-label=' in b.request('/rings/'+ring)[1],'approved member gets escaped HTML embed')
    for direction in ['next','previous','random']:
        status,html,_=anon.request('/rings/'+ring+'/hop?member='+origin+'&direction='+direction)
        check(status==200 and 'https://example.org/' in html and 'Check the destination' in html,direction+' hop shows external destination before leaving')
    check(b.post('/report',dict(path='/rings/'+ring,reason='HTTP ring test'))[0]==200,'ring can be reported')
    b.post('/rings/leave',dict(id=ring));check(names[1] not in anon.request('/rings/'+ring)[1],'leaving removes public membership')
    status,_,_=a.post('/directory/submit',dict(name='Lead '+prefix,website='https://example.org',claim='Canadian-owned',evidence_url='https://example.org/about',notes='A test lead',alternatives='Alternative-'+prefix,ownership_description='Independent owner',ownership_source='https://example.org/about',ownership_date='2025-01-01'))
    check(status==200,'directory with separate evidence submits')
    # Admin discovers the exact fixture through its unique title.
    adminhtml=admin.request('/admin')[1]
    match=re.search(r'<h3>Lead '+prefix+r'</h3>(.*?)</article>',adminhtml,re.S)
    assert match,'fixture directory absent from admin'
    listing=re.search(r'/admin/directory/(\d+)',match.group(1)).group(1)
    check('Independent owner' in match.group(1),'moderator sees supporting details before approval')
    admin.post('/admin/directory',dict(id=listing,decision='approved'))
    html=anon.request('/directory?q=Alternative-'+prefix)[1]
    check('Lead '+prefix in html,'alternative labels are searchable')
    check('Independent owner' in html and 'Not documented separately' in html,'directory distinguishes documented and unknown dimensions')
    check(b.post('/directory/correction',dict(id=listing,body='CORRECTION-'+prefix,evidence_url='https://example.org/new'))[0]==200,'sourced correction submits')
    check('CORRECTION-'+prefix not in anon.request('/directory')[1],'correction is not published automatically')
    html=admin.request('/admin')[1];check('CORRECTION-'+prefix in html,'moderator sees correction')
    check(a.post('/admin/directory/evidence',dict(id=listing))[0]==403,'member cannot edit moderated evidence')
    admin.post('/admin/directory/evidence',dict(id=listing,claim='Canadian-hosted',evidence_url='https://example.org/new',notes='Updated',alternatives='Alternative-'+prefix,hosting_description='Published region',hosting_source='https://example.org/new',hosting_date='2025-01-02'))
    check('Lead '+prefix not in anon.request('/directory?q=Alternative-'+prefix)[1],'evidence revision unpublishes until approved')
    admin.post('/admin/directory',dict(id=listing,decision='approved'))
    check('Published region' in anon.request('/directory?q=Alternative-'+prefix)[1],'reapproved evidence is visible')
    report=dict(month='2001-02',hosting='10.25',backups='1.10',other='0',contributions='8.05',notes='HTTP fixture '+prefix)
    check(a.post('/funding/save',report)[0]==403,'member cannot publish funding')
    check(a.post('/funding/delete',dict(month=report['month']))[0]==403,'member cannot remove funding')
    check(a.request('/funding?month='+report['month'])[0]==403,'member cannot open funding editor')
    status,html,_=admin.post('/funding/save',report)
    check(status==200 and 'HTTP fixture '+prefix in html,'administrator publishes funding report')
    check('CA$10.25' in anon.request('/funding')[1],'published figures are publicly readable')
    admin.post('/funding/save',dict(report,contributions='12.34'))
    check('CA$12.34' in anon.request('/funding')[1],'administrator can correct report')
    check(admin.post('/funding/save',dict(report,hosting='-1'))[0]==422,'negative funding rejected over HTTP')
    admin.post('/funding/delete',dict(month=report['month']))
    check('HTTP fixture '+prefix not in anon.request('/funding')[1],'report deletion removes public figures')
    data=export(a)
    check(data['start_page']['note']=='<script>PRIVATE NOTE</script>','private note included in owner export')
    check(len(data['projects'][0]['updates'])==2,'project history included in export')
    check(len(data['directory_evidence'])==1,'revised evidence included in export')
    check(len(export(b)['directory_corrections'])==1,'correction author can export submission')
    for path in ['/start','/workbench','/projects','/rings','/funding','/apps','/support','/privacy','/admin/directory/'+listing]:
        client=admin if path.startswith('/admin') else a
        check(client.request(path)[0]==200,path+' renders')
    a.post('/projects/update/delete',dict(project_id=project,id=part))
    check('CA$0.00' in a.request('/projects/'+project)[1],'deleting part recalculates total')
    a.post('/projects/delete',dict(id=project));check(anon.request('/projects/'+project)[0]==404,'project deletion removes permalink')
    a.post('/rings/delete',dict(id=ring));check(anon.request('/rings/'+ring)[0]==404,'ring deletion removes permalink')
    a.post('/start/link/delete',dict(id=link));check('PRIVATE-NAS-'+prefix not in a.request('/start')[1],'shortcut can be removed')
    print(f'PASS: {CHECKS} community HTTP checks')
finally:
    # Match generated usernames only; never truncate shared tables.
    cli("$names=json_decode(stream_get_contents(STDIN),true);foreach($names as $n)query('DELETE FROM users WHERE username=?',[$n]);",names)
    # Funding fixture is intentionally separate from user-owned records.
    cli("$prefix=json_decode(stream_get_contents(STDIN),true);query('DELETE FROM funding_reports WHERE month=? AND notes=?',['2001-02','HTTP fixture '.$prefix]);",prefix)
