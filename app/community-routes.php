<?php
declare(strict_types=1);
// Called after the front controller's session, CSRF, authentication and rate-limit checks.
function community_post(string $path,array $u): void {
    $id=(int)($_POST['id']??0);
    switch($path){
        case '/start/link/save':save_start_link($u,$_POST,$id?:null);flash('Shortcut saved.');redirect('/start');
        case '/start/link/delete':
            if(!query('DELETE FROM start_links WHERE id=? AND user_id=?',[$id,$u['id']])->rowCount())fail(404,'Shortcut not found.');
            flash('Shortcut removed.');redirect('/start');
        case '/start/note':save_start_note($u,$_POST);flash('Private note saved.');redirect('/start');
        case '/projects/save':$id=save_project($u,$_POST,$id?:null);flash('Project saved.');redirect('/projects/'.$id);
        case '/projects/delete':owned_project($u,$id);query('DELETE FROM projects WHERE id=? AND user_id=?',[$id,$u['id']]);flash('Project and its updates deleted.');redirect('/workbench');
        case '/projects/update/save':$project=(int)($_POST['project_id']??0);save_project_update($u,$project,$_POST,$id?:null);flash('Workbench updated.');redirect('/projects/'.$project);
        case '/projects/update/delete':
            $project=(int)($_POST['project_id']??0);owned_project($u,$project);
            if(!query('DELETE FROM project_updates WHERE id=? AND project_id=?',[$id,$project])->rowCount())fail(404,'Update not found.');
            flash('Update deleted.');redirect('/projects/'.$project);
        case '/rings/save':$id=save_ring($u,$_POST,$id?:null);flash('Your neighbourhood is ready.');redirect('/rings/'.$id);
        case '/rings/join':rate_limit('ring-join:'.$u['id'],10,3600);join_ring($u,$id,$_POST);flash('Your request is waiting for the ring owner.');redirect('/rings/'.$id);
        case '/rings/leave':query('DELETE FROM ring_members WHERE ring_id=? AND user_id=?',[$id,$u['id']]);flash('You have left the membership list. Owned rings remain yours to manage.');redirect('/rings/'.$id);
        case '/rings/member':
            manage_ring($u,$id);$member=(int)($_POST['member_id']??0);$decision=choice($_POST,'decision',['approve','remove'],'approve');
            if($decision==='approve')query("UPDATE ring_members SET status='approved' WHERE id=? AND ring_id=?",[$member,$id]);
            else query('DELETE FROM ring_members WHERE id=? AND ring_id=?',[$member,$id]);
            flash('Membership updated.');redirect('/rings/'.$id);
        case '/rings/delete':manage_ring($u,$id);query('DELETE FROM rings WHERE id=?',[$id]);flash('Webring deleted.');redirect('/rings');
        case '/funding/save':require_admin();save_funding($u,$_POST);flash('Monthly report published.');redirect('/funding');
        case '/funding/delete':require_admin();query('DELETE FROM funding_reports WHERE month=?',[input($_POST,'month',7)]);flash('Report removed.');redirect('/funding');
        case '/directory/correction':rate_limit('correction:'.$u['id'],10,3600);directory_correction($u,$id,$_POST);flash('Your sourced correction is with the moderator.');redirect('/directory');
        case '/admin/correction':require_admin();query("UPDATE directory_corrections SET status='resolved' WHERE id=?",[$id]);redirect('/admin');
        case '/admin/directory/evidence':
            require_admin();if(!one('SELECT id FROM directory WHERE id=?',[$id]))fail(404,'Listing not found.');
            $facts=directory_evidence($_POST);$alternatives=input($_POST,'alternatives',250);
            $claim=choice($_POST,'claim',['Canadian-owned','Made in Canada','Canadian-hosted','Community organization'],'Canadian-owned');
            $source=web_url(input($_POST,'evidence_url',2000),true);$notes=input($_POST,'notes',3000);
            db()->beginTransaction();
            try {store_directory_evidence($id,$alternatives,$facts);query("UPDATE directory SET claim=?,evidence_url=?,notes=?,status='pending',reviewed_at='' WHERE id=?",[$claim,$source,$notes,$id]);db()->commit();}
            catch(Throwable $e){db()->rollBack();throw $e;}
            flash('Evidence saved. Review and approve the updated listing.');redirect('/admin');
    }
}
function community_get(string $path): void {
    if($path==='/start'){
        $u=require_user();$edit=null;
        if(isset($_GET['edit'])){$edit=one('SELECT * FROM start_links WHERE id=? AND user_id=?',[(int)$_GET['edit'],$u['id']]);if(!$edit)fail(404,'Shortcut not found.');}
        render('start',['title'=>'My start page','links'=>rows('SELECT * FROM start_links WHERE user_id=? ORDER BY group_name,position,id',[$u['id']]),'note'=>one('SELECT body FROM start_notes WHERE user_id=?',[$u['id']])['body']??'','edit'=>$edit,'projects'=>rows('SELECT * FROM projects WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 5',[$u['id']]),'recent'=>rows('SELECT * FROM entries WHERE user_id=? ORDER BY id DESC LIMIT 5',[$u['id']]),'noindex'=>true]);
    }
    if($path==='/workbench'){
        $u=require_user();render('workbench',['title'=>'My project workbench','projects'=>rows('SELECT * FROM projects WHERE user_id=? ORDER BY updated_at DESC,id DESC',[$u['id']]),'noindex'=>true]);
    }
    if($path==='/projects'){
        $q=input($_GET,'q',150);$state=input($_GET,'state',20);$where="p.visibility='public' AND u.suspended=0 AND u.listed=1";$params=[];
        if($q!==''){$where.=' AND (p.title LIKE ? OR p.summary LIKE ?)';$params=['%'.$q.'%','%'.$q.'%'];}
        if($state!==''){if(!isset(PROJECT_STATES[$state]))fail(422,'Choose a valid project state.');$where.=' AND p.state=?';$params[]=$state;}
        render('projects',['title'=>'Made by neighbours','q'=>$q,'state'=>$state,'projects'=>rows('SELECT p.*,u.username FROM projects p JOIN users u ON u.id=p.user_id WHERE '.$where.' ORDER BY p.updated_at DESC,p.id DESC LIMIT 100',$params)]);
    }
    if(preg_match('~^/projects/([1-9][0-9]*)$~D',$path,$m)){
        $u=current_user();$project=public_project((int)$m[1],$u);$owner=$u&&(int)$project['user_id']===(int)$u['id'];$edit=null;
        if(isset($_GET['update'])){if(!$owner)fail(404,'Update not found.');$edit=one('SELECT * FROM project_updates WHERE id=? AND project_id=?',[(int)$_GET['update'],$project['id']]);if(!$edit)fail(404,'Update not found.');}
        render('project',['title'=>$project['title'],'project'=>$project,'owner'=>$owner,'updates'=>rows('SELECT * FROM project_updates WHERE project_id=? ORDER BY id DESC',[$project['id']]),'edit'=>$edit,'noindex'=>$project['visibility']!=='public'||!$project['listed']]);
    }
    if($path==='/rings'){
        $u=current_user();$mine=$u?rows('SELECT DISTINCT r.id,r.name FROM rings r JOIN users owner ON owner.id=r.user_id LEFT JOIN ring_members m ON m.ring_id=r.id WHERE owner.suspended=0 AND (r.user_id=? OR m.user_id=?) ORDER BY r.id DESC LIMIT 100',[$u['id'],$u['id']]):[];
        render('rings',['title'=>'Small web neighbourhoods','my_rings'=>$mine,'rings'=>rows('SELECT r.*,u.username FROM rings r JOIN users u ON u.id=r.user_id WHERE u.suspended=0 AND u.listed=1 ORDER BY r.id DESC LIMIT 100')]);
    }
    if(preg_match('~^/rings/([1-9][0-9]*)(/hop)?$~D',$path,$m)){
        $ring=ring_for((int)$m[1]);$u=current_user();
        if(isset($m[2])){
            $destination=ring_destination((int)$m[1],(int)($_GET['member']??0),input($_GET,'direction',10,'next'));
            render('ring-hop',['title'=>'A little further around the web','ring'=>$ring,'destination'=>$destination]);
        }
        $manager=$u&&((int)$ring['user_id']===(int)$u['id']||$u['role']==='admin');
        render('ring',['title'=>$ring['name'],'ring'=>$ring,'people'=>ring_people((int)$ring['id']),'membership'=>$u?one('SELECT * FROM ring_members WHERE ring_id=? AND user_id=?',[$ring['id'],$u['id']]):null,'manager'=>$manager,'noindex'=>!$ring['owner_listed'],'requests'=>$manager?rows('SELECT m.*,u.username,u.suspended,u.listed FROM ring_members m JOIN users u ON u.id=m.user_id WHERE m.ring_id=? ORDER BY m.id',[$ring['id']]):[]]);
    }
    if($path==='/funding'){
        $u=current_user();$edit=null;
        if(isset($_GET['month'])){require_admin();$edit=one('SELECT * FROM funding_reports WHERE month=?',[input($_GET,'month',7)]);if(!$edit)fail(404,'Report not found.');}
        render('funding',['title'=>'Open books, open doors','reports'=>rows('SELECT * FROM funding_reports ORDER BY month DESC LIMIT 120'),'edit'=>$edit]);
    }
    if(preg_match('~^/admin/directory/([1-9][0-9]*)$~D',$path,$m)){
        require_admin();$listing=one('SELECT * FROM directory WHERE id=?',[(int)$m[1]]);if(!$listing)fail(404,'Listing not found.');
        render('directory-evidence',['title'=>'Review directory evidence','listing'=>$listing,'noindex'=>true]);
    }
}
