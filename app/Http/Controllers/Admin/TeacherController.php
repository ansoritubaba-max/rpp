<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetTeacherPasswordRequest;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Plan;
use App\Models\Feature;
use App\Models\User;
use App\Services\Auth\SessionDeviceLabel;
use App\Services\Auth\EmailOtpService;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $teachers = User::query()
            ->where('role', 'guru')
            ->with(['activeSubscription.plan', 'schools'])
            ->withCount(['documents', 'questions'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('plan'), fn ($query) => $query->whereHas('activeSubscription', fn ($subscription) => $subscription->where('plan_id', $request->integer('plan'))))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.teachers.index', [
            'teachers' => $teachers,
            'plans' => Plan::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.teachers.form', ['teacher' => new User]);
    }

    public function store(StoreTeacherRequest $request, AuditService $auditService, EmailOtpService $otp): RedirectResponse
    {
        $teacher = User::query()->create($request->safe()->only(['name', 'email', 'phone', 'status', 'password']) + [
            'role' => 'guru',
        ]);
        $auditService->record('teacher.created', $teacher, [], $teacher->only(['name', 'email', 'phone', 'status', 'role']));

        try {
            $otp->send($teacher);
        } catch (Throwable $error) {
            Log::error('Failed to send admin-created teacher verification code', ['user_id' => $teacher->id, 'exception' => $error]);
            return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Akun dibuat, tetapi kode verifikasi belum terkirim. Guru dapat meminta ulang kode setelah masuk.');
        }

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Akun guru dibuat. Kode verifikasi telah dikirim ke email guru.');
    }

    public function show(User $teacher, SessionDeviceLabel $devices): View
    {
        abort_unless($teacher->role === 'guru', 404);
        $teacher->load([
            'schools',
            'payments' => fn ($query) => $query->with('plan')->latest('payment_date')->limit(6),
        ])->loadCount(['documents', 'questions']);

        $active = $teacher->subscriptions()->with(['plan.features', 'usages'])
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->latest('expires_at')->first();
        $teacher->setRelation('activeSubscription', $active);

        $totals = DB::table('feature_usages')
            ->join('subscriptions', 'subscriptions.id', '=', 'feature_usages.subscription_id')
            ->where('subscriptions.user_id', $teacher->id)
            ->select('feature_usages.feature_id')
            ->selectRaw('SUM(feature_usages.generation_count) as generations, SUM(feature_usages.regeneration_count) as regenerations')
            ->groupBy('feature_usages.feature_id')->get()->keyBy('feature_id');
        $featureUsage = Feature::query()->orderBy('name')->get()->map(function (Feature $feature) use ($totals, $active): array {
            $current = $active?->usages->firstWhere('feature_id', $feature->id);
            $included = $active?->plan?->features->firstWhere('id', $feature->id);
            $enabled = $included?->pivot?->is_enabled ?? false;

            return [
                'name' => $feature->name,
                'current_generations' => (int) ($current?->generation_count ?? 0),
                'current_regenerations' => (int) ($current?->regeneration_count ?? 0),
                'total_generations' => (int) ($totals->get($feature->id)?->generations ?? 0),
                'total_regenerations' => (int) ($totals->get($feature->id)?->regenerations ?? 0),
                'generation_limit' => $enabled ? $included->pivot->generation_limit : null,
                'regeneration_limit' => $enabled ? $included->pivot->regeneration_limit : null,
                'enabled' => $enabled,
            ];
        });

        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $teacher->id)
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
                ->orderByDesc('last_activity')->get(['user_agent', 'last_activity'])
                ->map(fn ($session) => [
                    'device' => $devices->fromUserAgent($session->user_agent),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->locale('id')->diffForHumans(),
                ])
            : null;

        return view('admin.teachers.show', [
            'teacher' => $teacher,
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
            'aiUsage' => DB::table('ai_usage_logs')->where('user_id', $teacher->id)->selectRaw('COUNT(*) as requests, COALESCE(SUM(input_tokens + output_tokens), 0) as tokens, COALESCE(SUM(estimated_cost), 0) as cost')->first(),
            'featureUsage' => $featureUsage,
            'sessions' => $sessions,
        ]);
    }

    public function edit(User $teacher): View
    {
        abort_unless($teacher->role === 'guru', 404);

        return view('admin.teachers.form', compact('teacher'));
    }

    public function update(UpdateTeacherRequest $request, User $teacher, AuditService $auditService, EmailOtpService $otp): RedirectResponse
    {
        abort_unless($teacher->role === 'guru', 404);
        $before = $teacher->only(['name', 'email', 'phone', 'status']);
        $emailChanged = $teacher->email !== $request->validated('email');
        $teacher->update($request->validated());
        if ($emailChanged) {
            $teacher->forceFill(['email_verified_at' => null])->save();
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $teacher->id)->delete();
            }
            try {
                $otp->send($teacher);
            } catch (Throwable $error) {
                Log::error('Failed to send teacher email-change verification code', ['user_id' => $teacher->id, 'exception' => $error]);
                $auditService->record('teacher.updated', $teacher, $before, $teacher->only(['name', 'email', 'phone', 'status']));
                return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Data guru diperbarui, tetapi kode verifikasi belum terkirim. Guru dapat meminta ulang kode setelah masuk.');
            }
        }
        $auditService->record('teacher.updated', $teacher, $before, $teacher->only(['name', 'email', 'phone', 'status']));

        return redirect()->route('admin.teachers.show', $teacher)->with('success', $emailChanged ? 'Data guru diperbarui. Alamat email baru perlu diverifikasi.' : 'Data guru berhasil diperbarui.');
    }

    public function resetPassword(ResetTeacherPasswordRequest $request, User $teacher, AuditService $auditService): RedirectResponse
    {
        abort_unless($teacher->role === 'guru', 404);
        $teacher->update(['password' => $request->validated('password')]);
        $auditService->record('teacher.password_reset', $teacher, [], ['reset_by' => $request->user()->id]);

        return back()->with('success', 'Kata sandi guru berhasil direset.');
    }
}
