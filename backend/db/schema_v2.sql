-- ============================================
-- Workee SaaS - Esquema Base de Datos v2
-- ============================================
-- Multitenant: todas las tablas de negocio tienen tenant_id
-- Excepciones: tenants, subscriptions, invoices, audit_logs (globales)
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
-- ============================================
-- TABLAS GLOBALES (sin tenant_id)
-- ============================================
-- Usuarios globales del sistema
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  phone VARCHAR(50) NULL,
  email_verified_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_active (is_active)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tenants (Empresas y Temporeras)
CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NULL UNIQUE,
  org_type ENUM('empresa', 'temporera') NOT NULL,
  plan ENUM('basic', 'pro', 'enterprise') NOT NULL DEFAULT 'basic',
  currency CHAR(3) NOT NULL DEFAULT 'CHF',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Zurich',
  locale VARCHAR(10) NOT NULL DEFAULT 'es',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_org_type (org_type),
  INDEX idx_active (is_active),
  INDEX idx_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Suscripciones
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  external_id VARCHAR(120) NULL,
  billing_cycle_anchor DATE NOT NULL,
  status ENUM('active', 'past_due', 'canceled') NOT NULL DEFAULT 'active',
  fixed_price DECIMAL(10, 2) NOT NULL,
  variable_price_per_employee DECIMAL(10, 2) NOT NULL,
  measure ENUM('monthly_avg', 'monthly_peak', 'daily') NOT NULL DEFAULT 'monthly_avg',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant_status (tenant_id, status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Snapshots de empleados activos (para billing)
CREATE TABLE IF NOT EXISTS usage_employee_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  date DATE NOT NULL,
  active_count INT NOT NULL,
  org_type ENUM('empresa', 'temporera') NOT NULL,
  UNIQUE KEY uq_tenant_date (tenant_id, date),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_date (date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Facturas
CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  invoice_number VARCHAR(50) NOT NULL UNIQUE,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  fixed_amount DECIMAL(10, 2) NOT NULL,
  variable_units INT NOT NULL,
  variable_rate DECIMAL(10, 2) NOT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  pdf_path VARCHAR(255) NULL,
  status ENUM('draft', 'issued', 'paid', 'failed', 'canceled') NOT NULL DEFAULT 'draft',
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_period (period_from, period_to)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Logs de auditoría globales
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NULL,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  resource_type VARCHAR(100) NOT NULL,
  resource_id INT NULL,
  changes_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_user (user_id),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_created (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================
-- TABLAS CON TENANT_ID (Negocio)
-- ============================================
-- Usuarios por tenant (roles)
CREATE TABLE IF NOT EXISTS tenant_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM(
    'owner_admin',
    'empresa_admin',
    'planificador',
    'supervisor',
    'empleado',
    'cliente_viewer',
    'temporera_admin',
    'coordinador',
    'trabajador'
  ) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_user (tenant_id, user_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_tenant_role (tenant_id, role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Relaciones Empresa ↔ Temporera
CREATE TABLE IF NOT EXISTS agency_company_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  tenant_agency_id INT NOT NULL,
  status ENUM('active', 'paused', 'blocked') NOT NULL DEFAULT 'active',
  terms_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_link (tenant_company_id, tenant_agency_id),
  FOREIGN KEY (tenant_company_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_company (tenant_company_id),
  INDEX idx_agency (tenant_agency_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Clientes (Empresa)
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  contact_name VARCHAR(150) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Proyectos (Empresa)
CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  client_id INT NULL,
  name VARCHAR(200) NOT NULL,
  code VARCHAR(50) NULL,
  description TEXT NULL,
  location VARCHAR(200) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM(
    'planning',
    'active',
    'on_hold',
    'completed',
    'canceled'
  ) NOT NULL DEFAULT 'planning',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE
  SET NULL,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_client (client_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Empleados propios (Empresa)
CREATE TABLE IF NOT EXISTS own_employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NULL,
  employee_code VARCHAR(50) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  address TEXT NULL,
  skills_json JSON NULL,
  documents_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE
  SET NULL,
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Trabajadores temporeros (Temporera)
CREATE TABLE IF NOT EXISTS agency_workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NULL,
  worker_code VARCHAR(50) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  address TEXT NULL,
  latitude DECIMAL(10, 8) NULL,
  longitude DECIMAL(11, 8) NULL,
  skills_json JSON NULL,
  languages_json JSON NULL,
  documents_json JSON NULL,
  availability_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE
  SET NULL,
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_user (user_id),
    INDEX idx_location (latitude, longitude)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Solicitudes de personal (Empresa → Temporera)
CREATE TABLE IF NOT EXISTS staffing_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  created_by_user INT NOT NULL,
  project_id INT NULL,
  date_from DATETIME NOT NULL,
  date_to DATETIME NOT NULL,
  location VARCHAR(160) NULL,
  latitude DECIMAL(10, 8) NULL,
  longitude DECIMAL(11, 8) NULL,
  qty_needed INT NOT NULL,
  requirements_json JSON NULL,
  status ENUM('open', 'partially_filled', 'filled', 'canceled') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_company_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE
  SET NULL,
    INDEX idx_company_status (tenant_company_id, status),
    INDEX idx_dates (date_from, date_to)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Destinatarios de solicitudes
CREATE TABLE IF NOT EXISTS staffing_request_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  tenant_agency_id INT NOT NULL,
  status ENUM('sent', 'responded', 'declined') NOT NULL DEFAULT 'sent',
  sent_at DATETIME NULL,
  responded_at DATETIME NULL,
  FOREIGN KEY (request_id) REFERENCES staffing_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_request (request_id),
  INDEX idx_agency_status (tenant_agency_id, status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Propuestas de candidatos (Temporera → Empresa)
CREATE TABLE IF NOT EXISTS staffing_proposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  tenant_agency_id INT NOT NULL,
  proposed_by_user INT NOT NULL,
  proposal_json JSON NOT NULL,
  status ENUM(
    'pending_company',
    'accepted',
    'rejected',
    'changed'
  ) NOT NULL DEFAULT 'pending_company',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES staffing_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (proposed_by_user) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_request_status (request_id, status),
  INDEX idx_agency (tenant_agency_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Grupos de trabajo
CREATE TABLE IF NOT EXISTS work_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  leader_employee_id INT NULL,
  project_id INT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_company_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (leader_employee_id) REFERENCES own_employees(id) ON DELETE
  SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE
  SET NULL,
    INDEX idx_company (tenant_company_id),
    INDEX idx_project (project_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Miembros de grupos
CREATE TABLE IF NOT EXISTS group_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  member_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  tenant_agency_id INT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_group (group_id),
  INDEX idx_employee (own_employee_id),
  INDEX idx_worker (agency_worker_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Turnos (Shifts)
CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  group_id INT NULL,
  employee_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  location VARCHAR(160) NULL,
  status ENUM(
    'planned',
    'assigned',
    'in_progress',
    'done',
    'canceled'
  ) NOT NULL DEFAULT 'planned',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE
  SET NULL,
    FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
    INDEX idx_tenant_dates (tenant_id, start_at, end_at),
    INDEX idx_group (group_id),
    INDEX idx_employee (own_employee_id),
    INDEX idx_worker (agency_worker_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Logs de trabajo (Work Logs)
CREATE TABLE IF NOT EXISTS work_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  employee_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  project_id INT NULL,
  shift_id INT NULL,
  date DATE NOT NULL,
  clock_in DATETIME NOT NULL,
  clock_out DATETIME NULL,
  breaks_minutes INT NOT NULL DEFAULT 0,
  status ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE
  SET NULL,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE
  SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE
  SET NULL,
    INDEX idx_tenant_date (tenant_id, date),
    INDEX idx_status (status),
    INDEX idx_employee (own_employee_id),
    INDEX idx_worker (agency_worker_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Partes del líder (Group Shift Reports)
CREATE TABLE IF NOT EXISTS group_shift_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  group_id INT NOT NULL,
  shift_id INT NULL,
  date DATE NOT NULL,
  leader_user_id INT NOT NULL,
  notes TEXT NULL,
  status ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE
  SET NULL,
    FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tenant_date (tenant_id, date),
    INDEX idx_group (group_id),
    INDEX idx_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Líneas del parte del líder
CREATE TABLE IF NOT EXISTS group_shift_report_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  member_type ENUM('own_employee', 'agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 1,
  clock_in DATETIME NULL,
  clock_out DATETIME NULL,
  breaks_minutes INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  FOREIGN KEY (report_id) REFERENCES group_shift_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (own_employee_id) REFERENCES own_employees(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  INDEX idx_report (report_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Disputas
CREATE TABLE IF NOT EXISTS disputes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  work_log_id BIGINT NULL,
  report_line_id INT NULL,
  raised_by_user INT NOT NULL,
  raised_by_tenant_id INT NOT NULL,
  reason TEXT NOT NULL,
  evidence_json JSON NULL,
  status ENUM('sin_disputa', 'disputado', 'resuelto') NOT NULL DEFAULT 'sin_disputa',
  resolution TEXT NULL,
  resolved_by INT NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (work_log_id) REFERENCES work_logs(id) ON DELETE CASCADE,
  FOREIGN KEY (report_line_id) REFERENCES group_shift_report_lines(id) ON DELETE CASCADE,
  FOREIGN KEY (raised_by_user) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (raised_by_tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE
  SET NULL,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_work_log (work_log_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Valoraciones (Ratings)
CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  agency_worker_id INT NOT NULL,
  rated_by_user INT NOT NULL,
  score TINYINT NOT NULL CHECK (
    score >= 1
    AND score <= 5
  ),
  tags_json JSON NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_company_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (agency_worker_id) REFERENCES agency_workers(id) ON DELETE CASCADE,
  FOREIGN KEY (rated_by_user) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_company_worker (tenant_company_id, agency_worker_id),
  INDEX idx_score (score)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================
-- ÓRDENES DE TRABAJO (OT)
-- ============================================
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
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE
  SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE
  SET NULL,
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
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE
  SET NULL,
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
  signed_by VARCHAR(160) NULL,
  role ENUM('client', 'company_leader', 'agency_coordinator') NOT NULL,
  signed_at DATETIME NULL,
  signature_url VARCHAR(255) NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  INDEX idx_work_order (work_order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Cambios solicitados en OT
CREATE TABLE IF NOT EXISTS work_order_changes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  change_type ENUM('scope', 'schedule', 'resources') NOT NULL,
  description TEXT NOT NULL,
  requested_by INT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_work_order_status (work_order_id, status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================
-- CONFIGURACIÓN Y OTROS
-- ============================================
-- Configuraciones (herencia Owner → Tenant → Proyecto → Grupo)
CREATE TABLE IF NOT EXISTS configurations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NULL,
  project_id INT NULL,
  group_id INT NULL,
  config_key VARCHAR(100) NOT NULL,
  config_value JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id),
  INDEX idx_project (project_id),
  INDEX idx_group (group_id),
  INDEX idx_key (config_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Preferencias de notificaciones
CREATE TABLE IF NOT EXISTS notification_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NULL,
  user_id INT NULL,
  channel ENUM('email', 'sms', 'whatsapp', 'webpush') NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_channel_event (channel, event_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Webhooks
CREATE TABLE IF NOT EXISTS webhooks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  events_json JSON NOT NULL,
  secret VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Entregas de webhooks
CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  webhook_id INT NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_json JSON NOT NULL,
  attempt INT NOT NULL DEFAULT 1,
  status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  response_code INT NULL,
  response_body TEXT NULL,
  attempted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
  INDEX idx_webhook_status (webhook_id, status),
  INDEX idx_attempted (attempted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- API Keys (para API pública)
CREATE TABLE IF NOT EXISTS api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  key_hash VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  scopes_json JSON NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant_active (tenant_id, is_active),
  INDEX idx_key_hash (key_hash)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Claves de idempotencia
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_value VARCHAR(255) NOT NULL UNIQUE,
  response_json JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  INDEX idx_key (key_value),
  INDEX idx_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Refresh tokens (JWT)
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_token (token_hash),
  INDEX idx_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;