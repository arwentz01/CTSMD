<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Database.php';

final class AccessPolicy
{
    public static function isStaff(array|string $userOrRole): bool
    {
        if(is_array($userOrRole)){
            $roles=self::rolesFor($userOrRole);
            if($roles!==null){
                return in_array('administrator',$roles,true)
                    || in_array('production_staff',$roles,true);
            }
        }
        $role = strtolower(is_array($userOrRole) ? (string)($userOrRole['role'] ?? '') : $userOrRole);
        return str_contains($role, 'staff') || str_contains($role, 'manager') || str_contains($role, 'admin') || str_contains($role, 'director');
    }

    public static function isStudent(array|string $userOrRole): bool
    {
        if(is_array($userOrRole)){
            $roles=self::rolesFor($userOrRole);
            if($roles!==null)return in_array('student',$roles,true);
        }
        $role = strtolower(is_array($userOrRole) ? (string)($userOrRole['role'] ?? '') : $userOrRole);
        return str_contains($role, 'student');
    }

    public static function canManagePeople(array $user): bool{return self::permissionOrLegacy($user,'people.manage');}
    public static function canManageSafeguarding(array $user): bool{return self::permissionOrLegacy($user,'safeguarding.manage');}
    public static function canManageProduction(array $user): bool{return self::permissionOrLegacy($user,'production.manage');}
    public static function canManageCommunity(array $user): bool{return self::permissionOrLegacy($user,'community.manage');}
    public static function canModerateCommunity(array $user): bool{return self::permissionOrLegacy($user,'community.moderate');}
    public static function canManageVolunteers(array $user): bool{return self::permissionOrLegacy($user,'volunteer.manage');}
    public static function canManageForms(array $user): bool{return self::permissionOrLegacy($user,'forms.manage');}
    public static function canManageResources(array $user): bool{return self::permissionOrLegacy($user,'resources.manage');}
    public static function canManagePlaybill(array $user): bool{return self::permissionOrLegacy($user,'playbill.manage');}
    public static function canManageAccounts(array $user): bool{return self::permissionOrLegacy($user,'accounts.manage');}
    public static function canViewAudit(array $user): bool{return self::permissionOrLegacy($user,'audit.view');}

    public static function localIdentitySwitchEnabled(): bool{return Auth::localIdentitySwitchEnabled();}

    private static function permissionOrLegacy(array $user,string $permission):bool
    {
        $permissions=self::permissionsFor($user);
        if($permissions!==null)return in_array($permission,$permissions,true);
        return self::isStaff($user);
    }

    private static function rolesFor(array $user):?array
    {
        if(isset($user['roles']))return (array)$user['roles'];
        $id=(int)($user['id']??0);
        if($id<1||Auth::userId()!==$id)return null;
        try{$db=Database::connect(dirname(__DIR__));return Auth::roles($db,$id);}catch(Throwable){return [];}
    }

    private static function permissionsFor(array $user):?array
    {
        if(isset($user['permissions']))return (array)$user['permissions'];
        $id=(int)($user['id']??0);
        if($id<1||Auth::userId()!==$id)return null;
        try{$db=Database::connect(dirname(__DIR__));return Auth::permissions($db,$id);}catch(Throwable){return [];}
    }
}
