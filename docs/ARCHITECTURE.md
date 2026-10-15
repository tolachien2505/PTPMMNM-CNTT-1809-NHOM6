# Kiến Trúc Hệ Thống (System Architecture)

## 1. Tổng Quan Kiến Trúc
Ứng dụng **PDF Manager** được thiết kế theo kiến trúc phân lớp (Layered Architecture) với mô hình Single Page Application (SPA) kết hợp RESTful API Backend viết bằng PHP hiện đại (hỗ trợ PSR-4, PSR-12).

```
+-------------------------------------------------------------+
|                 Trình duyệt người dùng (Client)            |
|  - HTML5 / Vanilla Modern CSS / ES6 JavaScript Modules      |
|  - Trình biên tập trực quan: kéo thả sắp xếp, xoay, Undo/Redo|
|  - Hiển thị xem trước: PDF.js & Canvas rendering            |
+------------------------------+------------------------------+
                               | (HTTP REST / JSON API)
                               v
+-------------------------------------------------------------+
|                     API Controller Layer                    |
|  - DocumentController (Tải lên, kiểm tra metadata, xóa)    |
|  - EditorController   (Quản lý kế hoạch, Undo, xác thực)    |
|  - ExportController   (Ghép, tách, theo dõi job, tải file)  |
|  - AuthController     (Xác thực & phân quyền người dùng)    |
+------------------------------+------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                     Business Service Layer                  |
|  - PdfProcessorService: Quản lý danh sách trang, thứ tự,    |
|    ghép nối, chia tách, áp dụng góc xoay.                   |
|  - SecurityValidator: Kiểm tra MIME, dung lượng, thời gian, |
|    quyền sở hữu dữ liệu, chống path traversal.              |
|  - StorageService: Quản lý tệp gốc bất biến, tệp xuất tạm.  |
|  - PdfPreviewService: Trích xuất thumbnail từng trang.     |
+------------------------------+------------------------------+
                               |
            +------------------+------------------+
            |                                     |
            v                                     v
+-----------------------+             +-----------------------+
|  Database Layer       |             | File Storage Layer    |
|  (SQLite / MySQL PDO) |             | storage/documents/    |
|  - users              |             | storage/exports/      |
|  - documents          |             | storage/previews/     |
|  - source_pages       |             | storage/logs/         |
|  - edit_plans         |             +-----------------------+
|  - export_jobs        |
|  - outputs            |
+-----------------------+
```

## 2. Giải Quyết Hai Yêu Cầu Kỹ Thuật Cốt Lõi

### Yêu Cầu Kỹ Thuật 1: Quản lý danh sách trang và thứ tự thao tác
- **Cơ chế**: Mỗi trang trong kế hoạch xuất được định danh bằng một bộ ba thuộc tính bắt buộc:
  `{source_document_id, source_page_number, rotation_degree}`.
- **Tính thống nhất**: Bản xem trước trên trình duyệt và tệp PDF xuất ra từ Backend sử dụng chung một cấu trúc dữ liệu JSON (`plan_data`). Khi Client gửi yêu cầu xuất, Backend kiểm tra `pre-flight validation` trước khi gọi engine PDF, đảm bảo thứ tự trang xuất ra khớp 100% với những gì người dùng nhìn thấy.

### Yêu Cầu Kỹ Thuật 2: Tách biệt dữ liệu và bảo toàn tệp gốc
- **Nguyên tắc bất biến (Immutability)**: Tệp gốc trong `storage/documents/` là Read-Only đối với các thao tác xử lý PDF.
- **Cô lập phiên xuất (Export Isolation)**: Mỗi lần bấm xuất sẽ sinh ra một `export_job_id` độc lập. Kế hoạch đã chốt được đánh dấu `is_locked = 1`. Nếu lần sửa sau có thay đổi hoặc hủy thì tác vụ đang xử lý không bị ảnh hưởng.
- **Xử lý lỗi nguyên tử (Atomic / Safe Failure)**: Tệp đầu ra được ghi vào thư mục tạm trước. Chỉ khi toàn bộ các trang được render thành công và kiểm tra tính toàn vẹn thì mới chuyển vào bảng `outputs` và cho phép tải về. Nếu có lỗi, file tạm bị hủy ngay lập tức, không bao giờ xuất hiện dưới dạng tài liệu hoàn chỉnh.
