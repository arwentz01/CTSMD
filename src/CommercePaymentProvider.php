<?php
declare(strict_types=1);
/** Server-side provider results only; a browser return URL never settles an order. */
interface CommercePaymentProvider
{
    public function inspect(array $attempt,DateTimeImmutable $now):string;
}
final class CommerceSimulatedPaymentProvider implements CommercePaymentProvider
{
    public function inspect(array $attempt,DateTimeImmutable $now):string
    {
        if($attempt['scenario']==='timeout')return in_array($attempt['simulated_resolution']??null,['succeeded','failed','canceled'],true)?$attempt['simulated_resolution']:'unknown';
        if($attempt['scenario']==='delayed'&&$now<new DateTimeImmutable($attempt['ready_at']))return 'pending';
        return match($attempt['scenario']){'success','delayed'=>'succeeded','declined'=>'failed','canceled'=>'canceled',default=>throw new RuntimeException('Unknown simulation outcome.')};
    }
}
