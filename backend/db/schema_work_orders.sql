-- ============================================
-- ESQUEMA ÓRDENES DE TRABAJO (OT)
-- Sprint 3.5 - DB-038
-- ============================================
-- Este archivo contiene las tablas necesarias para el módulo de Órdenes de Trabajo
-- Las tablas ya están incluidas en schema_v2.sql, este archivo es para referencia y migraciones

-- Órdenes de Trabajo
CREATE TABLE IF NOT EXISTS work_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  code VARCHAR(50) NULL,
  title VARCHAR(180) NOT NULL,
  client_id INT NULL,
  project_id INT NULL,
  location VARCHAR(200) NULL,
  start_planned DATE NULL,
  end_planned DATE NULL,
  start_actual DATE NULL,
  end_actual DATE NULL,
  status ENUM(
    'draft',
    'planned',
    'in_progress',
    'on_hold',
    'completed',
    'canceled'
  ) NOT NULL DEFAULT 'draft',
  scope_text TEXT NULL,
  safety_notes TEXT NULL,
  attachments_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_dates (start_planned, end_planned)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tareas de OT
CREATE TABLE IF NOT EXISTS work_order_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  pos INT NOT NULL DEFAULT 1,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  estimated_hours DECIMAL(6, 2) NULL,
  required_skills JSON NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  INDEX idx_work_order (work_order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Checklists de OT
CREATE TABLE IF NOT EXISTS work_order_checklists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  item VARCHAR(200) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  INDEX idx_work_order (work_order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Materiales de OT
CREATE TABLE IF NOT EXISTS work_order_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  unit VARCHAR(20) NULL,
  qty_planned DECIMAL(10, 2) NULL,
  qty_used DECIMAL(10, 2) NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  INDEX idx_work_order (work_order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Asignaciones a OT
CREATE TABLE IF NOT EXISTS work_order_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  group_id INT NULL,
  employee_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  tenant_agency_id INT NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_work_order (work_order_id),
  INDEX idx_group (group_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Partes diarios de OT
CREATE TABLE IF NOT EXISTS work_order_daily_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  date DATE NOT NULL,
  group_id INT NULL,
  leader_user_id INT NOT NULL,
  notes TEXT NULL,
  progress_pct DECIMAL(5, 2) NULL,
  attachments_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wo_date_group (work_order_id, date, group_id),
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE SET NULL,
  FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_work_order_date (work_order_id, date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Líneas del parte diario de OT
CREATE TABLE IF NOT EXISTS work_order_daily_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  daily_report_id INT NOT NULL,
  member_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 1,
  clock_in DATETIME NULL,
  clock_out DATETIME NULL,
  breaks_minutes INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  FOREIGN KEY (daily_report_id) REFERENCES work_order_daily_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  INDEX idx_daily_report (daily_report_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Firmas de OT
CREATE TABLE IF NOT EXISTS work_order_signoffs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  signer_type ENUM('client', 'company', 'agency') NOT NULL,
  signer_user_id INT NULL,
  signer_name VARCHAR(160) NULL,
  signature_data TEXT NULL,
  signed_at DATETIME NOT NULL,
  ip_address VARCHAR(45) NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (signer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_work_order (work_order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

