<div class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm">
    <a href="{{ route('admin.ai.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-bold transition {{ request()->routeIs('admin.ai.index') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-brand-700' }}">Monitoring</a>
    <a href="{{ route('admin.ai.providers.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-bold transition {{ request()->routeIs('admin.ai.providers.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-brand-700' }}">Provider</a>
    <a href="{{ route('admin.ai.models.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-bold transition {{ request()->routeIs('admin.ai.models.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-brand-700' }}">Model</a>
    <a href="{{ route('admin.ai.routing.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-bold transition {{ request()->routeIs('admin.ai.routing.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-brand-700' }}">Routing & Failover</a>
</div>
