<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceEmailTemplate.php';
require_once __DIR__.'/OrganizationBranding.php';
require_once __DIR__.'/AccountEmailTemplate.php';

/**
 * Branded HTML alternative for general CTSMD transactional mail.
 * The existing plain-text body remains authoritative and unchanged.
 * URL tokens stay intact and are never fetched, shortened or logged.
 */
final class GeneralEmailTemplate
{
    private static function escape(string $s):string
    {
        return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }

    private static function urls(string $text):array
    {
        preg_match_all('~https?://[^\s<>"\']+~iu',$text,$matches);
        $urls=[];
        foreach($matches[0] as $url){
            $url=rtrim($url,".,;!?)]}");
            if(strlen($url)>4096||!filter_var($url,FILTER_VALIDATE_URL))continue;
            if(!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true))continue;
            if(parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_PASS)!==null)continue;
            $urls[]=$url;
        }
        return array_values(array_unique($urls));
    }

    private static function paragraph(string $paragraph):string
    {
        $parts=preg_split('~(https?://[^\s<>"\']+)~iu',$paragraph,-1,PREG_SPLIT_DELIM_CAPTURE);
        $html='';
        foreach($parts as $part){
            if(preg_match('~^https?://~i',$part)){
                $url=rtrim($part,".,;!?)]}");
                if(in_array($url,self::urls($url),true)){
                    $escaped=self::escape($url);
                    $html.='<a href="'.$escaped.'" style="color:#8b233b;text-decoration:underline;overflow-wrap:anywhere;word-break:break-word">'.$escaped.'</a>'.self::escape(substr($part,strlen($url)));
                    continue;
                }
            }
            $html.=self::escape($part);
        }
        return nl2br($html,false);
    }

    public static function render(string $category,string $subject,string $text):string
    {
        if($category==='account_security'&&AccountEmailTemplate::supports($subject))
            return AccountEmailTemplate::render($subject,$text);
        $brand=OrganizationBranding::fromEnvironment();
        $eyebrow=match($category){
            'account_security'=>'ACCOUNT & SECURITY',
            'schedule'=>'PRODUCTION UPDATES',
            'forms'=>'FORMS & DOCUMENTS',
            'volunteer'=>'VOLUNTEERING',
            'community'=>'COMMUNITY',
            'digest'=>'YOUR CTSMD DIGEST',
            default=>'CTSMD CONNECT'
        };
        $heading=trim($subject)!==''?$subject:'An update from CTSMD';
        $html=CommerceEmailTemplate::start($brand,$eyebrow,$heading,'An update from '.$brand['name'].'.');
        $paragraphs=preg_split('/\R{2,}/u',trim($text));
        foreach($paragraphs as $paragraph){
            if(trim($paragraph)==='')continue;
            $html.='<p style="margin:0 0 16px;font-size:14px;line-height:1.75;color:#3d3837">'.self::paragraph(trim($paragraph)).'</p>';
        }
        $links=self::urls($text);
        $url=$links[0]??null;
        if($url!==null){
            $action=match(true){
                $category==='account_security'&&preg_match('/reset.*password|password.*reset/i',$subject)===1=>'Reset password',
                $category==='account_security'&&preg_match('/verif|confirm.*email/i',$subject)===1=>'Verify email',
                $category==='account_security'&&preg_match('/invit|activate|set.*password/i',$subject)===1=>'Complete account setup',
                $category==='forms'=>'Open forms',
                $category==='volunteer'=>'View volunteering',
                $category==='schedule'=>'View schedule',
                default=>'Open CTSMD Connect'
            };
            $footer=$category==='account_security'
                ?'For your security, only use this link if you requested or expected this message. Never share one-time verification or reset links.'
                :'You are receiving this email because of your involvement with CTSMD Connect.';
            return $html.CommerceEmailTemplate::finish($brand,$action,$url,'This link was provided in your original notification. Any expiration and eligibility restrictions still apply.',$footer);
        }
        return $html.'</td></tr><tr><td style="padding:22px 38px 28px;background:#fcfaf8;border-top:1px solid #eee6e0;color:#716661;font-size:12px;line-height:1.6">'
            .'This message was sent by '.self::escape((string)$brand['name']).' through CTSMD Connect.'
            .'</td></tr></table></td></tr></table></body></html>';
    }
}
