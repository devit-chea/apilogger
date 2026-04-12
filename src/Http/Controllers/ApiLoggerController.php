<?php

namespace GDI\ApiLogger\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;

class ApiLoggerController extends Controller
{
    public function __construct(protected ApiLoggerInterface $logger) {}

    /**
     * Display the log dashboard with filtering and pagination.
     */
    public function index(Request $request)
    {
        $perPage = (int) config('apilogger.per_page', 20);

        $filters = $request->only(['method', 'status_code', 'url']);

        $logs = $this->logger->all($perPage, array_filter($filters));

        return view('apilogger::index', compact('logs', 'filters'));
    }

    /**
     * Show a single log entry's detail.
     */
    public function show(string $id)
    {
        $log = $this->logger->find($id);

        if (!$log) {
            abort(404, 'Log entry not found.');
        }

        return view('apilogger::show', compact('log'));
    }

    /**
     * Clear all logs (POST for CSRF safety).
     */
    public function clear(Request $request)
    {
        $this->logger->clear();

        return redirect()
            ->route('apilogger.index')
            ->with('success', 'All API logs have been cleared.');
    }
}
