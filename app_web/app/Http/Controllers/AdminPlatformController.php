<?php

namespace App\Http\Controllers;

use App\Models\MarketingBroadcast;
use App\Models\PlatformCustomer;
use App\Models\ProductCatalogPublication;
use App\Models\PromotionCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPlatformController extends Controller
{
    public function index(Request $request): View
    {
        $customersQuery = PlatformCustomer::with('user')->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $customersQuery->where('subscription_status', $request->string('status'));
        }

        if ($request->filled('started_from')) {
            $customersQuery->whereDate('started_at', '>=', $request->date('started_from'));
        }

        if ($request->filled('started_to')) {
            $customersQuery->whereDate('started_at', '<=', $request->date('started_to'));
        }

        $customers = $customersQuery->paginate(15)->withQueryString();

        $totals = [
            'users' => PlatformCustomer::count(),
            'paid_users' => PlatformCustomer::where('is_paid', true)->count(),
            'unpaid_users' => PlatformCustomer::where('is_paid', false)->count(),
            'active' => PlatformCustomer::where('subscription_status', 'active')->count(),
            'inactive' => PlatformCustomer::where('subscription_status', 'inactive')->count(),
            'total_sales' => PlatformCustomer::sum('sales_total'),
            'total_gglob_pay' => PlatformCustomer::sum('sales_gglob_pay'),
            'total_gglob_pos' => PlatformCustomer::sum('sales_gglob_pos'),
        ];

        return view('admin.platform.index', [
            'customers' => $customers,
            'totals' => $totals,
            'recentBroadcasts' => MarketingBroadcast::latest()->take(8)->get(),
            'promotionCodes' => PromotionCode::with('customer.user')->latest()->take(8)->get(),
            'catalogPublications' => ProductCatalogPublication::latest()->take(8)->get(),
            'activeCustomers' => PlatformCustomer::with('user')->where('subscription_status', 'active')->get(),
            'usersList' => User::orderBy('name')->get(['id', 'name', 'last_name']),
            'filters' => [
                'status' => $request->get('status'),
                'started_from' => $request->get('started_from'),
                'started_to' => $request->get('started_to'),
            ],
        ]);
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
            'service_action' => 'nullable|in:increase,decrease,remove',
            'target_customer_id' => 'nullable|exists:platform_customers,id',
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
