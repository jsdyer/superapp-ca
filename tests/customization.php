<?php
declare(strict_types=1);
putenv('SUPERAPP_DSN='.(getenv('SUPERAPP_TEST_DSN')?:'sqlite::memory:'));
putenv('SUPERAPP_DB_USER='.(getenv('SUPERAPP_TEST_DB_USER')?:''));
putenv('SUPERAPP_DB_PASS='.(getenv('SUPERAPP_TEST_DB_PASS')?:''));
putenv('SUPERAPP_DEMO=false');putenv('SUPERAPP_DISABLED_FEATURES=');
require __DIR__.'/../app/bootstrap.php';
$checks=0;
function ok(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException('FAILED: '.$label);echo 'ok '.(++$checks).' - '.$label."\n";}
function rejects(callable $fn,int $code,string $label):void{try{$fn();}catch(HttpError $e){ok($e->getCode()===$code,$label);return;}throw new RuntimeException('FAILED: '.$label);}
migrate();$old=one('SELECT * FROM instance_settings WHERE name=?',['site']);query('DELETE FROM instance_settings WHERE name=?',['site']);instance_options(true);
$prefix='cu'.bin2hex(random_bytes(4));$a=create_user(['username'=>$prefix.'a','email'=>$prefix.'a@example.invalid','password'=>'customization test passphrase'],'admin');$b=create_user(['username'=>$prefix.'b','email'=>$prefix.'b@example.invalid','password'=>'another test passphrase']);
$admin=one('SELECT * FROM users WHERE id=?',[$a]);$member=one('SELECT * FROM users WHERE id=?',[$b]);
function configure(array $admin,array $enabled,array $extra=[]):void{save_instance_options($admin,array_replace(['enabled'=>$enabled,'theme'=>'cedar','welcome'=>'Welcome','revision'=>(string)instance_options()['revision']],$extra));}
try{
 ok(count(active_apps())===8,'all original writing apps enabled by default');
 ok(count(THEMES)===7,'seven bounded themes offered');
 rejects(fn()=>configure($member,[]),403,'member cannot change instance settings');
 rejects(fn()=>configure($admin,['not-real']),422,'unknown feature rejected');
 rejects(fn()=>configure($admin,[],['theme'=>'url(evil)']),422,'arbitrary theme rejected');
 $entry=save_entry($member,['kind'=>'post','title'=>'Hidden when disabled','slug'=>'flagged-post','visibility'=>'public']);
 $all=array_keys(features());configure($admin,array_values(array_diff($all,['post','api','projects','rings','garden','uses','guestbook','funding','directory','pocket','ideas','focus','feeds'])));
 ok(!feature_enabled('post')&&!feature_enabled('api'),'operator settings change effective flags');
 ok(!isset(active_apps()['post']),'disabled entry kind absent from app registry');
 ok(!str_contains(json_encode(public_entries()),'Hidden when disabled'),'disabled entries excluded from public discovery');
 ok(!visible_entry(one('SELECT * FROM entries WHERE id=?',[$entry]),$member),'disabled entry permalink hidden even from owner');
 rejects(fn()=>save_entry($member,['kind'=>'post','title'=>'blocked']),404,'disabled entry create rejected');
 rejects(fn()=>save_entry($member,['body'=>'blocked'],$entry),404,'disabled entry update rejected');
 rejects(fn()=>save_entry($member,['kind'=>'status'],$entry),404,'changing kind cannot bypass disabled original kind');
 rejects(fn()=>delete_entry($member,$entry),404,'disabled entry delete rejected');
 ok(count(export_account($member)['entries'])===1,'disabled data remains in owner export');
 foreach(['/projects','/projects/42','/projects/save','/workbench','/rings/join','/directory/submit','/garden','/@member/now','/@member/uses','/@member/feed.xml','/guestbook/sign','/funding','/api/me','/api/entries','/token/create','/pocket/toggle','/ideas/draft','/focus','/feed.xml'] as $path)rejects(fn()=>guarded_route($path),404,'disabled route guarded: '.$path);
 foreach(['/export','/export/bookmarks','/account/delete','/password','/token/revoke','/admin/features','/customize','/profile','/workspace'] as $path){guarded_route($path);ok(true,'core route survives: '.$path);}
 rejects(fn()=>save_instance_options($admin,['enabled'=>$all,'theme'=>'cedar','revision'=>'0']),409,'stale initial settings form rejected');
 rejects(fn()=>save_instance_options($admin,['enabled'=>$all,'theme'=>'cedar','revision'=>'999']),409,'stale settings revision rejected');
 configure($admin,$all,['theme'=>'midnight']);
 ok(count(public_entries('post','',0,$b))===1,'reenabling restores original entry');
 global $config;$config['disabled_features']='api, post';
 ok(!feature_enabled('api')&&!feature_enabled('post'),'server hard-off overrides dashboard');$config['disabled_features']='';
 $prefs=['favourites'=>['start','lab','pocket'],'widgets'=>['note','pocket'],'theme'=>'coast','font'=>'mono','density'=>'compact','banner'=>'grid','avatar'=>'compass','landing'=>'start'];
 save_preferences($member,$prefs);$stored=preferences($member);
 ok($stored['favourites']===['start','lab','pocket']&&$stored['setup_done'],'member favourites and setup completion persist');
 ok(one('SELECT theme FROM users WHERE id=?',[$b])['theme']==='coast','public profile theme updated with preferences');
 ok($stored['font']==='mono'&&$stored['banner']==='grid'&&$stored['density']==='compact','bounded appearance preferences persist');
 ok(widget_visible($member,'note')&&!widget_visible($member,'shortcuts'),'widget selection applied');
 ok(count(favourite_features($member))===3,'only selected favourites displayed');
 configure($admin,array_values(array_diff($all,['start','pocket'])));
 ok(member_landing($member)==='/workspace','disabled start page falls back to workspace');
 ok(count(favourite_features($member))===1&&!widget_visible($member,'pocket'),'disabled favourites and widgets hidden');
 ok(preferences($member)['favourites']===['start','lab','pocket'],'operator switch does not erase member choices');
 save_preferences($member,array_replace($prefs,['favourites'=>['lab']]));
 ok(preferences($member)['favourites']===['lab','start','pocket'],'customizing while apps are disabled preserves hidden favourites');
 configure($admin,$all);
 rejects(fn()=>save_preferences($member,array_replace($prefs,['font'=>'css injection'])),422,'invalid font rejected');
 rejects(fn()=>save_preferences($member,array_replace($prefs,['widgets'=>['fake']])),422,'unknown widget rejected');
 rejects(fn()=>save_preferences($member,array_replace($prefs,['favourites'=>'not an array'])),422,'malformed favourites rejected');
 $task=save_pocket_task($member,['title'=>'Keep the backups labelled']);
 rejects(fn()=>save_pocket_task($admin,['title'=>'stolen'],$task),404,'pocket edit enforces owner');
 rejects(fn()=>save_pocket_task($member,['title'=>'']),422,'empty task rejected');
 save_pocket_task($member,['title'=>'Test a restore'],$task);ok(one('SELECT title FROM pocket_tasks WHERE id=?',[$task])['title']==='Test a restore','task edit persists');
 $draft=idea_to_draft($member,0);$d=one('SELECT * FROM entries WHERE id=?',[$draft]);ok($d['visibility']==='private'&&$d['kind']==='lab','idea creates a private draft');
 $bookmarkIdea=idea_to_draft($member,4);ok(one('SELECT kind FROM entries WHERE id=?',[$bookmarkIdea])['kind']==='post','URL-requiring idea falls back to notebook draft');
 rejects(fn()=>idea_to_draft($member,999),404,'unknown idea rejected');
 configure($admin,[]);ok(active_apps()===[],'core-only instance supported');
 ok(count(export_account($member)['pocket_tasks'])===1,'pocket records export while disabled');
 ok(export_account($member)['preferences']['avatar']==='compass','preferences included in export');
 rejects(fn()=>save_pocket_task($member,['title'=>'blocked']),404,'disabled pocket blocks writes');
 configure($admin,['ideas']);rejects(fn()=>idea_to_draft($member,0),422,'idea draft gracefully rejects when writing apps are off');
 migrate();ok(one('SELECT id FROM pocket_tasks WHERE id=?',[$task])!==null,'migration rerun preserves pocket records');
 query('DELETE FROM users WHERE id=?',[$b]);
 ok(!one('SELECT user_id FROM member_preferences WHERE user_id=?',[$b]),'preferences cascade on account deletion');
 ok(!one('SELECT id FROM pocket_tasks WHERE user_id=?',[$b]),'pocket records cascade on account deletion');
 echo "PASS: {$checks} customization checks on ".db()->getAttribute(PDO::ATTR_DRIVER_NAME)."\n";
}finally{
 query('DELETE FROM users WHERE id IN (?,?)',[$a,$b]);query('DELETE FROM instance_settings WHERE name=?',['site']);
 if($old)query('INSERT INTO instance_settings (name,value,revision) VALUES (?,?,?)',[$old['name'],$old['value'],$old['revision']]);instance_options(true);
}
