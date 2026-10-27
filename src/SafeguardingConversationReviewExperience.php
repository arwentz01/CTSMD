<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/SafeguardingConversationReviewService.php';

final class SafeguardingConversationReviewExperience
{
    private const ROUTE='/safeguarding/conversations';
    public static function handles(string $route):bool{return $route===self::ROUTE;}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        try{SafeguardingConversationReviewService::assertReviewer($db,$user);}
        catch(RuntimeException){http_response_code(403);exit('Explicit safeguarding review permission required.');}
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
        $_SESSION['safeguarding_dm_csrf']??=bin2hex(random_bytes(24));
        $error=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!hash_equals((string)$_SESSION['safeguarding_dm_csrf'],(string)($_POST['csrf_token']??''))){http_response_code(403);exit('Your session expired.');}
            $id=filter_var($_POST['conversation_id']??null,FILTER_VALIDATE_INT)?:0;
            $reason=trim((string)($_POST['reason']??''));
            $caseId=filter_var($_POST['case_id']??null,FILTER_VALIDATE_INT)?:null;
            // Persist the reason only in this authenticated session, not the URL.
            if($id>0&&mb_strlen($reason)>=12&&mb_strlen($reason)<=1000){
                $_SESSION['safeguarding_dm_review']=['id'=>$id,'reason'=>$reason,'case_id'=>$caseId,'reviewer_id'=>(int)$user['id'],'authorized_at'=>time()];
                header('Location: '.($basePath?:'').self::ROUTE.'?conversation_id='.$id,true,303);exit;
            }
            $error='Provide a valid conversation and a review reason of at least 12 characters.';
        }
        $searchA=trim((string)($_GET['participant_a']??''));
        $searchB=trim((string)($_GET['participant_b']??''));
        $idA=filter_var($_GET['user_a']??null,FILTER_VALIDATE_INT)?:null;
        $idB=filter_var($_GET['user_b']??null,FILTER_VALIDATE_INT)?:null;
        $page=max(1,min(10000,(int)($_GET['page']??1)));
        $search=SafeguardingConversationReviewService::search($db,$idA,$idB,mb_substr($searchA,0,100),mb_substr($searchB,0,100),$page);
        $selected=null;$viewId=filter_var($_GET['conversation_id']??null,FILTER_VALIDATE_INT)?:0;
        $sessionReview=$_SESSION['safeguarding_dm_review']??null;
        if($viewId>0){
            if(!is_array($sessionReview)||(int)($sessionReview['id']??0)!==$viewId||(int)($sessionReview['reviewer_id']??0)!==(int)$user['id']||(int)($sessionReview['authorized_at']??0)<time()-900){
                $error='Choose Review and provide a reason to open conversation content.';
            }else{
                try{$selected=SafeguardingConversationReviewService::review($db,$user,$viewId,(string)$sessionReview['reason'],$sessionReview['case_id'],max(1,(int)($_GET['message_page']??1)));}
                catch(RuntimeException $e){$error=$e->getMessage();}
            }
        }
        self::page($basePath,$user,$search,$selected,$error,$searchA,$searchB,$idA,$idB);
    }

    private static function page(string $base,array $user,array $search,?array $selected,?string $error,string $a,string $b,?int $idA,?int $idB):never
    {
        $u=static fn(string $path):string=>($base?:'').$path;
        $e=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $params=['participant_a'=>$a,'participant_b'=>$b];
        if($idA)$params['user_a']=$idA;if($idB)$params['user_b']=$idB;
        $query=http_build_query($params);
        $nav=[['label'=>'Cases','href'=>'/safeguarding/cases','active'=>false],['label'=>'Conversation review','href'=>self::ROUTE,'active'=>true],['label'=>'Credential & integrity review','href'=>'/safeguarding','active'=>false],['label'=>'Audit history','href'=>'/safeguarding/audit','active'=>false]];
        header('Content-Type: text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conversation review · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/safeguarding-conversations.css?v=20261026')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$base,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Restricted safeguarding','Conversation review',$base,$nav);?><div class="sgdm-page">
        <header class="sgdm-hero"><small>SAFEGUARDING · AUDITED OVERSIGHT</small><h1>Conversation review</h1><p>Find a conversation by one or both participants. Opening messages requires a specific safeguarding reason, and each page of content is logged. This view does not add you as a participant or mark anyone's messages read.</p></header>
        <?php if($error):?><div class="sgdm-error" role="alert"><?=$e($error)?></div><?php endif;?>
        <section class="sgdm-panel"><h2>Find conversations</h2><form method="get" action="<?=$u(self::ROUTE)?>" class="sgdm-filters"><label>Participant 1 name or email<input type="search" name="participant_a" maxlength="100" value="<?=$e($a)?>" placeholder="Optional"></label><label>Participant 2 name or email<input type="search" name="participant_b" maxlength="100" value="<?=$e($b)?>" placeholder="Optional"></label><label>Participant 1 user ID<input type="number" name="user_a" min="1" value="<?=$idA?:''?>" placeholder="Optional"></label><label>Participant 2 user ID<input type="number" name="user_b" min="1" value="<?=$idB?:''?>" placeholder="Optional"></label><button type="submit">Search conversations</button></form><p class="sgdm-help">Both participant filters must match when supplied. Searches return at most 50 conversations per page.</p></section>
        <section class="sgdm-panel"><h2>Matching conversations</h2><?php if(!$search['rows']):?><p>No conversations match these filters.</p><?php endif;?><?php foreach($search['rows'] as $row):?><article class="sgdm-result"><div><small>Conversation #<?=(int)$row['id']?> · <?=$e((string)$row['conversation_type'])?> · <?=isset($row['last_message_at'])&&$row['last_message_at']?$e(date('M j, Y',strtotime($row['last_message_at']))):'No messages'?></small><h3><?=$e((string)($row['subject']?:'Direct conversation'))?></h3><p><?=$e((string)$row['participant_names'])?></p><span><?=(int)$row['message_count']?> messages</span></div><details><summary>Review conversation</summary><form method="post" action="<?=$u(self::ROUTE)?>"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['safeguarding_dm_csrf'])?>"><input type="hidden" name="conversation_id" value="<?=(int)$row['id']?>"><label>Safeguarding reason<textarea name="reason" required minlength="12" maxlength="1000" rows="3" placeholder="Describe the report, safety concern, or specific review purpose"></textarea></label><label>Related safeguarding case ID (optional)<input name="case_id" type="number" min="1"></label><button type="submit">Open audited review</button></form></details></article><?php endforeach;?><?php if($search['page']>1||$search['has_more']):?><nav class="sgdm-pagination"><?php if($search['page']>1):?><a href="<?=$u(self::ROUTE.'?'.$query.'&page='.($search['page']-1))?>">Previous</a><?php endif;?><?php if($search['has_more']):?><a href="<?=$u(self::ROUTE.'?'.$query.'&page='.($search['page']+1))?>">Next 50</a><?php endif;?></nav><?php endif;?></section>
        <?php if($selected):?><section class="sgdm-panel sgdm-thread"><header><div><small>AUDITED CONTENT REVIEW · #<?=(int)$selected['id']?></small><h2><?=$e((string)($selected['subject']?:'Conversation'))?></h2><p><?php foreach($selected['participants'] as $i=>$participant):?><?=$i?', ':''?><?=$e((string)$participant['name'])?> (<?=$e((string)$participant['participant_role'])?><?=$participant['access_revoked_at']?' · access revoked':''?>)<?php endforeach;?></p></div></header><div class="sgdm-messages"><?php foreach($selected['messages'] as $message):?><article><small>#<?=(int)$message['id']?> · <?=$e((string)$message['sender_name'])?> · <?=$e(date('M j, Y · g:i A',strtotime($message['created_at'])))?><?=$message['hidden_at']?' · Hidden':''?></small><p><?=nl2br($e((string)$message['body']))?></p></article><?php endforeach;?></div><?php if($selected['has_older']):?><a href="<?=$u(self::ROUTE.'?conversation_id='.(int)$selected['id'].'&message_page='.($selected['page']+1))?>">Review 50 older messages (audited)</a><?php endif;?></section><?php endif;?>
        </div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
