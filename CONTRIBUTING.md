# Hướng Dẫn Đóng Góp (Contributing Guidelines)

Dự án **PDF Manager** - Đề tài 12: "Xây dựng công cụ sắp xếp, ghép, tách và xử lý tài liệu PDF trên trình duyệt" được phát triển theo mô hình mã nguồn mở trong khuôn khổ học phần **Phát triển phần mềm mã nguồn mở - Trường Đại học Đại Nam**.

Chúng tôi rất hoan nghênh sự đóng góp từ tất cả các thành viên trong nhóm và cộng đồng.

---

## 1. Quy Trình Làm Việc (Git Workflow)

1. **Fork hoặc tạo nhánh mới** từ nhánh chính (`main` hoặc `develop`):
   ```bash
   git checkout -b feature/ten-tinh-nang
   # hoặc
   git checkout -b fix/ten-loi
   ```
2. **Quy chuẩn đặt tên nhánh (Branch Naming)**:
   - `feature/xxx`: Phát triển chức năng mới (vd: `feature/pdf-rotate`, `feature/drag-and-drop`).
   - `fix/xxx`: Sửa lỗi (vd: `fix/corrupt-pdf-handling`).
   - `docs/xxx`: Cập nhật tài liệu, README, rubric.
   - `test/xxx`: Bổ sung kiểm thử tự động, test case dữ liệu.

3. **Quy chuẩn Commit Message (Conventional Commits)**:
   - `feat: thêm chức năng xoay trang 90/180/270 độ`
   - `fix: xử lý lỗi khi tải lên tệp rỗng không làm crash kế hoạch`
   - `docs: bổ sung hướng dẫn cài đặt Docker trong README`
   - `test: viết kịch bản kiểm thử tự động Test Case 1 ghép A2, B1, A1`
   - `refactor: tái cấu trúc PdfProcessorService độc lập dữ liệu`

---

## 2. Tiêu Chuẩn Lập Trình (Coding Standards)

- **PHP**: Tuân thủ chuẩn PSR-12, sử dụng PSR-4 cho Autoloading.
- **Frontend**: HTML5 ngữ nghĩa, Vanilla CSS theo hệ thống biến màu sắc (CSS custom properties), Javascript ES6+.
- **Bảo mật**:
  - Không truyền đường dẫn tệp thực tế ra Client.
  - Luôn kiểm tra MIME type thực tế bằng Fileinfo, không chỉ dựa vào phần mở rộng `.pdf`.
  - Giới hạn dung lượng tệp (`MAX_FILE_SIZE_MB`) và thời gian thực thi (`MAX_EXECUTION_TIME`).
  - Sử dụng Prepared Statements cho tất cả các thao tác Database (chống SQL Injection).

---

## 3. Quy Trình Review & Tạo Pull Request (PR)

1. Đảm bảo chạy thử nghiệm tất cả các Test Case trước khi gửi PR:
   ```bash
   composer test
   ```
2. Mở Pull Request vào nhánh `main` và điền đầy đủ thông tin theo mẫu `pull_request_template.md`.
3. Cần ít nhất 01 thành viên trong nhóm duyệt (Code Review) trước khi gộp code (Merge).
4. Phải vượt qua các bước kiểm tra tự động của CI/CD GitHub Actions.
