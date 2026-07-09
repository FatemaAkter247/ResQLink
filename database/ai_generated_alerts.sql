CREATE TABLE IF NOT EXISTS ai_generated_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_by INT DEFAULT NULL,
  alert_type VARCHAR(100) NOT NULL,
  location_text VARCHAR(255) NOT NULL,
  severity VARCHAR(50) NOT NULL,
  affected_area VARCHAR(255) DEFAULT NULL,
  shelter_name VARCHAR(150) DEFAULT NULL,
  emergency_contact VARCHAR(100) DEFAULT NULL,
  extra_notes TEXT DEFAULT NULL,
  message_en TEXT DEFAULT NULL,
  message_bn TEXT DEFAULT NULL,
  final_message_en TEXT DEFAULT NULL,
  final_message_bn TEXT DEFAULT NULL,
  status ENUM('draft','approved','published') DEFAULT 'draft',
  gemini_prompt TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
