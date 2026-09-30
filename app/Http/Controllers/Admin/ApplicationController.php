<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\MlPredictionService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function dashboard(MlPredictionService $mlPredictionService)
    {
        $applications = LoanApplication::with('user', 'aiAssessment')->latest()->get();
        $featureImportance = $mlPredictionService->getFeatureImportance();

        return view('admin.applications.dashboard', compact('applications', 'featureImportance'));
    }

    public function index()
    {
        $applications = LoanApplication::with('user', 'aiAssessment')->latest()->get();

        return view('admin.applications.index', compact('applications'));
    }

    public function featureImportance(MlPredictionService $mlPredictionService)
    {
        $featureImportance = $mlPredictionService->getFeatureImportance();

        return view('admin.applications.feature-importance', compact('featureImportance'));
    }

    public function show(LoanApplication $application)
    {
        $application->load('user', 'documents.ocrResult', 'aiAssessment');

        return view('admin.applications.show', compact('application'));
    }
}
