<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LoanAccountController extends Controller
{
    public function show(): View
    {
        return view('loan.account.show');
    }

    public function salaryAdvance(): View
    {
        return view('loan.account.salary-advance');
    }

    public function approvalRequests(): View
    {
        return view('loan.account.approval-requests');
    }
}
