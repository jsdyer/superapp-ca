<?php
declare(strict_types=1);
const THEMES = ['cedar'=>'Cedar · forest & paper','maple'=>'Maple · brick & cream','terminal'=>'Terminal · phosphor & midnight','coast'=>'Coast · ocean & mist','prairie'=>'Prairie · wheat & warm earth','midnight'=>'Midnight · blue hour','paper'=>'Paper · ink & newsprint'];
const EXTRA_FEATURES = [
 'start'=>['My start page','Private shortcuts and notes.','grid','/start'],
 'projects'=>['Project workbench','Build logs, milestones and parts costs.','server','/workbench'],
 'rings'=>['Webrings','Owner-approved circles of small websites.','link','/rings'],
 'directory'=>['Buy Canadian','Sourced Canadian discoveries and alternatives.','leaf','/directory'],
 'garden'=>['/now garden','Public now pages and member discovery.','plant','/garden'],
 'uses'=>['/uses pages','Public equipment and software notes.','server','/profile'],
 'guestbook'=>['Guestbooks','Owner-moderated hellos.','book','/guestbook'],
 'commons'=>['The commons','Chronological public entry discovery.','radio','/commons'],
 'feeds'=>['RSS feeds','Public entries in open feed format.','radio','/feed.xml'],
 'support'=>['Voluntary support','Pay-what-you-can information and hosted payment link.','heart','/support'],
 'funding'=>['Open funding','Manual monthly operating reports.','heart','/funding'],
 'api'=>['Entry API','Bearer-token access for scripts.','code','/settings'],
 'pocket'=>['Pocket list','A small private checklist.','book','/pocket'],
 'ideas'=>['Idea cards','Small prompts for making and exploring.','compass','/ideas'],
 'focus'=>['Focus room','A quiet timer that runs in your browser.','sun','/focus'],
];
const WIDGETS=['shortcuts'=>'Shortcuts','note'=>'Private note','recent'=>'Recent entries','projects'=>'Recent projects','pocket'=>'Pocket list','ideas'=>'A small idea'];
const PRESETS=[
 'blank'=>['Start simple',['post','bookmark','start','pocket'],'cedar'],
 'tinkerer'=>['Linux tinkerer',['lab','paste','projects','start','pocket','focus'],'terminal'],
 'writer'=>['Small-web writer',['post','status','bookmark','rings','ideas'],'paper'],
 'wanderer'=>['Wandering maker',['field','archive','projects','ideas','directory'],'coast'],
];
const IDEA_CARDS=[
 ['title'=>'Write the rescue note','body'=>'Document the three steps that would get your home server back online after a bad update. Leave out passwords and private addresses.','kind'=>'lab','topic'=>'homelab'],
 ['title'=>'Give an old machine a second job','body'=>'Choose one unused device. Sketch a useful job it could do with free software before buying anything new.','kind'=>'lab','topic'=>'repair'],
 ['title'=>'Keep the context','body'=>'Pick one saved web link. Note who made it, why it matters, and what you would want to remember if the site disappeared.','kind'=>'archive','topic'=>'archiving'],
 ['title'=>'A small web introduction','body'=>'Write a short introduction to your own little website: what you make, what you are learning, and how a neighbour can say hello.','kind'=>'post','topic'=>'indieweb'],
 ['title'=>'One source, carefully read','body'=>'Choose a Canadian product or service you already use. Find a primary source and distinguish ownership, manufacturing, and hosting. Record what remains unknown.','kind'=>'bookmark','topic'=>'canada'],
 ['title'=>'The familiar street, differently','body'=>'On a public route you know well, notice a reflection, an old sign, or quiet infrastructure. Write down what caught your eye without sharing anyone’s private details.','kind'=>'field','topic'=>'exploring'],
 ['title'=>'A tiny shell kindness','body'=>'Describe a repetitive Linux task you could simplify with a short script. Write down how you would test it safely on disposable data.','kind'=>'paste','topic'=>'linux'],
 ['title'=>'The useful failure','body'=>'Tell the story of a small project that failed. Record one thing you would do differently and one thing you are glad you tried.','kind'=>'post','topic'=>'making'],
 ['title'=>'A backup worth trusting','body'=>'Plan a restore test using a spare location. What would convince you that the backup is complete and usable?','kind'=>'lab','topic'=>'homelab'],
 ['title'=>'A postcard from now','body'=>'Write five lines about what has your attention today. Keep the ordinary details that a future version of you might appreciate.','kind'=>'field','topic'=>'reflection'],
 ['title'=>'Share a good little corner','body'=>'Find a personal website or community resource worth revisiting. Explain its appeal in your own words and keep its original link.','kind'=>'bookmark','topic'=>'indieweb'],
 ['title'=>'Repair before replace','body'=>'Choose a small object that needs attention. Record the symptom, what you can safely inspect, and when it would make sense to ask someone experienced.','kind'=>'post','topic'=>'repair'],
];
function features(): array { $all=APPS;foreach(EXTRA_FEATURES as $key=>$value)$all[$key]=$value;return $all; }
function instance_options(bool $refresh=false): array {
    static $cache;
    if($refresh)$cache=null;
    if($cache===null){$row=one('SELECT value,revision FROM instance_settings WHERE name=?',['site']);$data=$row?json_decode($row['value'],true):[];
        $cache=array_replace(['enabled'=>array_keys(features()),'theme'=>'cedar','welcome'=>'A little space for your ideas, your tools, and your people.','revision'=>0],is_array($data)?$data:[]);$cache['revision']=(int)($row['revision']??0);}
    return $cache;
}
function feature_enabled(string $key): bool {
    $forced=array_filter(array_map('trim',explode(',',(string)cfg('disabled_features'))));
    return isset(features()[$key]) && in_array($key,instance_options()['enabled'],true) && !in_array($key,$forced,true);
}
function require_feature(string $key): void { if(!feature_enabled($key))fail(404,'This feature is switched off on this instance. Your existing data remains available in account exports.'); }
function active_apps(): array { return array_filter(APPS,fn($key)=>feature_enabled($key),ARRAY_FILTER_USE_KEY); }
function active_kinds_sql(): string { return implode(',',array_map(fn($key)=>"'".$key."'",array_keys(active_apps())))?:"'__none__'"; }
function string_list(array $data,string $key,array $allowed): array {
    $value=$data[$key]??[];
    if(!is_array($value)||!array_is_list($value)||count($value)>count($allowed))fail(422,'Invalid '.$key.' list.');
    foreach($value as $item)if(!is_string($item)||!in_array($item,$allowed,true))fail(422,'Unknown '.$key.' choice.');
    return array_values(array_unique($value));
}
function save_instance_options(array $user,array $data): void {
    protect_demo($user);if($user['role']!=='admin')fail(403,'Only administrators can configure the instance.');
    $enabled=string_list($data,'enabled',array_keys(features()));$theme=choice($data,'theme',array_keys(THEMES),'cedar');
    $welcome=input($data,'welcome',300);$revision=input($data,'revision',12,'0');if(!preg_match('/^[0-9]+$/D',$revision))fail(422,'Invalid revision.');
    $json=json_encode(compact('enabled','theme','welcome'),JSON_THROW_ON_ERROR);
    if((int)$revision===0){try{query('INSERT INTO instance_settings (name,value,revision) VALUES (?,?,1)',['site',$json]);}catch(PDOException $e){if(one('SELECT name FROM instance_settings WHERE name=?',['site']))fail(409,'Settings changed in another window. Reload before saving.');throw $e;}}
    elseif(!query('UPDATE instance_settings SET value=?,revision=revision+1 WHERE name=? AND revision=?',[$json,'site',(int)$revision])->rowCount())fail(409,'Settings changed in another window. Reload before saving.');
    instance_options(true);
}
function preferences(array $u,bool $refresh=false): array {
    static $cache=[];$id=(int)$u['id'];if($refresh)unset($cache[$id]);if(isset($cache[$id]))return $cache[$id];
    $row=one('SELECT settings FROM member_preferences WHERE user_id=?',[$u['id']]);$data=$row?json_decode($row['settings'],true):[];
    return $cache[$id]=array_replace(['favourites'=>['post','bookmark','start','projects'],'widgets'=>array_keys(WIDGETS),'font'=>'classic','density'=>'comfortable','banner'=>'contours','avatar'=>'initials','landing'=>'start','setup_done'=>false],is_array($data)?$data:[]);
}
function save_preferences(array $u,array $data): void {
    protect_demo($u);$p=preferences($u);
    $hidden=array_filter($p['favourites'],fn($key)=>isset(features()[$key])&&!feature_enabled($key));
    $p['favourites']=array_values(array_unique(array_merge(string_list($data,'favourites',array_keys(features())),$hidden)));$p['widgets']=string_list($data,'widgets',array_keys(WIDGETS));
    foreach(['font'=>['classic','sans','mono'],'density'=>['comfortable','compact'],'banner'=>['none','contours','grid','dots'],'avatar'=>['initials','leaf','plant','code','compass','radio'],'landing'=>['start','workspace']] as $key=>$values)$p[$key]=choice($data,$key,$values,$values[0]);
    $theme=choice($data,'theme',array_keys(THEMES),'cedar');$p['setup_done']=true;
    db()->beginTransaction();try{
        $json=json_encode($p,JSON_THROW_ON_ERROR);
        if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')query('INSERT INTO member_preferences (user_id,settings) VALUES (?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings)',[$u['id'],$json]);
        else query('INSERT INTO member_preferences (user_id,settings) VALUES (?,?) ON CONFLICT(user_id) DO UPDATE SET settings=excluded.settings',[$u['id'],$json]);
        query('UPDATE users SET theme=?,updated_at=? WHERE id=?',[$theme,now(),$u['id']]);db()->commit();preferences($u,true);
    }catch(Throwable $e){db()->rollBack();throw $e;}
}
function member_landing(array $u): string { return preferences($u)['landing']==='start'&&feature_enabled('start')?'/start':'/workspace'; }
function favourite_features(array $u): array { $favourites=preferences($u)['favourites'];return array_filter(features(),fn($key)=>feature_enabled($key)&&in_array($key,$favourites,true),ARRAY_FILTER_USE_KEY); }
function widget_visible(array $u,string $widget): bool {
    if(!in_array($widget,preferences($u)['widgets'],true))return false;
    return !in_array($widget,['projects','pocket','ideas'],true)||feature_enabled($widget);
}
function feature_path(string $key): string { return isset(APPS[$key])?'/workspace?kind='.$key:EXTRA_FEATURES[$key][3]; }
function guarded_route(string $path): void {
    $routes=['/start'=>'start','/projects'=>'projects','/workbench'=>'projects','/rings'=>'rings','/directory'=>'directory','/garden'=>'garden','/guestbook'=>'guestbook','/funding'=>'funding','/support'=>'support','/commons'=>'commons','/feed.xml'=>'feeds','/api'=>'api','/token/create'=>'api','/pocket'=>'pocket','/ideas'=>'ideas','/focus'=>'focus'];
    foreach($routes as $prefix=>$key)if($path===$prefix||str_starts_with($path,$prefix.'/'))require_feature($key);
    if(preg_match('~^/@[^/]+/(now|uses|feed\.xml)$~D',$path,$m))require_feature(['now'=>'garden','uses'=>'uses','feed.xml'=>'feeds'][$m[1]]);
}
function save_pocket_task(array $u,array $data,?int $id=null): int {
    require_feature('pocket');protect_demo($u);$title=required_text($data,'title',240);
    if($id){if(!one('SELECT id FROM pocket_tasks WHERE id=? AND user_id=?',[$id,$u['id']]))fail(404,'Task not found.');query('UPDATE pocket_tasks SET title=? WHERE id=? AND user_id=?',[$title,$id,$u['id']]);}
    else {if((int)one('SELECT COUNT(*) AS n FROM pocket_tasks WHERE user_id=?',[$u['id']])['n']>=200)fail(422,'Your pocket holds 200 tasks. Remove finished tasks to make room.');query('INSERT INTO pocket_tasks (user_id,title,created_at) VALUES (?,?,?)',[$u['id'],$title,now()]);$id=(int)db()->lastInsertId();}
    return $id;
}
function idea_to_draft(array $u,int $index): int {
    require_feature('ideas');if(!isset(IDEA_CARDS[$index]))fail(404,'Idea not found.');$idea=IDEA_CARDS[$index];
    $kind=isset(active_apps()[$idea['kind']])?$idea['kind']:(isset(active_apps()['post'])?'post':null);
    // URL-requiring tools need an actual URL: keep the prompt as a notebook draft instead.
    if(in_array($kind,['bookmark','archive','link'],true))$kind=feature_enabled('post')?'post':null;
    if(!$kind)fail(422,'Enable Notebook or this idea’s writing app to save a draft.');
    return save_entry($u,['kind'=>$kind,'title'=>$idea['title'],'body'=>$idea['body']."\n\nMy notes:\n",'tags'=>$idea['topic'],'visibility'=>'private']);
}
