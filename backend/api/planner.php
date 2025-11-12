<?php
header('Content-Type: application/json');
require_once __DIR__.'/../lib/Database.php';
require_once __DIR__.'/../middleware/TenantGuard.php';

$tenantId = TenantGuard::requireTenant();
TenantGuard::requireRole(['empresa_admin','planificador','supervisor']);

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
  $db = Database::get();

  if ($method==='GET' && $path==='events') {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $projectId = $_GET['projectId'] ?? null;
    $groupId = $_GET['groupId'] ?? null;
    $state = $_GET['state'] ?? '';
    $mode = $_GET['mode'] ?? 'PLANNING';

    // Example unified feed composed from slots/WO + proposals + assignments + shifts
    // NOTE: You must adapt table names to your schema if different.
    $params = [$tenantId, $from, $to];
    $sql = "SELECT s.id, s.project_id, p.name AS projectName, s.group_id, g.name AS groupName,
                   s.start_at AS start, s.end_at AS end, s.required_qty AS required,
                   COALESCE(prop.covered,0) AS covered,
                   CASE
                     WHEN COALESCE(ass.ass_cnt,0) > 0 THEN 'ASSIGNED'
                     WHEN COALESCE(prop.covered,0) >= s.required_qty THEN 'CONFIRMED'
                     WHEN COALESCE(prop.covered,0) > 0 THEN 'PROPOSED'
                     ELSE 'UNCOVERED'
                   END AS state,
                   ass.names AS assignees_json
            FROM slots s
            JOIN projects p ON p.id=s.project_id AND p.tenant_id=?
            JOIN work_groups g ON g.id=s.group_id AND g.tenant_id=?
            LEFT JOIN (
              SELECT slot_id, COUNT(*) AS covered
              FROM staffing_proposals
              WHERE status='confirmed'
              GROUP BY slot_id
            ) prop ON prop.slot_id=s.id
            LEFT JOIN (
              SELECT a.slot_id, COUNT(*) AS ass_cnt, JSON_ARRAYAGG(emp.name) AS names
              FROM assignments a
              JOIN employees emp ON emp.id=a.employee_id
              GROUP BY a.slot_id
            ) ass ON ass.slot_id=s.id
            WHERE s.tenant_id=? AND s.start_at>=? AND s.end_at<=?";

    // Optional filters
    array_unshift($params, $tenantId); // tenant for g
    array_unshift($params, $tenantId); // tenant for p
    if ($projectId) { $sql.=" AND s.project_id=?"; $params[]=(int)$projectId; }
    if ($groupId)   { $sql.=" AND s.group_id=?";   $params[]=(int)$groupId; }
    if ($state==='ASSIGNED') { /* could restrict further */ }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Normalize assignees array
    foreach ($rows as &$r) {
      $r['assignees'] = $r['assignees_json'] ? json_decode($r['assignees_json'], true) : [];
      unset($r['assignees_json']);
    }
    echo json_encode(['data'=>$rows]); exit;
  }

  if ($method==='POST' && $path==='slot') {
    $input = json_decode(file_get_contents('php://input'), true);
    $start = $input['start'] ?? null;
    $end = $input['end'] ?? null;
    $projectId = (int)($input['projectId'] ?? 0);
    $groupId = (int)($input['groupId'] ?? 0);
    $required = (int)($input['required_qty'] ?? 1);

    if(!$start || !$end || !$projectId || !$groupId){
      http_response_code(422);
      echo json_encode(['error'=>['code'=>'VALIDATION','message'=>'Missing fields']]); exit;
    }

    $stmt = $db->prepare("INSERT INTO slots(tenant_id,project_id,group_id,start_at,end_at,required_qty)
                          VALUES(?,?,?,?,?,?)");
    $stmt->execute([$tenantId,$projectId,$groupId,$start,$end,$required]);
    echo json_encode(['data'=>['id'=>$db->lastInsertId()]]); exit;
  }

  http_response_code(404);
  echo json_encode(['error'=>['code'=>'NOT_FOUND','message'=>'Unknown path']]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>['code'=>'SERVER_ERROR','message'=>$e->getMessage()]]);
}

