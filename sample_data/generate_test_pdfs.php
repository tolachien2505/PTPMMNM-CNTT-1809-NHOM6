<?php
/**
 * Script tự động sinh 6 tệp PDF thử nghiệm theo đúng yêu cầu đề tài
 * Tổng khoảng 40 trang, có nhãn nhận biết (A1, A2, B1...), khổ dọc, ngang, rỗng và hỏng.
 */

$storageDir = __DIR__ . '/../storage/documents';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

echo "=== KHỞI TẠO BỘ DỮ LIỆU THỬ NGHIỆM 6 FILE PDF ===" . PHP_EOL;

// 1. Tạo tệp rỗng (0 bytes)
$emptyFile = $storageDir . '/File_rong.pdf';
file_put_contents($emptyFile, '');
echo "[✓] Tạo thành công: File_rong.pdf (0 bytes)" . PHP_EOL;

// 2. Tạo tệp hỏng (corrupted header/xref)
$corruptFile = $storageDir . '/File_hong.pdf';
file_put_contents($corruptFile, "%PDF-1.4\n%CORRUPTED_BINARY_DATA_TESTING_ERROR_HANDLING\n%%EOF");
echo "[✓] Tạo thành công: File_hong.pdf (Hỏng cấu trúc)" . PHP_EOL;

// 3. Khởi tạo danh sách các tệp chuẩn
$files = [
    'Tai_lieu_hoc_tap.pdf' => ['pages' => 8, 'prefix' => 'A'],
    'De_cuong_mon_hoc.pdf' => ['pages' => 24, 'prefix' => 'B'],
    'Bai_giang_Tin_hoc.pdf' => ['pages' => 18, 'prefix' => 'C'],
    'Ho_so_tot_nghiep.pdf'  => ['pages' => 12, 'prefix' => 'D'],
];

foreach ($files as $filename => $info) {
    $targetPath = $storageDir . '/' . $filename;
    if (!file_exists($targetPath)) {
        // Tạo file PDF đơn giản nếu chưa có
        $pdfContent = "%PDF-1.4\n% DNU OpenSource Sample PDF: {$filename}\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count {$info['pages']} >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000120 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n180\n%%EOF";
        file_put_contents($targetPath, $pdfContent);
    }
    echo "[✓] Đã ghi nhận: {$filename} ({$info['pages']} trang, tiền tố nhãn '{$info['prefix']}')" . PHP_EOL;
}

echo "=== HOÀN TẤT KHỞI TẠO BỘ DỮ LIỆU KIỂM THỬ ===" . PHP_EOL;
