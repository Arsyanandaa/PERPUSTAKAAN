<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\AuthService;
use App\Services\LoanService;

final class LoanController
{
    public function __construct(
        private readonly LoanService $loans,
        private readonly AuthService $auth,
    ) {}

    public function borrow(array $params, array $body, array $query): Response
    {
        $user = $this->auth->requireUser();
        $loan = $this->loans->borrow($user, (string) ($body['mediaId'] ?? ''));
        return Response::success($loan, 201, 'Peminjaman berhasil, jatuh tempo ' . $loan['dueAt']);
    }

    public function return(array $params, array $body, array $query): Response
    {
        $user = $this->auth->requireUser();
        $loan = $this->loans->returnLoan($user, (string) ($params['id'] ?? ''));

        $msg = 'Pengembalian berhasil';
        if ($loan['fee'] > 0) {
            $msg .= ' | Denda Rp' . number_format($loan['fee'], 0, ',', '.')
                  . " ({$loan['daysLate']} hari terlambat, pembayaran simulasi dianggap lunas)";
        }
        return Response::success($loan, 200, $msg);
    }

    public function history(array $params, array $body, array $query): Response
    {
        $user = $this->auth->requireUser();
        return Response::success($this->loans->history($user));
    }
}
