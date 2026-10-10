<?php

namespace App\Http\Controllers;

use App\Services\AdminAccess;
use App\Services\AdminDashboard;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\ReturnRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AdminController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;

            return response()->view('admin.unavailable', ['status' => $status], $status);
        }
    }

    public function login(AdminAccess $access)
    {
        return view('admin.login', ['configured' => $access->configured()]);
    }

    public function authenticate(Request $request, AdminAccess $access)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:256']]);
        if (! $access->login($request, $data['username'], $data['password'])) {
            return back()->withErrors(['username' => 'Identifiant ou mot de passe incorrect.'])->withInput(['username' => $data['username']]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin.auth');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(AdminDashboard $dashboard, CheckoutReadiness $readiness)
    {
        try {
            $metrics = $dashboard->overview();
        } catch (\Throwable) {
            $metrics = null;
        }

        return view('admin.dashboard', ['metrics' => $metrics, 'returns' => $dashboard->returns(), 'readiness' => $readiness]);
    }

    public function orders(Request $request, AdminDashboard $dashboard)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(AdminDashboard::STATUSES))]]);

        return view('admin.orders', ['orders' => $dashboard->orders($data['status'] ?? null)]);
    }

    public function order(string $id, AdminDashboard $dashboard)
    {
        return view('admin.order', $dashboard->order($id));
    }

    public function inventory(Request $request, AdminDashboard $dashboard)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return view('admin.inventory', ['inventory' => $dashboard->inventory($data['q'] ?? '')]);
    }

    public function returns(AdminDashboard $dashboard)
    {
        return view('admin.returns', ['returns' => $dashboard->returns()]);
    }

    public function returnRequest(string $id, ReturnRequests $requests)
    {
        return view('admin.return', ['record' => $requests->find($id)]);
    }
}
