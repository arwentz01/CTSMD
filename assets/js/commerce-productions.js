'use strict';
(() => {
  const setZones=(select,zones)=>{const value=select.value;select.replaceChildren(new Option('Choose zone',''),...zones.map(zone=>new Option(zone,zone)));select.value=zones.includes(value)?value:'';};
  const initialize = root => {
    root.querySelectorAll('[data-price-editor]').forEach(editor => {
      if(editor.dataset.initialized)return;editor.dataset.initialized='1';
      const rows=editor.querySelector('[data-price-rows]');let index=rows.children.length;
      const template=rows.firstElementChild?.cloneNode(true);
      editor.querySelector('[data-add-price]').addEventListener('click',()=>{
        if(!template)return;const row=template.cloneNode(true);
        row.querySelectorAll('input,select').forEach(input=>{const previous=input.value;input.name=input.name.replace(/price_rows\[\d+\]/,`price_rows[${index}]`);input.value=input.tagName==='SELECT'?previous:input.name.endsWith('[amount]')?'0.00':'';});index++;rows.append(row);const layout=editor.closest('form').querySelector('[data-schedule-layout]');if(layout)row.querySelectorAll('[data-price-zone]').forEach(select=>setZones(select,(layout.selectedOptions[0]?.dataset.zones||'').split(',').map(zone=>zone.trim()).filter(Boolean)));row.querySelector('input,select').focus();
      });
      rows.addEventListener('click',event=>{const button=event.target.closest('[data-remove-price]');if(!button)return;if(rows.children.length>1){const next=button.closest('[data-price-row]').nextElementSibling||button.closest('[data-price-row]').previousElementSibling;button.closest('[data-price-row]').remove();next.querySelector('input,select').focus();}});
    });
    root.querySelectorAll('[data-compact-showtimes]').forEach(rows=>{
      if(rows.dataset.initialized)return;rows.dataset.initialized='1';const template=rows.firstElementChild.cloneNode(true);
      rows.closest('fieldset').querySelector('[data-add-compact-showtime]').addEventListener('click',()=>{const row=template.cloneNode(true),previousDate=rows.lastElementChild.querySelector('[name="dates[]"]').value;const occurrence=row.querySelector('select[name="offsets[]"]');if(occurrence){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='offsets[]';occurrence.closest('label').replaceWith(hidden);}row.querySelectorAll('input').forEach(input=>input.value=input.name==='dates[]'?previousDate:'');rows.append(row);row.querySelector('[name="times[]"]').focus();});
      rows.addEventListener('input',event=>{if(!['dates[]','times[]'].includes(event.target.name))return;const row=event.target.closest('[data-compact-showtime]'),choice=row.querySelector('select[name="offsets[]"]');if(choice){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='offsets[]';choice.closest('label').replaceWith(hidden);}const offset=row.querySelector('[name="offsets[]"]');if(offset)offset.value='';});
      rows.addEventListener('click',event=>{const button=event.target.closest('[data-remove-compact-showtime]');if(!button)return;if(rows.children.length>1){button.closest('[data-compact-showtime]').remove();rows.lastElementChild.querySelector('input').focus();}else{rows.querySelectorAll('input,select').forEach(input=>input.value='');rows.querySelector('input').focus();}});
    });
    root.querySelectorAll('[data-pricing-source],[data-pricing-mode]').forEach(select=>{
      if(select.dataset.initialized)return;select.dataset.initialized='1';const form=select.closest('form'),editor=form.querySelector('[data-price-editor]'),selection=form.querySelector('[data-pricing-selection]');
      const update=()=>{const inherit=['production','inherit'].includes(select.value);if(editor){editor.hidden=inherit;editor.disabled=inherit;}if(selection)selection.hidden=select.value==='defaults';};select.addEventListener('change',update);update();
    });
    root.querySelectorAll('[data-schedule-layout]').forEach(layout=>{
      if(layout.dataset.initialized)return;layout.dataset.initialized='1';const form=layout.closest('form'),space=form.querySelector('[data-space-choice]'),status=form.querySelector('[data-schedule-timezone]');
      const update=()=>{const zones=(layout.selectedOptions[0]?.dataset.zones||'').split(',').map(zone=>zone.trim()).filter(Boolean);form.querySelectorAll('[data-price-zone]').forEach(select=>setZones(select,zones));form.querySelector('[data-schedule-zones]').textContent=`Seating zones to price: ${layout.selectedOptions[0]?.dataset.zones||'none'}`;status.textContent=`Venue timezone: ${layout.selectedOptions[0]?.dataset.timezone||'Choose a layout'}. Showtimes use this local timezone.`;};layout.addEventListener('change',update);update();
      space?.addEventListener('change',()=>{[...layout.options].forEach(option=>{option.hidden=!!space.value&&option.dataset.space!==space.value;option.disabled=option.hidden;});if(layout.selectedOptions[0]?.disabled)layout.value=[...layout.options].find(option=>!option.disabled)?.value||'';update();});
      const production=form.querySelector('[data-production-choice]'),source=form.querySelector('[data-pricing-source]'),defaults=JSON.parse(root.querySelector('[data-production-defaults]')?.textContent||'{}');
      production?.addEventListener('change',()=>{const option=source.querySelector('[value=production]');option.disabled=!defaults[production.value];source.value=option.disabled?'override':'production';source.dispatchEvent(new Event('change'));});
    });
  };
  initialize(document);
  let drawer=null,body=null,returnFocus=null,currentUrl='',loadVersion=0;
  const showError=message=>{let error=body.querySelector('[data-drawer-error]');if(!error){error=document.createElement('div');error.dataset.drawerError='';error.className='commerce-error';error.setAttribute('role','alert');error.tabIndex=-1;body.prepend(error);}error.textContent=message;error.focus();};
  const load=async(url,options={})=>{
    const target=body,version=++loadVersion;target.setAttribute('aria-busy','true');
    try{
      const response=await fetch(url,{credentials:'same-origin',...options}),doc=new DOMParser().parseFromString(await response.text(),'text/html');
      if(body!==target||version!==loadVersion)return;
      if(!response.ok)throw Error('The request could not be completed. Reload this view and try again.');
      const error=doc.querySelector('[role=alert]');if(error&&options.method==='POST'){showError(error.textContent);return;}
      const content=doc.querySelector('[data-production-drawer],[data-order-detail]');
      if(!content){if(error){showError(error.textContent);return;}window.location.assign(response.url);return;}
      currentUrl=response.url;content.querySelectorAll('form').forEach(form=>form.setAttribute('action',currentUrl));
      body.replaceChildren(content);if(error)showError(error.textContent);initialize(body);
      const heading=body.querySelector('h2,h1');if(heading){heading.tabIndex=-1;heading.focus();}
    }catch(error){if(body===target&&version===loadVersion)showError(error.message||'Unable to load this view. Try again.');}
    finally{if(version===loadVersion)target.removeAttribute('aria-busy');}
  };
  document.addEventListener('click',event=>{
    const link=event.target.closest('[data-commerce-drawer-link]');if(!link||event.metaKey||event.ctrlKey||event.altKey||event.shiftKey)return;
    event.preventDefault();
    if(!drawer){
      returnFocus=link;drawer=document.createElement('dialog');drawer.className='commerce-page commerce-drawer commerce-production-drawer';drawer.setAttribute('aria-label','Production and performance management');
      const close=document.createElement('button');close.type='button';close.className='commerce-drawer-close';close.textContent='Close';close.addEventListener('click',()=>drawer.close());body=document.createElement('div');body.className='commerce-drawer-body';drawer.append(close,body);document.body.append(drawer);drawer.addEventListener('close',()=>{drawer.remove();drawer=null;body=null;loadVersion++;returnFocus?.focus();});drawer.showModal();close.focus();
    }
    void load(link.href);
  });
  document.addEventListener('submit',event=>{
    if(!drawer||!drawer.contains(event.target))return;
    const form=event.target;if(form.method.toLowerCase()!=='post')return;event.preventDefault();
    if(body.getAttribute('aria-busy')==='true')return;
    void load(form.getAttribute('action')||currentUrl,{method:'POST',body:new URLSearchParams(new FormData(form))});
  });
})();

// Keep the text fallback if a private poster is missing or fails to decode.
(() => {
  const fallback=image=>{if(image.matches?.('[data-commerce-poster]')){image.hidden=true;const text=image.parentElement.querySelector('[data-poster-fallback]');if(text)text.hidden=false;}};
  document.addEventListener('error',event=>fallback(event.target),true);
  document.querySelectorAll('[data-commerce-poster]').forEach(image=>{if(image.complete&&!image.naturalWidth)fallback(image);});
})();
