# Kế Hoạch Kiểm Thử (Test Plan)

Dự án áp dụng quy trình kiểm thử tự động (Unit Test / Integration Test) với PHPUnit và bộ dữ liệu kiểm thử thực tế.

---

## 1. Mục Tiêu Kiểm Thử
- Đảm bảo 5 phép thử bắt buộc được định nghĩa ở mục 6 của đề tài luôn vượt qua (Pass 100%).
- Đảm bảo tính toàn vẹn của tệp nguồn sau mọi thao tác ghép/tách.
- Đảm bảo không xảy ra hiện tượng rò rỉ dữ liệu giữa các phiên người dùng.

---

## 2. Danh Sách Phép Thử Bắt Buộc (Theo Mục 6 & Rubric)

| Mã Thử Nghiệm | Tên Phép Thử | Kịch Bản & Thao Tác | Kết Quả Kỳ Vọng | File Test Tương Ứng |
|---|---|---|---|---|
| **TC-01** | Ghép A2, B1, A1 | Chọn trang 2 của tệp A (nhãn A2), trang 1 của tệp B (nhãn B1), trang 1 của tệp A (nhãn A1). Bấm Ghép. | Tệp kết quả có đúng 3 trang, nhãn lần lượt là A2, B1, A1. | `tests/TestCase1_MergeTest.php` |
| **TC-02** | Xoay 90° & bỏ trang | Chọn xoay trang A1 góc 90°, bỏ trang A2 khỏi danh sách. | Chỉ trang A1 đổi góc xoay, A2 bị loại bỏ; các trang khác giữ nguyên nội dung. | `tests/TestCase2_RotateDropTest.php` |
| **TC-03** | Tách theo 2 nhóm | Nhóm 1: trang 1, 2, 3; Nhóm 2: trang 4, 5. Thử yêu cầu trang 99 (không tồn tại). | Xuất ra 2 tệp PDF độc lập; yêu cầu trang 99 bị từ chối với thông báo lỗi. | `tests/TestCase3_SplitGroupTest.php` |
| **TC-04** | Cách ly lỗi tệp hỏng | Tải lên tệp hỏng (`File_hong.pdf`) hoặc tệp rỗng (`File_rong.pdf`) khi đang có kế hoạch hợp lệ. | Báo lỗi chi tiết theo tệp; kế hoạch hợp lệ không bị mất; job lỗi không hiển thị hoàn thành. | `tests/TestCase4_ErrorIsolationTest.php` |
| **TC-05** | Bảo mật đa người dùng | Tài khoản A và B cùng tải tệp và xuất tài liệu. | Không nhìn thấy hoặc tải nhầm tệp của nhau; mã SHA-256 tệp gốc không đổi. | `tests/TestCase5_MultiUserSecurityTest.php` |

---

## 3. Lệnh Chạy Kiểm Thử Tự Động
```bash
# Chạy toàn bộ test suite
vendor/bin/phpunit tests --testdox
```
