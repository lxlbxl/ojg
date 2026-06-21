<?php
require_once 'auth.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'A/B Experiments - OJG Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
    <div>
        <p class="text-[#6B7C70] text-sm font-medium uppercase tracking-wider mb-2">Optimisation</p>
        <h2 class="text-4xl md:text-5xl font-serif text-[#2C3E35]">A/B <span class="italic font-light">Experiments</span></h2>
        <p class="text-[#6B7C70] mt-2">Funnel experiments, variant performance &amp; bandit decisions</p>
    </div>
    <button onclick="createExperiment()" class="flex items-center gap-2 px-5 py-2.5 bg-[#2C3E35] text-white rounded-full text-sm font-medium hover:bg-[#3a5246] transition-colors self-start">
        <i class="fas fa-plus text-xs"></i> New Experiment
    </button>
</div>

<!-- Filter Bar -->
<div class="luxury-card p-5 mb-8 flex flex-wrap gap-3 items-center">
    <select id="funnelFilter" onchange="loadExperiments()" class="flex-1 min-w-[140px] px-4 py-2 bg-[#F2F4F1] rounded-full text-sm text-[#2C3E35] border-none outline-none">
        <option value="">All Funnels</option>
        <option value="pcos">PCOS</option>
        <option value="acne">Acne</option>
        <option value="weight">Weight</option>
        <option value="egbon">Egbon</option>
    </select>
    <select id="statusFilter" onchange="loadExperiments()" class="flex-1 min-w-[140px] px-4 py-2 bg-[#F2F4F1] rounded-full text-sm text-[#2C3E35] border-none outline-none">
        <option value="">All Statuses</option>
        <option value="running">Running</option>
        <option value="paused">Paused</option>
        <option value="completed">Completed</option>
    </select>
    <button onclick="loadExperiments()" class="px-5 py-2 bg-[#E3E8E1] text-[#2C3E35] rounded-full text-sm font-medium hover:bg-[#D0D8CC] transition-colors flex items-center gap-2">
        <i class="fas fa-sync-alt text-xs"></i> Refresh
    </button>
</div>

<!-- Experiments Grid -->
<div id="experimentsContainer" class="space-y-4 mb-8">
    <div class="text-center py-16 text-[#6B7C70]">
        <div class="inline-block w-8 h-8 border-2 border-[#E3E8E1] border-t-[#2C3E35] rounded-full animate-spin mb-4"></div>
        <p>Loading experiments…</p>
    </div>
</div>

<!-- Create / Edit Modal -->
<div id="expModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-serif text-[#2C3E35]" id="expModalTitle">New Experiment</h3>
            <button onclick="closeExpModal()" class="w-8 h-8 rounded-full bg-[#F2F4F1] flex items-center justify-center text-[#6B7C70] hover:bg-[#E3E8E1]">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form onsubmit="saveExperiment(event)" class="space-y-4">
            <input type="hidden" id="expId">
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-[#6B7C70] mb-1 block">Name</label>
                <input id="expName" required placeholder="e.g. Hero CTA Button Test"
                    class="w-full px-4 py-3 bg-[#F2F4F1] rounded-2xl text-sm text-[#2C3E35] outline-none focus:ring-2 focus:ring-[#2C3E35]/20">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-[#6B7C70] mb-1 block">Funnel</label>
                    <select id="expFunnel" class="w-full px-4 py-3 bg-[#F2F4F1] rounded-2xl text-sm text-[#2C3E35] outline-none">
                        <option value="pcos">PCOS</option>
                        <option value="acne">Acne</option>
                        <option value="weight">Weight</option>
                        <option value="egbon">Egbon</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-[#6B7C70] mb-1 block">Metric</label>
                    <select id="expMetric" class="w-full px-4 py-3 bg-[#F2F4F1] rounded-2xl text-sm text-[#2C3E35] outline-none">
                        <option value="conversion">Conversion</option>
                        <option value="click">Click Rate</option>
                        <option value="pageview">Page View</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-[#6B7C70] mb-1 block">Description</label>
                <textarea id="expDesc" rows="2" placeholder="What are you testing and why?"
                    class="w-full px-4 py-3 bg-[#F2F4F1] rounded-2xl text-sm text-[#2C3E35] outline-none resize-none focus:ring-2 focus:ring-[#2C3E35]/20"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeExpModal()" class="flex-1 py-3 bg-[#F2F4F1] text-[#6B7C70] rounded-2xl text-sm font-bold hover:bg-[#E3E8E1] transition-colors">Cancel</button>
                <button type="submit" class="flex-1 py-3 bg-[#2C3E35] text-white rounded-2xl text-sm font-bold hover:bg-[#3a5246] transition-colors">Save Experiment</button>
            </div>
        </form>
    </div>
</div>

<script>
async function loadExperiments() {
    const funnel = document.getElementById('funnelFilter').value;
    const status = document.getElementById('statusFilter').value;
    const container = document.getElementById('experimentsContainer');
    container.innerHTML = `<div class="text-center py-16 text-[#6B7C70]"><div class="inline-block w-8 h-8 border-2 border-[#E3E8E1] border-t-[#2C3E35] rounded-full animate-spin mb-4"></div><p>Loading…</p></div>`;

    try {
        let url = `api/experiments_data.php?sub=list`;
        if (funnel) url += `&funnel=${funnel}`;
        if (status) url += `&status=${status}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) renderExperiments(data.data);
        else container.innerHTML = `<div class="luxury-card p-8 text-center text-[#6B7C70]">${data.error || 'Failed to load'}</div>`;
    } catch(e) {
        container.innerHTML = `<div class="luxury-card p-8 text-center text-[#E57373]">Connection error — ${e.message}</div>`;
    }
}

function renderExperiments(experiments) {
    const container = document.getElementById('experimentsContainer');
    if (!experiments || !experiments.length) {
        container.innerHTML = `
            <div class="luxury-card p-16 text-center text-[#6B7C70]">
                <i class="fas fa-flask text-4xl text-[#E3E8E1] mb-4 block"></i>
                <p class="font-serif text-lg text-[#2C3E35]">No experiments yet</p>
                <p class="text-sm mt-1">Create your first A/B experiment to optimise your funnels.</p>
                <button onclick="createExperiment()" class="mt-6 px-6 py-2.5 bg-[#2C3E35] text-white rounded-full text-sm font-medium hover:bg-[#3a5246] transition-colors">
                    <i class="fas fa-plus mr-2"></i>New Experiment
                </button>
            </div>`;
        return;
    }

    const statusColors = { running: ['#E8F5E9','#388E3C'], paused: ['#FFF3E0','#E65100'], completed: ['#E3E8E1','#2C3E35'], draft: ['#F2F4F1','#6B7C70'] };

    container.innerHTML = experiments.map(exp => {
        const [sBg, sText] = statusColors[exp.status] || statusColors.draft;
        const variants = exp.variants || [];
        const winner = exp.decision;
        const totalTraffic = variants.reduce((s, v) => s + parseInt(v.impressions || 0), 0);

        return `
        <div class="luxury-card p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase" style="background:${sBg};color:${sText}">${exp.status || 'draft'}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#F2F4F1] text-[#6B7C70]">${exp.funnel || 'all'}</span>
                        ${winner ? `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#FDF1E8] text-[#D97757]">🏆 Winner Ready</span>` : ''}
                    </div>
                    <h4 class="text-xl font-serif text-[#2C3E35]">${exp.name}</h4>
                    ${exp.description ? `<p class="text-sm text-[#6B7C70] mt-1">${exp.description}</p>` : ''}
                </div>
                <div class="flex gap-2 shrink-0">
                    ${exp.status === 'running'
                        ? `<button onclick="stopExp(${exp.id})" class="px-4 py-2 rounded-full text-xs font-bold bg-[#FFEBEE] text-[#E57373] hover:bg-[#FFCDD2] transition-colors">Stop</button>`
                        : ''}
                    <button onclick="editExperiment(${JSON.stringify(exp).replace(/"/g,'&quot;')})" class="px-4 py-2 rounded-full text-xs font-bold bg-[#F2F4F1] text-[#6B7C70] hover:bg-[#E3E8E1] transition-colors">Edit</button>
                </div>
            </div>

            <!-- Variants -->
            ${variants.length ? `
            <div class="grid grid-cols-1 md:grid-cols-${Math.min(variants.length, 3)} gap-4">
                ${variants.map(v => {
                    const rate = v.impressions > 0 ? ((v.conversions / v.impressions) * 100).toFixed(1) : '0.0';
                    const share = totalTraffic > 0 ? Math.round((v.impressions / totalTraffic) * 100) : 0;
                    const isWinner = winner && winner.variant_id === v.id;
                    return `
                    <div class="rounded-2xl p-4 border ${isWinner ? 'border-[#D97757] bg-[#FDF1E8]' : 'border-[#EAEAE5] bg-[#FDFCF8]'}">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wide ${isWinner ? 'text-[#D97757]' : 'text-[#6B7C70]'}">${v.name || 'Variant ' + v.id}${isWinner ? ' 🏆' : ''}</span>
                            <span class="text-[10px] text-[#A4B4A6]">${share}% traffic</span>
                        </div>
                        <div class="flex gap-4 text-center">
                            <div><p class="text-xl font-serif text-[#2C3E35]">${v.impressions || 0}</p><p class="text-[9px] text-[#A4B4A6] uppercase">Views</p></div>
                            <div><p class="text-xl font-serif text-[#2C3E35]">${v.conversions || 0}</p><p class="text-[9px] text-[#A4B4A6] uppercase">Converts</p></div>
                            <div><p class="text-xl font-serif ${isWinner ? 'text-[#D97757]' : 'text-[#2C3E35]'}">${rate}%</p><p class="text-[9px] text-[#A4B4A6] uppercase">Rate</p></div>
                        </div>
                    </div>`;
                }).join('')}
            </div>` : `<p class="text-sm text-[#A4B4A6] italic">No variants configured yet.</p>`}

            <div class="mt-4 pt-4 border-t border-[#EAEAE5] flex items-center justify-between text-xs text-[#A4B4A6]">
                <span>Started ${exp.started_at ? new Date(exp.started_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : 'N/A'}</span>
                <span>${totalTraffic.toLocaleString()} total impressions</span>
            </div>
        </div>`;
    }).join('');
}

function createExperiment() {
    document.getElementById('expId').value = '';
    document.getElementById('expName').value = '';
    document.getElementById('expDesc').value = '';
    document.getElementById('expModalTitle').textContent = 'New Experiment';
    document.getElementById('expModal').classList.remove('hidden');
    document.getElementById('expModal').classList.add('flex');
}

function editExperiment(exp) {
    document.getElementById('expId').value = exp.id;
    document.getElementById('expName').value = exp.name || '';
    document.getElementById('expDesc').value = exp.description || '';
    document.getElementById('expFunnel').value = exp.funnel || 'pcos';
    document.getElementById('expMetric').value = exp.metric || 'conversion';
    document.getElementById('expModalTitle').textContent = 'Edit Experiment';
    document.getElementById('expModal').classList.remove('hidden');
    document.getElementById('expModal').classList.add('flex');
}

function closeExpModal() {
    document.getElementById('expModal').classList.add('hidden');
    document.getElementById('expModal').classList.remove('flex');
}

async function saveExperiment(e) {
    e.preventDefault();
    const id = document.getElementById('expId').value;
    const body = new URLSearchParams({
        sub: id ? 'update' : 'create',
        id: id,
        name: document.getElementById('expName').value,
        funnel: document.getElementById('expFunnel').value,
        metric: document.getElementById('expMetric').value,
        description: document.getElementById('expDesc').value,
    });
    try {
        const res = await fetch('api/experiments_data.php', { method: 'POST', body });
        const data = await res.json();
        if (data.success) { closeExpModal(); loadExperiments(); }
        else alert(data.error || 'Failed to save');
    } catch(e) { alert('Connection error'); }
}

async function stopExp(id) {
    if (!confirm('Stop this experiment?')) return;
    try {
        const res = await fetch('api/experiments_data.php', {
            method: 'POST',
            body: new URLSearchParams({ sub: 'stop', id })
        });
        const data = await res.json();
        if (data.success) loadExperiments();
        else alert(data.error || 'Failed to stop');
    } catch(e) { alert('Connection error'); }
}

// Load on page ready
document.addEventListener('DOMContentLoaded', loadExperiments);
</script>

<?php include 'includes/footer.php'; ?>
