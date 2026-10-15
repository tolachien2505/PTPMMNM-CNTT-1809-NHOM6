# Lab 01 — Vận hành dự án trên Linux và viết shell script

Mã nguồn hoàn chỉnh cho phần hướng dẫn và bài mở rộng CommunityPass.

## Mở bài

Mở terminal Ubuntu/WSL tại thư mục chứa `lab.sh`. Dùng PHP từ 8.1 và Git. Hướng dẫn chi tiết, sơ đồ, đoạn mã cần sửa, dữ liệu đối chiếu và rubric nằm trong tài liệu của bài lab.

```bash
bash lab.sh check
bash lab.sh run
```

Mở http://127.0.0.1:8101. Giữ terminal máy chủ hoạt động; dùng terminal thứ hai để kiểm thử. Nhấn Ctrl+C để dừng.

Bài này không cần PostgreSQL, Composer hoặc framework.

## Kiểm tra bài

```bash
bash lab.sh test
bash lab.sh extend
```

`test` kiểm tra script môi trường; `extend` chạy bốn tình huống kiểm thử cho script quét mã PHP.

Kết quả đạt yêu cầu:

```text
CHECK_ENV=PASS
SHELL_CASES=4 FAILED=0
```

`scripts/check-env.sh` kiểm tra PHP, Git, các tệp cần thiết và cú pháp trang PHP. `scripts/scan-source.sh DIRECTORY` quét đệ quy các tệp PHP, giữ nguyên tên đường dẫn có khoảng trắng và in tổng số tệp đạt/lỗi. Script trả mã `0` nếu tất cả tệp hợp lệ, `1` nếu có tệp sai cú pháp và `2` nếu đầu vào không dùng được hoặc không có tệp PHP.

Có thể kiểm tra trực tiếp một thư mục:

```bash
bash scripts/scan-source.sh public
echo "exit=$?"
```

## Bàn giao

Giữ mã nguồn, dữ liệu mẫu và kiểm thử. Chạy `bash lab.sh check`, `bash lab.sh test` và `bash lab.sh extend` trước khi nộp. Không đưa `.local`, mật khẩu hoặc dữ liệu cá nhân thật vào kho mã và gói nộp. Nộp đúng commit theo yêu cầu của bài; nếu dùng Actions, đính kèm lần chạy tương ứng.


## Phần trình bày giao diện

Lab này giữ CSS cục bộ tại `public/assets/css/app.css`. HTML nằm trong `public/`; các lab có nhiều trang dùng `templates/header.php` và `templates/footer.php`. Có thể lần từ các lớp `form-field`, `event-item`, `error-summary` sang CSS để đọc cách trình bày; Lab01 chỉ dùng phần trang giới thiệu.

Các lớp, nhãn và bố cục chỉ phục vụ hiển thị. Giữ nguyên khối PHP, GET/POST, tên trường, giá trị option, hidden input và thuộc tính kiểm tra đầu vào khi thực hành. Không cần JavaScript, thư viện hoặc bước build mới để chạy giao diện.


## Giao diện Material 3

Màu và kiểu chữ nằm trong `public/assets/css/tokens.css`; bố cục trong `app.css`. Không có bước build hoặc thư viện mới.
