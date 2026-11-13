// Client-side estimate uses the same national dates and local-day split as the server.
window.carePocketEstimate = (startText,endText,weekday,special) => {
  const start=new Date(startText+'Z'),end=new Date(endText+'Z');
  if(!Number.isFinite(+start)||!Number.isFinite(+end)||end<=start)return 0;
  const holidays=year=>{
    const floor=Math.floor,a=year%19,b=floor(year/100),c=year%100,d=floor(b/4),e=b%4,f=floor((b+8)/25),g=floor((b-f+1)/3),h=(19*a+b-d-g+15)%30,i=floor(c/4),k=c%4,l=(32+2*e+2*i-h-k)%7,m=floor((a+11*h+22*l)/451),month=floor((h+l-7*m+114)/31),day=(h+l-7*m+114)%31+1;
    const dates=['01-01','04-25','05-01','06-10','08-15','10-05','11-01','12-01','12-08','12-25'].map(date=>year+'-'+date);
    for(const offset of [-2,0,60])dates.push(new Date(Date.UTC(year,month-1,day+offset)).toISOString().slice(0,10));return dates;
  };
  // Inputs encode Lisbon wall times in UTC fields. Subtract the Lisbon offset
  // at each endpoint to obtain the elapsed duration across clock changes.
  const clock = new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Lisbon',timeZoneName:'shortOffset'});
  const offset = date => clock.formatToParts(date).find(part=>part.type==='timeZoneName').value === 'GMT+1' ? 3600000 : 0;
  const instant = date => +date - offset(new Date(+date - offset(date)));
  let total=0,cursor=start;
  while(cursor<end){const next=new Date(Math.min(+end,Date.UTC(cursor.getUTCFullYear(),cursor.getUTCMonth(),cursor.getUTCDate()+1)));const isSpecial=[0,6].includes(cursor.getUTCDay())||holidays(cursor.getUTCFullYear()).includes(cursor.toISOString().slice(0,10));total+=(instant(next)-instant(cursor))/3600000*Number(isSpecial&&special!==''?special:weekday);cursor=next;}
  return total;
};
document.addEventListener('DOMContentLoaded', () => {
  const form=document.getElementById('service-form');
  if (form) {
    const field=name=>form.elements.namedItem(name);
    const refresh=()=>{
      const editing=Boolean(field('id').value);
      form.querySelector('[data-series-control]').hidden=!editing;
      form.querySelector('[data-extra-times-control]').hidden=editing;
      const extra=!editing && field('extra_times').value==='1';
      form.querySelector('[data-extra-times]').hidden=!extra;
      form.querySelectorAll('[data-extra-times] input').forEach(input=>{input.disabled=!extra;input.required=extra&&input.name.endsWith('_2');});
    };
    const reset=()=>{field('extra_times').value='0';field('edit_series').value='0';form.querySelectorAll('[data-extra-times] input').forEach(input=>input.value='');refresh();};
    let originalCarer = '';
    form.addEventListener('service-selected', () => {
      originalCarer = field('carer_id').value;
      reset();
    });
    document.getElementById('open-new-service').addEventListener('click',()=>{reset();field('hourly_rate_special').value='';});
    document.getElementById('clear-service').addEventListener('click',reset);
    field('extra_times').addEventListener('change',refresh);
    field('carer_id').addEventListener('change',()=>{

      const option=field('carer_id').selectedOptions[0];
      if (field('id').value && option?.value && option.value !== originalCarer) {
        const apply = window.confirm('Substituir também a cuidadora nos próximos serviços agendados desta série?');
        field('edit_series').value = apply ? 'future' : '0';
        field('edit_series').dispatchEvent(new Event('change', {bubbles:true}));
      }
      field('hourly_rate').value=option?.dataset.weekday || field('hourly_rate').defaultValue;
      field('hourly_rate_special').value=option?.dataset.special||'';
      field('hourly_rate').dispatchEvent(new Event('input',{bubbles:true}));
    });
    field('patient_id').addEventListener('change',()=>{
      const place=field('patient_id').selectedOptions[0]?.dataset.municipality;
      if(place)field('municipality').value=place;
    });
    const params=new URLSearchParams(location.search);
    if(params.get('new_service')==='1' && params.get('patient_id')) {
      requestAnimationFrame(()=>{
      document.getElementById('open-new-service').click();
      field('patient_id').value=params.get('patient_id');
      field('patient_id').dispatchEvent(new Event('change',{bubbles:true}));
      });
    }
    refresh();
  }
  const month=document.getElementById('copy-month-form');
  if(!month)return;
  const preview=month.querySelector('[data-month-preview]'),button=month.querySelector('[data-month-preview-button]'),submit=month.querySelector('[type=submit]');
  let approved='',generation=0,submitting=false;
  const snapshot=()=>new URLSearchParams(new FormData(month)).toString();
  const invalidate=()=>{generation++;approved='';month.elements.preview_token.value='';submit.disabled=true;preview.replaceChildren();month.elements.idempotency_key.value=crypto.randomUUID().replaceAll('-','');};
  month.addEventListener('input',invalidate);month.addEventListener('change',invalidate);
  button.addEventListener('click',async()=>{
    if(!month.reportValidity())return;
    const current=generation,body=new FormData(month);body.set('action','preview_service');button.disabled=true;
    try {
      const response=await fetch('index.php?page=scales',{method:'POST',body,credentials:'same-origin'});
      if(!response.ok || !response.headers.get('content-type')?.includes('application/json'))throw Error('Não foi possível pré-visualizar. Atualize a página e tente novamente.');
      const result=await response.json();if(current!==generation)return;preview.replaceChildren();
      const title=document.createElement('p');title.textContent=`${result.count} serviços com correspondência. ${(result.conflicts||[]).length} conflitos. ${(result.unmatched||[]).length} sem correspondência.`;preview.append(title);
      for(const message of result.errors||[]){const line=document.createElement('p');line.textContent=message;preview.append(line);}
      const list=document.createElement('ul');
      for(const item of result.unmatched||[]){const line=document.createElement('li');line.textContent='Sem correspondência: '+item.starts_at;list.append(line);}
      for(const item of result.occurrences||[]){const line=document.createElement('li');line.textContent=item.starts_at+' – '+item.ends_at+' · '+item.municipality;list.append(line);}
      preview.append(list);month.elements.preview_token.value=result.preview_token||'';
      approved=result.valid && month.elements.preview_token.value?snapshot():'';submit.disabled=!approved;
    }catch(error){if(current===generation)preview.textContent=error.message;}finally{button.disabled=false;}
  });
  month.addEventListener('submit',event=>{if(submitting||!approved||snapshot()!==approved){event.preventDefault();return;}submitting=true;submit.disabled=true;});
});

document.querySelector('[data-sheet-popup]')?.addEventListener('click', event => {
    const popup = window.open(event.currentTarget.href, 'care4all_sheet', 'popup,width=1280,height=850');
    if (popup) { event.preventDefault(); popup.focus(); }
});
