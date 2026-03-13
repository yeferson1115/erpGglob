<?php

namespace App\Http\Controllers;

use App\Models\MarketingBroadcast;
use App\Models\PlatformCustomer;
use App\Models\ProductCatalogPublication;
use App\Models\PromotionCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminPlatformController extends Controller
{
    public function index(): View
    {
        $customers = PlatformCustomer::with('user')->orderByDesc('updated_at')->paginate(12);
        $users = User::orderBy('name')->get(['id', 'name', 'last_name', 'email']);

        $totals = [
            'users' => PlatformCustomer::count(),
            'active' => PlatformCustomer::where('subscription_status', 'active')->count(),
            'inactive' => PlatformCustomer::where('subscription_status', 'inactive')->count(),
            'total_sales' => PlatformCustomer::sum('sales_total'),
            'total_gglob_pay' => PlatformCustomer::sum('sales_gglob_pay'),
            'total_gglob_pos' => PlatformCustomer::sum('sales_gglob_pos'),
        ];

        return view('admin.platform.index', [
            'customers' => $customers,
            'users' => $users,
            'totals' => $totals,
            'recentBroadcasts' => MarketingBroadcast::latest()->take(6)->get(),
            'promotionCodes' => PromotionCode::latest()->take(6)->get(),
            'catalogPublications' => ProductCatalogPublication::latest()->take(6)->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'contact_phone' => 'nullable|string|max:30',
            'plan_name' => 'nullable|string|max:60',
            'is_paid' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['contact_phone'] ?? null,
        ]);

        PlatformCustomer::create([
            'user_id' => $user->id,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'plan_name' => $validated['plan_name'] ?? 'Sin plan',
            'is_paid' => (bool)($validated['is_paid'] ?? false),
            'subscription_status' => 'inactive',
            'electronic_billing_status' => 'pending',
        ]);

        return back()->with('status', 'Usuario creado y listo para activación de servicios.');
    }

    public function updateCustomer(Request $request, PlatformCustomer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'plan_name' => 'required|string|max:60',
            'subscription_status' => 'required|in:active,inactive,suspended',
            'is_paid' => 'nullable|boolean',
            'started_at' => 'nullable|date',
            'active_until' => 'nullable|date',
            'gglob_cloud_enabled' => 'nullable|boolean',
            'gglob_pay_enabled' => 'nullable|boolean',
            'gglob_pos_enabled' => 'nullable|boolean',
            'pos_mode' => 'required|in:mono,multi',
            'pos_boxes' => 'required|integer|min:1|max:30',
            'gglob_accounting_enabled' => 'nullable|boolean',
            'electronic_billing_enabled' => 'nullable|boolean',
            'electronic_billing_scope' => 'required|in:single_branch,multi_branch',
            'electronic_billing_boxes' => 'required|integer|min:1|max:100',
            'electronic_billing_monthly_limit' => 'nullable|integer|min:0',
            'electronic_billing_status' => 'required|in:active,suspended,pending',
        ]);

        $customer->update([
            ...$validated,
            'is_paid' => (bool)($validated['is_paid'] ?? false),
            'gglob_cloud_enabled' => (bool)($validated['gglob_cloud_enabled'] ?? false),
            'gglob_pay_enabled' => (bool)($validated['gglob_pay_enabled'] ?? false),
            'gglob_pos_enabled' => (bool)($validated['gglob_pos_enabled'] ?? false),
            'gglob_accounting_enabled' => (bool)($validated['gglob_accounting_enabled'] ?? false),
            'electronic_billing_enabled' => (bool)($validated['electronic_billing_enabled'] ?? false),
        ]);

        return back()->with('status', 'Cliente actualizado correctamente.');
    }

    public function storeMarketing(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:email,whatsapp,banner,sms',
            'audience_type' => 'required|in:segment,user',
            'audience_segment' => 'nullable|string|max:80',
            'user_id' => 'nullable|exists:users,id',
            'frequency' => 'nullable|in:daily,weekly,monthly,specific_date',
            'scheduled_for' => 'nullable|date',
            'is_automated' => 'nullable|boolean',
            'message' => 'required|string|max:2000',
        ]);

        MarketingBroadcast::create([
            ...$validated,
            'is_automated' => (bool)($validated['is_automated'] ?? true),
        ]);

        return back()->with('status', 'Campaña de marketing registrada.');
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:40|unique:promotion_codes,code',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'target_service' => 'nullable|string|max:80',
            'wompi_rule' => 'nullable|string|max:120',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        PromotionCode::create([
            ...$validated,
            'is_active' => (bool)($validated['is_active'] ?? true),
        ]);

        return back()->with('status', 'Código promocional creado.');
    }

    public function storeCatalog(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:80',
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_published' => 'nullable|boolean',
        ]);

        ProductCatalogPublication::create([
            ...$validated,
            'is_published' => (bool)($validated['is_published'] ?? true),
            'published_at' => now(),
        ]);

        return back()->with('status', 'Base de datos de productos publicada.');
    }
}
