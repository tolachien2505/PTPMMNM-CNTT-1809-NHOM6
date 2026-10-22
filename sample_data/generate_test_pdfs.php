<?php
/**
 * Script tự động sinh 6 tệp PDF thử nghiệm theo đúng yêu cầu đề tài
 * Tổng khoảng 40 trang, có nhãn nhận biết (A1, A2, B1...), khổ dọc, ngang, rỗng và hỏng.
 * Sử dụng thư viện FPDF để tạo tài liệu PDF chuẩn 100%, hỗ trợ FPDI ghép/tách.
 */

require_once __DIR__ . '/../vendor/autoload.php';

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

// 3. Khởi tạo danh sách các tệp chuẩn bằng FPDF
$files = [
    'Tai_lieu_hoc_tap.pdf' => ['pages' => 8, 'prefix' => 'A', 'orientation' => 'P', 'title' => 'Tai Lieu Hoc Tap - CNTT FIT DNU'],
    'De_cuong_mon_hoc.pdf' => ['pages' => 24, 'prefix' => 'B', 'orientation' => 'L', 'title' => 'De Cuong Mon Hoc - PTPM Ma Nguon Mo'],
    'Bai_giang_Tin_hoc.pdf' => ['pages' => 18, 'prefix' => 'C', 'orientation' => 'L', 'title' => 'Bai Giang Tin Hoc Dai Cuong'],
    'Ho_so_tot_nghiep.pdf'  => ['pages' => 12, 'prefix' => 'D', 'orientation' => 'P', 'title' => 'Ho So Tot Nghiep - Mau Chuan'],
];

foreach ($files as $filename => $info) {
    $targetPath = $storageDir . '/' . $filename;
    
    $pdf = new \FPDF($info['orientation'], 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);

    for ($page = 1; $page <= $info['pages']; $page++) {
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(37, 99, 235); // Blue
        $pdf->Cell(0, 15, $info['title'], 0, 1, 'C');

        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 8, "File: {$filename} | Page {$page}/{$info['pages']}", 0, 1, 'C');
        $pdf->Ln(20);

        // Huge Page Label Badge (e.g. A1, A2, B1...)
        $label = $info['prefix'] . $page;
        $pdf->SetFont('Arial', 'B', 60);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 40, $label, 0, 1, 'C');

        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(0, 10, "Trang nhan dien: [ {$label} ]", 0, 1, 'C');
        $pdf->Ln(15);

        // Border rectangle
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->SetLineWidth(0.8);
        $width = ($info['orientation'] === 'L') ? 277 : 190;
        $height = ($info['orientation'] === 'L') ? 190 : 277;
        $pdf->Rect(10, 10, $width, $height);

        // Footer info
        $pdf->SetY(-25);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell(0, 8, "He thong PDF Manager v1.0 - FIT Dai Hoc Dai Nam", 0, 0, 'C');
    }

    $pdf->Output('F', $targetPath);
    echo "[✓] Đã tạo PDF chuẩn: {$filename} ({$info['pages']} trang, tiền tố nhãn '{$info['prefix']}')" . PHP_EOL;
}

echo "=== HOÀN TẤT KHỞI TẠO BỘ DỮ LIỆU KIỂM THỬ CHUẨN FPDF ===" . PHP_EOL;
