# Bộ Dữ Liệu Thử Nghiệm (Sample Dataset)

Theo yêu cầu mục 5 của đề tài:
> "Nhóm chuẩn bị 6 PDF tự tạo, tổng khoảng 40 trang; mỗi trang có nhãn nhận biết riêng. Có trang dọc, ngang, kích thước khác nhau, tệp rỗng hoặc hỏng."

---

## Danh Sách 6 Tệp PDF Phục Vụ Kiểm Thử:

| STT | Tên Tệp PDF | Số Trang | Kích thước / Chiều | Nhãn Trang (Labels) | Mục Đích Kiểm Thử |
|---|---|---|---|---|---|
| **1** | `Tai_lieu_hoc_tap.pdf` | 8 trang | A4 dọc (Portrait) | `A1` đến `A8` | Thử nghiệm ghép trang cơ bản, đổi thứ tự, xoay trang. |
| **2** | `De_cuong_mon_hoc.pdf` | 24 trang | Xen kẽ dọc và ngang (Landscape) | `B1` đến `B24` | Kiểm tra khả năng xử lý tài liệu nhiều trang và đa dạng hướng giấy. |
| **3** | `Bai_giang_Tin_hoc.pdf` | 18 trang | Khổ màn hình trình chiếu (16:9 / Letter) | `C1` đến `C18` | Kiểm tra tài liệu có tỉ lệ kích thước khác biệt. |
| **4** | `Ho_so_tot_nghiep.pdf` | 12 trang | Khổ A4 tiêu chuẩn | `D1` đến `D12` | Kịch bản ghép bộ hồ sơ sinh viên thực tế (Tình huống 1). |
| **5** | `File_rong.pdf` | 0 trang | 0 bytes | Không có | Kiểm tra kiểm soát lỗi tệp rỗng (Empty file rejection). |
| **6** | `File_hong.pdf` | - | Header hỏng (corrupted EOF/xref) | Không có | Kiểm tra cơ chế cách ly lỗi (Error isolation), không làm crash kế hoạch. |

---

## Cách Khởi Tạo Bộ Tệp Kiểm Thử

Chạy lệnh sau để tự động sinh toàn bộ 6 tệp PDF có nhãn chuẩn:
```bash
php sample_data/generate_test_pdfs.php
```
