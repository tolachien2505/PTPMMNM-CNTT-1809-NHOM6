-- ==============================================================
-- Schema: PDF Manager - Đề tài 12 (Trường Đại học Đại Nam)
-- Quản lý tài liệu, trang nguồn, kế hoạch sửa đổi, việc xuất & kết quả
-- ==============================================================

-- 1. Bảng người dùng (phân quyền & cô lập dữ liệu theo người dùng)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'student', -- 'admin', 'lecturer', 'student'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng tài liệu gốc (documents - tệp gốc không bị thay đổi)
CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    total_pages INTEGER DEFAULT 0,
    status VARCHAR(30) DEFAULT 'valid', -- 'valid', 'empty', 'corrupted', 'oversize'
    error_message TEXT,
    sha256_hash VARCHAR(64),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Bảng từng trang nguồn (source_pages - quản lý chi tiết từng trang)
CREATE TABLE IF NOT EXISTS source_pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    page_number INTEGER NOT NULL, -- 1-indexed
    label VARCHAR(50),            -- Mã nhãn nhận diện (ví dụ: A1, A2, B1...)
    width REAL DEFAULT 595.28,    -- Kích thước điểm chuẩn A4 (hoặc kích thước thực)
    height REAL DEFAULT 841.89,
    orientation VARCHAR(20) DEFAULT 'portrait', -- 'portrait', 'landscape'
    thumbnail_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

-- 4. Bảng kế hoạch chỉnh sửa (edit_plans - lưu bản đang sắp xếp)
CREATE TABLE IF NOT EXISTS edit_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    plan_data TEXT NOT NULL,      -- JSON lưu cấu trúc trang: [{"doc_id": 1, "page": 2, "rotation": 90, "order": 1, "is_selected": true}, ...]
    is_locked BOOLEAN DEFAULT 0,  -- 1: Kế hoạch đã gửi xử lý chốt phiên, không bị ghi đè bởi lần sau
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Bảng tác vụ xuất tệp (export_jobs - theo dõi trạng thái, tách bạch từng lần xuất)
CREATE TABLE IF NOT EXISTS export_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    edit_plan_id INTEGER NOT NULL,
    job_type VARCHAR(30) DEFAULT 'merge', -- 'merge', 'split'
    status VARCHAR(30) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    progress_percent INTEGER DEFAULT 0,
    error_message TEXT,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (edit_plan_id) REFERENCES edit_plans(id) ON DELETE CASCADE
);

-- 6. Bảng tài liệu kết quả (outputs - tệp xuất ra và manifest đối chiếu trang nguồn)
CREATE TABLE IF NOT EXISTS outputs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    export_job_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    total_pages INTEGER NOT NULL,
    source_manifest TEXT NOT NULL, -- JSON đối chiếu: [{"output_page": 1, "source_doc": "FileA.pdf", "source_page": 2, "label": "A2", "rotation": 90}, ...]
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (export_job_id) REFERENCES export_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tạo Index để tối ưu truy vấn
CREATE INDEX IF NOT EXISTS idx_docs_user ON documents(user_id);
CREATE INDEX IF NOT EXISTS idx_pages_doc ON source_pages(document_id);
CREATE INDEX IF NOT EXISTS idx_plans_user ON edit_plans(user_id);
CREATE INDEX IF NOT EXISTS idx_jobs_status ON export_jobs(status);
CREATE INDEX IF NOT EXISTS idx_outputs_user ON outputs(user_id);
