# Nhật Ký Thay Đổi (Changelog)

Toàn bộ các cập nhật quan trọng của dự án **PDF Manager** được ghi lại tại đây theo chuẩn [Keep a Changelog](https://keepachangelog.com/).

## [1.0.0-alpha] - 2026-10-15
### Khởi tạo cấu trúc & thiết kế hệ thống
- Khởi tạo kiến trúc dự án mã nguồn mở theo chuẩn PSR-4 (PHP).
- Thiết kế giao diện Dashboard và bộ công cụ xử lý PDF trên trình duyệt (theo nguyên mẫu `giaodien_demo.png`).
- Xây dựng sơ đồ cơ sở dữ liệu cho 5 thực thể chính: `users`, `documents`, `source_pages`, `edit_plans`, `export_jobs`, `outputs`.
- Định nghĩa 5 chức năng cơ bản, 2 yêu cầu kỹ thuật độc lập dữ liệu & bảo toàn nguồn gốc trang.
- Thiết lập kịch bản và 5 phép thử bắt buộc (Test Cases 1-5).
- Cung cấp cấu hình Docker, Docker Compose và tài liệu cài đặt.
