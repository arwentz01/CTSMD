<?php

declare(strict_types=1);

$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
if(!$key){fwrite(STDERR,"Unable to generate P-256 key.\n");exit(1);}openssl_pkey_export($key,$privatePem);$details=openssl_pkey_get_details($key);$ec=$details['ec']??null;if(!$ec||!isset($ec['x'],$ec['y'])){fwrite(STDERR,"OpenSSL did not expose EC public coordinates.\n");exit(1);} $public="\x04".$ec['x'].$ec['y'];$b64url=static fn(string $v):string=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');
echo "Add these values to .env:\n\n";echo 'PUSH_VAPID_PUBLIC_KEY='.$b64url($public)."\n";echo 'PUSH_VAPID_PRIVATE_KEY_B64='.base64_encode($privatePem)."\n";echo "PUSH_VAPID_SUBJECT=mailto:notifications@ctsmd.org\n";
