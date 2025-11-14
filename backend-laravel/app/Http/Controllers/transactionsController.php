<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // Show all transactions
    public function index()
    {
        $transactions = Transaction::with('approver')->get();
        return view('transactions.index', compact('transactions'));
    }

    // Show form to create a new transaction
    public function create()
    {
        $users = User::all(); // to select approver
        return view('transactions.create', compact('users'));
    }

    // Save new transaction
    public function store(Request $request)
    {
        $request->validate([
            'approved_by' => 'required|exists:users,id',
            'borrower_name' => 'required|string',
            'location' => 'nullable|string',
            'item_name' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        Transaction::create($request->all());

        return redirect()->route('transactions.index')->with('success', 'Transaction added successfully!');
    }
}
