<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * TransactionController — Adapter / backward-compatibility controller
 * yang mendelegasikan seluruh alur operasional ke StockMovementController.
 */
class TransactionController extends Controller
{
    protected StockMovementController $handler;

    public function __construct()
    {
        $this->handler = app(StockMovementController::class);
    }

    public function index(Request $request)
    {
        return $this->handler->index($request);
    }

    public function create(Request $request)
    {
        return $this->handler->create($request);
    }

    public function store(Request $request)
    {
        return $this->handler->store($request);
    }
}
