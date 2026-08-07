<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

final class AccessPolicy
{
    public static function isStaff(array|string $userOrRole): bool
    {
        if(is_array($userOrRole)){
            $roles=(array)($userOrRole['roles']??[]);
            $permissions=(array)($userOrRole['permissions']??[]);
            if($roles||$permissions){
                return in_array('administrator',$roles,true)
                    || in_array('production_staff',$roles,true)
                    || in_array('moderator',$roles,true)
                    || in_array('safeguarding',$roles,true)
                    || (bool)$permissions;
            }
        }
        $role = strtolower(is_array($userOrRole) ? (string)($userOrRole['role'] ?? '') : $userOrRole);
        return str_contains($role, 'staff') || str_contains($role, 'manager') || str_contains($role, 'admin') || str_contains($role, 'director');
    }

    public static function isStudent(array|string $userOrRole): bool
    {
        if(is_array($userOrRole)&&isset($userOrRole['roles']))return in_array('student',(array)$userOrRole['roles'],true);
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
        if(isset($user['permissions']))return Auth::hasPermission($user,$permission);
        return self::isStaff($user);
    }
}
