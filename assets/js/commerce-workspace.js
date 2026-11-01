'use strict';
(() => {
  // Dialogs enhance ordinary links/details; all server routes still work without JS.
  const page = document.querySelector('.commerce-page:last-of-type');
  if (!page) return;
  const openDrawer = (content, title, trigger) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'commerce-page commerce-drawer';
    dialog.setAttribute('aria-label', title);
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'commerce-drawer-close'; close.textContent = 'Close';
    close.addEventListener('click', () => dialog.close());
    const body = document.createElement('div'); body.className = 'commerce-drawer-body'; body.append(content);
    dialog.append(close, body); document.body.append(dialog);
    dialog.addEventListener('close', () => { dialog.remove(); trigger?.focus(); });
    dialog.showModal(); close.focus(); return dialog;
  };
  document.querySelectorAll('[data-order-link]').forEach(link => {
    link.addEventListener('click', async event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault(); link.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(link.href, {credentials:'same-origin'});
        if (!response.ok) throw Error();
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const detail = doc.querySelector('[data-order-detail]');
        if (!detail) throw Error();
        // Submitting a drawer form uses the original detail URL, preserving CSRF and redirects.
        detail.querySelectorAll('form').forEach(form => form.setAttribute('action', link.href));
        openDrawer(detail, 'Order management', link);
      } catch { window.location.assign(link.href); }
      finally { link.removeAttribute('aria-busy'); }
    });
  });
  document.querySelectorAll('.commerce-venue-create').forEach(details => {
    const summary = details.querySelector('summary');
    const button = document.createElement('button'); button.type = 'button'; button.textContent = summary.textContent;
    details.before(button); details.hidden = true;
    button.addEventListener('click', () => {
      const content = document.createElement('div');
      const heading = document.createElement('h2'); heading.textContent = summary.textContent; content.append(heading);
      const fields = details.querySelector('.commerce-columns'); content.append(fields);
      const drawer = openDrawer(content, summary.textContent, button);
      drawer.addEventListener('close', () => details.append(fields));
    });
  });
  const scan = document.querySelector('[data-scan]');
  if (scan && !document.querySelector('[data-commerce-error]')) scan.focus();
  // Keep scope controls relevant without changing the posted allocation contract.
  const scope = document.querySelector('select[name=scope]');
  if (scope) {
    const selected = scope.closest('form').querySelector('fieldset');
    const range = scope.closest('form').querySelector('[name=from]')?.closest('.commerce-columns');
    const update = () => { selected.hidden = scope.value !== 'selected'; if (range) range.hidden = scope.value !== 'range'; };
    scope.addEventListener('change', update); update();
  }
})();

(() => {
  const ns = 'http://www.w3.org/2000/svg';
  const make = (tag, attrs, text) => {
    const node = document.createElementNS(ns, tag);
    Object.entries(attrs).forEach(([key,value]) => node.setAttribute(key, value));
    if (text !== undefined) node.textContent = text;
    return node;
  };
  document.querySelectorAll('[data-allocation-map]').forEach(root => {
    const doc = JSON.parse(root.querySelector('[data-allocation-document]').textContent);
    const svg = root.querySelector('svg');
    const checks = [...root.closest('form').querySelectorAll('input[name="seats[]"]')];
    let view = window.CommerceGeometry.bounds(doc.items);
    const render = () => {
      svg.replaceChildren(); svg.setAttribute('viewBox', view.join(' '));
      for (const item of doc.items) {
        const check = checks.find(input => input.value === item.key);
        const group = make('g', {transform:`translate(${item.x},${item.y})`});
        if (item.kind === 'seat' && check) {
          const label = check.closest('label').textContent.trim();
          const title = `${label}${check.checked?', selected':''}`;
          group.setAttribute('role','button'); group.setAttribute('tabindex',check.disabled?'-1':'0');
          group.setAttribute('aria-label',title); group.setAttribute('aria-pressed',String(check.checked));
          group.setAttribute('aria-disabled',String(check.disabled));group.dataset.allocationSeat=item.key;
          group.append(make('title',{},title),make('circle',{cx:20,cy:20,r:18,fill:check.disabled?'#d9d3cf':check.checked?'#702a3f':'#f1e3cd',stroke:'#8c787b'}),make('text',{x:20,y:25,'text-anchor':'middle',fill:check.checked?'white':'#30282a'},check.checked?'✓':item.label));
        } else if(item.kind !== 'seat') {
          const size = window.CommerceGeometry.dimensions(item);
          group.append(make('rect',{width:size.width,height:size.height,fill:'#e2d9d6',rx:4}),make('text',{x:size.width/2,y:size.height/2+5,'text-anchor':'middle'},item.label));
        }
        svg.append(group);
      }
      root.querySelector('[data-allocation-count]').textContent = `${checks.filter(input=>input.checked).length} positions selected`;
    };
    const toggle = group => {
      const input=checks.find(check=>check.value===group.dataset.allocationSeat);
      if(!input || input.disabled) return;
      input.checked=!input.checked; input.dispatchEvent(new Event('change',{bubbles:true}));
      svg.querySelectorAll('[data-allocation-seat]').forEach(node=>{if(node.dataset.allocationSeat===input.value)node.focus();});
    };
    svg.addEventListener('click',event=>{const group=event.target.closest('[data-allocation-seat]');if(group)toggle(group);});
    svg.addEventListener('keydown',event=>{const group=event.target.closest('[data-allocation-seat]');if(group&&['Enter',' '].includes(event.key)){event.preventDefault();toggle(group);}});
    checks.forEach(input=>input.addEventListener('change',render));
    root.querySelectorAll('[data-allocation-zoom]').forEach(button=>button.addEventListener('click',()=>{const scale=button.dataset.allocationZoom==='in'?.8:1.25;view[2]*=scale;view[3]*=scale;render();}));
    root.querySelector('[data-allocation-fit]').addEventListener('click',()=>{view=window.CommerceGeometry.bounds(doc.items);render();});
    render();
  });
})();

(() => {
  // Keep the existing cents-based service payload, but edit familiar money fields.
  document.querySelectorAll('textarea[name=prices]').forEach(source => {
    let prices;
    try { prices=JSON.parse(source.value); } catch { return; }
    if (!prices || Array.isArray(prices) || typeof prices!=='object') return;
    const entries=Object.entries(prices);
    if(!entries.length || entries.some(([,categories])=>!categories||typeof categories!=='object'||Object.values(categories).some(value=>!Number.isInteger(value))))return;
    const fieldset=document.createElement('fieldset'),legend=document.createElement('legend');legend.textContent='Ticket prices';fieldset.append(legend);
    const inputs=[];
    for(const [zone,categories] of entries){
      const group=document.createElement('div');group.className='commerce-quantity-grid';
      for(const [category,minor] of Object.entries(categories)){
        const label=document.createElement('label');label.textContent=`${zone} / ${category} · amount`;
        const input=document.createElement('input');input.type='number';input.min='0';input.max='10000';input.step='.01';input.required=true;input.value=(minor/100).toFixed(2);
        label.append(input);group.append(label);inputs.push({input,zone,category});
      }fieldset.append(group);
    }
    const advanced=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Advanced: zones & ticket categories (JSON cents)';advanced.append(summary);
    const label=source.closest('label');label.before(fieldset,advanced);advanced.append(label);
    let advancedEdited=false;
    const sync = () => { for(const {input,zone,category} of inputs){if(input.validity.valid&&input.value!=='')prices[zone][category]=Math.round(Number(input.value)*100);}source.value=JSON.stringify(prices,null,2); };
    inputs.forEach(({input})=>input.addEventListener('input',()=>{advancedEdited=false;sync();}));
    source.addEventListener('input',()=>{advancedEdited=true;fieldset.hidden=true;inputs.forEach(({input})=>input.required=false);});
    source.closest('form').addEventListener('submit',()=>{if(!advancedEdited)sync();});
  });
})();

(() => {
  document.querySelectorAll('.commerce-venue-setting form').forEach(form => {
    const host=form.parentElement, name=host.querySelector('strong').textContent;
    const disclosure=document.createElement('details'),summary=document.createElement('summary');
    summary.textContent='Edit timezone';disclosure.append(summary);form.before(disclosure);disclosure.append(form);
    disclosure.setAttribute('aria-label',`Timezone for ${name}`);
  });
  const tender=document.querySelector('select[name=method]');
  if(tender){
    const form=tender.closest('form'),cash=form.querySelector('[name=cash_amount]')?.closest('label'),provider=form.querySelector('[name=provider]')?.closest('label'),reference=form.querySelector('[name=terminal_reference]')?.closest('label'),change=form.querySelector('[data-change-due]');
    const update=()=>{const external=tender.value==='external_terminal';if(cash)cash.hidden=external;if(change)change.hidden=external;if(provider)provider.hidden=!external;if(reference)reference.hidden=!external;};
    tender.addEventListener('change',update);update();
  }
})();

(() => {
  const selector=document.querySelector('[data-seat-selector]');
  if(!selector)return;
  const listHeading=selector.querySelector('#commerce-seat-list-heading');
  if(!listHeading)return;
  const mapPane=document.createElement('div'),purchasePane=document.createElement('div');
  mapPane.className='commerce-selection-map';purchasePane.className='commerce-selection-purchase';
  let purchase=false;
  for(const child of [...selector.children]){if(child===listHeading)purchase=true;(purchase?purchasePane:mapPane).append(child);}
  selector.append(mapPane,purchasePane);selector.classList.add('commerce-sale-grid');
  const toolbar=mapPane.querySelector('.commerce-map-toolbar');
  if(toolbar){
    const details=document.createElement('details'),summary=document.createElement('summary'),tools=document.createElement('div');
    summary.textContent='Pan controls';tools.className='commerce-actions';details.append(summary,tools);
    toolbar.querySelectorAll('[data-select-pan]').forEach(button=>tools.append(button));toolbar.after(details);
  }
})();

(() => {
  const local=document.querySelector('.commerce-workspace-nav');
  const current=local?.querySelector('.commerce-nav-group nav');
  if(current){
    const tools=document.createElement('details'),summary=document.createElement('summary');
    tools.className='commerce-mobile-tools';summary.textContent=current.getAttribute('aria-label');
    const links=current.cloneNode(true);tools.append(summary,links);local.append(tools);
  }
  const selector=document.querySelector('[data-seat-selector]');
  if(selector){
    const form=selector.closest('form'),terms=form.previousElementSibling?.previousElementSibling;
    const pricing=form.previousElementSibling;
    const purchase=selector.querySelector('.commerce-selection-purchase');
    if(pricing?.tagName==='DETAILS'&&pricing.querySelector('summary')?.textContent==='Prices by zone')purchase.prepend(pricing);
    if(terms?.classList.contains('commerce-pricing-terms'))purchase.prepend(terms);
  }
})();
