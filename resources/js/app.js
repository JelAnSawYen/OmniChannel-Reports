const qs=(s,r=document)=>r.querySelector(s), qsa=(s,r=document)=>[...r.querySelectorAll(s)];
const csrf=()=>qs('meta[name="csrf-token"]')?.content||'';
const expandedViewKey='omnichannel.expandedView';
function readPageScroll(){
    const se=document.scrollingElement||document.documentElement;
    return {x:window.scrollX||0,y:window.scrollY||se.scrollTop||0};
}
function writePageScroll(pos){
    if(!pos)return;
    const x=Number(pos.x)||0;
    const y=Number(pos.y)||0;
    window.scrollTo(x,y);
    if(document.scrollingElement)document.scrollingElement.scrollTop=y;
}
let pinnedPageScroll=null;
function pinPageScroll(pos){
    pinnedPageScroll=pos||readPageScroll();
}
const showModal=(id)=>qs('#'+id)?.classList.add('visible');
const hideModal=(id)=>qs('#'+id)?.classList.remove('visible');

function bindCampaignCombo(root){
    if(!root||root.dataset.campaignComboBound==='1')return;
    root.dataset.campaignComboBound='1';
    const input=root.querySelector('input.form-control');
    const menu=root.querySelector('.pin-campaign-menu');
    if(!input||!menu)return;
    const options=()=>[...menu.querySelectorAll('.pin-campaign-option')];
    const filterMenu=()=>{
        const query=input.value.trim().toLowerCase();
        let visible=0;
        options().forEach(option=>{
            const match=!query||(option.dataset.name||'').toLowerCase().includes(query);
            option.hidden=!match;
            if(match)visible+=1;
        });
        const empty=menu.querySelector('.pin-campaign-empty');
        if(empty&&options().length)empty.hidden=visible>0;
        const matched=options().find(option=>(option.dataset.name||'').toLowerCase()===query);
        input.dispatchEvent(new CustomEvent('campaign-combo-change',{bubbles:true,detail:{option:matched||null}}));
    };
    const openMenu=()=>{
        filterMenu();
        menu.hidden=false;
        input.setAttribute('aria-expanded','true');
    };
    const closeMenu=()=>{
        menu.hidden=true;
        input.setAttribute('aria-expanded','false');
    };
    input.addEventListener('focus',openMenu);
    input.addEventListener('input',openMenu);
    menu.addEventListener('click',event=>{
        const option=event.target.closest('.pin-campaign-option');
        if(!option)return;
        input.value=option.dataset.name||'';
        closeMenu();
        input.dispatchEvent(new CustomEvent('campaign-combo-change',{bubbles:true,detail:{option}}));
    });
    root._closeCampaignCombo=closeMenu;
}

function initCampaignCombos(){
    qsa('[data-campaign-combo]').forEach(bindCampaignCombo);
    document.addEventListener('click',event=>{
        if(!(event.target instanceof Element))return;
        qsa('[data-campaign-combo]').forEach(root=>{
            if(!root.contains(event.target))root._closeCampaignCombo?.();
        });
    });
}

function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}

function isSystemFlash(el){
    return el && el.id!=='importUploadError' && !el.classList.contains('import-upload-error');
}
function bindFlashClose(el){
    el?.querySelector('.flash-close')?.addEventListener('click',()=>el.remove());
}
function flash(message,type='success'){
    qsa('.flash').forEach(el=>{
        if(isSystemFlash(el))el.remove();
    });
    const el=document.createElement('div');
    el.className='flash '+type;
    el.setAttribute('role',type==='error'?'alert':'status');
    const text=document.createElement('span');
    text.className='flash-message';
    text.textContent=message;
    const close=document.createElement('button');
    close.type='button';
    close.className='flash-close';
    close.setAttribute('aria-label','Close');
    close.textContent='×';
    el.append(text,close);
    (qs('.flash-host')||document.body).appendChild(el);
    bindFlashClose(el);
    window.setTimeout(()=>el.remove(),2000);
}

function initGlobal(){
    if(document.body.dataset.uiBound==='1')return;
    document.body.dataset.uiBound='1';
    const body=document.body;
    initCampaignCombos();

    qsa('.flash').forEach(el=>{
        if(!isSystemFlash(el))return;
        bindFlashClose(el);
        window.setTimeout(()=>el.remove(),2000);
    });

    qs('#sidebarToggle')?.addEventListener('click',()=>{
        if(window.innerWidth<=800){
            qs('#sidebar')?.classList.toggle('mobile-open');
            qs('#sidebarOverlay')?.classList.toggle('visible');
        }else{
            body.classList.toggle('sidebar-collapsed');
        }
    });

    qs('#sidebarClose')?.addEventListener('click',()=>{
        qs('#sidebar')?.classList.remove('mobile-open');
        qs('#sidebarOverlay')?.classList.remove('visible');
    });

    qs('#sidebarOverlay')?.addEventListener('click',()=>{
        qs('#sidebar')?.classList.remove('mobile-open');
        qs('#sidebarOverlay')?.classList.remove('visible');
    });

    qs('#accountButton')?.addEventListener('click',e=>{
        e.stopPropagation();
        qs('#accountMenu')?.classList.toggle('open');
    });

    qs('#accountSettingsToggle')?.addEventListener('click',e=>{
        e.preventDefault();
        e.stopPropagation();
        const group=qs('#accountSettingsGroup');
        const open=!group?.classList.contains('open');
        group?.classList.toggle('open',open);
        qs('#accountSettingsToggle')?.setAttribute('aria-expanded',open?'true':'false');
    });

    document.addEventListener('click',e=>{
        if(!e.target.closest('.account-wrap'))qs('#accountMenu')?.classList.remove('open');
    });

    const networkGroup=qs('#networkGroup');
    const networkToggle=qs('#networkToggle');
    const locationNav=qs('#sidebarNav');
    const locationScrollKey='omnichannel.sidebar.navScroll';
    if(locationNav){
        const saved=Number(sessionStorage.getItem(locationScrollKey)||0);
        if(saved>0)locationNav.scrollTop=saved;
        locationNav.addEventListener('scroll',()=>{
            sessionStorage.setItem(locationScrollKey,String(locationNav.scrollTop));
        },{passive:true});
    }
    const syncNetworkOpen=()=>{
        const open=networkGroup?.classList.contains('open');
        networkToggle?.setAttribute('aria-expanded',open?'true':'false');
    };
    const toggleNetwork=e=>{
        e.preventDefault();
        e.stopPropagation();
        networkGroup?.classList.toggle('open');
        syncNetworkOpen();
    };
    networkToggle?.addEventListener('click',toggleNetwork);
    syncNetworkOpen();

    const sipChannelsGroup=qs('#sipChannelsGroup');
    const sipChannelsToggle=qs('#sipChannelsToggle');
    const sipChannelsCaret=qs('#sipChannelsCaret');
    const syncSipChannelsOpen=()=>{
        const open=sipChannelsGroup?.classList.contains('open');
        sipChannelsToggle?.setAttribute('aria-expanded',open?'true':'false');
    };
    const toggleSipChannels=e=>{
        e.preventDefault();
        e.stopPropagation();
        sipChannelsGroup?.classList.toggle('open');
        syncSipChannelsOpen();
    };
    sipChannelsCaret?.addEventListener('click',toggleSipChannels);
    sipChannelsToggle?.addEventListener('click',e=>{
        if(e.target.closest('#sipChannelsCaret, .nav-caret')){
            toggleSipChannels(e);
            return;
        }
        if(document.body?.dataset?.page==='sip-channels'){
            toggleSipChannels(e);
        }
    });
    syncSipChannelsOpen();

    qs('#logoutButton')?.addEventListener('click',()=>{
        qs('#accountMenu')?.classList.remove('open');
        showModal('logoutModal');
    });

    qsa('[data-close]').forEach(b=>b.addEventListener('click',()=>hideModal(b.dataset.close)));
    qsa('[data-open]').forEach(el=>el.addEventListener('click',e=>{
        e.preventDefault();
        showModal(el.dataset.open);
    }));

    function liveSearchUrl(form, searchValue){
        const action=form.getAttribute('action')||location.pathname;
        const url=new URL(action,location.origin);
        const data=new FormData(form);
        data.delete('page');
        if(searchValue!==undefined)data.set('search',searchValue);
        const params=new URLSearchParams();
        data.forEach((value,key)=>{
            if(key==='search' && !String(value||'').trim())return;
            if(value==='' && (key==='user_type_id'||key==='status'||key==='action'||key==='location'))return;
            params.set(key,String(value));
        });
        url.search=params.toString();
        return url;
    }

    function resultCards(root){
        return [...root.querySelectorAll('.table-card, .ar-tree-card, .health-module-card')];
    }

    function swapLiveResults(fromDoc, input){
        const current=resultCards(document);
        const next=resultCards(fromDoc);
        current.forEach((card,i)=>{
            const incoming=next[i];
            if(!incoming)return;
            if(card.contains(input)){
                const cur=card.querySelector('form[data-select-user], .table-wrap');
                const nxt=incoming.querySelector('form[data-select-user], .table-wrap');
                if(cur&&nxt)cur.replaceWith(document.importNode(nxt,true));
                const curFoot=card.querySelector(':scope > .table-footer');
                const nxtFoot=incoming.querySelector(':scope > .table-footer');
                if(curFoot&&nxtFoot)curFoot.replaceWith(document.importNode(nxtFoot,true));
                else if(!curFoot&&nxtFoot)card.appendChild(document.importNode(nxtFoot,true));
                else if(curFoot&&!nxtFoot)curFoot.remove();
                return;
            }
            card.replaceWith(document.importNode(incoming,true));
        });
        const exportNext=fromDoc.querySelector('#exportDataButton');
        const exportCur=document.querySelector('#exportDataButton');
        if(exportCur&&exportNext)exportCur.setAttribute('href',exportNext.getAttribute('href')||exportNext.href);
    }

    let liveSearchRequest=null;
    async function fetchLiveResults(form, input, searchValue){
        if(!form||form.id==='mediaSearchForm')return;
        const url=liveSearchUrl(form, searchValue);
        if(liveSearchRequest)liveSearchRequest.abort();
        const controller=new AbortController();
        liveSearchRequest=controller;
        try{
            const response=await fetch(url.toString(),{
                headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'},
                signal:controller.signal,
                credentials:'same-origin'
            });
            if(!response.ok)return;
            if(input && input.value!==searchValue)return;
            const html=await response.text();
            if(input && input.value!==searchValue)return;
            const doc=new DOMParser().parseFromString(html,'text/html');
            swapLiveResults(doc, input);
            const next=url.pathname+(url.search||'');
            if(`${location.pathname}${location.search}`!==next)history.replaceState({},'',next);
        }catch(error){
            if(error?.name==='AbortError')return;
        }finally{
            if(liveSearchRequest===controller)liveSearchRequest=null;
        }
    }

    qsa('form').forEach(form=>{
        if(form.id==='mediaSearchForm')return;
        if((form.getAttribute('method')||'get').toLowerCase()==='post')return;
        const input=form.querySelector('input[name="search"]:not([type="hidden"])');
        if(!input||form.dataset.liveSearchBound==='1')return;
        form.dataset.liveSearchBound='1';
        let timer;
        input.addEventListener('input',()=>{
            if(liveSearchRequest)liveSearchRequest.abort();
            clearTimeout(timer);
            const value=input.value;
            timer=setTimeout(()=>fetchLiveResults(form, input, value),180);
        });
        form.addEventListener('submit',event=>{
            event.preventDefault();
            clearTimeout(timer);
            fetchLiveResults(form, input, input.value);
        });
    });
    document.addEventListener('change',event=>{
        const form=event.target.closest('form[data-select-user]');
        if(!form||!event.target.matches('input[type="radio"][name="user"]'))return;
        form.submit();
    });
    document.addEventListener('click',event=>{
        const row=event.target.closest('form[data-select-user] tr');
        if(!row||event.target.closest('input,a,button'))return;
        const radio=row.querySelector('input[type="radio"][name="user"]');
        if(!radio||radio.checked)return;
        radio.checked=true;
        radio.closest('form')?.submit();
    });

    initConfirm();
    initPreserveExpandedView();
}

function initPreserveExpandedView(){
    const here=()=>location.pathname+location.search;
    const detailsKey=(el)=>{
        const parts=[];
        let node=el;
        while(node&&node.tagName==='DETAILS'){
            parts.unshift((node.querySelector(':scope > summary')?.textContent||'').replace(/\s+/g,' ').trim());
            node=node.parentElement?node.parentElement.closest('details'):null;
        }
        return parts.join('>');
    };
    const openPanels=()=>qsa('[id^="ca-panel-"],[id^="pdc-panel-"]').filter(el=>!el.hasAttribute('hidden')).map(el=>el.id);
    const openDetails=()=>qsa('details[open]').map(detailsKey);
    const readAll=()=>{
        try{
            const raw=JSON.parse(sessionStorage.getItem(expandedViewKey)||'null');
            if(!raw)return {};
            if(raw.path)return{[raw.path]:raw};
            return raw;
        }catch(e){return {};}
    };
    const persist=()=>{
        const all=readAll();
        const state={scroll:readPageScroll(),panels:openPanels(),details:openDetails()};
        all[here()]=state;
        all[location.pathname]=state;
        sessionStorage.setItem(expandedViewKey,JSON.stringify(all));
    };
    const restorePanels=(ids)=>{
        (ids||[]).forEach(id=>{
            const panel=document.getElementById(id);
            if(!panel||!panel.hasAttribute('hidden'))return;
            const toggleId=id.replace(/^(ca|pdc)-panel-/,'');
            qs('[data-ca-toggle="'+toggleId+'"]')?.click();
        });
    };
    const restoreDetails=(keys)=>{
        const want=new Set(keys||[]);
        if(!want.size)return;
        qsa('details').forEach(el=>{
            if(want.has(detailsKey(el)))el.open=true;
        });
    };

    document.addEventListener('submit',persist,true);
    window.addEventListener('beforeunload',persist);
    qsa('.per-page-select').forEach(select=>select.addEventListener('change',persist));

    const all=readAll();
    const key=Object.prototype.hasOwnProperty.call(all,here())?here():(Object.prototype.hasOwnProperty.call(all,location.pathname)?location.pathname:'');
    if(!key)return;
    const state=all[key];
    delete all[key];
    if(key!==location.pathname)delete all[location.pathname];
    sessionStorage.setItem(expandedViewKey,JSON.stringify(all));
    restorePanels(state.panels);
    restoreDetails(state.details);
    pinPageScroll(state.scroll);
    writePageScroll(state.scroll);
}

function initConfirm(){
    const modal=qs('#confirmModal');
    if(!modal||modal.dataset.bound==='1')return;
    modal.dataset.bound='1';

    const title=qs('#confirmModalTitle');
    const message=qs('#confirmModalMessage');
    const ok=qs('#confirmModalOk');
    const cancel=qs('#confirmModalCancel');
    let pending=null;

    function closeConfirm(){
        pending=null;
        hideModal('confirmModal');
    }

    function openConfirm({titleText,messageText,okText,cancelText,onConfirm}){
        if(title)title.textContent=titleText||'Confirm';
        if(message)message.textContent=messageText||'Are you sure?';
        if(ok)ok.textContent=okText||'Yes';
        if(cancel)cancel.textContent=cancelText||'No';
        pending=typeof onConfirm==='function'?onConfirm:null;
        showModal('confirmModal');
    }

    ok?.addEventListener('click',()=>{
        const run=pending;
        closeConfirm();
        if(run)run();
    });
    cancel?.addEventListener('click',closeConfirm);
    qs('#confirmModalDismiss')?.addEventListener('click',closeConfirm);
    modal.addEventListener('click',event=>{
        if(event.target===modal)closeConfirm();
    });

    document.addEventListener('submit',event=>{
        const form=event.target;
        if(!(form instanceof HTMLFormElement)||!form.hasAttribute('data-confirm'))return;
        if(form.dataset.confirmAccepted==='1'){
            delete form.dataset.confirmAccepted;
            return;
        }
        event.preventDefault();
        openConfirm({
            titleText:form.dataset.confirmTitle||'Confirm',
            messageText:form.dataset.confirm||'Are you sure?',
            okText:form.dataset.confirmOk||'Yes',
            cancelText:form.dataset.confirmCancel||'No',
            onConfirm:()=>{
                form.dataset.confirmAccepted='1';
                if(typeof form.requestSubmit==='function')form.requestSubmit();
                else form.submit();
            }
        });
    });
}

async function initMedia(){
    if(!['media-gateways','gsm-gateways'].includes(document.body.dataset.page) && !qs('#mediaGatewayForm'))return;
    if(document.body.dataset.mediaJsBound==='1')return;
    document.body.dataset.mediaJsBound='1';

    const base=window.location.origin+(document.body.dataset.resourceBase||'/media-gateways');
    const isGsm=document.body.dataset.page==='gsm-gateways';
    const entity=isGsm?'GSM Gateway':'Media Gateway';
    const initial=new URLSearchParams(location.search);
    let state={
        search:initial.get('search')||'',
        sortBy:initial.get('sort_by')||'id',
        sortDir:initial.get('sort_dir')||'asc',
        page:Number(initial.get('page')||1),
        perPage:Number(initial.get('per_page')||10)
    };
    let deleteId=null;
    let simDelete=null;
    const expandedIds=new Set();
    const canCreate=!!qs('[data-open-modal="add-media-gateway"]');

    const transfer=qs('#transferButton');

    const dom={
        rows:qs('#mediaGatewayRows'),
        summary:qs('#recordSummary'),
        pages:qs('#paginationLinks'),
        perPage:qs('#perPageSelect'),
        form:qs('#mediaGatewayForm'),
        modalTitle:qs('#mediaGatewayModalTitle'),
        gatewayId:qs('#gatewayId'),
        formMethod:qs('#formMethod'),
        deleteConfirm:qs('#confirmDeleteBtn'),
        search:qs('#mediaSearchInput')
    };

    function updateUrl(){
        const p=new URLSearchParams();
        if(state.search)p.set('search',state.search);
        if(state.sortBy!=='id')p.set('sort_by',state.sortBy);
        if(state.sortDir!=='asc')p.set('sort_dir',state.sortDir);
        if(state.page!==1)p.set('page',state.page);
        if(state.perPage!==10)p.set('per_page',state.perPage);
        const next=location.pathname+(p.toString()?'?'+p:'');
        if(`${location.pathname}${location.search}`!==next){
            history.replaceState({},'',next);
        }
    }

    function updateExportLink(){
        const exportLink=qs('#exportDataButton') || qs('#transferButton');
        if(!exportLink||exportLink.tagName!=='A')return;
        const url=new URL(exportLink.getAttribute('href')||exportLink.href, window.location.href);
        url.search='';
        if(state.search)url.searchParams.set('search',state.search);
        if(state.sortBy)url.searchParams.set('sort_by',state.sortBy);
        if(state.sortDir)url.searchParams.set('sort_dir',state.sortDir);
        exportLink.href=url.pathname+url.search;
    }

    const editIcon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>';
    const deleteIcon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>';
    const dotsIcon='<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>';
    let simCatalog=[];
    let simPicked=null;
    let simFilter='';
    let simPickedPort='';

    function passwordCell(g,canReveal){
        return `<span class="pdc-secret">
            <span class="pdc-secret-mask">••••••</span>
            ${canReveal && g.password ? `<span class="pdc-secret-value" hidden>${escapeHtml(g.password)}</span>
            <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>` : ''}
        </span>`;
    }

    function editAttrs(g,canReveal){
        return `data-edit-id="${escapeHtml(g.id)}" data-edit-hostname="${escapeHtml(g.hostname||'')}" data-edit-site_name="${escapeHtml(g.site_name)}" data-edit-site_code="${escapeHtml(g.site_code)}" data-edit-ip_address="${escapeHtml(g.ip_address)}" data-edit-channel_count="${escapeHtml(g.channel_count||'')}" data-edit-plan="${escapeHtml(g.plan||'')}" data-edit-port="${escapeHtml(g.port||'')}" data-edit-network="${escapeHtml(g.network||'')}" data-edit-device_function="${escapeHtml(g.device_function||'')}" data-edit-username="${escapeHtml(g.username)}"${canReveal?` data-edit-password="${escapeHtml(g.password||'')}"`:''}`;
    }

    function actionsCell(g,canEdit,canDelete,canReveal){
        if(isGsm){
            if(!canEdit && !canDelete)return '';
            return `<div class="ca-menu">
                <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="${entity} actions" title="${entity} actions">${dotsIcon}</button>
                <div class="ca-menu-dropdown" role="menu" hidden>
                    ${canEdit?`<button class="ca-menu-item edit" type="button" role="menuitem" ${editAttrs(g,canReveal)} title="Edit ${entity}" aria-label="Edit ${entity}">${editIcon} Edit</button>`:''}
                    ${canDelete?`<button class="ca-menu-item delete" type="button" role="menuitem" data-delete-id="${escapeHtml(g.id)}" title="Delete ${entity}" aria-label="Delete ${entity}">${deleteIcon} Delete</button>`:''}
                </div>
            </div>`;
        }
        return `<div class="row-actions">
            ${canEdit?`<button type="button" class="action-btn edit" ${editAttrs(g,canReveal)} title="Edit ${entity}" aria-label="Edit ${entity}">${editIcon}</button>`:''}
            ${canDelete?`<button type="button" class="action-btn delete" data-delete-id="${escapeHtml(g.id)}" title="Delete ${entity}" aria-label="Delete ${entity}">${deleteIcon}</button>`:''}
        </div>`;
    }

    function simKey(item){
        return `${item.sim_type}:${item.id}`;
    }

    function renderSimList(){
        const body=qs('#gsmSimRows');
        if(!body)return;
        const needle=simFilter.trim().toLowerCase();
        const rows=(simCatalog||[]).filter(sim=>{
            if(!needle)return true;
            return String(sim.imei||'').toLowerCase().includes(needle) || String(sim.mobile_number||'').toLowerCase().includes(needle);
        });
        if(!qs('#gsmSimNetwork')?.value){
            body.innerHTML='<tr><td colspan="4">Select a network to load SIM records.</td></tr>';
            return;
        }
        if(!rows.length){
            body.innerHTML='<tr><td colspan="4">No SIM records found.</td></tr>';
            return;
        }
        body.innerHTML=rows.map(sim=>{
            const selected=simPicked && simKey(simPicked)===simKey(sim);
            return `<tr class="gsm-sim-row${selected?' is-selected':''}" data-sim-type="${escapeHtml(sim.sim_type)}" data-sim-id="${escapeHtml(sim.id)}">
                <td>${escapeHtml(sim.imei||'—')}</td>
                <td>${escapeHtml(sim.mobile_number||'—')}</td>
                <td>${escapeHtml(sim.plan||'—')}</td>
                <td>${selected && simPickedPort?escapeHtml(simPickedPort):'—'}</td>
            </tr>`;
        }).join('');
    }

    function pickSim(sim){
        simPicked=sim;
        renderSimList();
    }

    async function loadSims(network){
        const body=qs('#gsmSimRows');
        if(!body)return;
        if(!network){
            simCatalog=[];
            renderSimList();
            return;
        }
        body.innerHTML='<tr><td colspan="4">Loading SIM records...</td></tr>';
        const params=new URLSearchParams({network});
        const gatewayId=qs('#gsmSimGatewayId')?.value||'';
        const assignmentId=qs('#gsmSimAssignmentId')?.value||'';
        if(gatewayId)params.set('gateway_id',gatewayId);
        if(assignmentId)params.set('assignment_id',assignmentId);
        try{
            const response=await fetch(base+'/sims?'+params.toString(),{
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}
            });
            const data=await response.json().catch(()=>({}));
            if(!response.ok)throw new Error(data.message||'Unable to load SIM records.');
            simCatalog=data.records||[];
            renderSimList();
        }catch(error){
            body.innerHTML=`<tr><td colspan="4">${escapeHtml(error.message||'Unable to load SIM records.')}</td></tr>`;
        }
    }

    function resetSims(){
        simCatalog=[];
        simPicked=null;
        simPickedPort='';
        simFilter='';
        const search=qs('#gsmSimSearch');
        if(search)search.value='';
        const network=qs('#gsmSimNetwork');
        if(network)network.value='';
        renderSimList();
    }

    function closeGsmMenus(except){
        qsa('#mediaGatewayRows .ca-menu.open').forEach(menu=>{
            if(menu===except)return;
            menu.classList.remove('open');
            menu.querySelector('.ca-menu-dropdown')?.setAttribute('hidden','');
            menu.querySelector('.ca-menu-btn')?.setAttribute('aria-expanded','false');
        });
    }

    function gsmChevron(id, hostname){
        return `<span class="gsm-host-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="${escapeHtml(id)}" aria-expanded="false" aria-controls="gsm-panel-${escapeHtml(id)}" title="Expand ${escapeHtml(hostname||'gateway')}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span>${escapeHtml(hostname||'—')}</span>
        </span>`;
    }

    function nestedSimTable(g,canEdit,canDelete){
        const assignments=g.assignments||[];
        const rows=assignments.length
            ? assignments.map(item=>`<tr>
                <td>${escapeHtml(item.imei||'—')}</td>
                <td>${escapeHtml(item.mobile_number||'—')}</td>
                <td>${escapeHtml(item.plan||'—')}</td>
                <td>${escapeHtml(g.ip_address||'—')}</td>
                <td>${escapeHtml(item.port||'—')}</td>
                <td class="actions-column">
                    <div class="row-actions">
                        ${canEdit?`<button class="action-btn edit" type="button" data-gsm-sim-edit data-gateway-id="${escapeHtml(g.id)}" data-assignment-id="${escapeHtml(item.assignment_id)}" data-sim-type="${escapeHtml(item.sim_type)}" data-sim-id="${escapeHtml(item.id)}" data-network="${escapeHtml(item.network||'')}" data-port="${escapeHtml(item.port||'')}" title="Edit" aria-label="Edit">${editIcon}</button>`:''}
                        ${canDelete?`<button class="action-btn delete" type="button" data-gsm-sim-delete data-gateway-id="${escapeHtml(g.id)}" data-assignment-id="${escapeHtml(item.assignment_id)}" title="Delete" aria-label="Delete">${deleteIcon}</button>`:''}
                    </div>
                </td>
            </tr>`).join('')
            : `<tr><td colspan="6"><div class="empty-state">No SIM assignments.</div></td></tr>`;
        const plus=canCreate
            ? `<button class="plus-btn" type="button" data-gsm-sim-add="${escapeHtml(g.id)}" data-channel-count="${escapeHtml(g.channel_count||'')}" data-assignment-count="${assignments.length}" title="Add SIM Assignment" aria-label="Add SIM Assignment">+</button>`
            : '';
        return `<tr class="ca-nested-row" id="gsm-panel-${escapeHtml(g.id)}" hidden>
            <td colspan="9">
                <div class="ca-nested">
                    <table class="gsm-sim-nested" aria-label="SIM assignments">
                        <thead>
                            <tr>
                                <th>IMEI</th>
                                <th>Mobile Number</th>
                                <th>Plan</th>
                                <th>IP</th>
                                <th>Port</th>
                                <th class="actions-column"><span class="ca-actions-head">Actions${plus}</span></th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </td>
        </tr>`;
    }

    function restoreExpanded(){
        expandedIds.forEach(id=>{
            const panel=document.getElementById('gsm-panel-'+id);
            const row=document.querySelector('tr.gsm-gateway-row[data-gateway="'+id+'"]');
            if(!panel||!row)return;
            panel.removeAttribute('hidden');
            row.classList.add('open');
            row.querySelectorAll('[data-ca-toggle]').forEach(el=>el.setAttribute('aria-expanded','true'));
        });
    }

    function setDeleteCopy(kind){
        const title=qs('#deleteModalTitle');
        const body=qs('#deleteModalBody');
        if(!title||!body)return;
        if(kind==='sim'){
            title.textContent='Delete SIM Assignment';
            body.textContent='Are you sure you want to delete this SIM assignment?';
            return;
        }
        title.textContent='Delete '+entity;
        body.textContent='Are you sure you want to delete this '+entity+'?';
    }

    function render(data){
        const records=data.records||[];
        const canEdit=document.body.dataset.canEdit==='1';
        const canDelete=document.body.dataset.canDelete==='1';
        const canReveal=document.body.dataset.canRevealSecrets==='1';
        const colspan=isGsm?9:10;

        closeGsmMenus();
        if(!dom.rows)return;
        dom.rows.innerHTML=records.length
            ? records.map(g=>{
                if(isGsm){
                    return `<tr class="gsm-gateway-row" data-gateway="${escapeHtml(g.id)}">
                <td>${gsmChevron(g.id,g.hostname)}</td>
                <td>${escapeHtml(g.ip_address)}</td>
                <td>${escapeHtml(g.site_code)}</td>
                <td>${escapeHtml(g.channel_count||'—')}</td>
                <td>${escapeHtml(g.device_function||'—')}</td>
                <td>${escapeHtml(g.site_name)}</td>
                <td>${escapeHtml(g.username)}</td>
                <td>${passwordCell(g,canReveal)}</td>
                <td class="actions-column">${actionsCell(g,canEdit,canDelete,canReveal)}</td>
            </tr>${nestedSimTable(g,canEdit,canDelete)}`;
                }
                return `<tr>
                <td>${escapeHtml(g.ip_address)}</td>
                <td>${escapeHtml(g.site_code)}</td>
                <td>${escapeHtml(g.plan||'—')}</td>
                <td>${escapeHtml(g.port||'—')}</td>
                <td>${escapeHtml(g.network||'—')}</td>
                <td>${escapeHtml(g.device_function||'—')}</td>
                <td>${escapeHtml(g.site_name)}</td>
                <td>${escapeHtml(g.username)}</td>
                <td>${passwordCell(g,canReveal)}</td>
                <td class="actions-column">${actionsCell(g,canEdit,canDelete,canReveal)}</td>
            </tr>`;
            }).join('')
            : `<tr><td colspan="${colspan}"><div class="empty-state">No ${entity}s Found</div></td></tr>`;

        if(isGsm)restoreExpanded();

        dom.summary.textContent=`Showing ${data.pagination?.from||0} to ${data.pagination?.to||0} of ${data.pagination?.total||0} entries`;

        const last=Number(data.pagination?.last_page||1);
        const cur=Number(data.pagination?.current_page||1);
        dom.pages.innerHTML='';
        for(let i=1;i<=last;i++){
            dom.pages.insertAdjacentHTML(
                'beforeend',
                i===cur
                    ? `<span class="page-number active">${i}</span>`
                    : `<a href="#" class="page-number" data-page="${i}">${i}</a>`
            );
        }

        qsa('.sortable-button').forEach(button=>{
            const indicator=qs('.sort-indicator',button);
            if(indicator)indicator.textContent=button.dataset.sort===state.sortBy?(state.sortDir==='asc'?'↑':'↓'):'↕';
        });

        updateExportLink();
        bindRowEvents();
        updateUrl();
    }

    let activeLoad=null;
    async function load(page=state.page){
        state.page=Math.max(1,Number(page)||1);
        const p=new URLSearchParams({
            sort_by:state.sortBy,
            sort_dir:state.sortDir,
            per_page:String(state.perPage),
            page:String(state.page)
        });
        if(state.search)p.set('search',state.search);

        if(activeLoad)activeLoad.abort();
        const controller=new AbortController();
        activeLoad=controller;

        try{
            const response=await fetch(base+'?'+p.toString(),{
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
                signal:controller.signal
            });

            const data=await response.json().catch(()=>({}));
            if(!response.ok)throw new Error(data.message||`Unable to load records (${response.status})`);
            render(data);
        }catch(error){
            if(error?.name==='AbortError')return;
            throw error;
        }
    }

    function fillGatewayFields(source){
        ['hostname','site_name','site_code','ip_address','channel_count','plan','port','network','device_function','username','password'].forEach(field=>{
            const element=qs('#'+field);
            if(!element)return;
            const value=source[field]||'';
            if((field==='site_name' || field==='device_function' || field==='network') && element.tagName==='SELECT' && value){
                const exists=[...element.options].some((option)=>option.value===value);
                if(!exists){
                    const option=document.createElement('option');
                    option.value=value;
                    option.textContent=value;
                    element.appendChild(option);
                }
            }
            element.value=value;
        });
    }

    function openAdd(){
        dom.modalTitle.textContent='Add '+entity;
        dom.form.reset();
        dom.gatewayId.value='';
        dom.formMethod.value='POST';
        if(dom.form)dom.form.action=base;
        const password=qs('#password');
        if(password)password.type='password';
        showModal('mediaGatewayModal');
    }

    function openEdit(button){
        const id=button.dataset.editId;
        dom.modalTitle.textContent='Edit '+entity;
        dom.gatewayId.value=id;
        dom.formMethod.value='PUT';
        if(dom.form)dom.form.action=base+'/'+encodeURIComponent(id);
        const values={};
        ['hostname','site_name','site_code','ip_address','channel_count','plan','port','network','device_function','username','password'].forEach(field=>{
            values[field]=button.dataset['edit'+field.charAt(0).toUpperCase()+field.slice(1)]||'';
        });
        fillGatewayFields(values);
        const password=qs('#password');
        if(password)password.type='password';
        showModal('mediaGatewayModal');
    }

    async function save(event){
        event.preventDefault();
        const id=dom.gatewayId.value;
        const endpoint=id?base+'/'+encodeURIComponent(id):base;
        const formData=new FormData(dom.form);

        try{
            const response=await fetch(endpoint,{
                method:'POST',
                headers:{
                    Accept:'application/json',
                    'X-CSRF-TOKEN':csrf(),
                    'X-Requested-With':'XMLHttpRequest'
                },
                body:formData
            });
            const data=await response.json().catch(()=>({}));
            if(!response.ok){
                const validation=Object.values(data.errors||{}).flat().join(' ');
                throw new Error(data.message||validation||`Unable to save record (${response.status})`);
            }
            hideModal('mediaGatewayModal');
            flash(data.message||'Saved successfully.');
            await load(id?state.page:1);
        }catch(error){
            flash(error.message||'Unable to save record.','error');
        }
    }

    async function saveSimAssignment(event){
        event.preventDefault();
        const gatewayId=qs('#gsmSimGatewayId')?.value;
        const assignmentId=qs('#gsmSimAssignmentId')?.value;
        const network=qs('#gsmSimNetwork')?.value||'';
        if(!gatewayId)return;
        if(!network || !simPicked){
            flash('Select a network and a SIM record.','error');
            return;
        }
        const formData=new FormData();
        formData.append('network', network);
        formData.append('sim_type', simPicked.sim_type);
        formData.append('sim_id', String(simPicked.id));
        if(assignmentId)formData.append('_method','PUT');
        const endpoint=assignmentId
            ? base+'/'+encodeURIComponent(gatewayId)+'/assignments/'+encodeURIComponent(assignmentId)
            : base+'/'+encodeURIComponent(gatewayId)+'/assignments';
        try{
            const response=await fetch(endpoint,{
                method:'POST',
                headers:{
                    Accept:'application/json',
                    'X-CSRF-TOKEN':csrf(),
                    'X-Requested-With':'XMLHttpRequest'
                },
                body:formData
            });
            const data=await response.json().catch(()=>({}));
            if(!response.ok){
                const validation=Object.values(data.errors||{}).flat().join(' ');
                throw new Error(data.message||validation||`Unable to save SIM assignment (${response.status})`);
            }
            hideModal('gsmSimModal');
            expandedIds.add(String(gatewayId));
            flash(data.message||'Saved successfully.');
            await load(state.page);
        }catch(error){
            flash(error.message||'Unable to save SIM assignment.','error');
        }
    }

    function openSimAdd(button){
        const gatewayId=button.dataset.gsmSimAdd;
        const channelCount=Number(button.dataset.channelCount||0);
        const assignmentCount=Number(button.dataset.assignmentCount||0);
        if(channelCount>0 && assignmentCount>=channelCount){
            flash('SIM assignments cannot exceed the Channel Count.','error');
            return;
        }
        qs('#gsmSimModalTitle').textContent='Add SIM Assignment';
        qs('#gsmSimGatewayId').value=gatewayId;
        qs('#gsmSimAssignmentId').value='';
        resetSims();
        showModal('gsmSimModal');
    }

    function openSimEdit(button){
        qs('#gsmSimModalTitle').textContent='Edit SIM Assignment';
        qs('#gsmSimGatewayId').value=button.dataset.gatewayId||'';
        qs('#gsmSimAssignmentId').value=button.dataset.assignmentId||'';
        simPicked={
            id:button.dataset.simId,
            sim_type:button.dataset.simType
        };
        simPickedPort=button.dataset.port||'';
        simFilter='';
        const search=qs('#gsmSimSearch');
        if(search)search.value='';
        const network=qs('#gsmSimNetwork');
        if(network){
            const value=button.dataset.network||'';
            if(value && ![...network.options].some(option=>option.value===value)){
                const option=document.createElement('option');
                option.value=value;
                option.textContent=value;
                network.appendChild(option);
            }
            network.value=value;
        }
        loadSims(qs('#gsmSimNetwork')?.value||'');
        showModal('gsmSimModal');
    }

    async function remove(){
        if(simDelete){
            try{
                const response=await fetch(base+'/'+encodeURIComponent(simDelete.gatewayId)+'/assignments/'+encodeURIComponent(simDelete.assignmentId),{
                    method:'DELETE',
                    headers:{
                        Accept:'application/json',
                        'X-CSRF-TOKEN':csrf(),
                        'X-Requested-With':'XMLHttpRequest'
                    }
                });
                const data=await response.json().catch(()=>({}));
                if(!response.ok)throw new Error(data.message||`Unable to delete (${response.status})`);
                hideModal('deleteModal');
                expandedIds.add(String(simDelete.gatewayId));
                simDelete=null;
                flash(data.message||'Deleted successfully.');
                await load(state.page);
            }catch(error){
                flash(error.message||'Unable to delete SIM assignment.','error');
            }
            return;
        }
        if(!deleteId)return;

        try{
            const response=await fetch(base+'/'+encodeURIComponent(deleteId),{
                method:'DELETE',
                headers:{
                    Accept:'application/json',
                    'X-CSRF-TOKEN':csrf(),
                    'X-Requested-With':'XMLHttpRequest'
                }
            });
            const data=await response.json().catch(()=>({}));
            if(!response.ok)throw new Error(data.message||`Unable to delete (${response.status})`);

            hideModal('deleteModal');
            expandedIds.delete(String(deleteId));
            deleteId=null;
            flash(data.message||'Deleted successfully.');

            const totalAfterDelete=Math.max(0,Number(data.total||0));
            if(state.page>1 && totalAfterDelete && ((state.page-1)*state.perPage)>=totalAfterDelete)state.page--;
            await load(state.page);
        }catch(error){
            flash(error.message||'Unable to delete record.','error');
        }
    }

    function bindPasswordToggles(){
        qsa('#mediaGatewayRows .pdc-secret-toggle').forEach(button=>{
            button.onclick=()=>{
                const wrap=button.closest('.pdc-secret');
                const mask=qs('.pdc-secret-mask',wrap);
                const value=qs('.pdc-secret-value',wrap);
                if(!mask||!value)return;
                const showing=!value.hasAttribute('hidden');
                value.toggleAttribute('hidden',showing);
                mask.toggleAttribute('hidden',!showing);
                button.setAttribute('title',showing?'Show password':'Hide password');
                button.setAttribute('aria-label',showing?'Show password':'Hide password');
            };
        });
    }

    function bindRowEvents(){
        qsa('#mediaGatewayRows [data-edit-id]').forEach(button=>button.onclick=()=>{
            closeGsmMenus();
            openEdit(button);
        });
        qsa('#mediaGatewayRows [data-delete-id]').forEach(button=>button.onclick=()=>{
            simDelete=null;
            deleteId=button.dataset.deleteId;
            setDeleteCopy('gateway');
            showModal('deleteModal');
        });
        qsa('#mediaGatewayRows [data-gsm-sim-add]').forEach(button=>button.onclick=event=>{
            event.preventDefault();
            event.stopPropagation();
            openSimAdd(button);
        });
        qsa('#mediaGatewayRows [data-gsm-sim-edit]').forEach(button=>button.onclick=event=>{
            event.preventDefault();
            event.stopPropagation();
            openSimEdit(button);
        });
        qsa('#mediaGatewayRows [data-gsm-sim-delete]').forEach(button=>button.onclick=event=>{
            event.preventDefault();
            event.stopPropagation();
            deleteId=null;
            simDelete={
                gatewayId:button.dataset.gatewayId,
                assignmentId:button.dataset.assignmentId
            };
            setDeleteCopy('sim');
            showModal('deleteModal');
        });
        qsa('#paginationLinks [data-page]').forEach(button=>button.onclick=event=>{
            event.preventDefault();
            load(Number(button.dataset.page));
        });
        bindPasswordToggles();
    }

    qs('[data-open-modal="add-media-gateway"]')?.addEventListener('click',openAdd);
    qs('#mediaGatewayForm .pdc-secret-toggle[data-toggle-input="password"]')?.addEventListener('click',()=>{
        const input=qs('#password');
        if(!input)return;
        input.type=input.type==='password'?'text':'password';
    });
    dom.form?.addEventListener('submit',save);
    qs('#gsmSimForm')?.addEventListener('submit',saveSimAssignment);
    dom.deleteConfirm?.addEventListener('click',remove);

    qs('#gsmSimNetwork')?.addEventListener('change',()=>{
        simPicked=null;
        simPickedPort='';
        loadSims(qs('#gsmSimNetwork').value);
    });
    qs('#gsmSimSearch')?.addEventListener('input',()=>{
        simFilter=qs('#gsmSimSearch').value||'';
        renderSimList();
    });
    qs('#gsmSimRows')?.addEventListener('click',event=>{
        const row=event.target.closest('.gsm-sim-row');
        if(!row)return;
        const sim=simCatalog.find(item=>String(item.id)===String(row.dataset.simId) && item.sim_type===row.dataset.simType);
        if(sim)pickSim(sim);
    });
    if(isGsm && !document.body.dataset.gsmMenuBound){
        document.body.dataset.gsmMenuBound='1';
        document.addEventListener('click',event=>{
            if(!(event.target instanceof Element))return;
            const toggle=event.target.closest('#mediaGatewayRows [data-ca-toggle]');
            if(toggle){
                const id=toggle.getAttribute('data-ca-toggle');
                const panel=document.getElementById('gsm-panel-'+id);
                const row=document.querySelector('tr.gsm-gateway-row[data-gateway="'+id+'"]');
                if(!panel)return;
                const open=panel.hasAttribute('hidden');
                panel.toggleAttribute('hidden', !open);
                row?.classList.toggle('open', open);
                row?.querySelectorAll('[data-ca-toggle]').forEach(el=>el.setAttribute('aria-expanded', open?'true':'false'));
                if(open)expandedIds.add(String(id));
                else expandedIds.delete(String(id));
                return;
            }
            const gatewayRow=event.target.closest('tr.gsm-gateway-row[data-gateway]');
            if(gatewayRow && !event.target.closest('.actions-column, .ca-menu, a, input, select, textarea, label, .action-btn, .plus-btn, .pdc-secret-toggle, button')){
                gatewayRow.querySelector('[data-ca-toggle]')?.click();
            }
            const button=event.target.closest('#mediaGatewayRows .ca-menu-btn');
            if(button){
                event.preventDefault();
                event.stopPropagation();
                const menu=button.closest('.ca-menu');
                const dropdown=menu?.querySelector('.ca-menu-dropdown');
                const willOpen=!menu.classList.contains('open');
                closeGsmMenus(willOpen?menu:null);
                if(!menu||!dropdown)return;
                menu.classList.toggle('open',willOpen);
                dropdown.toggleAttribute('hidden',!willOpen);
                button.setAttribute('aria-expanded',willOpen?'true':'false');
                return;
            }
            if(!event.target.closest('#mediaGatewayRows .ca-menu'))closeGsmMenus();
        });
        document.addEventListener('keydown',event=>{
            if(event.key==='Escape')closeGsmMenus();
        });
    }

    dom.perPage?.addEventListener('change',()=>{
        state.perPage=Number(dom.perPage.value);
        state.page=1;
        load(1).catch(error=>flash(error.message,'error'));
    });

    qsa('.sortable-button').forEach(button=>button.addEventListener('click',()=>{
        const sort=button.dataset.sort;
        if(state.sortBy===sort)state.sortDir=state.sortDir==='asc'?'desc':'asc';
        else{state.sortBy=sort;state.sortDir='asc';}
        state.page=1;
        load(1).catch(error=>flash(error.message,'error'));
    }));

    let timer=null;
    qs('#mediaSearchForm')?.addEventListener('submit',event=>{
        event.preventDefault();
        clearTimeout(timer);
        state.search=dom.search?.value.trim()||'';
        state.page=1;
        load(1).catch(error=>flash(error.message,'error'));
    });
    dom.search?.addEventListener('input',()=>{
        clearTimeout(timer);
        state.search=dom.search.value.trim();
        state.page=1;
        timer=setTimeout(()=>load(1).catch(error=>flash(error.message,'error')),180);
    });

    bindRowEvents();
    updateExportLink();
}

const DASHBOARD_POLL_MS=20000;
function initDashboard(){
    const root=qs('#dashboardHome');
    if(!root)return;
    const url=root.dataset.snapshotUrl;
    if(!url)return;
    let fingerprint=root.dataset.fingerprint||'';
    let timer=null;
    let inFlight=false;
    let utilRows=[];
    let utilSearch='';
    let utilPage=1;
    let utilPerPage=10;
    const dataNode=qs('#dashOverviewData',root);
    if(dataNode){
        try{utilRows=JSON.parse(dataNode.textContent||'{}').utilization_rows||[];}catch(_error){utilRows=[];}
    }
    const formatNumber=(value)=>Number(value||0).toLocaleString('en-US');
    const renderUtilTable=()=>{
        const panel=qs('[data-dash-panel="utilization"]',root);
        if(!panel)return;
        const query=utilSearch.trim().toLowerCase();
        const filtered=query?utilRows.filter(row=>String(row.name||'').toLowerCase().includes(query)):utilRows;
        const totalCount=filtered.length;
        if(![5,10,25,50].includes(utilPerPage))utilPerPage=10;
        const lastPage=Math.max(1,Math.ceil((totalCount||1)/utilPerPage));
        utilPage=Math.max(1,Math.min(utilPage,lastPage));
        const slice=filtered.slice((utilPage-1)*utilPerPage,utilPage*utilPerPage);
        const from=totalCount===0?0:((utilPage-1)*utilPerPage)+1;
        const to=Math.min(utilPage*utilPerPage,totalCount);
        const sum=(key)=>filtered.reduce((n,row)=>n+(Number(row[key])||0),0);
        let body='';
        if(!slice.length){
            body='<tr><td colspan="4"><div class="empty-state">No campaigns match this search.</div></td></tr>';
        }else{
            body=slice.map(row=>`<tr><td>${escapeHtml(row.name)}</td><td class="num">${escapeHtml(row.total_display||formatNumber(row.total))}</td><td class="num">${escapeHtml(row.sip_display||formatNumber(row.sip))}</td><td class="num">${escapeHtml(row.gsm_display||formatNumber(row.gsm))}</td></tr>`).join('');
            body+=`<tr class="dash-total-row"><td>Total</td><td class="num">${formatNumber(sum('total'))}</td><td class="num">${formatNumber(sum('sip'))}</td><td class="num">${formatNumber(sum('gsm'))}</td></tr>`;
        }
        let pages='';
        for(let i=1;i<=lastPage;i++){
            pages+=`<button type="button" class="page-number${i===utilPage?' active':''}" data-dash-page="${i}">${i}</button>`;
        }
        const options=[5,10,25,50].map(size=>`<option value="${size}"${size===utilPerPage?' selected':''}>${size}</option>`).join('');
        panel.innerHTML=`<div class="table-card table-wrap dash-util-table"><table><thead><tr><th>Campaign</th><th class="num">Total Channels</th><th class="num">SIP</th><th class="num">GSM</th></tr></thead><tbody>${body}</tbody></table><div class="table-footer"><span>Showing ${from} to ${to} of ${totalCount} entries</span><div class="footer-right"><span>Records per page:</span><select class="per-page-select" data-dash-per-page aria-label="Records per page">${options}</select><div class="pager">${pages}</div></div></div></div>`;
    };
    const apply=(data)=>{
        if(!data)return;
        const updatedEl=qs('[data-dash-updated]',root);
        if(updatedEl&&data.generated_at_label)updatedEl.textContent=data.generated_at_label;
        if(data.fingerprint===fingerprint)return;
        fingerprint=data.fingerprint||fingerprint;
        root.dataset.fingerprint=fingerprint;
        const kpis=data.kpis||{};
        Object.keys(kpis).forEach(key=>{
            qsa(`[data-dash-kpi="${key}"]`,root).forEach(el=>{
                el.textContent=kpis[key]?.display ?? kpis[key]?.value ?? el.textContent;
            });
        });
        const insight=qs('[data-dash-insight]',root);
        if(insight&&data.campaign_insight)insight.textContent=data.campaign_insight;
        const html=data.html||{};
        ['bars','trend'].forEach(key=>{
            const panel=qs(`[data-dash-panel="${key}"]`,root);
            if(panel&&typeof html[key]==='string')panel.innerHTML=html[key];
        });
        if(Array.isArray(data.utilization_rows)){
            utilRows=data.utilization_rows;
            renderUtilTable();
        }
        const globeCount=qs('.sim-pick-globe .sim-pick-count');
        const smartCount=qs('.sim-pick-smart .sim-pick-count');
        if(globeCount&&kpis.globe)globeCount.textContent=kpis.globe.display ?? kpis.globe.value;
        if(smartCount&&kpis.smart)smartCount.textContent=kpis.smart.display ?? kpis.smart.value;
    };
    const poll=async()=>{
        if(inFlight||document.visibilityState==='hidden')return;
        inFlight=true;
        try{
            const response=await fetch(url,{
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
                cache:'no-store'
            });
            if(!response.ok)return;
            apply(await response.json());
        }catch(_error){
        }finally{
            inFlight=false;
        }
    };
    const schedule=()=>{
        window.clearTimeout(timer);
        timer=window.setTimeout(async()=>{
            await poll();
            schedule();
        },DASHBOARD_POLL_MS);
    };
    root.addEventListener('input',event=>{
        const input=event.target.closest('[data-dash-util-search]');
        if(!input)return;
        utilSearch=input.value||'';
        utilPage=1;
        renderUtilTable();
    });
    root.addEventListener('change',event=>{
        const select=event.target.closest('[data-dash-per-page]');
        if(!select)return;
        utilPerPage=Number(select.value)||10;
        utilPage=1;
        renderUtilTable();
    });
    root.addEventListener('click',event=>{
        const pageBtn=event.target.closest('[data-dash-page]');
        if(pageBtn){
            event.preventDefault();
            utilPage=Number(pageBtn.dataset.dashPage)||1;
            renderUtilTable();
            return;
        }
        if(event.target.closest('[data-dash-refresh]')){
            event.preventDefault();
            poll().finally(schedule);
        }
    });
    document.addEventListener('visibilitychange',()=>{
        if(document.visibilityState==='visible'){
            poll().finally(schedule);
        }else{
            window.clearTimeout(timer);
        }
    });
    schedule();
}

async function init(){
    initGlobal();
    initDashboard();
    await initMedia();
}

document.addEventListener('DOMContentLoaded',init);
window.addEventListener('pageshow',event=>{
    if(!event.persisted)return;
    qsa('.modal-backdrop.visible').forEach(el=>el.classList.remove('visible'));
    qs('#accountMenu')?.classList.remove('open');
});
