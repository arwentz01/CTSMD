<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/AppClock.php';
$original=getenv('APP_TIMEZONE');
try{
    putenv('APP_TIMEZONE=America/New_York');
    $check=static function(bool $yes,string $name):void{if(!$yes)throw new RuntimeException($name);echo "PASS ".$name."\n";};
    $check(AppClock::name()==='America/New_York','Application business timezone is Eastern');
    $check(AppClock::endOfLocalDayUtc('2026-01-10')==='2026-01-11 04:59:59','Winter EST end of day stored correctly in UTC');
    $check(AppClock::endOfLocalDayUtc('2026-07-10')==='2026-07-11 03:59:59','Summer EDT end of day stored correctly in UTC');
    $check(AppClock::endOfLocalDayUtc('2026-03-08')==='2026-03-09 03:59:59','Spring DST transition uses the new offset');
    $check(AppClock::endOfLocalDayUtc('2026-11-01')==='2026-11-02 04:59:59','Fall DST transition uses the new offset');
    $check(AppClock::dateInputFromUtc('2026-07-11 03:59:59')==='2026-07-10','Local date input roundtrips');
    $check(AppClock::localDateTime('2026-01-11 04:59:59','T')==='EST','Winter display uses EST');
    $check(AppClock::localDateTime('2026-07-11 03:59:59','T')==='EDT','Summer display uses EDT');
    $rejected=false;try{AppClock::endOfLocalDayUtc('2026-02-30');}catch(RuntimeException){$rejected=true;}
    $check($rejected,'Invalid calendar dates rejected');
    $rejected=false;putenv('APP_TIMEZONE=Invalid/Timezone');try{AppClock::timezone();}catch(RuntimeException){$rejected=true;}
    $check($rejected,'Invalid timezone configuration fails closed');
}finally{
    if($original===false)putenv('APP_TIMEZONE');else putenv('APP_TIMEZONE='.$original);
}
