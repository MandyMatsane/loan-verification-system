<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    
    public function index()
    {
        $applications = LoanApplication::with('user', 'aiAssessment')->latest()->get();

        return view('admin.applications.index', compact('applications'));
    }

    public function show(LoanApplication $application)
    {
        $application->load('user', 'documents.ocrResult', 'aiAssessment');

        return view('admin.applications.show', compact('application'));
    }
}
