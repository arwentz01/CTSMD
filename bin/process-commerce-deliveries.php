<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require_once dirname(__DIR__).'/src/Database.php';require_once dirname(__DIR__).'/src/CommerceDeliveryService.php';
try{$db=Database::connect(dirname(__DIR__));if(getenv('COMMERCE_MAIL_WORKER_ENABLED')!=='1')throw new RuntimeException('Disabled');$q=$db->prepare('SELECT id FROM commerce_organizations WHERE organization_key=?');$q->execute([CommerceAccess::primaryKey()]);$org=(int)$q->fetchColumn();if(!$org)throw new RuntimeException('Owner unavailable');echo json_encode(CommerceDeliveryService::process($db,$org,25)).PHP_EOL;}catch(Throwable){fwrite(STDERR,"Commerce delivery worker disabled or unavailable. Inspect configured gates and normalized delivery errors; no recipient or message content is logged.\n");exit(1);}
