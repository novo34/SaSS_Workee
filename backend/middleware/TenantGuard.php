<?php
class TenantGuard {
  public static function requireTenant(): int {
    session_start();
    if (!isset($_SESSION['tenant_id'])) {
      http_response_code(401);
      echo json_encode(['error'=>['code'=>'NO_TENANT','message'=>'Tenant not found']]);
      exit;
    }
    return (int)$_SESSION['tenant_id'];
  }
  public static function requireRole(array $allowed): void {
    $role = $_SESSION['role'] ?? null;
    if (!$role || !in_array($role, $allowed, true)) {
      http_response_code(403);
      echo json_encode(['error'=>['code'=>'FORBIDDEN','message'=>'Insufficient role']]);
      exit;
    }
  }
}

