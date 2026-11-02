<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/SafeguardingStatementService.php';

/** Public, token-scoped form. No case detail API or case ID is exposed. */
final class SafeguardingStatementExperience
{
    public static function handles(string $route):bool{return $route==='/incident-statement';}

    public static function render(string $basePath):never
    {
        Auth::startSession();
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
        $db=Database::connect(dirname(__DIR__));
        $token=(string)($_GET['token']??$_POST['token']??'');
        $request=SafeguardingStatementService::byToken($db,$token);
        $expired= !$request||$request['status']!=='pending'||strtotime((string)$request['expires_at'])<=time();
        $_SESSION['statement_form_csrf']??=bin2hex(random_bytes(32));
        $error=null;$success=false;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!hash_equals((string)$_SESSION['statement_form_csrf'],(string)($_POST['csrf_token']??''))){$error='This form session has expired. Reload the page and try again.';}
            elseif($expired){$error='This private statement request is no longer available.';}
            else{
                try{
                    SafeguardingStatementService::submit($db,$token,$_POST);
                    $success=true;$request=null;unset($_SESSION['statement_form_csrf']);
                }catch(RuntimeException $e){$error=$e->getMessage();}
            }
        }
        $e=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private incident statement · CTSMD</title><style>
        :root{font:16px/1.5 system-ui,sans-serif;color:#262124;background:#f7f4f2}*{box-sizing:border-box}body{margin:0}main{max-width:780px;margin:42px auto;padding:24px}article{background:white;border:1px solid #e6e0e3;border-radius:16px;padding:clamp(20px,4vw,36px);box-shadow:0 8px 28px #291c2210}h1{font-size:1.65rem;margin:0 0 10px}h2{font-size:1.1rem;margin:24px 0 8px}small{color:#71565e;font-weight:700;letter-spacing:.05em}p{margin:10px 0;color:#463f42}.context{background:#fffdfa;border:1px solid #e6e0e3;border-radius:10px;padding:14px;margin:16px 0;overflow-wrap:anywhere}.context dl{margin:0}.context dt{font-weight:700;margin-top:8px}.context dd{margin:2px 0 8px;white-space:pre-wrap}label{display:block;font-weight:700;margin:18px 0 6px}input,textarea{width:100%;font:inherit;padding:11px;border:1px solid #bcb3b7;border-radius:8px}textarea{min-height:115px;resize:vertical}input[type=checkbox]{width:auto}.attest{display:flex;gap:9px;align-items:start;font-weight:500}.attest input{margin-top:5px}button{background:#a6192e;color:white;font:inherit;font-weight:750;border:0;border-radius:9px;padding:12px 20px;margin-top:22px;cursor:pointer}button:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid #b88724;outline-offset:2px}.error{border-left:4px solid #a6192e;padding:12px;background:#fff4f5}footer{font-size:13px;color:#645960;margin-top:20px}@media(max-width:600px){main{margin:10px auto;padding:12px}}
        </style></head><body><main><article><small>CHILDREN'S THEATRE OF SOUTHERN MARYLAND · PRIVATE REQUEST</small><h1>Your account of an incident</h1>
        <?php if($success):?><p role="status"><strong>Your statement was received.</strong> It has been recorded with the case and cannot be edited through this form. Thank you for providing your perspective.</p>
        <?php elseif($expired):?><p role="status">This statement link is no longer available. If you need to provide additional information, please contact CTSMD directly for a new request.</p>
        <?php else:?>
        <p>CTSMD is gathering individual perspectives about an incident. Please describe only what you personally saw, heard, experienced, or were told, distinguishing those clearly. Other people's accounts are not shared with you.</p>
        <section class="context" aria-label="Information shared for this request"><dl>
        <dt>Case</dt><dd><?=$e($request['shared_title'])?></dd>
        <?php if($request['shared_occurred_at']):?><dt>Reported incident date and time</dt><dd><?=$e(date('M j, Y · g:i A',strtotime($request['shared_occurred_at'])))?></dd><?php endif;?>
        <?php if($request['shared_production_title']):?><dt>Production</dt><dd><?=$e($request['shared_production_title'])?></dd><?php endif;?>
        <dt>Brief description provided by CTSMD</dt><dd><?=$e($request['shared_description'])?></dd>
        </dl></section>
        <?php if($error):?><p class="error" role="alert"><?=$e($error)?></p><?php endif;?>
        <form method="post" action="<?=$e(($basePath?:'').'/incident-statement')?>"><input type="hidden" name="token" value="<?=$e($token)?>"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['statement_form_csrf'])?>">
        <label for="account">What happened, from your perspective? *</label><textarea id="account" name="statement_body" maxlength="12000" required placeholder="Describe the sequence of events, approximate times, who was present, and what you personally observed."><?=$e($_POST['statement_body']??'')?></textarea>
        <label for="witnesses">Were other people present? (optional)</label><textarea id="witnesses" name="statement_witnesses" maxlength="2000" placeholder="Please share names or roles only if relevant."><?=$e($_POST['statement_witnesses']??'')?></textarea>
        <label for="followup">Is there anything else CTSMD should know or follow up on? (optional)</label><textarea id="followup" name="statement_followup" maxlength="2000"><?=$e($_POST['statement_followup']??'')?></textarea>
        <label for="attested_name">Your full name *</label><input id="attested_name" name="attested_name" maxlength="190" required autocomplete="name" value="<?=$e($_POST['attested_name']??$request['recipient_name'])?>">
        <label class="attest"><input type="checkbox" name="attestation" value="1" required><span>I confirm that this is my own account, accurate to the best of my knowledge. I understand my submitted statement will be preserved as written and cannot be edited afterward.</span></label>
        <button type="submit">Submit my statement</button></form><footer>This is a confidential statement request, not an emergency service. Please contact emergency services immediately if someone is in danger. Do not forward your private invitation link.</footer>
        <?php endif;?></article></main></body></html><?php exit;
    }
}
