-- Enetoku 水道光熱費管理アプリ
-- データベースセットアップ

CREATE DATABASE IF NOT EXISTS enetoku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE enetoku;

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    display_name VARCHAR(100),
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 光熱費記録テーブル
CREATE TABLE IF NOT EXISTS utility_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    utility_type ENUM('water','electricity','gas','other') NOT NULL,
    billing_year INT NOT NULL,
    billing_month INT NOT NULL,
    usage_amount DECIMAL(10,2) COMMENT '使用量',
    usage_unit VARCHAR(20) COMMENT '単位 (m3, kWh, etc)',
    billing_amount DECIMAL(10,2) NOT NULL COMMENT '請求金額(円)',
    billing_date DATE,
    ocr_raw_text TEXT COMMENT 'OCR生テキスト',
    image_path VARCHAR(255) COMMENT 'アップロード画像パス',
    memo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_record (user_id, utility_type, billing_year, billing_month)
);

-- AI分析履歴テーブル
CREATE TABLE IF NOT EXISTS ai_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    analysis_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- デフォルトadminユーザー (パスワード: admin1234)
INSERT IGNORE INTO users (username, password_hash, display_name, is_admin) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1);

-- テストユーザー (パスワード: test1234)
INSERT IGNORE INTO users (username, password_hash, display_name, is_admin) 
VALUES ('testuser', '$2y$10$TKh8H1.PJy3gc3eF.Y8e2.WYjFBnHc7TOd5lNVHWI44MblFGLFMey', 'テストユーザー', 0);
