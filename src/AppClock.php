<?php
declare(strict_types=1);

/** CTSMD business clock. Display and local calendar entry never depend on server timezone. */
final class AppClock
{
    public static function timezone():DateTimeZone
    {
        $name=(string)(getenv('APP_TIMEZONE')?:'America/New_York');
        if(!in_array($name,DateTimeZone::listIdentifiers(),true))throw new RuntimeException('APP_TIMEZONE must be a valid IANA timezone.');
        return new DateTimeZone($name);
    }
    public static function name():string{return self::timezone()->getName();}
    public static function label():string{return self::name()==='America/New_York'?'Eastern Time':self::name();}
    public static function today():string{return (new DateTimeImmutable('now',self::timezone()))->format('Y-m-d');}
    public static function localDateTime(string $utc,string $format='M j, Y · g:i A T'):string
    {
        return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(self::timezone())->format($format);
    }
    /** The entered date is the last usable business-local day, inclusive. */
    public static function endOfLocalDayUtc(string $date):string
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('Choose a valid expiration date.');
        $local=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date.' 23:59:59',self::timezone());
        if(!$local||$local->format('Y-m-d')!==$date)throw new RuntimeException('Choose a valid expiration date.');
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public static function dateInputFromUtc(string $utc):string{return self::localDateTime($utc,'Y-m-d');}
}
