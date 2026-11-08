<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceEmailTemplate.php';
require_once __DIR__.'/OrganizationBranding.php';

/**
 * Account lifecycle messages use the same CTSMD visual system as ticket emails.
 * Existing plain-text copy remains the source of truth for instructions and expiry.
 */
final class AccountEmailTemplate
{
    private static function e(string $value):string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }

    public static function supports(string $subject):bool
    {
        return in_array($subject,[
            'Verify your CTSMD Connect account',
            'Finish your CTSMD Connect student account',
            'Reset your CTSMD Connect password',
            'Your CTSMD Connect invitation',
            'Complete your CTSMD Connect invitation',
            'Your CTSMD Connect membership is approved',
        ],true);
    }

    public static function render(string $subject,string $text):string
    {
        $brand=OrganizationBranding::fromEnvironment();
        $type=match($subject){
            'Verify your CTSMD Connect account'=>'verify',
            'Finish your CTSMD Connect student account'=>'student',
            'Reset your CTSMD Connect password'=>'reset',
            'Your CTSMD Connect invitation'=>'invite',
            'Complete your CTSMD Connect invitation'=>'adult_invite',
            'Your CTSMD Connect membership is approved'=>'approved',
            default=>throw new InvalidArgumentException('Unsupported account email'),
        };
        $settings=match($type){
            'verify'=>['WELCOME TO CTSMD CONNECT','One more step!','Verify your email address to finish creating your CTSMD Connect account.','Verify my email'],
            'student'=>['STUDENT ACCOUNT','You’re invited!','Your parent or guardian has invited you to finish setting up your CTSMD Connect student account.','Set up my account'],
            'reset'=>['ACCOUNT SECURITY','Reset your password','We received a request to reset the password for your CTSMD Connect account.','Reset my password'],
            'invite'=>['ACCOUNT INVITATION','You’re invited to CTSMD Connect','An invitation is waiting for you. Activate your account and choose your password to get started.','Activate my account'],
            'adult_invite'=>['WELCOME TO CTSMD CONNECT','Your invitation is here','CTSMD has invited you to join as a General Member. Complete your profile and create a password to get started.','Complete my account'],
            'approved'=>['ACCOUNT UPDATE','Welcome to the community!','Your CTSMD Connect membership has been approved.','Open CTSMD Connect'],
        };
        [$eyebrow,$heading,$description,$button]=$settings;
        $url=null;
        if(preg_match('~https?://[^\s<>"\']+~iu',$text,$match)){
            $candidate=rtrim($match[0],".,;!?)]}");
            if(strlen($candidate)<=4096&&filter_var($candidate,FILTER_VALIDATE_URL)
                &&in_array(strtolower((string)parse_url($candidate,PHP_URL_SCHEME)),['https','http'],true)
                &&parse_url($candidate,PHP_URL_USER)===null
                &&parse_url($candidate,PHP_URL_PASS)===null)$url=$candidate;
        }
        if($type!=='approved'&&$url===null){
            // Do not fabricate a verification or reset action for a malformed message.
            throw new InvalidArgumentException('Account email is missing a valid action link');
        }

        $html=CommerceEmailTemplate::start($brand,$eyebrow,$heading,$description);
        $paragraphs=preg_split('/\R{2,}/u',trim($text));
        foreach($paragraphs as $paragraph){
            $paragraph=trim((string)$paragraph);
            if($paragraph==='')continue;
            if(preg_match('/^Hi [^,\r\n]+,$/u',$paragraph)){
                $html.='<p style="margin:0 0 20px;font-weight:bold;font-size:15px;color:#292528">'.self::e($paragraph).'</p>';
                continue;
            }
            // The call-to-action replaces the raw token URL in the HTML only.
            if($url!==null)$paragraph=trim(str_replace($url,'',$paragraph));
            $paragraph=trim($paragraph);
            if($paragraph===''||preg_match('/^[—-]\s*Children.s Theatre of Southern Maryland$/u',$paragraph)
                ||$paragraph==="Children's Theatre of Southern Maryland")continue;
            $html.='<p style="margin:0 0 15px;font-size:14px;line-height:1.7;color:#504744">'.nl2br(self::e($paragraph),false).'</p>';
        }
        if($url!==null){
            $security=match($type){
                'verify'=>'This verification link expires after 24 hours. If you did not create this account, you can ignore this message.',
                'student'=>'This invitation expires after 48 hours. If you were not expecting it, you can ignore this message.',
                'adult_invite'=>'This invitation is for adults aged 18 or older and expires in seven days. General Membership does not provide staff or production access. If you were not expecting it, ignore this email.',
                'reset'=>'This private reset link expires after 2 hours. If you did not request it, you can ignore this message.',
                default=>'Your invitation link expires after 7 days. If you were not expecting an invitation, contact CTSMD.',
            };
            return $html.CommerceEmailTemplate::finish($brand,$button,$url,'Use the button above to continue securely. If it does not work, use the link below.',$security);
        }
        // No fictitious CTA for membership approval: the source message has no link.
        return $html.'</td></tr><tr><td style="padding:22px 38px 28px;background:#fcfaf8;border-top:1px solid #eee6e0;border-radius:0 0 9px 9px">'
            .'<p style="font-size:12px;color:#726862;margin:0">Thank you for being part of '.self::e((string)$brand['name']).'.</p>'
            .'</td></tr></table></td></tr></table></body></html>';
    }
}
