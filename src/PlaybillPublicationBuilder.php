<?php

declare(strict_types=1);

final class PlaybillPublicationBuilder
{
    public static function build(array $playbill,array $sections,array $cast,array $staff,array $sponsors):array
    {
        $pages=[['type'=>'cover','title'=>(string)($playbill['display_title']?:$playbill['production_title']),'toc'=>false]];
        $content=[];
        $performers=array_values(array_filter($cast,static fn(array $person):bool=>($person['participant_type']??'cast')==='cast'));
        foreach(array_chunk($performers,24) as $i=>$chunk)$content[]=['type'=>'cast-credits','title'=>$i?'Cast & characters · continued':'Cast & characters','toc'=>$i===0,'items'=>$chunk];
        foreach(self::chunkBios($cast) as $i=>$chunk)$content[]=['type'=>'cast-bios','title'=>$i?'Meet the company · continued':'Meet the company','toc'=>$i===0,'items'=>$chunk];
        foreach(array_chunk($staff,20) as $i=>$chunk)$content[]=['type'=>'team','title'=>$i?'Production team · continued':'Production team','toc'=>$i===0,'items'=>$chunk];
        foreach($sections as $section){$chunks=self::chunkText((string)$section['body']);foreach($chunks as $i=>$body)$content[]=['type'=>'editorial','title'=>(string)$section['heading'].($i?' · continued':''),'toc'=>$i===0,'section_type'=>(string)$section['section_type'],'body'=>$body,'anchor'=>'section-'.(int)$section['id']];}
        $sponsorTocAdded=false;foreach($sponsors as $sponsor){if($sponsor['tier']==='premium'||$sponsor['placement_format']==='full_page'){$content[]=['type'=>'sponsors','title'=>'Our sponsors','toc'=>!$sponsorTocAdded,'items'=>[$sponsor]];$sponsorTocAdded=true;}}
        $regular=array_values(array_filter($sponsors,static fn(array $s):bool=>$s['tier']!=='premium'&&$s['placement_format']!=='full_page'));
        foreach(array_chunk($regular,4) as $i=>$chunk){$content[]=['type'=>'sponsors','title'=>$sponsorTocAdded||$i?'Our sponsors · continued':'Our sponsors','toc'=>!$sponsorTocAdded,'items'=>$chunk];$sponsorTocAdded=true;}
        $links=array_values(array_filter([['url'=>$playbill['website_url']??null,'label'=>'Visit CTSMD'],['url'=>$playbill['upcoming_url']??null,'label'=>'Upcoming productions'],['url'=>$playbill['donate_url']??null,'label'=>'Support CTSMD']],static fn(array $link):bool=>!empty($link['url'])));
        if($links)$content[]=['type'=>'closing','title'=>'Stay connected','toc'=>true,'links'=>$links];
        $toc=[];$pageNumber=3;foreach($content as $page){if(!empty($page['toc']))$toc[]=['title'=>$page['title'],'page'=>$pageNumber];++$pageNumber;}
        $pages[]=['type'=>'contents','title'=>'Contents','toc'=>false,'items'=>$toc];
        return array_merge($pages,$content);
    }

    private static function chunkBios(array $cast):array
    {
        $chunks=[];$current=[];$weight=0;
        foreach($cast as $member){$itemWeight=650+mb_strlen((string)($member['biography']??''));if($current&&($weight+$itemWeight>3900||count($current)>=4)){$chunks[]=$current;$current=[];$weight=0;}$current[]=$member;$weight+=$itemWeight;}
        if($current)$chunks[]=$current;return$chunks;
    }

    public static function chunkText(string $text,int $limit=2600):array
    {
        $text=trim(str_replace(["\r\n","\r"],"\n",$text));if($text==='')return[];$blocks=preg_split('/\n{2,}/',$text)?:[];$chunks=[];$current='';
        foreach($blocks as $block){$block=trim($block);if($block==='')continue;foreach(self::splitBlock($block,$limit) as $part){$candidate=$current===''?$part:$current."\n\n".$part;if(mb_strlen($candidate)>$limit&&$current!==''){$chunks[]=$current;$current=$part;}else$current=$candidate;}}
        if($current!=='')$chunks[]=$current;return$chunks;
    }

    private static function splitBlock(string $block,int $limit):array
    {
        if(mb_strlen($block)<=$limit)return[$block];$words=preg_split('/\s+/',$block)?:[];$parts=[];$current='';foreach($words as $word){$candidate=$current===''?$word:$current.' '.$word;if(mb_strlen($candidate)>$limit&&$current!==''){$parts[]=$current;$current=$word;}else$current=$candidate;}if($current!=='')$parts[]=$current;return$parts;
    }
}
