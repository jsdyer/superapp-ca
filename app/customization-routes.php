<?php
declare(strict_types=1);
function customization_post(string $path,array $u): void {
    $id=(int)($_POST['id']??0);
    switch($path){
        case '/admin/features/save':require_admin();save_instance_options($u,$_POST);flash('Instance settings saved. Disabled tools keep their data.');redirect('/admin/features');
        case '/customize/save':save_preferences($u,$_POST);flash('Saved. This corner feels more like you.');redirect('/customize');
        case '/welcome/save':save_preferences($u,$_POST);flash('Your little corner is ready. Pick one small thing and make it yours.');redirect(member_landing($u));
        case '/pocket/save':save_pocket_task($u,$_POST,$id?:null);flash('Pocket list saved.');redirect('/pocket');
        case '/pocket/toggle':
            if(!one('SELECT id FROM pocket_tasks WHERE id=? AND user_id=?',[$id,$u['id']]))fail(404,'Task not found.');
            $done=choice($_POST,'completed',['0','1'],'1');query('UPDATE pocket_tasks SET completed=? WHERE id=? AND user_id=?',[(int)$done,$id,$u['id']]);redirect('/pocket');
        case '/pocket/delete':if(!query('DELETE FROM pocket_tasks WHERE id=? AND user_id=?',[$id,$u['id']])->rowCount())fail(404,'Task not found.');flash('Task removed.');redirect('/pocket');
        case '/ideas/draft':$draft=idea_to_draft($u,$id);flash('A private draft is ready for your own words.');redirect('/compose?id='.$draft);
    }
}
function customization_get(string $path): void {
    if($path==='/admin/features'){require_admin();render('features',['title'=>'Choose your Superapp','options'=>instance_options(),'noindex'=>true]);}
    if($path==='/customize'||$path==='/welcome'){
        $u=require_user();render($path==='/welcome'?'welcome':'customize',['title'=>'Make this corner yours','prefs'=>preferences($u),'noindex'=>true]);
    }
    if($path==='/themes'){
        $theme=choice($_GET,'theme',array_keys(THEMES),instance_options()['theme']);render('themes',['title'=>'Find your colours','preview_theme'=>$theme]);
    }
    if($path==='/pocket'){
        $u=require_user();$edit=null;if(isset($_GET['edit'])){$edit=one('SELECT * FROM pocket_tasks WHERE id=? AND user_id=?',[(int)$_GET['edit'],$u['id']]);if(!$edit)fail(404,'Task not found.');}
        render('pocket',['title'=>'My pocket list','tasks'=>rows('SELECT * FROM pocket_tasks WHERE user_id=? ORDER BY completed,id DESC',[$u['id']]),'edit'=>$edit,'noindex'=>true]);
    }
    if($path==='/ideas'){
        if(isset($_GET['card'])&&!preg_match('/^[0-9]+$/D',input($_GET,'card',3)))fail(422,'Choose a card number.');$index=isset($_GET['card'])?(int)input($_GET,'card',3):((int)gmdate('z')%count(IDEA_CARDS));if(!isset(IDEA_CARDS[$index]))fail(404,'Idea not found.');
        render('ideas',['title'=>'A small idea','card'=>IDEA_CARDS[$index],'index'=>$index]);
    }
    if($path==='/focus')render('focus',['title'=>'A little room to focus']);
}
