<?php
header('Content-Type: application/json');
require_once __DIR__.'/../lib/Database.php';
require_once __DIR__.'/../middleware/TenantGuard.php';

$tenantId = TenantGuard::requireTenant();
// Empresa + Temporera coordinan asignaciones en día real
TenantGuard::requireRole(['empresa_admin','planificador','supervisor','temporera_admin','coordinador']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
  $db = Database::get();

  if ($method==='GET' && $action==='list') {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $stmt = $db->prepare("SELECT * FROM shifts WHERE tenant_id=? AND start_at>=? AND end_at<=?");
    $stmt->execute([$tenantId,$from,$to]);
    echo json_encode(['data'=>$stmt->fetchAll()]); exit;
  }

  if ($method==='POST' && $action==='create') {
    $i = json_decode(file_get_contents('php://input'), true);
    // Validate overlaps for the same person (own_employee_id or agency_worker_id)
    // ... overlap check here ...
    $stmt = $db->prepare("INSERT INTO shifts(tenant_id,group_id,own_employee_id,agency_worker_id,start_at,end_at,status)
                          VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([
      $tenantId, (int)$i['group_id'], $i['own_employee_id']??null, $i['agency_worker_id']??null,
      $i['start'], $i['end'], $i['status'] ?? 'planned'
    ]);
    echo json_encode(['data'=>['id'=>$db->lastInsertId()]]); exit;
  }

  if ($method==='POST' && $action==='reassign') {
    $i = json_decode(file_get_contents('php://input'), true);
    // move a person to another shift or replace person in same shift
    $stmt = $db->prepare("UPDATE shifts SET own_employee_id=?, agency_worker_id=? WHERE id=? AND tenant_id=?");
    $stmt->execute([$i['own_employee_id']??null,$i['agency_worker_id']??null,(int)$i['shift_id'],$tenantId]);
    echo json_encode(['data'=>true]); exit;
  }

  if ($method==='POST' && $action==='split') {
    $i = json_decode(file_get_contents('php://input'), true);
    // split a shift into two (end at split_time for first, create second)
    $stmt = $db->prepare("UPDATE shifts SET end_at=? WHERE id=? AND tenant_id=?");
    $stmt->execute([$i['split_time'], (int)$i['shift_id'], $tenantId]);
    $stmt = $db->prepare("INSERT INTO shifts(tenant_id,group_id,own_employee_id,agency_worker_id,start_at,end_at,status)
                          SELECT tenant_id,group_id,own_employee_id,agency_worker_id,?,end_at,status
                          FROM shifts WHERE id=? AND tenant_id=?");
    $stmt->execute([$i['split_time'], (int)$i['shift_id'], $tenantId]);
    echo json_encode(['data'=>true]); exit;
  }

  if ($method==='POST' && $action==='status') {
    $i = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("UPDATE shifts SET status=? WHERE id=? AND tenant_id=?");
    $stmt->execute([$i['status'], (int)$i['shift_id'], $tenantId]);
    echo json_encode(['data'=>true]); exit;
  }

  if ($method==='POST' && $action==='substitute') {
    $i = json_decode(file_get_contents('php://input'), true);
    // replace old person with new (or set null if urgent request will be sent)
    $stmt = $db->prepare("UPDATE shifts SET own_employee_id=?, agency_worker_id=? WHERE id=? AND tenant_id=?");
    $stmt->execute([$i['newOwnId']??null, $i['newAgencyId']??null, (int)$i['slotId'], $tenantId]);
    echo json_encode(['data'=>true]); exit;
  }

  http_response_code(404);
  echo json_encode(['error'=>['code'=>'NOT_FOUND','message'=>'Unknown action']]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>['code'=>'SERVER_ERROR','message'=>$e->getMessage()]]);
}
