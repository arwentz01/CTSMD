<?php

declare(strict_types=1);

final class PersonEligibility
{
    public static function birthDate(string $value,bool $required=true):?string
    {
        $value=trim($value);
        if($value===''){
            if($required)throw new RuntimeException('Enter a date of birth.');
            return null;
        }
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value||$date>new DateTimeImmutable('today')||$date<new DateTimeImmutable('today -120 years'))throw new RuntimeException('Enter a valid date of birth.');
        return $value;
    }

    public static function ageOn(string $birthDate,string $asOf):int
    {
        $dob=DateTimeImmutable::createFromFormat('!Y-m-d',$birthDate);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$asOf);
        if(!$dob||!$date||$dob->format('Y-m-d')!==$birthDate||$date->format('Y-m-d')!==$asOf||$dob>$date)throw new RuntimeException('A valid birth date and audition eligibility date are required.');
        return $dob->diff($date)->y;
    }

    public static function cutoff(array $opportunity):?string
    {
        if(!empty($opportunity['age_eligibility_date']))return substr((string)$opportunity['age_eligibility_date'],0,10);
        if(!empty($opportunity['starts_at']))return substr((string)$opportunity['starts_at'],0,10);
        return null;
    }

    public static function evaluate(array $opportunity,?string $dob):array
    {
        $min=isset($opportunity['minimum_age'])&&$opportunity['minimum_age']!==null?(int)$opportunity['minimum_age']:null;
        $max=isset($opportunity['maximum_age'])&&$opportunity['maximum_age']!==null?(int)$opportunity['maximum_age']:null;
        if($min===null&&$max===null)return ['status'=>'unrestricted','age'=>null];
        $cutoff=self::cutoff($opportunity);
        if(!$cutoff||!$dob)return ['status'=>'needs_review','age'=>null];
        $age=self::ageOn($dob,$cutoff);
        return ['status'=>($min!==null&&$age<$min)||($max!==null&&$age>$max)?'ineligible':'eligible','age'=>$age];
    }

    public static function group(string $dob,string $asOf):string
    {
        $age=self::ageOn($dob,$asOf);
        return $age<13?'under_13':($age<18?'13_17':'adult');
    }

    public static function contact(array $input,bool $requireAddress=true):array
    {
        $fields=['contact_phone'=>40,'address_line1'=>190,'address_line2'=>190,'address_city'=>100,'address_state'=>60,'address_postal_code'=>20];
        $result=[];
        foreach($fields as $field=>$limit){
            $value=trim((string)($input[$field]??''));
            if(mb_strlen($value)>$limit)throw new RuntimeException('One of the contact details is too long.');
            $result[$field]=$value!==''?$value:null;
        }
        if(empty($result['contact_phone']))throw new RuntimeException('Enter a contact phone number.');
        if($requireAddress&&(!$result['address_line1']||!$result['address_city']||!$result['address_state']||!$result['address_postal_code']))throw new RuntimeException('Enter a complete mailing address.');
        return $result;
    }
}
