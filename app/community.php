<?php
declare(strict_types=1);

const PROJECT_STATES = ['planning'=>'Planning', 'building'=>'Building', 'paused'=>'On pause', 'finished'=>'Finished'];
const CONNECTIONS = ['ownership'=>'Ownership', 'hosting'=>'Hosting / data location', 'manufacturing'=>'Manufacturing'];
function choice(array $data, string $key, array $options, string $default): string {
    $value=input($data,$key,30,$default);
    if (!in_array($value,$options,true)) fail(422,'Choose a valid '.$key.'.');
    return $value;
}
function required_text(array $data, string $key, int $max): string {
    $value=input($data,$key,$max);
    if ($value==='') fail(422,'Please fill in '.$key.'.');
    return $value;
}
function cents(string $value): int {
    if (!preg_match('/^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?$/D',$value,$m)) fail(422,'Use a CAD amount from 0 to 9,999,999.99 with at most two decimal places.');
    return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
}
function cad(int $value): string { return 'CA$'.number_format($value/100,2); }
function amount_input(int $value): string { return number_format($value/100,2,'.',''); }
function owned_project(array $user, int $id): array {
    return one('SELECT * FROM projects WHERE id=? AND user_id=?',[$id,$user['id']]) ?? throw new HttpError('Project not found.',404);
}
function public_project(int $id, ?array $viewer): array {
    $p=one('SELECT p.*,u.username,u.listed FROM projects p JOIN users u ON u.id=p.user_id WHERE p.id=? AND u.suspended=0',[$id]);
    if (!$p || !visible_entry($p,$viewer)) fail(404,'Project not found.');
    return $p;
}
function save_start_link(array $user, array $data, ?int $id=null): int {
    protect_demo($user);
    if ($id && !one('SELECT id FROM start_links WHERE id=? AND user_id=?',[$id,$user['id']])) fail(404,'Shortcut not found.');
    $title=required_text($data,'title',100);
    $target=web_url(input($data,'target_url',2000),true);
    $group=input($data,'group_name',50)?:'Everyday';
    $position=input($data,'position',4,'0');
    if (!preg_match('/^[0-9]{1,4}$/D',$position)) fail(422,'Order must be a whole number from 0 to 9999.');
    if ($id) query('UPDATE start_links SET title=?,target_url=?,group_name=?,position=? WHERE id=? AND user_id=?',[$title,$target,$group,(int)$position,$id,$user['id']]);
    else {
        if ((int)one('SELECT COUNT(*) AS n FROM start_links WHERE user_id=?',[$user['id']])['n']>=100) fail(422,'Keep your start page to 100 shortcuts.');
        query('INSERT INTO start_links (user_id,title,target_url,group_name,position) VALUES (?,?,?,?,?)',[$user['id'],$title,$target,$group,(int)$position]);
        $id=(int)db()->lastInsertId();
    }
    return $id;
}
function save_start_note(array $user, array $data): void {
    protect_demo($user);$body=input($data,'body',4000);
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') query('INSERT INTO start_notes (user_id,body) VALUES (?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)',[$user['id'],$body]);
    else query('INSERT INTO start_notes (user_id,body) VALUES (?,?) ON CONFLICT(user_id) DO UPDATE SET body=excluded.body',[$user['id'],$body]);
}
function save_project(array $user, array $data, ?int $id=null): int {
    protect_demo($user);$old=$id?owned_project($user,$id):[];$data=array_replace($old,$data);
    $title=required_text($data,'title',160);$summary=input($data,'summary',15000);
    $source=web_url(input($data,'source_url',2000));
    $state=choice($data,'state',array_keys(PROJECT_STATES),'planning');
    $visibility=choice($data,'visibility',['private','unlisted','public'],'private');
    if ($id) query('UPDATE projects SET title=?,summary=?,source_url=?,state=?,visibility=?,updated_at=? WHERE id=? AND user_id=?',[$title,$summary,$source,$state,$visibility,now(),$id,$user['id']]);
    else {
        if ((int)one('SELECT COUNT(*) AS n FROM projects WHERE user_id=?',[$user['id']])['n']>=100) fail(422,'This workspace has reached its 100 project limit.');
        query('INSERT INTO projects (user_id,title,summary,source_url,state,visibility,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',[$user['id'],$title,$summary,$source,$state,$visibility,now(),now()]);$id=(int)db()->lastInsertId();
    }
    return $id;
}
function save_project_update(array $user, int $projectId, array $data, ?int $id=null): int {
    protect_demo($user);owned_project($user,$projectId);
    if ($id && !one('SELECT id FROM project_updates WHERE id=? AND project_id=?',[$id,$projectId])) fail(404,'Update not found.');
    $kind=choice($data,'kind',['log','milestone','part'],'log');$title=required_text($data,'title',160);$body=input($data,'body',15000);
    $cost=$kind==='part'?cents(input($data,'cost',12,'0')):0;
    $done=$kind==='milestone'&&isset($data['completed'])?1:0;
    if ($id) query('UPDATE project_updates SET kind=?,title=?,body=?,cost_cents=?,completed=? WHERE id=? AND project_id=?',[$kind,$title,$body,$cost,$done,$id,$projectId]);
    else {
        if ((int)one('SELECT COUNT(*) AS n FROM project_updates WHERE project_id=?',[$projectId])['n']>=500) fail(422,'Keep each project to 500 updates.');
        query('INSERT INTO project_updates (project_id,kind,title,body,cost_cents,completed,created_at) VALUES (?,?,?,?,?,?,?)',[$projectId,$kind,$title,$body,$cost,$done,now()]);$id=(int)db()->lastInsertId();
    }
    query('UPDATE projects SET updated_at=? WHERE id=?',[now(),$projectId]);
    return $id;
}
function ring_for(int $id): array {
    return one('SELECT r.*,u.username,u.listed AS owner_listed FROM rings r JOIN users u ON u.id=r.user_id WHERE r.id=? AND u.suspended=0',[$id]) ?? throw new HttpError('Webring not found.',404);
}
function manage_ring(array $user,int $id): array {
    protect_demo($user);$r=ring_for($id);
    if ((int)$r['user_id']!==(int)$user['id'] && $user['role']!=='admin') fail(403,'Only the ring owner or an administrator can manage this ring.');
    return $r;
}
function save_ring(array $user,array $data,?int $id=null): int {
    protect_demo($user);if($id)manage_ring($user,$id);
    $name=required_text($data,'name',100);$description=required_text($data,'description',4000);
    if($id)query('UPDATE rings SET name=?,description=? WHERE id=?',[$name,$description,$id]);
    else {
        if ((int)one('SELECT COUNT(*) AS n FROM rings WHERE user_id=?',[$user['id']])['n']>=10)fail(422,'You can tend up to 10 webrings.');
        db()->beginTransaction();
        try {
            query('INSERT INTO rings (user_id,name,description,created_at) VALUES (?,?,?,?)',[$user['id'],$name,$description,now()]);$id=(int)db()->lastInsertId();
            query("INSERT INTO ring_members (ring_id,user_id,status,created_at) VALUES (?,?,'approved',?)",[$id,$user['id'],now()]);db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
    }
    return $id;
}
function join_ring(array $user,int $id,array $data): void {
    protect_demo($user);ring_for($id);$website=web_url(input($data,'website',2000));
    if(one('SELECT id FROM ring_members WHERE ring_id=? AND user_id=?',[$id,$user['id']]))fail(422,'You already belong to this ring or have a pending request. Leave first to change your website.');
    if((int)one('SELECT COUNT(*) AS n FROM ring_members WHERE ring_id=?',[$id])['n']>=500)fail(422,'This ring has reached its 500 membership/request limit.');
    query('INSERT INTO ring_members (ring_id,user_id,website,created_at) VALUES (?,?,?,?)',[$id,$user['id'],$website,now()]);
}
function ring_people(int $id): array {
    return rows("SELECT m.*,u.username,u.display_name FROM ring_members m JOIN users u ON u.id=m.user_id WHERE m.ring_id=? AND m.status='approved' AND u.suspended=0 AND u.listed=1 ORDER BY m.id",[$id]);
}
function ring_destination(int $id,int $member,string $direction): array {
    ring_for($id);
    if(!in_array($direction,['next','previous','random'],true))fail(422,'Choose next, previous, or random.');
    $people=ring_people($id);$index=array_search($member,array_column($people,'id'));
    if($index===false)fail(404,'This member is not on the public ring.');
    if(count($people)<2)fail(422,'This ring needs at least two discoverable members to travel.');
    if($direction==='random'){$others=$people;array_splice($others,(int)$index,1);return $others[random_int(0,count($others)-1)];}
    $offset=$direction==='next'?1:-1;return $people[($index+$offset+count($people))%count($people)];
}
function save_funding(array $user,array $data): void {
    protect_demo($user);if($user['role']!=='admin')fail(403,'Only administrators can publish financial reports.');
    $month=input($data,'month',7);
    if(!preg_match('/^[2-9][0-9]{3}-(0[1-9]|1[0-2])$/D',$month)||$month>gmdate('Y-m'))fail(422,'Choose the current month or an earlier month (YYYY-MM).');
    $values=[];foreach(['hosting','backups','other','contributions'] as $key)$values[]=cents(input($data,$key,12,'0'));
    $values[]=input($data,'notes',4000);$values[]=now();
    if(one('SELECT month FROM funding_reports WHERE month=?',[$month]))query('UPDATE funding_reports SET hosting_cents=?,backups_cents=?,other_cents=?,contributions_cents=?,notes=?,updated_at=? WHERE month=?',[...$values,$month]);
    else query('INSERT INTO funding_reports (hosting_cents,backups_cents,other_cents,contributions_cents,notes,updated_at,month) VALUES (?,?,?,?,?,?,?)',[...$values,$month]);
}
function directory_evidence(array $data): array {
    $facts=[];
    foreach(CONNECTIONS as $key=>$label){
        $description=input($data,$key.'_description',500);
        if($description==='')continue;
        $source=web_url(input($data,$key.'_source',2000),true);$date=input($data,$key.'_date',10);
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$d||$d->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))fail(422,'Use a real source-check date, today or earlier.');
        $facts[]=[$key,$description,$source,$date];
    }
    return $facts;
}
function store_directory_evidence(int $id,string $alternatives,array $facts): void {
    query('DELETE FROM directory_details WHERE directory_id=?',[$id]);
    query('INSERT INTO directory_details (directory_id,alternatives) VALUES (?,?)',[$id,$alternatives]);
    query('DELETE FROM directory_facts WHERE directory_id=?',[$id]);
    foreach($facts as $fact)query('INSERT INTO directory_facts (directory_id,dimension,description,evidence_url,checked_on) VALUES (?,?,?,?,?)',[$id,...$fact]);
}
function directory_correction(array $user,int $id,array $data): void {
    protect_demo($user);
    if(!one("SELECT d.id FROM directory d JOIN users u ON u.id=d.user_id WHERE d.id=? AND d.status='approved' AND u.suspended=0",[$id]))fail(404,'Listing not found.');
    $body=required_text($data,'body',3000);$source=web_url(input($data,'evidence_url',2000),true);
    query('INSERT INTO directory_corrections (directory_id,user_id,body,evidence_url,created_at) VALUES (?,?,?,?,?)',[$id,$user['id'],$body,$source,now()]);
}
function community_export(array $user): array {
    $id=$user['id'];$projects=rows('SELECT * FROM projects WHERE user_id=? ORDER BY id',[$id]);
    foreach($projects as &$p)$p['updates']=rows('SELECT * FROM project_updates WHERE project_id=? ORDER BY id',[$p['id']]);unset($p);
    return [
        'start_page'=>['links'=>rows('SELECT title,target_url,group_name,position FROM start_links WHERE user_id=? ORDER BY group_name,position,id',[$id]),'note'=>one('SELECT body FROM start_notes WHERE user_id=?',[$id])['body']??''],
        'projects'=>$projects,
        'webrings_owned'=>rows('SELECT * FROM rings WHERE user_id=?',[$id]),
        'webring_memberships'=>rows('SELECT m.*,r.name FROM ring_members m JOIN rings r ON r.id=m.ring_id WHERE m.user_id=?',[$id]),
        'directory_evidence'=>rows('SELECT f.* FROM directory_facts f JOIN directory d ON d.id=f.directory_id WHERE d.user_id=?',[$id]),
        'directory_alternatives'=>rows('SELECT x.* FROM directory_details x JOIN directory d ON d.id=x.directory_id WHERE d.user_id=?',[$id]),
        'directory_corrections'=>rows('SELECT * FROM directory_corrections WHERE user_id=?',[$id])
    ];
}
