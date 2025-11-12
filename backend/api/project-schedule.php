<?php
header('Content-Type: application/json');
require_once __DIR__.'/../lib/Database.php';
require_once __DIR__.'/../middleware/TenantGuard.php';

$tenantId = TenantGuard::requireTenant();
TenantGuard::requireRole(['empresa_admin','planificador']);

$input = json_decode(file_get_contents('php://input'), true);
$projectId = (int)($input['projectId'] ?? 0);
$groupId = (int)($input['groupId'] ?? 0);
$startDate = $input['startDate'] ?? null; // YYYY-MM-DD
$endDate = $input['endDate'] ?? null;     // YYYY-MM-DD
$weekdays = $input['weekdays'] ?? [];     // [1..7] (Mon..Sun)
$timeStart = $input['timeStart'] ?? '08:00';
$timeEnd = $input['timeEnd'] ?? '16:00';
$required = (int)($input['required_qty'] ?? 1);

if(!$projectId || !$groupId || !$startDate || !$endDate || empty($weekdays)){
  http_response_code(422);
  echo json_encode(['error'=>['code'=>'VALIDATION','message'=>'Missing fields']]); exit;
}

try{
  $db = Database::get();
  $date = new DateTime($startDate);
  $end  = new DateTime($endDate);
  $count = 0;

  while($date <= $end){
    $dow = (int)$date->format('N'); // 1..7
    if (in_array($dow, $weekdays, true)) {
      $startDt = $date->format('Y-m-d').' '.$timeStart.':00';
      $endDt   = $date->format('Y-m-d').' '.$timeEnd.':00';
      $stmt = $db->prepare("INSERT INTO slots(tenant_id,project_id,group_id,start_at,end_at,required_qty)
                            VALUES(?,?,?,?,?,?)");
      $stmt->execute([$tenantId,$projectId,$groupId,$startDt,$endDt,$required]);
      $count++;
    }
    $date->modify('+1 day');
  }

  echo json_encode(['data'=>['created'=>$count]]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>['code'=>'SERVER_ERROR','message'=>$e->getMessage()]]);
}

