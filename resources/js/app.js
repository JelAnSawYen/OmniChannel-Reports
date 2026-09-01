const qs=(s,r=document)=>r.querySelector(s), qsa=(s,r=document)=>[...r.querySelectorAll(s)];
const csrf=()=>qs('meta[name="csrf-token"]')?.content||'';
const showModal=(id)=>qs('#'+id)?.classList.add('visible');
const hideModal=(id)=>qs('#'+id)?.classList.remove('visible');

function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}

function flash(message,type='success'){
    let el=qs('.flash');
    if(!el){
        el=document.createElement('div');
        el.className='flash';
        qs('.page-container')?.prepend(el);
    }
    el.className='flash '+type;
    el.textContent=message;
    window.setTimeout(()=>el.remove(),2000);
}

function initGlobal(){
    if(document.body.dataset.uiBound==='1')return;
    document.body.dataset.uiBound='1';
    const body=document.body;

    qsa('.flash').forEach(el=>{
        if(el.id==='importUploadError'||el.classList.contains('import-upload-error'))return;
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
        qs('#notificationMenu')?.classList.remove('open');
    });

    const userManagementGroup=qs('#accountUserManagement');
    const userManagementToggle=qs('#accountUserManagementToggle');
    userManagementToggle?.addEventListener('click',e=>{
        e.stopPropagation();
        userManagementGroup?.classList.toggle('open');
        userManagementToggle.setAttribute('aria-expanded',userManagementGroup?.classList.contains('open')?'true':'false');
    });

    qs('#notificationButton')?.addEventListener('click',e=>{
        e.stopPropagation();
        qs('#notificationMenu')?.classList.toggle('open');
        qs('#accountMenu')?.classList.remove('open');
    });

    document.addEventListener('click',e=>{
        if(!e.target.closest('.account-wrap'))qs('#accountMenu')?.classList.remove('open');
        if(!e.target.closest('.notification-wrap'))qs('#notificationMenu')?.classList.remove('open');
    });

    const locationGroup=qs('#programLocationGroup');
    const locationToggle=qs('#programLocationToggle');
    const locationNav=qs('#sidebarNav');
    const locationScrollKey='omnichannel.sidebar.navScroll';
    if(locationNav){
        const saved=Number(sessionStorage.getItem(locationScrollKey)||0);
        if(saved>0)locationNav.scrollTop=saved;
        locationNav.addEventListener('scroll',()=>{
            sessionStorage.setItem(locationScrollKey,String(locationNav.scrollTop));
        },{passive:true});
    }
    locationToggle?.addEventListener('click',e=>{
        e.preventDefault();
        e.stopPropagation();
        locationGroup?.classList.toggle('open');
        const open=locationGroup?.classList.contains('open');
        locationToggle.setAttribute('aria-expanded',open?'true':'false');
    });

    qs('#logoutButton')?.addEventListener('click',()=>{
        qs('#accountMenu')?.classList.remove('open');
        showModal('logoutModal');
    });

    qsa('[data-close]').forEach(b=>b.addEventListener('click',()=>hideModal(b.dataset.close)));
    qsa('[data-open]').forEach(el=>el.addEventListener('click',e=>{
        e.preventDefault();
        showModal(el.dataset.open);
    }));
    qsa('.modal-backdrop').forEach(m=>m.addEventListener('click',e=>{
        if(e.target===m)m.classList.remove('visible');
    }));

    qsa('[data-clear-search]').forEach(button=>{
        button.addEventListener('click',()=>{
            const form=button.closest('form');
            const input=form?.querySelector('input[name="search"]');
            if(input){
                input.value='';
                form?.submit();
            }
        });
    });
    qsa('form[data-live-search]').forEach(form=>{
        const input=form.querySelector('input[name="search"]');
        if(!input)return;
        let timer;
        input.addEventListener('input',()=>{
            clearTimeout(timer);
            timer=setTimeout(()=>form.submit(),300);
        });
    });
    qsa('form[data-select-user]').forEach(form=>{
        form.querySelectorAll('input[type="radio"][name="user"]').forEach(radio=>{
            radio.addEventListener('change',()=>form.submit());
        });
        form.querySelectorAll('tr').forEach(row=>{
            row.addEventListener('click',event=>{
                if(event.target.closest('input,a,button'))return;
                const radio=row.querySelector('input[type="radio"][name="user"]');
                if(!radio||radio.checked)return;
                radio.checked=true;
                form.submit();
            });
        });
    });

    initConfirm();
    initNotifications();
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

function initNotifications(){
    const menu=qs('#notificationMenu');
    if(!menu||menu.dataset.bound==='1')return;
    menu.dataset.bound='1';

    async function post(url, method){
        const response=await fetch(url,{
            method,
            headers:{
                Accept:'application/json',
                'X-CSRF-TOKEN':csrf(),
                'X-Requested-With':'XMLHttpRequest',
                'X-HTTP-METHOD-OVERRIDE': method==='DELETE'?'DELETE':''
            }
        });
        if(!response.ok)throw new Error('Unable to update notification');
    }

    function markRow(row){
        if(!row)return;
        row.classList.remove('unread');
        const mark=qs('[data-notification-mark]',row);
        if(mark)mark.hidden=true;
    }

    function refreshEmpty(){
        const list=qs('#notificationList');
        if(!list)return;
        const items=qsa('.notification-item',list);
        if(!items.length){
            list.innerHTML='<div class="notification-empty">No operational alerts right now.</div>';
            qs('.notification-footer')?.remove();
            qs('.notification-readall-form')?.remove();
            qs('#notificationBadge')?.remove();
        }
        const unread=qsa('.notification-item.unread',list).length;
        const badge=qs('#notificationBadge');
        if(badge){
            if(!unread)badge.remove();
            else badge.textContent=String(unread);
        }
        const pill=qs('#notificationCountPill');
        if(pill){
            pill.textContent=unread+' New';
            pill.hidden=!unread;
        }
    }

    qsa('[data-notification-read]').forEach(link=>{
        link.addEventListener('click',()=>{
            const id=link.dataset.notificationRead;
            const row=link.closest('.notification-item');
            markRow(row);
            const pill=qs('.status-pill',row||document);
            if(pill)pill.textContent='Read';
            post('/notifications/'+encodeURIComponent(id)+'/read','POST').catch(()=>{});
            refreshEmpty();
        });
    });

    qsa('[data-notification-mark]').forEach(button=>{
        button.addEventListener('click',async event=>{
            event.preventDefault();
            event.stopPropagation();
            const id=button.dataset.notificationMark;
            try{
                await post('/notifications/'+encodeURIComponent(id)+'/read','POST');
                markRow(button.closest('.notification-item'));
                refreshEmpty();
            }catch(error){
                flash(error.message||'Unable to mark the notification as read.','error');
            }
        });
    });

    qs('#markAllNotificationsForm')?.addEventListener('submit',async event=>{
        event.preventDefault();
        try{
            await post('/notifications/read-all','POST');
            qsa('.notification-item',qs('#notificationList')).forEach(markRow);
            refreshEmpty();
        }catch(error){
            flash(error.message||'Unable to mark notifications as read.','error');
        }
    });

    qsa('[data-notification-dismiss]').forEach(button=>{
        button.addEventListener('click',async event=>{
            event.preventDefault();
            event.stopPropagation();
            const id=button.dataset.notificationDismiss;
            try{
                await fetch('/notifications/'+encodeURIComponent(id),{
                    method:'DELETE',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'}
                });
                button.closest('.notification-item')?.remove();
                refreshEmpty();
            }catch(error){
                flash(error.message||'Unable to remove notification.','error');
            }
        });
    });

}

async function initMedia(){
    if(!['media-gateways','gsm-gateways'].includes(document.body.dataset.page) && !qs('#mediaGatewayForm'))return;
    if(qs('#mediaGatewayForm')?.dataset.bound==='1')return;
    if(qs('#mediaGatewayForm'))qs('#mediaGatewayForm').dataset.bound='1';

    const base=window.location.origin+(document.body.dataset.resourceBase||'/media-gateways');
    const entity=document.body.dataset.page==='gsm-gateways'?'GSM Gateway':'Media Gateway';
    const initial=new URLSearchParams(location.search);
    let state={
        search:initial.get('search')||'',
        sortBy:initial.get('sort_by')||'id',
        sortDir:initial.get('sort_dir')||'asc',
        page:Number(initial.get('page')||1),
        perPage:Number(initial.get('per_page')||10)
    };
    let deleteId=null;

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
        search:qs('#mediaSearchInput'),
        searchClear:qs('#mediaSearchClear')
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

    function updateSearchClear(){
        if(!dom.searchClear)return;
        dom.searchClear.style.display=state.search?'inline-flex':'none';
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

    function render(data){
        const records=data.records||[];
        const canEdit=document.body.dataset.canEdit==='1';
        const canDelete=document.body.dataset.canDelete==='1';

        dom.rows.innerHTML=records.length
            ? records.map(g=>`<tr>
                <td>${escapeHtml(g.display_id ?? g.id)}</td>
                <td>${escapeHtml(g.site_name)}</td>
                <td>${escapeHtml(g.site_code)}</td>
                <td>${escapeHtml(g.ip_address)}</td>
                <td>${escapeHtml(g.username)}</td>
                <td>${escapeHtml(g.database)}</td>
                <td>${escapeHtml(g.last_updated||'—')}</td>
                <td><div class="row-actions">
                    ${canEdit?`<button type="button" class="action-btn edit" data-edit-id="${escapeHtml(g.id)}" data-edit-site_name="${escapeHtml(g.site_name)}" data-edit-site_code="${escapeHtml(g.site_code)}" data-edit-ip_address="${escapeHtml(g.ip_address)}" data-edit-username="${escapeHtml(g.username)}" data-edit-database="${escapeHtml(g.database)}" title="Edit ${entity}" aria-label="Edit ${entity}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                    </button>`:''}
                    ${canDelete?`<button type="button" class="action-btn delete" data-delete-id="${escapeHtml(g.id)}" title="Delete ${entity}" aria-label="Delete ${entity}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>`:''}
                </div></td>
            </tr>`).join('')
            : `<tr><td colspan="8"><div class="empty-state">No ${entity}s Found</div></td></tr>`;

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
        updateSearchClear();
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

    function openAdd(){
        dom.modalTitle.textContent='Add '+entity;
        dom.form.reset();
        dom.gatewayId.value='';
        dom.formMethod.value='POST';
        if(dom.form)dom.form.action=base;
        showModal('mediaGatewayModal');
    }

    function openEdit(button){
        const id=button.dataset.editId;
        dom.modalTitle.textContent='Edit '+entity;
        dom.gatewayId.value=id;
        dom.formMethod.value='PUT';
        if(dom.form)dom.form.action=base+'/'+encodeURIComponent(id);
        ['site_name','site_code','ip_address','username','database'].forEach(field=>{
            const element=qs('#'+field);
            if(element)element.value=button.dataset['edit'+field.charAt(0).toUpperCase()+field.slice(1)]||'';
        });
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

    async function remove(){
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
            deleteId=null;
            flash(data.message||'Deleted successfully.');

            const totalAfterDelete=Math.max(0,Number(data.total||0));
            if(state.page>1 && totalAfterDelete && ((state.page-1)*state.perPage)>=totalAfterDelete)state.page--;
            await load(state.page);
        }catch(error){
            flash(error.message||'Unable to delete record.','error');
        }
    }

    function bindRowEvents(){
        qsa('#mediaGatewayRows [data-edit-id]').forEach(button=>button.onclick=()=>openEdit(button));
        qsa('#mediaGatewayRows [data-delete-id]').forEach(button=>button.onclick=()=>{
            deleteId=button.dataset.deleteId;
            showModal('deleteModal');
        });
        qsa('#paginationLinks [data-page]').forEach(button=>button.onclick=event=>{
            event.preventDefault();
            load(Number(button.dataset.page));
        });
    }

    qs('[data-open-modal="add-media-gateway"]')?.addEventListener('click',openAdd);
    dom.form?.addEventListener('submit',save);
    dom.deleteConfirm?.addEventListener('click',remove);

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
    dom.search?.addEventListener('input',()=>{
        clearTimeout(timer);
        state.search=dom.search.value.trim();
        state.page=1;
        updateSearchClear();
        timer=setTimeout(()=>load(1).catch(error=>flash(error.message,'error')),300);
    });

    dom.searchClear?.addEventListener('click',()=>{
        clearTimeout(timer);
        state.search='';
        if(dom.search)dom.search.value='';
        state.page=1;
        load(1).catch(error=>flash(error.message,'error'));
    });

    bindRowEvents();
    updateSearchClear();
    updateExportLink();
}

async function init(){
    initGlobal();
    await initMedia();
}

document.addEventListener('DOMContentLoaded',init);
window.addEventListener('pageshow',event=>{
    if(!event.persisted)return;
    qsa('.modal-backdrop.visible').forEach(el=>el.classList.remove('visible'));
    qs('#accountMenu')?.classList.remove('open');
    qs('#notificationMenu')?.classList.remove('open');
});
