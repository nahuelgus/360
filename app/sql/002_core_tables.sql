SET NAMES utf8; SET FOREIGN_KEY_CHECKS=0;
-- Etiquetas globales
CREATE TABLE IF NOT EXISTS product_labels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  icon_path VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NULL,
  barcode VARCHAR(60) NULL,
  name VARCHAR(200) NOT NULL,
  unit ENUM('unit','gram','box') NOT NULL DEFAULT 'unit',
  box_units INT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_barcode (barcode), INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS product_label_links (
  product_id INT UNSIGNED NOT NULL,
  label_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id,label_id),
  CONSTRAINT fk_pll_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pll_l FOREIGN KEY (label_id) REFERENCES product_labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Cajas
CREATE TABLE IF NOT EXISTS cash_registers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  INDEX idx_cr_branch (branch_id),
  CONSTRAINT fk_cr_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS cash_shifts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  register_id INT UNSIGNED NOT NULL,
  user_id_open INT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  closed_at DATETIME NULL,
  closing_amount DECIMAL(12,2) NULL,
  keep_in_drawer_amount DECIMAL(12,2) NULL,
  delivered_amount DECIMAL(12,2) NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  CONSTRAINT fk_cs_reg FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS cash_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id INT UNSIGNED NOT NULL,
  kind ENUM('income','expense','sale','refund') NOT NULL DEFAULT 'income',
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(200) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_cm_shift FOREIGN KEY (shift_id) REFERENCES cash_shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Medios de pago
CREATE TABLE IF NOT EXISTS payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  bank VARCHAR(100) NULL,
  fee_percent DECIMAL(6,3) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Ventas
CREATE TABLE IF NOT EXISTS sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  society_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NOT NULL,
  shift_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  doc_type ENUM('TICKET_X','INVOICE') NOT NULL DEFAULT 'TICKET_X',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  arca_status ENUM('not_applicable','pending','sent','voided','error') NOT NULL DEFAULT 'not_applicable',
  INDEX idx_sale_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount_pct DECIMAL(6,3) NULL,
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS sale_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  payment_method_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  pos_info VARCHAR(120) NULL,
  CONSTRAINT fk_sp_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ARCA cuentas por sociedad
CREATE TABLE IF NOT EXISTS arca_accounts (
  society_id INT UNSIGNED PRIMARY KEY,
  env ENUM('none','sandbox','production') NOT NULL DEFAULT 'none',
  api_key VARCHAR(160) NULL,
  api_secret VARCHAR(160) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(40) NULL,
  CONSTRAINT fk_arca_soc FOREIGN KEY (society_id) REFERENCES societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Clientes y fidelización (1 punto cada $50)
CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dni VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL, phone VARCHAR(60) NULL,
  birthdate DATE NULL,
  address VARCHAR(160) NULL,
  diet_flags VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS loyalty_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  pesos_per_point DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO loyalty_settings (id,pesos_per_point) VALUES (1,50.00)
  ON DUPLICATE KEY UPDATE pesos_per_point=VALUES(pesos_per_point);

-- Mail SMTP
CREATE TABLE IF NOT EXISTS mail_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  smtp_host VARCHAR(120), smtp_port INT, smtp_user VARCHAR(120), smtp_pass VARCHAR(120),
  secure ENUM('none','tls','ssl') NOT NULL DEFAULT 'none',
  from_email VARCHAR(120), from_name VARCHAR(120),
  is_active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS=1;