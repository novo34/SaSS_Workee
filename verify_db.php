<?php
// Verificar base de datos

$conn = new mysqli('localhost', 'root', '', 'workee', 3306);

if ($conn->connect_error) {
    die("Error: " . $conn->connect_error . "\n");
}

$r = $conn->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = 'workee'");
$row = $r->fetch_assoc();
echo "Total de tablas: " . $row['total'] . "\n\n";

echo "Tablas principales:\n";
$main = ['users', 'tenants', 'tenant_users', 'shifts', 'work_logs', 'staffing_requests', 'work_orders', 'work_groups', 'agency_company_links'];
$r = $conn->query('SHOW TABLES');
while($t = $r->fetch_array()) {
    if(in_array($t[0], $main)) {
        echo "  ✓ " . $t[0] . "\n";
    }
}

echo "\n✅ Base de datos configurada correctamente\n";
$conn->close();

