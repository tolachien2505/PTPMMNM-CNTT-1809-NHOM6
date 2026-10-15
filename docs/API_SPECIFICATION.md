# Đặc Tả API Hệ Thống (RESTful API Specification)

Tất cả các Endpoint đều trả về định dạng JSON với mã trạng thái HTTP chuẩn.

---

## 1. Quản Lý Tài Liệu (Documents API)

### `GET /api/documents`
- **Mục đích**: Lấy danh sách tài liệu của người dùng hiện tại.
- **Header**: `Authorization: Bearer <token>` hoặc Cookie Session.
- **Phản hồi mẫu**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Tai_lieu_hoc_tap.pdf",
      "pages": 8,
      "size": 2202009,
      "status": "valid"
    }
  ]
}
```

### `POST /api/documents/upload`
- **Mục đích**: Tải lên một hoặc nhiều tệp PDF.
- **Content-Type**: `multipart/form-data`
- **Body**: `files[]: File (PDF)`

---

## 2. Kế Hoạch Chỉnh Sửa (Edit Plans API)

### `POST /api/plans/save`
- **Mục đích**: Lưu bản nháp sắp xếp trang hiện tại.
- **Body JSON**:
```json
{
  "title": "Bộ hồ sơ tốt nghiệp",
  "plan_items": [
    {"doc_id": 1, "page_number": 2, "rotation": 90, "label": "A2"},
    {"doc_id": 2, "page_number": 1, "rotation": 0, "label": "B1"}
  ]
}
```

### `POST /api/plans/validate`
- **Mục đích**: Kiểm tra kế hoạch trước khi tạo tệp (Pre-flight check).

---

## 3. Xuất Tài Liệu (Export API)

### `POST /api/export/merge`
- **Mục đích**: Ghép các trang theo thứ tự kế hoạch thành một tài liệu duy nhất.
- **Body JSON**: `{ "plan_id": 10 }`

### `POST /api/export/split`
- **Mục đích**: Tách thành các nhóm tệp PDF riêng biệt.
- **Body JSON**:
```json
{
  "groups": [
    {"name": "Nhom_1.pdf", "items": [{"doc_id": 1, "page_number": 1}, {"doc_id": 1, "page_number": 2}]},
    {"name": "Nhom_2.pdf", "items": [{"doc_id": 1, "page_number": 3}]}
  ]
}
```

### `GET /api/export/manifest/{output_id}`
- **Mục đích**: Tra cứu danh sách trang nguồn đối chiếu của bản xuất.
- **Phản hồi**:
```json
{
  "output_id": 5,
  "output_filename": "Ho_so_hoan_chinh.pdf",
  "manifest": [
    {"output_page": 1, "source_doc": "Tai_lieu_hoc_tap.pdf", "source_page": 2, "label": "A2", "rotation": 90},
    {"output_page": 2, "source_doc": "De_cuong_mon_hoc.pdf", "source_page": 1, "label": "B1", "rotation": 0}
  ]
}
```
