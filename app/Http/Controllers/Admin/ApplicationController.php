<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\ApplicationAssessor;
use App\Services\FraudCheckService;
use App\Services\MlPredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function dashboard(MlPredictionService $mlPredictionService, ApplicationAssessor $assessor)
    {
        $assessor->retryDue();

        $applications = LoanApplication::with('user', 'aiAssessment')->latest()->get();
        // short timeout: the dashboard must not wait for a sleeping ML service
        $featureImportance = $mlPredictionService->getFeatureImportance(5);

        return view('admin.applications.dashboard', compact('applications', 'featureImportance'));
    }

    public function index(ApplicationAssessor $assessor)
    {
        $assessor->retryDue();

        $applications = LoanApplication::with('user', 'aiAssessment')->latest()->get();

        return view('admin.applications.index', compact('applications'));
    }

    public function featureImportance(MlPredictionService $mlPredictionService)
    {
        $featureImportance = $mlPredictionService->getFeatureImportance();

        return view('admin.applications.feature-importance', compact('featureImportance'));
    }

    public function show(LoanApplication $application, FraudCheckService $fraudCheckService)
    {
        $application->load('user', 'documents.ocrResult', 'aiAssessment');

        // the gross and net pay read from the payslip, as the document check sees them
        $payslipFigures = $fraudCheckService->payslipFigures($application);

        return view('admin.applications.show', compact('application', 'payslipFigures'));
    }

    public function retry(LoanApplication $application, ApplicationAssessor $assessor): RedirectResponse
    {
        $back = redirect()->route('admin.applications.show', $application);

        if (! $application->isRetryable()) {
            return $back->with('warning', 'Only pending applications without an assessment can be retried.');
        }

        return match ($assessor->retry($application)) {
            true => $back->with('success', 'The application was assessed.'),
            false => $back->with('warning', 'The prediction service did not answer. Try again in a minute.'),
            null => $back->with('warning', 'This application is already being assessed. Refresh in a minute.'),
        };
    }

    public function retryPending(ApplicationAssessor $assessor): RedirectResponse
    {
        $back = redirect()->route('admin.applications.index');
        $result = $assessor->retryPending();

        if ($result['tried'] === 0) {
            return $back->with('success', 'No applications are waiting for an assessment.');
        }

        $message = sprintf('%d of %d retried %s assessed.', $result['assessed'], $result['tried'], $result['tried'] === 1 ? 'application was' : 'applications were');

        if ($result['stopped_early']) {
            $message .= ' Stopped early because the prediction service did not answer. Try again in a minute.';
        }

        return $back->with($result['assessed'] === $result['tried'] ? 'success' : 'warning', $message);
    }
}
