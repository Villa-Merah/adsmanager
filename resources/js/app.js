const normalizeCampaign = c => ({ ...c, date: String(c.date || '').slice(0, 10), clicks: Number(c.clicks ?? c.link_clicks ?? 0), leads: Number(c.leads ?? c.leads_count ?? 0), closing: Number(c.closing ?? c.closings_count ?? 0), budget: Number(c.budget ?? 0), impressions: Number(c.impressions ?? 0), reach: Number(c.reach ?? 0), revenue: Number(c.revenue ?? 0) });
let campaigns = (window.ADS_DATA?.campaigns || []).map(normalizeCampaign);
let reportCampaigns = [];
let metricsRequest = 0;
let leads = window.ADS_DATA?.leads || [];
let users = window.ADS_DATA?.users || [];
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const canManage = Boolean(window.ADS_DATA?.canManage);
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[char]));
const rupiah = value => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(Number(value || 0));
const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
const shortRupiah = value => value >= 1e6 ? `Rp${(value / 1e6).toLocaleString('id-ID', { maximumFractionDigits:1 })} jt` : rupiah(value);

const api = async (url, options = {}) => {
    const response = await fetch(url, { ...options, headers: { 'X-CSRF-TOKEN':csrf,'X-Branch':window.ADS_DATA?.branch||'', 'Accept':'application/json', ...(options.body instanceof FormData ? {} : {'Content-Type':'application/json'}), ...(options.headers || {}) } });
    if (!response.ok) { const data = await response.json().catch(() => ({})); const errors = data.errors ? Object.values(data.errors).flat().join(' ') : ''; throw new Error(errors || data.message || 'Permintaan gagal diproses.'); }
    return response.status === 204 ? null : response.json();
};

function showToast(message) {
    const toast=document.querySelector('#toast'); document.querySelector('#toastMessage').textContent=message; toast.classList.add('show');
    clearTimeout(window.toastTimer); window.toastTimer=setTimeout(()=>toast.classList.remove('show'),2800);
}

function statusClass(status) { return status === 'Aktif' ? 'active' : status === 'Selesai' ? 'done' : 'draft'; }
function rowTemplate(campaign, detailed=false) {
    const cpl=campaign.leads ? campaign.budget/campaign.leads : 0, roas=campaign.budget ? campaign.revenue/campaign.budget : 0;
    const platformClass=campaign.platform==='Instagram'?'insta':campaign.platform==='Facebook'?'fb':'meta';
    const icon=campaign.platform==='Instagram'?'IG':campaign.platform==='Facebook'?'f':'M';
    const actions=canManage?`<button class="more-button edit-campaign" data-id="${campaign.id}" title="Edit">✎</button><button class="more-button delete-campaign" data-id="${campaign.id}" title="Hapus">×</button>`:'';
    return `<tr data-platform="${escapeHtml(campaign.platform)}" data-name="${escapeHtml(campaign.name.toLowerCase())}">
        <td><div class="campaign-cell"><span class="campaign-logo ${platformClass}">${icon}</span><div><b>${escapeHtml(campaign.name)}</b><small>${escapeHtml(campaign.objective)} · ${escapeHtml(campaign.branch||'Belum ditetapkan')}</small></div></div></td>
        <td><span class="platform-tag"><span class="dot ${platformClass}"></span>${escapeHtml(campaign.platform)}</span></td>
        <td><span class="status-tag ${statusClass(campaign.status)}"><i></i>${escapeHtml(campaign.status)}</span></td>
        <td class="right">${rupiah(campaign.budget)}</td>${detailed?`<td class="right">${number(campaign.impressions)}</td>`:''}
        <td class="right">${number(campaign.leads)}</td>${detailed?'':`<td class="right">${rupiah(Math.round(cpl))}</td>`}
        <td class="right">${number(campaign.closing)}</td><td class="right"><b>${shortRupiah(campaign.revenue)}</b></td>
        <td class="right"><span class="roas-badge">${roas.toLocaleString('id-ID',{maximumFractionDigits:2})}×</span></td><td><span class="row-actions">${actions}</span></td></tr>`;
}

function renderOverview(list=campaigns) { document.querySelector('#campaignTableBody').innerHTML=list.map(c=>rowTemplate(c)).join('') || '<tr><td colspan="10" class="empty-cell">Tidak ada campaign pada filter ini.</td></tr>'; }
function renderAllCampaigns(list=campaigns) { if(!document.querySelector('#allCampaignTableBody'))return; document.querySelector('#allCampaignTableBody').innerHTML=list.map(c=>rowTemplate(c,true)).join('') || '<tr><td colspan="10" class="empty-cell">Campaign tidak ditemukan.</td></tr>'; }
function renderLeads() {
    const body=document.querySelector('#leadTableBody'); if(!body)return;
    body.innerHTML=leads.length?leads.map(lead=>`<tr><td><b>${escapeHtml(lead.name)}</b><br><small>${escapeHtml(lead.city||'—')} · ${escapeHtml(lead.branch||'Belum ditetapkan')}</small></td><td>${escapeHtml(lead.program||'—')}</td><td>${escapeHtml(lead.campaign?.name||'—')}</td><td>${escapeHtml(lead.source||'—')}</td><td><span class="status-tag ${lead.status==='Closing'?'active':lead.status==='Baru'?'draft':'done'}"><i></i>${escapeHtml(lead.status)}</span></td><td>${lead.follow_up_at?new Date(lead.follow_up_at).toLocaleDateString('id-ID'):'Belum dijadwalkan'}</td><td>${canManage?`<button class="more-button edit-lead" data-id="${lead.id}">Edit</button><button class="more-button close-lead" data-id="${lead.id}" title="Catat closing">✓</button><button class="more-button delete-lead" data-id="${lead.id}">×</button>`:''}</td></tr>`).join(''):'<tr><td colspan="7" class="empty-cell">Belum ada lead.</td></tr>';
}

function renderUsers() {
    const list=document.querySelector('#userList');if(!list)return;
    list.innerHTML=users.map(user=>`<div class="team-user"><div class="avatar">${escapeHtml(user.name.split(' ').map(v=>v[0]).join('').slice(0,2).toUpperCase())}</div><div><b>${escapeHtml(user.name)}</b><span>${escapeHtml(user.email)}</span></div><em class="role-badge">${escapeHtml(user.role.toUpperCase())} · ${escapeHtml(user.branch||(user.role==='superadmin'?'Pusat':'Belum ditetapkan'))}</em><button class="more-button edit-user" data-id="${user.id}">Edit</button>${user.id!==window.ADS_DATA.user.id?`<button class="more-button delete-user" data-id="${user.id}">×</button>`:''}</div>`).join('');
}

function paintMetrics(metrics, list) {
    const budget=Number(metrics.budget||0), impressions=Number(metrics.impressions||0), clicks=Number(metrics.clicks||0), leadCount=Number(metrics.leads||0), closing=Number(metrics.closings||0), revenue=Number(metrics.revenue||0);
    document.querySelector('#metricBudget').textContent=rupiah(budget); document.querySelector('#metricImpressions').textContent=number(impressions);
    document.querySelector('#metricClicks').textContent=number(clicks); document.querySelector('#metricCtr').textContent=impressions?`${(clicks/impressions*100).toLocaleString('id-ID',{maximumFractionDigits:2})}%`:'0%';
    document.querySelector('#metricLeads').textContent=number(leadCount); document.querySelector('#metricCpl').textContent=leadCount?rupiah(Math.round(budget/leadCount)):'Rp0';
    document.querySelector('#metricClosing').textContent=number(closing); document.querySelector('#metricClosingRate').textContent=leadCount?`${(closing/leadCount*100).toLocaleString('id-ID',{maximumFractionDigits:2})}%`:'0%';
    document.querySelector('#metricRevenue').textContent=rupiah(revenue); document.querySelector('#metricRoas').textContent=budget?`${(revenue/budget).toLocaleString('id-ID',{maximumFractionDigits:2})}×`:'0×'; renderOverview(list);renderAnalytics(metrics,list);
}

function renderAnalytics(metrics,list) {
    const values=[Number(metrics.impressions||0),Number(metrics.reach||0),Number(metrics.clicks||0),Number(metrics.chats||0),Number(metrics.leads||0),Number(metrics.closings||0)];
    const labels=['Impression','Reach (akumulasi)','Klik Link','Chat','Leads','Closing'];
    const colors=['#7c3aed','#2563eb','#0891b2','#ea580c','#d97706','#16a34a'];
    const maximum=Math.max(...values,1);
    document.querySelector('#metricReach').textContent=number(metrics.reach);
    document.querySelector('#overviewFunnel').innerHTML=values.map((value,i)=>`<div class="funnel-row"><div class="funnel-name"><b>${labels[i]}</b></div><strong>${number(value)}</strong><div class="funnel-bar"><i style="width:${value/maximum*100}%;background:${colors[i]}"></i></div><em>${i&&values[i-1]?(value/values[i-1]*100).toLocaleString('id-ID',{maximumFractionDigits:1})+'%':'-'}</em></div>`).join('');
    document.querySelector('#fullFunnel').innerHTML=values.map((value,i)=>`<div style="--w:${Math.max(15,value/maximum*100)}%;--c:${colors[i]}"><b>${labels[i]}</b><strong>${number(value)}</strong></div>`).join('');
    document.querySelector('#chartRevenue').textContent=rupiah(metrics.revenue);
    document.querySelector('#chartRoas').textContent=Number(metrics.budget)?(Number(metrics.revenue)/Number(metrics.budget)).toLocaleString('id-ID',{maximumFractionDigits:2})+'x':'0x';
    const days=new Map();for(const c of list){const item=days.get(c.date)||{budget:0,revenue:0};item.budget+=c.budget;item.revenue+=c.revenue;days.set(c.date,item);}
    const entries=[...days].sort(([a],[b])=>a.localeCompare(b));
    const max=Math.max(1,...entries.flatMap(([,v])=>[v.budget,v.revenue]));
    document.querySelector('#performanceChart').innerHTML=entries.length?entries.map(([date,v])=>`<div style="margin-bottom:12px"><small>${escapeHtml(date)} - Budget ${rupiah(v.budget)} - Omzet ${rupiah(v.revenue)}</small><div style="height:8px;width:${v.budget/max*100}%;background:#2563eb;margin-top:4px"></div><div style="height:8px;width:${v.revenue/max*100}%;background:#7c3aed;margin-top:4px"></div></div>`).join(''):'<p>Belum ada data pada periode ini.</p>';
    document.querySelector('#updatedAt').textContent='Diperbarui '+new Date().toLocaleTimeString('id-ID');
}

async function loadDashboardFilters(notify=true) {
    const requestId=++metricsRequest;
    const platform=document.querySelector('.filter-chip.active')?.dataset.platform||'Semua';
    const params=new URLSearchParams({from:document.querySelector('#dateFrom').value,to:document.querySelector('#dateTo').value,conversion_source:document.querySelector('#conversionSource').value}); if(platform!=='Semua')params.set('platform',platform);
    try { const data=await api(`/metrics?${params}`); if(requestId!==metricsRequest)return;const list=data.campaigns.map(normalizeCampaign);reportCampaigns=list;paintMetrics(data.metrics,list); if(notify===true)showToast(`Filter diterapkan · ${list.length} campaign.`); } catch(error){showToast(error.message);}
}

function profileInitials(name) {
    return String(name || '').trim().split(/\s+/).filter(Boolean).map(part=>Array.from(part)[0]).slice(0,2).join('').toUpperCase() || '?';
}

function paintProfileAvatar(element, user, previewUrl) {
    const url=previewUrl === undefined ? user.avatar_url : previewUrl;
    element.replaceChildren();
    if(url){const img=document.createElement('img');img.src=url;img.alt='Foto profil';element.append(img);}
    else element.textContent=profileInitials(user.name);
}

function updateProfileIdentity(user) {
    window.ADS_DATA.user=user;
    users=users.map(member=>member.id===user.id?user:member);
    document.querySelectorAll('[data-profile-name]').forEach(el=>el.textContent=user.name);
    document.querySelectorAll('[data-profile-email]').forEach(el=>el.textContent=user.email);
    document.querySelectorAll('[data-profile-avatar]').forEach(el=>paintProfileAvatar(el,user));
    renderUsers();
}

function setupProfileSettings() {
    const form=document.querySelector('#profileForm'), passwordForm=document.querySelector('#passwordForm');
    if(!form || !passwordForm)return;
    let previewUrl;
    const clearPreview=()=>{if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=undefined;};
    const feedback=(id,message,error=false)=>{const el=document.querySelector(id);el.textContent=message;el.classList.toggle('is-error',error);};
    const photo=document.querySelector('#profileAvatar');
    const preview=()=>paintProfileAvatar(form.querySelector('[data-profile-avatar]'),window.ADS_DATA.user,form.elements.remove_avatar.value==='1'?null:previewUrl);
    photo.addEventListener('change',()=>{
        clearPreview();const file=photo.files[0];
        if(file && (!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024)){
            photo.value='';feedback('#profileFeedback','Gunakan JPG, PNG, atau WebP dengan ukuran maksimal 2 MB.',true);preview();return;
        }
        form.elements.remove_avatar.value='0';
        document.querySelector('#avatarFilename').textContent=file?.name||'';
        if(file)previewUrl=URL.createObjectURL(file);
        feedback('#profileFeedback','');preview();
    });
    document.querySelector('#removeAvatarButton').addEventListener('click',()=>{
        clearPreview();photo.value='';form.elements.remove_avatar.value='1';document.querySelector('#avatarFilename').textContent='Foto akan dihapus setelah disimpan.';preview();
    });
    form.addEventListener('reset',()=>{
        clearPreview();setTimeout(()=>{const user=window.ADS_DATA.user;form.elements.name.value=user.name;form.elements.email.value=user.email;form.elements.remove_avatar.value='0';document.querySelector('#avatarFilename').textContent='';feedback('#profileFeedback','');preview();},0);
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();const button=form.querySelector('[type="submit"]');button.disabled=true;feedback('#profileFeedback','Menyimpan perubahan...');
        try{
            const body=new FormData(form);body.set('_method','PUT');if(!photo.files.length)body.delete('avatar');
            const result=await api('/profile',{method:'POST',body});
            clearPreview();updateProfileIdentity(result.user);photo.value='';form.elements.name.defaultValue=result.user.name;form.elements.email.defaultValue=result.user.email;form.elements.remove_avatar.value='0';document.querySelector('#avatarFilename').textContent='';feedback('#profileFeedback',result.message);
        }catch(error){feedback('#profileFeedback',error.message,true);}finally{button.disabled=false;}
    });
    document.querySelector('#showProfilePasswords').addEventListener('change',event=>passwordForm.querySelectorAll('input[name]').forEach(input=>input.type=event.target.checked?'text':'password'));
    passwordForm.addEventListener('submit',async event=>{
        event.preventDefault();const button=passwordForm.querySelector('[type="submit"]');
        if(passwordForm.elements.password.value!==passwordForm.elements.password_confirmation.value){feedback('#passwordFeedback','Konfirmasi kata sandi belum cocok.',true);return;}
        button.disabled=true;feedback('#passwordFeedback','Memperbarui kata sandi...');
        try{const result=await api('/profile/password',{method:'PUT',body:JSON.stringify(Object.fromEntries(new FormData(passwordForm)))});passwordForm.reset();passwordForm.querySelectorAll('input[name]').forEach(input=>input.type='password');feedback('#passwordFeedback',result.message);}catch(error){feedback('#passwordFeedback',error.message,true);}finally{button.disabled=false;}
    });
}

function navigate(page) {
    document.querySelectorAll('.page').forEach(el=>el.classList.remove('active')); document.querySelectorAll('.nav-item[data-page]').forEach(el=>el.classList.toggle('active',el.dataset.page===page));
    document.querySelector(`#page-${page}`)?.classList.add('active'); const label=document.querySelector(`.nav-item[data-page="${page}"] span:nth-child(2)`); document.querySelector('#breadcrumbCurrent').textContent=label?.textContent||'Overview';
    document.querySelector('#sidebar').classList.remove('open'); window.scrollTo({top:0,behavior:'smooth'});
}

function renderLeadCampaignOptions(){
    const form=document.querySelector('#leadForm'),select=form.elements.campaign_id,previous=select.value,branch=form.elements.branch.value;
    select.innerHTML='<option value="">Tanpa campaign</option>'+campaigns.filter(c=>c.branch===branch).map(c=>`<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    if(campaigns.some(c=>c.branch===branch && String(c.id)===previous))select.value=previous;
}
function openLeadModal(lead=null){
    const form=document.querySelector('#leadForm');form.reset();form.elements.id.value=lead?.id||'';
    if(lead)for(const key of ['branch','name','city','program','source','status'])if(form.elements[key])form.elements[key].value=lead[key]||'';
    renderLeadCampaignOptions();form.elements.campaign_id.value=lead?.campaign_id||'';
    document.querySelector('#leadModalTitle').textContent=lead?'Edit lead':'Tambah lead';document.querySelector('#leadModal').classList.add('open');
}
function updateUserBranchField(){
    const form=document.querySelector('#userForm'),isAdmin=(window.ADS_DATA?.centralRoles||['superadmin']).includes(form.elements.role.value);
    form.elements.branch.required=!isAdmin;form.elements.branch.disabled=isAdmin;if(isAdmin)form.elements.branch.value='';
}

function openUserModal(user=null){
    const form=document.querySelector('#userForm');form.reset();form.elements.id.value=user?.id||'';if(!user){form.elements.role.value='staff';form.elements.branch.value=window.ADS_DATA?.branch||'';}
    for(const key of ['name','email','role','branch'])if(user)form.elements[key].value=user[key]||'';
    form.elements.password.required=!user;updateUserBranchField();document.querySelector('#userModalTitle').textContent=user?'Edit anggota':'Tambah anggota';
    document.querySelector('#userModal').classList.add('open');
}

function openCampaignModal(campaign=null) {
    const modal=document.querySelector('#campaignModal'), form=document.querySelector('#campaignForm'); form.reset(); form.elements.id.value=campaign?.id||'';
    if(campaign){['date','name','objective','platform','status','budget','branch'].forEach(key=>{if(form.elements[key])form.elements[key].value=campaign[key]??'';});document.querySelector('#modalTitle').textContent='Edit campaign';document.querySelector('#campaignSubmit').textContent='Simpan perubahan';}
    else{form.elements.date.value=new Date().toISOString().slice(0,10);document.querySelector('#modalTitle').textContent='Buat campaign baru';document.querySelector('#campaignSubmit').textContent='Simpan campaign';}
    modal.classList.add('open');modal.setAttribute('aria-hidden','false');setTimeout(()=>form.elements.name.focus(),80);
}
function closeCampaignModal(){document.querySelector('#campaignModal').classList.remove('open');}

function exportCsv(){const headers=['Cabang','Campaign','Platform','Status','Budget','Impressions','Reach','Klik','Leads','Closing','Omzet','ROAS'];const rows=reportCampaigns.map(c=>[c.branch||'Belum ditetapkan',c.name,c.platform,c.status,c.budget,c.impressions,c.reach,c.clicks,c.leads,c.closing,c.revenue,c.budget?(c.revenue/c.budget).toFixed(2):0]);const csv=[headers,...rows].map(row=>row.map(value=>`"${String(typeof value==='string' && /^[=+@\-\t\r]/.test(value)?"'"+value:value).replaceAll('"','""')}"`).join(',')).join('\n');const blob=new Blob([`\ufeff${csv}`],{type:'text/csv;charset=utf-8'});const link=Object.assign(document.createElement('a'),{href:URL.createObjectURL(blob),download:'ads-manager-report.csv'});link.click();URL.revokeObjectURL(link.href);showToast('Laporan CSV berhasil diekspor.');}

document.addEventListener('DOMContentLoaded',()=>{
    const loginPassword=document.querySelector('#loginPassword'),loginPasswordToggle=document.querySelector('#loginPasswordToggle');
    if(loginPassword && typeof loginPasswordToggle?.addEventListener==='function')loginPasswordToggle.addEventListener('click',()=>{
        const visible=loginPassword.type==='password';
        loginPassword.type=visible?'text':'password';
        loginPasswordToggle.setAttribute('aria-pressed',String(visible));
        loginPasswordToggle.setAttribute('aria-label',visible?'Sembunyikan kata sandi':'Tampilkan kata sandi');
        loginPasswordToggle.querySelectorAll('svg').forEach((icon,index)=>icon.hidden=index===(visible?0:1));
        loginPassword.focus();
    });
    if(!window.ADS_DATA)return;
    renderAllCampaigns();renderOverview([]);renderLeads();renderUsers();loadDashboardFilters(false);
    document.querySelector('#workspaceBranch')?.addEventListener('change',event=>{const url=new URL(location.href);url.searchParams.set('branch',event.target.value);location.href=url.pathname+url.search;});
    renderLeadCampaignOptions();document.querySelector('#leadForm').elements.branch.addEventListener('change',renderLeadCampaignOptions);document.querySelector('#userForm').elements.role.addEventListener('change',updateUserBranchField);
    document.querySelectorAll('.nav-item[data-page]').forEach(button=>button.addEventListener('click',()=>navigate(button.dataset.page))); document.querySelectorAll('[data-go]').forEach(button=>button.addEventListener('click',()=>navigate(button.dataset.go)));
    document.querySelector('#menuToggle').addEventListener('click',()=>document.querySelector('#sidebar').classList.toggle('open'));
    document.querySelectorAll('[data-open-modal],#newCampaignButton').forEach(button=>button.addEventListener('click',()=>openCampaignModal())); document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',closeCampaignModal));
    document.querySelectorAll('.filter-chip[data-platform]').forEach(button=>button.addEventListener('click',async()=>{document.querySelectorAll('.filter-chip[data-platform]').forEach(chip=>chip.classList.remove('active'));button.classList.add('active');await loadDashboardFilters();}));
    document.querySelector('#applyDate')?.addEventListener('click',()=>loadDashboardFilters());document.querySelector('#conversionSource')?.addEventListener('change',()=>loadDashboardFilters());
    document.querySelector('#globalSearch').addEventListener('keydown',event=>{if(event.key==='Enter'){navigate('campaigns');document.querySelector('#campaignSearch').value=event.target.value;document.querySelector('#campaignSearch').dispatchEvent(new Event('input'));}});
    const filterCampaigns=()=>{const query=document.querySelector('#campaignSearch').value.toLowerCase(),platform=document.querySelector('#platformFilter').value.replace('Semua platform','Semua'),status=document.querySelector('#campaignStatusFilter').value;renderAllCampaigns(campaigns.filter(c=>c.name.toLowerCase().includes(query)&&(platform==='Semua'||c.platform===platform)&&(status==='Semua status'||c.status===status)));};
    document.querySelector('#campaignSearch').addEventListener('input',filterCampaigns);document.querySelector('#platformFilter').addEventListener('change',filterCampaigns);document.querySelector('#campaignStatusFilter').addEventListener('change',filterCampaigns);
    document.querySelector('#campaignForm')?.addEventListener('submit',async event=>{event.preventDefault();const values=Object.fromEntries(new FormData(event.target)),id=values.id;delete values.id;try{const saved=normalizeCampaign(await api(id?`/campaigns/${id}`:'/campaigns',{method:id?'PUT':'POST',body:JSON.stringify(values)}));campaigns=id?campaigns.map(c=>String(c.id)===String(id)?saved:c):[saved,...campaigns];renderAllCampaigns();await loadDashboardFilters(false);closeCampaignModal();showToast(id?'Perubahan campaign tersimpan.':'Campaign baru berhasil dibuat.');}catch(error){showToast(error.message);}});
    document.addEventListener('click',async event=>{
        const editLead=event.target.closest('.edit-lead');if(editLead)openLeadModal(leads.find(lead=>String(lead.id)===editLead.dataset.id));
        const edit=event.target.closest('.edit-campaign');if(edit){const campaign=campaigns.find(c=>String(c.id)===edit.dataset.id);if(campaign)openCampaignModal(campaign);}
        const remove=event.target.closest('.delete-campaign');if(remove&&confirm('Hapus campaign ini?'))try{await api(`/campaigns/${remove.dataset.id}`,{method:'DELETE'});campaigns=campaigns.filter(c=>String(c.id)!==remove.dataset.id);renderAllCampaigns();await loadDashboardFilters(false);showToast('Campaign dihapus.');}catch(error){showToast(error.message);}
        const close=event.target.closest('.close-lead');if(close){const value=prompt('Nilai closing (Rp), gunakan angka tanpa pemisah:');if(value!==null)try{await api(`/leads/${close.dataset.id}/close`,{method:'POST',body:JSON.stringify({date:new Date().getFullYear()+'-'+String(new Date().getMonth()+1).padStart(2,'0')+'-'+String(new Date().getDate()).padStart(2,'0'),value,status:'Lunas'})});location.reload();}catch(error){showToast(error.message);}}
        const leadButton=event.target.closest('.delete-lead');if(leadButton&&confirm('Hapus lead ini?'))try{await api(`/leads/${leadButton.dataset.id}`,{method:'DELETE'});leads=leads.filter(l=>String(l.id)!==leadButton.dataset.id);renderLeads();await loadDashboardFilters(false);showToast('Lead dihapus.');}catch(error){showToast(error.message);}
        const editUser=event.target.closest('.edit-user');if(editUser)openUserModal(users.find(user=>String(user.id)===editUser.dataset.id));
        const userButton=event.target.closest('.delete-user');if(userButton&&confirm('Hapus anggota tim ini?'))try{await api(`/users/${userButton.dataset.id}`,{method:'DELETE'});users=users.filter(user=>String(user.id)!==userButton.dataset.id);renderUsers();showToast('Anggota tim dihapus.');}catch(error){showToast(error.message);}
    });
    ['#exportButton','#reportExportButton'].forEach(selector=>document.querySelector(selector)?.addEventListener('click',exportCsv));
    document.querySelector('#newLeadButton')?.addEventListener('click',()=>openLeadModal());document.querySelectorAll('[data-close-lead]').forEach(button=>button.addEventListener('click',()=>document.querySelector('#leadModal').classList.remove('open')));
    document.querySelector('#leadForm')?.addEventListener('submit',async event=>{event.preventDefault();try{const data=Object.fromEntries(new FormData(event.target)),id=data.id;delete data.id;const created=await api(id?`/leads/${id}`:'/leads',{method:id?'PUT':'POST',body:JSON.stringify(data)});const saved={...created,campaign:campaigns.find(c=>String(c.id)===String(created.campaign_id))};leads=id?leads.map(lead=>String(lead.id)===id?saved:lead):[saved,...leads];renderLeads();await loadDashboardFilters(false);document.querySelector('#leadModal').classList.remove('open');event.target.reset();showToast(id?'Lead berhasil diperbarui.':'Lead berhasil ditambahkan.');}catch(error){showToast(error.message);}});
    const importFile=document.querySelector('#importFile');document.querySelector('#chooseFileButton')?.addEventListener('click',()=>importFile.click());importFile?.addEventListener('change',async()=>{if(!importFile.files[0])return;const form=new FormData();form.append('file',importFile.files[0]);form.append('branch',document.querySelector('#importBranch').value);const preview=document.querySelector('#importPreview');preview.innerHTML='<p>Memeriksa workbook...</p>';try{const data=await api('/imports/campaigns/preview',{method:'POST',body:form});preview.innerHTML=`<div class="preview-head"><div><b>${escapeHtml(importFile.files[0].name)}</b><span>${data.total} baris ditemukan</span></div><button class="primary-button" id="runImportButton">Impor ${data.total} baris</button></div><div class="table-wrap"><table><thead><tr>${data.headers.slice(0,8).map(h=>`<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>${data.rows.map(r=>`<tr>${data.headers.slice(0,8).map(h=>`<td>${escapeHtml(r[h]??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;document.querySelector('#runImportButton').addEventListener('click',async()=>{try{const upload=new FormData();upload.append('file',importFile.files[0]);upload.append('branch',document.querySelector('#importBranch').value);const result=await api('/imports/campaigns',{method:'POST',body:upload});showToast(`${result.rows_imported} baris berhasil diimpor.`);setTimeout(()=>location.reload(),800);}catch(error){showToast(error.message);}});}catch(error){preview.innerHTML=`<p class="form-error">${escapeHtml(error.message)}</p>`;}});
    const syncMeta=async branch=>{try{const result=await api('/meta/sync',{method:'POST',body:JSON.stringify({branch,since:document.querySelector('#dateFrom').value,until:document.querySelector('#dateTo').value})});showToast(result.imported?`${branch}: ${result.imported} baris disinkronkan.`:`${branch}: tidak ada data pada periode ini.`);if(result.imported)setTimeout(()=>location.reload(),800);}catch(error){showToast(error.message);}};
    const openMeta=branch=>{document.querySelector('#metaForm').elements.branch.value=branch||'';document.querySelector('#metaModal').classList.add('open');};
    document.querySelector('#connectButton')?.addEventListener('click',()=>{if(window.ADS_DATA.metaConnection)syncMeta(window.ADS_DATA.branch);else navigate('settings');});
    document.querySelectorAll('[data-connect-meta]').forEach(button=>button.addEventListener('click',()=>openMeta(button.dataset.connectMeta)));
    document.querySelectorAll('[data-sync-meta]').forEach(button=>button.addEventListener('click',()=>syncMeta(button.dataset.syncMeta)));
    document.querySelectorAll('[data-disconnect-meta]').forEach(button=>button.addEventListener('click',async()=>{if(!confirm('Putuskan koneksi Meta Ads '+button.dataset.disconnectMeta+'?'))return;try{await api('/meta/disconnect',{method:'DELETE',body:JSON.stringify({branch:button.dataset.disconnectMeta})});location.reload();}catch(error){showToast(error.message);}}));
    document.querySelectorAll('[data-close-meta]').forEach(button=>button.addEventListener('click',()=>document.querySelector('#metaModal').classList.remove('open')));
    document.querySelector('#metaForm')?.addEventListener('submit',async event=>{event.preventDefault();try{await api('/meta/connect',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(event.target)))});event.target.reset();showToast('Akun Meta Ads cabang terhubung.');setTimeout(()=>location.reload(),800);}catch(error){showToast(error.message);}});
    setupProfileSettings();
    document.querySelector('#newUserButton')?.addEventListener('click',()=>openUserModal());document.querySelectorAll('[data-close-user]').forEach(button=>button.addEventListener('click',()=>document.querySelector('#userModal').classList.remove('open')));
    document.querySelector('#userForm')?.addEventListener('submit',async event=>{event.preventDefault();try{const data=Object.fromEntries(new FormData(event.target)),id=data.id;delete data.id;if(!data.password)delete data.password;const user=await api(id?`/users/${id}`:'/users',{method:id?'PUT':'POST',body:JSON.stringify(data)});users=id?users.map(member=>String(member.id)===id?user:member):[...users,user];users.sort((a,b)=>a.name.localeCompare(b.name));renderUsers();event.target.reset();document.querySelector('#userModal').classList.remove('open');showToast(id?'Anggota tim diperbarui.':'Anggota tim ditambahkan.');if(id && String(window.ADS_DATA.user.id)===id)location.href='/';}catch(error){showToast(error.message);}});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){closeCampaignModal();document.querySelectorAll('.modal-backdrop').forEach(modal=>modal.classList.remove('open'));}if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k'){event.preventDefault();document.querySelector('#globalSearch').focus();}});
});
