SET NAMES utf8mb4;
-- Freeze ONLY previously public staff-name/title lists before new runtime removes
-- live RBAC-derived team publication. No new team functions, private data or consent.
-- Review populated hosting results/locks and back up before any approved execution.
INSERT INTO playbill_sections(playbill_id,section_type,heading,body,sort_order,active)
SELECT pb.id,'production_team_snapshot','Production Team',
 JSON_ARRAYAGG(JSON_OBJECT('name',CONCAT(u.first_name,' ',u.last_name),'participation_role',COALESCE(pm.participation_role,''))),15,1
FROM playbills pb
JOIN production_memberships pm ON pm.production_id=pb.production_id AND pm.status='active' AND pm.audience_type='staff'
JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
WHERE pb.public_enabled=1 AND pb.status IN ('current','archived')
 AND NOT EXISTS(SELECT 1 FROM playbill_sections s WHERE s.playbill_id=pb.id AND s.section_type='production_team_snapshot')
GROUP BY pb.id;
