<?php

namespace App\Http\Controllers\Admin;

use App\Controllers\PaymentController;
use PDO;

/**
 * Controller Quản trị Doanh thu & Kết nối Ngân hàng (Admin Payment Controller)
 * Quản lý cấu hình ngân hàng ACB, Sieuthicode API và đối soát hóa đơn
 */
class AdminPaymentController
{
    private PaymentController $paymentController;

    public function __construct(?PDO $pdo = null)
    {
        $this->paymentController = new PaymentController($pdo);
    }

    public function getBankSettings(): void
    {
        response()->json([
            'status' => 'success',
            'data' => $this->paymentController->getBankSettings()
        ]);
    }

    public function updateBankSettings(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $res = $this->paymentController->updateBankSettings($input);
        response()->json($res);
    }

    public function syncBankTransactions(): void
    {
        $res = $this->paymentController->syncBankTransactions();
        response()->json($res);
    }

    public function getInvoices(): void
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? '',
            'username' => $_GET['username'] ?? '',
            'trans_id' => $_GET['trans_id'] ?? '',
            'bank' => $_GET['bank'] ?? '',
            'date' => $_GET['date'] ?? '',
            'period' => $_GET['period'] ?? 'month',
            'limit' => intval($_GET['limit'] ?? 25),
            'sort' => $_GET['sort'] ?? 'desc',
        ];
        $res = $this->paymentController->getInvoicesData($filters);
        response()->json([
            'status' => 'success',
            'invoices' => $res['invoices'],
            'stats' => $res['stats']
        ]);
    }

    public function updateInvoice(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = intval($input['id'] ?? 0);
        $res = $this->paymentController->updateInvoice($id, $input);
        response()->json($res);
    }

    public function deleteInvoice(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = intval($input['id'] ?? 0);
        $res = $this->paymentController->deleteInvoice($id);
        response()->json($res);
    }
}
