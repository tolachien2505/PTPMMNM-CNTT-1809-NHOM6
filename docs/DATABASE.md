# Thiết Kế Cơ Sở Dữ Liệu (Database Design)

Cơ sở dữ liệu được thiết kế bám sát gợi ý của đề tài: `documents`, `source_pages`, `edit_plans`, `export_jobs`, `outputs` và bảng phụ trợ `users`.

---

## 1. Bảng `users` (Quản lý người dùng & phân quyền)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã định danh người dùng |
| `username` | VARCHAR(50) | NOT NULL, UNIQUE | Tên đăng nhập |
| `email` | VARCHAR(100) | NOT NULL, UNIQUE | Email |
| `full_name` | VARCHAR(100) | NOT NULL | Họ và tên |
| `role` | VARCHAR(20) | DEFAULT 'student' | Vai trò: 'admin', 'lecturer', 'student' |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Ngày tạo tài khoản |

---

## 2. Bảng `documents` (Quản lý tài liệu gốc)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã định danh tài liệu |
| `user_id` | INTEGER | FOREIGN KEY -> users(id) | Người sở hữu tài liệu |
| `original_name` | VARCHAR(255) | NOT NULL | Tên tệp gốc khi tải lên |
| `stored_filename`| VARCHAR(255) | NOT NULL | Tên lưu trữ mã hóa an toàn trên đĩa |
| `file_size` | INTEGER | NOT NULL | Dung lượng tệp tính bằng bytes |
| `total_pages` | INTEGER | DEFAULT 0 | Tổng số trang |
| `status` | VARCHAR(30) | DEFAULT 'valid' | 'valid', 'empty', 'corrupted', 'oversize' |
| `error_message` | TEXT | NULL | Thông báo lỗi nếu tệp hỏng hoặc quá giới hạn |
| `sha256_hash` | VARCHAR(64) | NULL | Mã băm kiểm tra tính bất biến của tệp gốc |

---

## 3. Bảng `source_pages` (Chi tiết từng trang nguồn)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã định danh trang |
| `document_id` | INTEGER | FOREIGN KEY -> documents(id)| Thuộc tài liệu nào |
| `page_number` | INTEGER | NOT NULL | Thứ tự trang trong tài liệu gốc (1-indexed) |
| `label` | VARCHAR(50) | NULL | Nhãn nhận diện thử nghiệm (vd: A1, A2, B1...) |
| `width` | REAL | DEFAULT 595.28 | Chiều rộng trang |
| `height` | REAL | DEFAULT 841.89 | Chiều cao trang |
| `orientation` | VARCHAR(20) | DEFAULT 'portrait' | 'portrait' (dọc), 'landscape' (ngang) |
| `thumbnail_path` | VARCHAR(255)| NULL | Đường dẫn ảnh xem trước thu nhỏ |

---

## 4. Bảng `edit_plans` (Bản lưu sắp xếp & kế hoạch xử lý)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã kế hoạch |
| `user_id` | INTEGER | FOREIGN KEY -> users(id) | Người tạo kế hoạch |
| `title` | VARCHAR(255) | NOT NULL | Tên kế hoạch lưu |
| `plan_data` | TEXT (JSON) | NOT NULL | Dữ liệu mảng trang, thứ tự, góc xoay |
| `is_locked` | BOOLEAN | DEFAULT 0 | Đã chốt phiên xuất, ngăn ghi đè |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Thời gian tạo |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Thời gian sửa đổi gần nhất |

---

## 5. Bảng `export_jobs` (Theo dõi tác vụ xuất độc lập)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã tác vụ xuất |
| `user_id` | INTEGER | FOREIGN KEY -> users(id) | Người kích hoạt |
| `edit_plan_id` | INTEGER | FOREIGN KEY -> edit_plans(id)| Kế hoạch sử dụng |
| `job_type` | VARCHAR(30) | DEFAULT 'merge' | Loại xuất: 'merge' (ghép) hoặc 'split' (tách) |
| `status` | VARCHAR(30) | DEFAULT 'pending' | 'pending', 'processing', 'completed', 'failed' |
| `error_message` | TEXT | NULL | Nhật ký lỗi nếu tác vụ thất bại |

---

## 6. Bảng `outputs` (Tài liệu kết quả xuất ra)
| Cột | Kiểu dữ liệu | Ràng buộc | Ý nghĩa |
|---|---|---|---|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Mã tài liệu kết quả |
| `export_job_id` | INTEGER | FOREIGN KEY -> export_jobs(id)| Thuộc tác vụ nào |
| `user_id` | INTEGER | FOREIGN KEY -> users(id) | Quyền sở hữu kết quả |
| `filename` | VARCHAR(255) | NOT NULL | Tên tệp hiển thị cho người dùng |
| `stored_path` | VARCHAR(255) | NOT NULL | Đường dẫn lưu trữ nội bộ an toàn |
| `source_manifest`| TEXT (JSON) | NOT NULL | Bảng kê đối chiếu từng trang đầu ra với nguồn |
