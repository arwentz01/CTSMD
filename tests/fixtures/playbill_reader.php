<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/PlaybillPublicRenderer.php';
$cast=[];for($i=1;$i<=29;++$i)$cast[]=['user_id'=>$i,'display_name'=>'Approved Student '.$i,'role_title'=>'Character '.$i,'biography'=>str_repeat('This approved biography demonstrates readable publication text. ',($i%4)+2),'headshot_stored_file_id'=>null,'photographer_credit'=>null];
$staff=[];for($i=1;$i<=27;++$i)$staff[]=['name'=>'Production Member '.$i,'participation_role'=>'Production role '.$i];
$sections=[['id'=>1,'heading'=>"Director's note",'section_type'=>'director_note','body'=>str_repeat('The company approached this production with generosity, discipline, and imagination. ',75)."\n\n".str_repeat('Thank you for joining us in the theatre. ',45)]];
$pb=['id'=>1,'public_slug'=>'reader-test','display_title'=>'A Theatre Publication','production_title'=>'A Theatre Publication','subtitle'=>'Reader V2 acceptance fixture','season'=>'2026','cover_note'=>'A complete program presented by CTSMD.','public_description'=>'Reader test','artwork_stored_file_id'=>null,'website_url'=>'https://example.com','upcoming_url'=>null,'donate_url'=>null];
$sponsors=[['id'=>1,'name'=>'Premium Sponsor','tier'=>'premium','placement_format'=>'full_page','advertisement_stored_file_id'=>null,'logo_stored_file_id'=>null,'placement_label'=>'Presenting Sponsor','public_description'=>'Supporting theatre education.','website_url'=>'https://example.com','cta_label'=>'Visit sponsor']];
PlaybillPublicRenderer::render('',$pb,$sections,$cast,$staff,$sponsors);
