SET NAMES utf8mb4;

INSERT IGNORE INTO auth_permissions (code,name,description) VALUES
('schedule.manage','Manage schedules','Create and edit production schedule items and publish schedule-change communications.');

INSERT IGNORE INTO auth_role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM auth_roles r
JOIN auth_permissions p ON p.code='schedule.manage'
WHERE r.code IN ('production_staff','administrator') AND r.active=1;
