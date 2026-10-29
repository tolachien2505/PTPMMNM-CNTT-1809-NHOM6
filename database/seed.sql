-- Dữ liệu mẫu khởi tạo (Seed Data)

-- 1. Người dùng mẫu (Người dùng & Quản trị viên)
INSERT INTO users (id, username, email, full_name, role) VALUES
(1, 'sinhvien_a', 'nguyenvana@dnu.edu.vn', 'Nguyễn Văn A', 'user'),
(2, 'giangvien_b', 'tranthib@dnu.edu.vn', 'Trần Thị B', 'user'),
(3, 'chien123hello', 'vvchien25@gmail.com', 'dsadas', 'admin');

-- 2. Dữ liệu 6 tài liệu mẫu (khớp chính xác đề bài và ảnh demo: 6 tệp, 40 trang, 5 hợp lệ, 1 lỗi)
INSERT INTO documents (id, user_id, original_name, stored_filename, file_size, total_pages, status, error_message, sha256_hash) VALUES
(1, 1, 'Tai_lieu_hoc_tap.pdf', 'doc_1_tai_lieu_hoc_tap.pdf', 2202009, 8, 'valid', NULL, 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'),
(2, 1, 'De_cuong_mon_hoc.pdf', 'doc_2_de_cuong_mon_hoc.pdf', 5033164, 24, 'valid', NULL, 'ca978112ca1bbdcafac231b39a23dc4da786eff8147c4e72b9807785afee48bb'),
(3, 1, 'Bai_giang_Tin_hoc.pdf', 'doc_3_bai_giang_tin_hoc.pdf', 3250585, 18, 'valid', NULL, '4e07408562bedb8b60ce05c1decfe3ad16b72230967de01f640b7e4729b49fce'),
(4, 1, 'Ho_so_tot_nghiep.pdf', 'doc_4_ho_so_tot_nghiep.pdf', 2516582, 12, 'valid', NULL, '4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a'),
(5, 1, 'File_rong.pdf', 'doc_5_file_rong.pdf', 0, 0, 'empty', 'Tệp tin không có nội dung hoặc dung lượng 0 bytes', 'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce'),
(6, 1, 'Tai_lieu_tham_khao.pdf', 'doc_6_tai_lieu_tham_khao.pdf', 1258291, 8, 'valid', NULL, 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e');

-- 3. Một số trang nguồn tiêu biểu có nhãn nhận biết (A1, A2, B1, B2...)
INSERT INTO source_pages (document_id, page_number, label, orientation) VALUES
(1, 1, 'A1', 'portrait'),
(1, 2, 'A2', 'portrait'),
(1, 3, 'A3', 'landscape'),
(2, 1, 'B1', 'portrait'),
(2, 2, 'B2', 'portrait');
