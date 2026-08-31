const $=(s,p=document)=>p.querySelector(s),$$=(s,p=document)=>Array.from(p.querySelectorAll(s)),fmt=n=>new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2}).format(+n||0);
function numToWordsFa(amount){
 const ones=['','یک','دو','سه','چهار','پنج','شش','هفت','هشت','نه'],teens=['ده','یازده','دوازده','سیزده','چهارده','پانزده','شانزده','هفده','هجده','نوزده'],tens=['','ده','بیست','سی','چهل','پنجاه','شصت','هفتاد','هشتاد','نود'],hundreds=['','صد','دویست','سیصد','چهارصد','پانصد','ششصد','هفتصد','هشتصد','نهصد'],scales=['','هزار','میلیون','میلیارد','تریلیون'];
 let n=Math.floor(Math.abs(+amount||0));
 if(n===0)return'صفر';
 function threeDigits(num){
  const h=Math.floor(num/100),t=Math.floor((num%100)/10),o=num%10;
  const parts=[];
  if(h)parts.push(hundreds[h]);
  if(t===1)parts.push(teens[o]);
  else{if(t)parts.push(tens[t]);if(o)parts.push(ones[o]);}
  return parts.join(' و ');
 }
 const groups=[];
 while(n>0){groups.unshift(n%1000);n=Math.floor(n/1000);}
 const words=[];
 groups.forEach((g,i)=>{
  if(!g)return;
  const scale=scales[groups.length-1-i];
  words.push(threeDigits(g)+(scale?' '+scale:''));
 });
 return words.join(' و ');
}
const dailyQuotesFa=['کیفیت هرگز یک اتفاق نیست؛ همیشه نتیجه‌ی تلاش هوشمندانه است.','بهترین راه پیش‌بینی آینده، ساختن آن است.','مشتری راضی، بهترین استراتژی تبلیغاتی است.','موفقیت مجموع تلاش‌های کوچک است که هر روز تکرار می‌شوند.','صداقت در معامله، پایدارترین سرمایه یک کسب‌وکار است.','هر روز فرصتی تازه برای بهتر شدن است.','اعتماد، با عمل ساخته می‌شود نه با وعده.'];
function dailyQuote(){const day=Math.floor(Date.now()/86400000);return dailyQuotesFa[day%dailyQuotesFa.length];}
async function api(action,body=null,form=false){const o=body?{method:'POST',headers:{'X-CSRF-Token':APP.csrf},body:form?body:JSON.stringify(body)}:{headers:{'X-CSRF-Token':APP.csrf}};if(body&&!form)o.headers['Content-Type']='application/json';const r=await fetch(APP.base+'/api?action='+action,o),j=await r.json();if(!r.ok)throw new Error(j.message||'خطا');return j}
let state={companies:[],quotes:[],templates:[],activeOutputTemplate:null,company:null,customer:null,quoteId:null,quoteNumber:null,quoteStatus:'draft'};function toast(m){const x=$('#toast');x.textContent=m;x.classList.add('show');setTimeout(()=>x.classList.remove('show'),2600)}
function showView(name){$$('.view').forEach(x=>x.hidden=x.id!=='view-'+name);$$('.sidebar nav button').forEach(x=>x.classList.toggle('active',x.dataset.view===name));$('.sidebar').classList.remove('open');stopChatPolling();if(name==='home')loadDashboard();if(name==='company')showCompanyList();if(name==='admin')loadAdminOverview();if(name==='admin-users')loadAdminUsers();if(name==='admin-catalog')loadAdminCatalog();if(name==='admin-output')loadOutputTemplates();if(name==='admin-settings')loadAdminSettings();if(name==='chat')startUserChat();if(name==='admin-chat')startAdminChat()}
$$('[data-view]').forEach(b=>b.onclick=()=>showView(b.dataset.view));$$('[data-open-editor]').forEach(b=>b.onclick=newQuote);$('#menu').onclick=()=>$('.sidebar').classList.toggle('open');
async function boot(){try{const j=await api('bootstrap'),previousId=state.company&&state.company.id;state.companies=j.data.companies||[];state.quotes=j.data.quotes||[];state.templates=j.data.templates||[];state.activeOutputTemplate=state.templates[0]||null;state.company=state.companies.find(c=>c.id==previousId)||state.companies[0]||null;try{renderOutputTemplateChoices()}catch(e){console.error('renderOutputTemplateChoices failed',e)};renderCompanies();renderQuotes();setChatBadge('#chat-unread-badge',j.data.chat_unread);setChatBadge('#admin-chat-unread-badge',j.data.admin_chat_unread);if(!state.company)toast('برای صدور سند، نخستین شرکت خود را ثبت کنید.');showView('home')}catch(e){console.error('boot failed',e);toast(e.message||'خطا در بارگذاری اولیه')}}
function setChatBadge(sel,n){const el=$(sel);if(!el)return;if(n>0){el.hidden=false;el.textContent=n>99?'۹۹+':String(n)}else{el.hidden=true}}
function renderCompanies(){const s=$('#active-company'),selected=state.company&&state.companies.some(c=>c.id==state.company.id)?state.company.id:(state.companies[0]||{}).id;s.innerHTML=state.companies.map(c=>'<option value="'+c.id+'">'+esc(c.name)+'</option>').join('')||'<option>شرکتی ثبت نشده</option>';if(selected)s.value=selected;s.onchange=()=>{state.company=state.companies.find(c=>c.id==s.value);renderCompanyList()};renderCompanyList()}
function renderQuotes(){const el=$('#quotes-list');if(!state.quotes.length){el.innerHTML='<div class="empty">هنوز پیش‌فاکتوری ایجاد نشده است.</div>';return}el.innerHTML='<table class="data-table"><thead><tr><th>شماره</th><th>شرکت</th><th>مشتری</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead><tbody>'+state.quotes.map(q=>'<tr><td>'+esc(q.quote_number)+'</td><td>'+esc(q.company_name)+'</td><td>'+esc(q.customer_name||'-')+'</td><td>'+esc(q.issue_date)+'</td><td>'+fmt(q.total)+'</td><td>'+(q.status==='draft'?'پیش‌نویس':'صادرشده')+'</td><td><button class="btn small" onclick="openQuote('+q.id+')">بازکردن</button></td></tr>').join('')+'</tbody></table>'}
function fillCompany(c={}){const f=$('#company-form');f.reset();Array.from(f.elements).forEach(x=>{if(x.name&&c[x.name]!=null)x.value=c[x.name]});if(!c.id){f.elements.id.value='';f.elements.default_tax.value='0';f.elements.brand_color.value='#2563eb'}}
function showCompanyList(){$('#company-list-panel').hidden=false;$('#company-form-panel').hidden=true;renderCompanyList()}
function openCompanyForm(c=null){fillCompany(c||{});$('#company-form-title').textContent=c&&c.id?'ویرایش شرکت «'+c.name+'»':'ثبت شرکت جدید';$('#company-list-panel').hidden=true;$('#company-form-panel').hidden=false;setTimeout(()=>$('#company-form [name=name]').focus(),50)}
function renderCompanyList(){const el=$('#companies-list');if(!el)return;if(!state.companies.length){el.innerHTML='<div class="panel empty">هنوز شرکتی ثبت نکرده‌اید.<br><br><button type="button" class="btn primary" id="empty-new-company">ثبت اولین شرکت</button></div>';const b=$('#empty-new-company');if(b)b.onclick=()=>openCompanyForm();return}el.innerHTML=state.companies.map(c=>'<article class="company-card '+(state.company&&state.company.id==c.id?'active':'')+'"><div class="company-card-head">'+(c.logo_path?'<img class="company-logo" src="'+APP.base+'/api?action=asset&company_id='+c.id+'&kind=logo" alt="">':'<div class="company-logo company-logo-placeholder">'+esc((c.name||'ش').slice(0,1))+'</div>')+'<div><h3>'+esc(c.name)+'</h3>'+(state.company&&state.company.id==c.id?'<span class="active-company-label">شرکت فعال</span>':'')+'</div></div><p>'+(c.legal_name?esc(c.legal_name)+'<br>':'')+(c.mobile||c.phone?'تلفن: '+esc(c.mobile||c.phone)+'<br>':'')+(c.national_id?'شناسه ملی: '+esc(c.national_id):'')+'</p><div class="company-card-actions"><button type="button" class="btn small" data-company-edit="'+c.id+'">ویرایش</button>'+(state.company&&state.company.id==c.id?'':'<button type="button" class="btn small primary" data-company-active="'+c.id+'">انتخاب به‌عنوان فعال</button>')+'</div></article>').join('');$$('[data-company-edit]',el).forEach(b=>b.onclick=()=>openCompanyForm(state.companies.find(c=>c.id==b.dataset.companyEdit)));$$('[data-company-active]',el).forEach(b=>b.onclick=()=>{state.company=state.companies.find(c=>c.id==b.dataset.companyActive);$('#active-company').value=state.company.id;renderCompanyList();toast('شرکت فعال تغییر کرد.')})}
$('#new-company-btn').onclick=()=>openCompanyForm();$('#back-companies-btn').onclick=showCompanyList;$('#cancel-company-btn').onclick=showCompanyList;
$('#company-form').onsubmit=async e=>{e.preventDefault();const body=Object.fromEntries(new FormData(e.target));try{const j=await api('company-save',body);await uploadAssets(j.id);toast(j.message);const data=await api('bootstrap');state.companies=data.data.companies;state.quotes=data.data.quotes;state.company=state.companies.find(c=>c.id==j.id)||state.companies[0]||null;renderCompanies();renderQuotes();showCompanyList()}catch(x){toast(x.message)}};
async function uploadAssets(id){for(const input of $$('[data-asset]'))if(input.files[0]){const fd=new FormData();fd.append('company_id',id);fd.append('kind',input.dataset.asset);fd.append('file',input.files[0]);await api('asset-upload',fd,true)}}
function newQuote(){if(!state.companies.length){showView('company');return}state.quoteId=null;state.quoteNumber=null;state.quoteStatus='draft';toggleFinalizeUi();$('#quote-form').reset();$('[name=id]',$('#quote-form')).value='';const today=new Date().toISOString().slice(0,10);$('[name=issue_date]').value=today;$('[name=issue_date_jalali]').value=toJalali(today);$('[name=valid_until]').value='';$('[name=valid_until_jalali]').value='';$('#items-body').innerHTML='';addRow();showView('editor');calculate()}
$('#add-row').onclick=()=>addRow();function addRow(data={}){const tr=$('#item-row-template').content.firstElementChild.cloneNode(true);$('#items-body').append(tr);$('.row-no',tr).textContent=$$('#items-body tr').length;const set=(s,v)=>{if(v!==undefined&&v!==null)$(s,tr).value=v};set('.item-name',data.name);set('.catalog-id',data.catalog_item_id);set('.item-type',data.type);set('.item-unit',data.unit);set('.item-qty',data.quantity);set('.item-price',data.unit_price);set('.item-discount',data.discount_percent);set('.item-tax',data.tax_percent);$('.remove-row',tr).onclick=()=>{tr.remove();renumber();calculate()};$$('input,select',tr).forEach(x=>x.oninput=calculate);setupItemSearch(tr);calculate()}
function renumber(){$$('#items-body tr').forEach((r,i)=>$('.row-no',r).textContent=i+1)}
function placeItemResults(input,box){const r=input.getBoundingClientRect();box.style.left=r.left+'px';box.style.top=(r.bottom+4)+'px';box.style.width=Math.max(r.width,280)+'px'}
let itemTimer;function setupItemSearch(tr){const input=$('.item-name',tr),box=$('.item-results',tr);input.oninput=()=>{clearTimeout(itemTimer);$('.catalog-id',tr).value='';if(input.value.trim().length<1){box.classList.remove('open');return}itemTimer=setTimeout(async()=>{try{const j=await api('catalog-search&company_id='+state.company.id+'&q='+encodeURIComponent(input.value.trim()));box.innerHTML=j.data.map((i,n)=>'<div class="result" data-n="'+n+'"><b>'+esc(i.name)+'</b><small>'+(i.type==='service'?'خدمت':'کالا')+' · '+esc(i.unit||'-')+' · آخرین قیمت: '+fmt(i.last_price)+'</small></div>').join('')+(j.data.length?'':'<div class="result result-empty">موردی در کاتالوگ این شرکت پیدا نشد</div>')+'<div class="result new-item">+ ذخیره مورد جدید</div>';placeItemResults(input,box);box.classList.add('open');$$('[data-n]',box).forEach(r=>r.onclick=()=>{const i=j.data[+r.dataset.n];input.value=i.name;$('.catalog-id',tr).value=i.id;$('.item-type',tr).value=i.type;$('.item-unit',tr).value=i.unit;$('.item-price',tr).value=i.last_price;$('.item-tax',tr).value=i.default_tax;box.classList.remove('open');calculate()});$('.new-item',box).onclick=()=>box.classList.remove('open')}catch(x){toast(x.message)}},220)}}
window.addEventListener('resize',()=>$$('.item-results.open').forEach(x=>x.classList.remove('open')));window.addEventListener('scroll',()=>$$('.item-results.open').forEach(x=>x.classList.remove('open')),true);
let customerTimer;$('#customer-search').oninput=e=>{clearTimeout(customerTimer);const q=e.target.value.trim(),box=$('#customer-results');$('[name=customer_id]').value='';if(q.length<2){box.classList.remove('open');return}customerTimer=setTimeout(async()=>{try{const j=await api('customer-search&company_id='+state.company.id+'&q='+encodeURIComponent(q));box.innerHTML=j.data.map((c,i)=>'<div class="result" data-i="'+i+'"><b>'+esc(c.name)+'</b><small>'+esc(c.mobile||'')+' · '+esc(c.national_id||'')+'</small></div>').join('')+'<div class="result customer-new">+ استفاده به‌عنوان مشتری جدید</div>';box.classList.add('open');$$('[data-i]',box).forEach(r=>r.onclick=()=>{const c=j.data[+r.dataset.i];e.target.value=c.name;$('[name=customer_id]').value=c.id;['mobile','phone','national_id','economic_code','registration_no','postal_code','address'].forEach(k=>$('[name=customer_'+k+']').value=c[k]||'');box.classList.remove('open')});$('.customer-new',box).onclick=()=>box.classList.remove('open')}catch(x){toast(x.message)}},280)};
function calculate(){let subtotal=0,itemDiscount=0,tax=0;$$('#items-body tr').forEach(tr=>{const q=+$('.item-qty',tr).value||0,p=+$('.item-price',tr).value||0,d=+$('.item-discount',tr).value||0,t=+$('.item-tax',tr).value||0,b=q*p,a=b*(1-d/100),lt=a*(1+t/100);subtotal+=b;itemDiscount+=b-a;tax+=a*t/100;$('.line-total',tr).textContent=fmt(lt)});const f=$('#quote-form'),dv=+$('[name=discount_value]',f).value||0,discountBase=Math.max(0,subtotal-itemDiscount),da=$('[name=discount_type]',f).value==='percent'?discountBase*Math.min(dv,100)/100:Math.min(dv,discountBase),discountTotal=itemDiscount+da,total=Math.max(0,subtotal-discountTotal+tax),pre=+$('[name=prepayment]',f).value||0;$('#sum-subtotal').textContent=fmt(subtotal);$('#sum-discount').textContent=fmt(discountTotal);$('#sum-tax').textContent=fmt(tax);$('#sum-total').textContent=fmt(total);$('#sum-payable').textContent=fmt(Math.max(0,total-pre))+' ریال';return{subtotal:subtotal,itemDiscount:itemDiscount,da:da,discountTotal:discountTotal,tax:tax,total:total,payable:Math.max(0,total-pre)}}
$$('#quote-form input,#quote-form select').forEach(x=>x.addEventListener('input',calculate));
function quotePayload(){const f=$('#quote-form'),g=n=>($('[name='+n+']',f)||{}).value||'';return{id:+g('id')||0,company_id:state.company.id,customer_id:+g('customer_id')||0,customer_name:g('customer_name'),customer_mobile:g('customer_mobile'),customer_phone:g('customer_phone'),customer_national_id:g('customer_national_id'),customer_economic_code:g('customer_economic_code'),customer_registration_no:g('customer_registration_no'),customer_postal_code:g('customer_postal_code'),customer_address:g('customer_address'),issue_date:g('issue_date'),valid_until:g('valid_until'),discount_type:g('discount_type'),discount_value:g('discount_value'),prepayment:g('prepayment'),notes:g('notes'),terms:g('terms'),items:$$('#items-body tr').map(tr=>({catalog_item_id:+$('.catalog-id',tr).value||0,name:$('.item-name',tr).value,type:$('.item-type',tr).value,unit:$('.item-unit',tr).value,quantity:$('.item-qty',tr).value,unit_price:$('.item-price',tr).value,discount_percent:$('.item-discount',tr).value,tax_percent:$('.item-tax',tr).value}))}}
$('#save-btn').onclick=saveQuote;async function saveQuote(){try{const j=await api('quote-save',quotePayload());state.quoteId=j.id;state.quoteNumber=j.quote_number||state.quoteNumber;$('[name=id]',$('#quote-form')).value=j.id;toast(j.message);await refresh()}catch(x){toast(x.message)}}async function refresh(){const j=await api('bootstrap');state.quotes=j.data.quotes;renderQuotes()}
window.openQuote=async id=>{try{const j=await api('quote-get&id='+id),q=j.data;state.company=state.companies.find(c=>c.id==q.company_id);$('#active-company').value=q.company_id;newQuote();state.quoteId=q.id;state.quoteNumber=q.quote_number;state.quoteStatus=q.status;toggleFinalizeUi();const f=$('#quote-form');['id','issue_date','valid_until','discount_type','discount_value','prepayment','notes','terms'].forEach(k=>{if($('[name='+k+']',f))$('[name='+k+']',f).value=q[k]||''});$('[name=issue_date_jalali]',f).value=toJalali(q.issue_date);$('[name=valid_until_jalali]',f).value=q.valid_until?toJalali(q.valid_until):'';const c=JSON.parse(q.customer_snapshot);$('[name=customer_id]',f).value=q.customer_id||'';$('[name=customer_name]',f).value=c.name||'';['mobile','phone','national_id','economic_code','registration_no','postal_code','address'].forEach(k=>$('[name=customer_'+k+']',f).value=c[k]||'');$('#items-body').innerHTML='';q.items.forEach(addRow);calculate()}catch(x){toast(x.message)}};
$('#finalize-btn').onclick=async()=>{if(!state.quoteId)await saveQuote();if(!state.quoteId)return;try{const j=await api('quote-finalize',{id:state.quoteId});toast(j.message);state.quoteStatus='issued';toggleFinalizeUi();await refresh()}catch(x){toast(x.message)}};
function toggleFinalizeUi(){const isIssued=state.quoteStatus&&state.quoteStatus!=='draft',fb=$('#finalize-btn'),rb=$('#revise-btn');if(fb)fb.hidden=isIssued;if(rb)rb.hidden=!isIssued;$$('#quote-form input,#quote-form select,#quote-form textarea').forEach(el=>{if(el.id!=='revise-btn')el.disabled=isIssued});$$('#items-body input,#items-body select,#items-body button').forEach(el=>el.disabled=isIssued);const addBtn=$('#add-item-btn');if(addBtn)addBtn.disabled=isIssued}
$('#revise-btn')&&($('#revise-btn').onclick=async()=>{if(!state.quoteId)return;if(!confirm('یک نسخه اصلاحیه جدید از این فاکتور ساخته می‌شود و می‌توانید آن را ویرایش کنید. سند فعلی به عنوان مدرک بدون تغییر باقی می‌ماند. ادامه می‌دهید؟'))return;try{const j=await api('quote-revise',{id:state.quoteId});toast(j.message);await refresh();openQuote(j.data.id)}catch(x){toast(x.message)}});
$('#preview-btn').onclick=()=>{buildPreview();$('#preview-dialog').showModal()};$('#close-preview').onclick=()=>$('#preview-dialog').close();$('#print-btn').onclick=()=>{const f=$('#output-format').value,parts=f.split('-'),p=parts[0].toUpperCase(),o=parts[1]==='landscape'?'landscape':'portrait';let s=$('#dynamic-page-style');if(!s){s=document.createElement('style');s.id='dynamic-page-style';document.head.append(s)}s.textContent='@media print{@page{size:'+p+' '+o+';margin:0}}';window.print()};
function applyOutputChoice(){const sheet=$('#print-sheet');sheet.dataset.template=$('#output-template').value;sheet.dataset.format=$('#output-format').value;buildPreview()}$('#output-template').onchange=applyOutputChoice;$('#output-format').onchange=applyOutputChoice;
function syncEditorOutput(){ $('#output-template').value=$('#editor-output-template').value;$('#output-format').value=$('#editor-output-format').value;const sheet=$('#print-sheet');sheet.dataset.template=$('#output-template').value;sheet.dataset.format=$('#output-format').value }$('#editor-output-template').onchange=syncEditorOutput;$('#editor-output-format').onchange=syncEditorOutput;$('#editor-preview-btn').onclick=()=>{syncEditorOutput();buildPreview();$('#preview-dialog').showModal()};$('#editor-share-btn').onclick=()=>$('#share-btn').click();
function renderOutputTemplateChoices(){const custom=state.templates.map(t=>'<option value="custom-'+t.id+'">'+esc(t.name)+'</option>').join('');$('#editor-output-template').innerHTML='<option value="formal">رسمی حسابداری</option><option value="modern">مدرن شرکتی</option><option value="minimal">ساده و کم‌جوهر</option>'+custom;$('#output-template').innerHTML=$('#editor-output-template').innerHTML;if(state.activeOutputTemplate){const v='custom-'+state.activeOutputTemplate.id;$('#editor-output-template').value=v;selectCustomTemplate(v)}}
function ensurePaperChoice(format){const label=format.split('-')[0].toUpperCase()+' '+(format.endsWith('landscape')?'افقی':'عمودی');[$('#editor-output-format'),$('#output-format')].forEach(select=>{if(!Array.from(select.options).some(o=>o.value===format))select.add(new Option(label,format))})}function selectCustomTemplate(value){const id=+(String(value).replace('custom-','')),t=state.templates.find(x=>+x.id===id);state.activeOutputTemplate=t||null;if(!t)return;const format=t.paper_size+'-'+t.orientation;ensurePaperChoice(format);$('#editor-output-format').value=format;$('#output-format').value=format;$('#output-template').value=value;const sheet=$('#print-sheet');sheet.dataset.template=t.style;sheet.dataset.format=format}
$('#editor-output-template').addEventListener('change',e=>{if(e.target.value.startsWith('custom-'))selectCustomTemplate(e.target.value);else state.activeOutputTemplate=null});$('#output-template').addEventListener('change',e=>{if(e.target.value.startsWith('custom-'))selectCustomTemplate(e.target.value);else state.activeOutputTemplate=null});
function applyConfiguredOutput(){const t=state.activeOutputTemplate,sheet=$('#print-sheet');if(!t||!sheet.children.length)return;sheet.dataset.template=t.style;sheet.dataset.format=t.paper_size+'-'+t.orientation;const cfg=t.config||{},cols=cfg.columns||{},map={code:2,unit:4,quantity:5,unit_price:6,gross:7,discount:8,after_discount:9,tax:10,total:11};Object.entries(map).forEach(([key,n])=>$$('.formal-items tr',sheet).forEach(row=>{const cell=row.children[n-1];if(cell)cell.style.display=cols[key]===false?'none':''}));const parties=$$('.formal-party',sheet),sections=cfg.sections||{};if(parties[0])parties[0].hidden=sections.seller===false;if(parties[1])parties[1].hidden=sections.buyer===false;const notes=$('.formal-notes',sheet),sign=$('.formal-signatures',sheet),footer=$('.formal-footer',sheet);if(notes)notes.hidden=sections.notes===false&&sections.payment===false;if(notes&&notes.querySelector('span'))notes.querySelector('span').style.display=sections.payment===false?'none':'';if(sign)sign.hidden=sections.signatures===false;if(footer)footer.hidden=sections.footer===false;const nodes={header:$('.formal-header',sheet),seller:parties[0],buyer:parties[1],items:$('.formal-table-title',sheet),notes:notes,signatures:sign,footer:footer};(cfg.order||[]).forEach(key=>{if(key==='items'){if(nodes.items)sheet.append(nodes.items);const table=$('.formal-items',sheet);if(table)sheet.append(table)}else if(nodes[key])sheet.append(nodes[key])});
 // Apply typography
 const typo=t.typography||{};
 const typoMap={header:'formal-header',body:'.formal-party,.party-grid',table:'.formal-items',notes:'.formal-notes',footer:'.formal-footer'};
 Object.entries(typoMap).forEach(([key,selector])=>{
  const el=sheet.querySelector(selector);
  if(el && typo[key]){
   if(typo[key].font_family)el.style.fontFamily=typo[key].font_family;
   if(typo[key].font_size)el.style.fontSize=typo[key].font_size;
  }
 });
 // Also apply to specific elements
 if(typo.header){
  const title=sheet.querySelector('.formal-title h1');
  if(title && typo.header.font_size)title.style.fontSize=typo.header.font_size;
  if(title && typo.header.font_family)title.style.fontFamily=typo.header.font_family;
 }
 if(typo.table){
  const ths=sheet.querySelectorAll('.formal-items th, .formal-items td');
  ths.forEach(th=>{if(typo.table.font_size)th.style.fontSize=typo.table.font_size;if(typo.table.font_family)th.style.fontFamily=typo.table.font_family;});
 }
 if(typo.notes){
  const notesEl=sheet.querySelector('.formal-notes');
  if(notesEl && typo.notes.font_size)notesEl.style.fontSize=typo.notes.font_size;
  if(notesEl && typo.notes.font_family)notesEl.style.fontFamily=typo.notes.font_family;
 }
 if(typo.footer){
  const footerEl=sheet.querySelector('.formal-footer');
  if(footerEl && typo.footer.font_size)footerEl.style.fontSize=typo.footer.font_size;
  if(footerEl && typo.footer.font_family)footerEl.style.fontFamily=typo.footer.font_family;
 }
}
const outputObserver=new MutationObserver(()=>{outputObserver.disconnect();applyConfiguredOutput();setTimeout(()=>outputObserver.observe($('#print-sheet'),{childList:true}),0)});outputObserver.observe($('#print-sheet'),{childList:true});
function buildPreview(){const p=quotePayload(),c=state.company,s=calculate(),rows=p.items.filter(i=>i.name&&+i.quantity>0),asset=k=>APP.base+'/api?action=asset&company_id='+c.id+'&kind='+k,issue=$('[name=issue_date_jalali]').value||toJalali(p.issue_date),valid=$('[name=valid_until_jalali]').value||(p.valid_until?toJalali(p.valid_until):'-'),dash=v=>esc(v||'-'),cell=(label,value)=>'<div><b>'+label+':</b> '+dash(value)+'</div>',blankCount=0,blankRows=Array.from({length:blankCount},(_,n)=>'<tr class="blank-row"><td>'+(rows.length+n+1)+'</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>').join('');const itemRows=rows.map((i,n)=>{const qty=+i.quantity||0,price=+i.unit_price||0,discount=(qty*price)*(+i.discount_percent||0)/100,after=qty*price-discount,tax=after*(+i.tax_percent||0)/100,total=after+tax;return'<tr><td>'+(n+1)+'</td><td>'+dash(i.sku)+'</td><td class="description">'+esc(i.name)+'</td><td>'+dash(i.unit)+'</td><td>'+fmt(qty)+'</td><td>'+fmt(price)+'</td><td>'+fmt(qty*price)+'</td><td>'+fmt(discount)+'</td><td>'+fmt(after)+'</td><td>'+fmt(tax)+'</td><td>'+fmt(total)+'</td></tr>'}).join('');$('#print-sheet').innerHTML='<header class="formal-header"><div class="formal-logo">'+(c.logo_path?'<img class="doc-logo" src="'+asset('logo')+'">':'<strong>'+esc(c.name)+'</strong>')+'</div><div class="formal-title"><h1>صورتحساب فروش کالا و خدمات</h1><span>پیش‌فاکتور</span></div><div class="formal-meta"><div><b>شماره:</b> '+dash(state.quoteNumber||state.quoteId||'پیش‌نویس')+'</div><div><b>تاریخ:</b> '+dash(issue)+'</div><div><b>اعتبار تا:</b> '+dash(valid)+'</div></div></header><section class="formal-party"><h2>مشخصات فروشنده</h2><div class="party-grid">'+cell('نام شخص حقیقی / حقوقی',c.legal_name||c.name)+cell('شماره اقتصادی',c.economic_code)+cell('شماره ثبت / شناسه ملی',[c.registration_no,c.national_id].filter(Boolean).join(' / '))+cell('نشانی',c.address)+cell('کد پستی',c.postal_code)+cell('تلفن / همراه',c.phone||c.mobile)+'</div></section><section class="formal-party"><h2>مشخصات خریدار</h2><div class="party-grid">'+cell('نام شخص حقیقی / حقوقی',p.customer_name)+cell('شماره اقتصادی',p.customer_economic_code)+cell('شماره ثبت / شناسه ملی',[p.customer_registration_no,p.customer_national_id].filter(Boolean).join(' / '))+cell('نشانی',p.customer_address)+cell('کد پستی',p.customer_postal_code)+cell('تلفن / همراه',p.customer_phone||p.customer_mobile)+'</div></section><div class="formal-table-title"><b>مشخصات کالا یا خدمات مورد معامله</b><span>(مبالغ به ریال)</span></div><table class="doc-items formal-items"><thead><tr><th>ردیف</th><th>کد کالا</th><th>شرح کالا یا خدمات</th><th>واحد</th><th>تعداد</th><th>مبلغ واحد</th><th>مبلغ کل</th><th>مبلغ تخفیف</th><th>مبلغ پس از تخفیف</th><th>مالیات و عوارض</th><th>جمع کل</th></tr></thead><tbody>'+itemRows+blankRows+'<tr class="grand-total"><th colspan="4">جمع کل</th><th>'+fmt(rows.reduce((a,i)=>a+(+i.quantity||0),0))+'</th><th></th><th>'+fmt(s.subtotal)+'</th><th>'+fmt(s.da)+'</th><th>'+fmt(Math.max(0,s.subtotal-s.da))+'</th><th>'+fmt(s.tax)+'</th><th>'+fmt(s.total)+'</th></tr></tbody></table><div class="formal-notes"><b>توضیحات:</b> '+dash(p.notes)+'<span><b>شرایط پرداخت:</b> '+dash(p.terms)+'</span><span><b>مشخصات واریزی:</b> '+dash(c.bank_info)+'</span><span><b>قابل پرداخت:</b> '+fmt(s.payable)+' ریال</span><span><b>مبلغ به حروف:</b> '+numToWordsFa(s.payable)+' ریال</span></div><div class="doc-signatures formal-signatures"><div><b>مهر و امضای فروشنده</b><div class="signature-assets">'+(c.signature_path?'<img src="'+asset('signature')+'">':'')+(c.stamp_path?'<img src="'+asset('stamp')+'">':'')+'</div></div><div><b>مهر و امضای خریدار</b></div></div><footer class="formal-footer"><div class="daily-quote">'+dailyQuote()+'</div>این پیش‌فاکتور بدون مهر و امضای فروشنده فاقد اعتبار است.</footer>'}
async function capture(type){buildPreview();if(!window.html2canvas)return toast('کتابخانه خروجی بارگذاری نشده است.');const canvas=await html2canvas($('#print-sheet'),{scale:2,backgroundColor:'#fff'}),a=document.createElement('a');a.download='proforma-'+(state.quoteId||'draft')+'.'+type;a.href=canvas.toDataURL(type==='jpg'?'image/jpeg':'image/png',.92);a.click()}
$('#png-btn').onclick=()=>capture('png');$('#jpg-btn').onclick=()=>capture('jpg');$('#pdf-btn').onclick=async()=>{buildPreview();if(!window.jspdf||!window.html2canvas)return toast('کتابخانه PDF بارگذاری نشده است.');const canvas=await html2canvas($('#print-sheet'),{scale:2,backgroundColor:'#fff'}),jsPDF=window.jspdf.jsPDF,choice=$('#output-format').value,parts=choice.split('-'),paper=parts[0]==='letter'?'letter':parts[0],orientation=parts[1]==='landscape'?'l':'p',pdf=new jsPDF(orientation,'mm',paper),pageW=pdf.internal.pageSize.getWidth(),pageH=pdf.internal.pageSize.getHeight(),h=canvas.height*pageW/canvas.width;pdf.addImage(canvas.toDataURL('image/jpeg',.95),'JPEG',0,0,pageW,Math.min(h,pageH));pdf.save('proforma-'+(state.quoteNumber||state.quoteId||'draft')+'.pdf')};
$('#share-btn').onclick=async()=>{if(!state.quoteId)await saveQuote();if(!state.quoteId)return;const p=quotePayload();$('#share-form').elements.mobile.value=p.customer_mobile||'';$('#share-result').hidden=true;$('#share-dialog').showModal()};$('#close-share').onclick=()=>$('#share-dialog').close();
$('#share-form').onsubmit=async e=>{e.preventDefault();const fd=Object.fromEntries(new FormData(e.target));fd.quote_id=state.quoteId;fd.send_sms=e.target.send_sms.checked?1:0;fd.send_email=e.target.send_email.checked?1:0;try{const j=await api('quote-share',fd),d=j.data,text='پیش‌فاکتور '+(state.quoteNumber||state.quoteId)+'\nلینک مشاهده: '+d.url+'\nرمز مشاهده: '+d.password;$('#share-url').value=d.url;$('#share-password').value=d.password;$('#share-result').hidden=false;$('#whatsapp-share').href='https://wa.me/?text='+encodeURIComponent(text);$('#telegram-share').href='https://t.me/share/url?url='+encodeURIComponent(d.url)+'&text='+encodeURIComponent('پیش‌فاکتور '+(state.quoteNumber||state.quoteId)+' - رمز: '+d.password);$('#copy-share').dataset.text=text;$('#native-share').dataset.text=text;toast(j.message)}catch(x){toast(x.message)}};
$('#copy-share').onclick=async e=>{await navigator.clipboard.writeText(e.currentTarget.dataset.text||'');toast('متن لینک و رمز کپی شد.')};$('#native-share').onclick=async e=>{const text=e.currentTarget.dataset.text||'';if(navigator.share)await navigator.share({title:'پیش‌فاکتور '+(state.quoteNumber||''),text:text});else{await navigator.clipboard.writeText(text);toast('متن اشتراک‌گذاری کپی شد.')}};$('#revoke-share').onclick=async()=>{if(!state.quoteId)return;try{const j=await api('quote-share-revoke',{quote_id:state.quoteId});$('#share-result').hidden=true;toast(j.message)}catch(x){toast(x.message)}};
async function loadDashboard(){
 const el=$('#user-kpis');
 if(!el)return;
 try{
  const j=await api('dashboard'),s=j.data.stats;
  el.innerHTML=kpi('شرکت‌های من',s.companies)+kpi('مشتریان',s.customers)+kpi('کالا و خدمات',s.catalog)+kpi('پیش‌فاکتورها',s.quotes)+kpi('پیش‌نویس‌ها',s.drafts)+kpi('جمع اسناد صادرشده',fmt(s.issued_total)+' ریال');
  const r=$('#dashboard-recent');
  r.innerHTML=j.data.recent.length?'<table class="data-table"><thead><tr><th>شماره</th><th>شرکت</th><th>مشتری</th><th>تاریخ</th><th>مبلغ</th></tr></thead><tbody>'+j.data.recent.map(q=>'<tr><td>'+esc(q.quote_number)+'</td><td>'+esc(q.company_name)+'</td><td>'+esc(q.customer_name||'-')+'</td><td>'+esc(q.issue_date)+'</td><td>'+fmt(q.total)+'</td></tr>').join('')+'</tbody></table>':'<div class="empty">هنوز سندی ثبت نشده است.</div>';
  // Load charts
  loadDashboardCharts();
 }catch(x){toast(x.message)}
}

async function loadDashboardCharts(){
 try{
  const j=await api('dashboard-charts');
  const revenue=j.data.revenue||[];
  const status=j.data.status||[];
  const trend=j.data.trend||[];
  renderBarChart(revenue);
  renderPieChart(status);
  renderLineChart(trend);
 }catch(x){console.error('Chart load failed',x)}
}

function renderBarChart(data){
 const ctx=$('#chart-bar');
 if(!ctx)return;
 const labels=data.map(d=>d.month?toJalali(d.month+'-01'):'');
 const values=data.map(d=>+d.total||0);
 new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[{label:'درآمد (ریال)',data:values,backgroundColor:'#1C39BB',borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>fmt(v)+' ریال'}}}});
}

function renderPieChart(data){
 const ctx=$('#chart-pie');
 if(!ctx)return;
 const labels=data.map(d=>d.status==='issued'?'صادرشده':(d.status==='draft'?'پیش‌نویس':(d.status==='cancelled'?'لغو':'سایر')));
 const values=data.map(d=>+d.count||0);
 const colors=['#1C39BB','#00A693','#FFB300','#C8102E','#7C3AED','#64748B'];
 new Chart(ctx,{type:'doughnut',data:{labels:labels,datasets:[{data:values,backgroundColor:colors.slice(0,values.length),borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:16,font:{size:12}}}}});
}

function renderLineChart(data){
 const ctx=$('#chart-line');
 if(!ctx)return;
 const labels=data.map(d=>d.month?toJalali(d.month+'-01'):'');
 const countValues=data.map(d=>+d.count||0);
 const totalValues=data.map(d=>+d.total||0);
 new Chart(ctx,{type:'line',data:{labels:labels,datasets:[{label:'تعداد فاکتور',data:countValues,borderColor:'#1C39BB',backgroundColor:'rgba(28,57,187,0.1)',fill:true,tension:0.3,yAxisID:'y'},{label:'مبلغ کل (ریال)',data:totalValues,borderColor:'#00A693',backgroundColor:'rgba(0,166,147,0.1)',fill:true,tension:0.3,yAxisID:'y1'}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},scales:{y:{type:'linear',display:true,position:'right',beginAtZero:true,ticks:{callback:v=>fmt(v)}},y1:{type:'linear',display:true,position:'left',beginAtZero:true,grid:{drawOnChartArea:false},ticks:{callback:v=>fmt(v)+' ریال'}}}});
}
function kpi(label,value){return '<div class="kpi"><span>'+esc(label)+'</span><strong>'+value+'</strong></div>'}
const profileForm=$('#profile-form');if(profileForm)profileForm.onsubmit=async e=>{e.preventDefault();try{const j=await api('profile-save',Object.fromEntries(new FormData(e.target)));toast(j.message);e.target.current_password.value='';e.target.new_password.value='';e.target.password_confirmation.value=''}catch(x){toast(x.message)}};
async function loadAdminOverview(){const el=$('#admin-kpis');if(!el)return;try{const j=await api('admin-overview'),s=j.data;el.innerHTML=kpi('همه کاربران',s.users)+kpi('کاربران فعال',s.active_users)+kpi('شرکت‌ها',s.companies)+kpi('کالا و خدمات',s.catalog)+kpi('همه اسناد',s.quotes)+kpi('جمع صادرشده',fmt(s.issued_total)+' ریال')}catch(x){toast(x.message)}}
async function loadAdminUsers(){const el=$('#admin-users-list');if(!el)return;try{const q=encodeURIComponent(($('#admin-user-search')||{}).value||''),j=await api('admin-users&q='+q);el.innerHTML='<table class="data-table"><thead><tr><th>کاربر</th><th>موبایل</th><th>شرکت‌ها</th><th>اسناد</th><th>آخرین ورود</th><th>دسترسی</th></tr></thead><tbody>'+j.data.map(u=>'<tr><td>'+esc(u.name||'-')+'<br><small>'+esc(u.email||'')+'</small></td><td dir="ltr">'+esc(u.mobile)+'</td><td>'+u.companies_count+'</td><td>'+u.quotes_count+'</td><td>'+esc(u.last_login_at||'-')+'</td><td><div class="inline-actions"><select data-user-role="'+u.id+'"><option value="user" '+(u.role==='user'?'selected':'')+'>کاربر</option><option value="admin" '+(u.role==='admin'?'selected':'')+'>مدیر</option></select><label><input type="checkbox" data-user-active="'+u.id+'" '+(+u.is_active?'checked':'')+'> فعال</label><button class="btn small" data-user-save="'+u.id+'">ذخیره</button></div></td></tr>').join('')+'</tbody></table>';$$('[data-user-save]',el).forEach(b=>b.onclick=async()=>{const id=b.dataset.userSave;try{const j=await api('admin-user-save',{id:id,role:$('[data-user-role="'+id+'"]').value,is_active:$('[data-user-active="'+id+'"]').checked?1:0});toast(j.message);loadAdminUsers()}catch(x){toast(x.message)}})}catch(x){toast(x.message)}}
const aus=$('#admin-user-search-btn');if(aus)aus.onclick=loadAdminUsers;
async function loadAdminCatalog(){const el=$('#admin-catalog-list');if(!el)return;try{const q=encodeURIComponent(($('#admin-catalog-search')||{}).value||''),j=await api('admin-catalog&q='+q);el.innerHTML='<table class="data-table"><thead><tr><th>مالک/شرکت</th><th>نام</th><th>نوع</th><th>واحد</th><th>آخرین قیمت</th><th>وضعیت</th><th></th></tr></thead><tbody>'+j.data.map(i=>'<tr><td>'+esc(i.user_name||i.user_mobile)+'<br><small>'+esc(i.company_name)+'</small></td><td><input data-cat-name="'+i.id+'" value="'+attr(i.name)+'"></td><td><select data-cat-type="'+i.id+'"><option value="product" '+(i.type==='product'?'selected':'')+'>کالا</option><option value="service" '+(i.type==='service'?'selected':'')+'>خدمت</option></select></td><td><input data-cat-unit="'+i.id+'" value="'+attr(i.unit)+'"></td><td><input type="number" data-cat-price="'+i.id+'" value="'+attr(i.last_price)+'"></td><td><input type="checkbox" data-cat-active="'+i.id+'" '+(+i.is_active?'checked':'')+'></td><td><button class="btn small" data-cat-save="'+i.id+'">ذخیره</button></td></tr>').join('')+'</tbody></table>';$$('[data-cat-save]',el).forEach(b=>b.onclick=async()=>{const id=b.dataset.catSave;try{const j=await api('admin-catalog-save',{id:id,name:$('[data-cat-name="'+id+'"]').value,type:$('[data-cat-type="'+id+'"]').value,unit:$('[data-cat-unit="'+id+'"]').value,last_price:$('[data-cat-price="'+id+'"]').value,is_active:$('[data-cat-active="'+id+'"]').checked?1:0});toast(j.message)}catch(x){toast(x.message)}})}catch(x){toast(x.message)}}
const acs=$('#admin-catalog-search-btn');if(acs)acs.onclick=loadAdminCatalog;
const outputOrderLabels={header:'سربرگ',seller:'مشخصات فروشنده',buyer:'مشخصات خریدار',items:'جدول اقلام',notes:'توضیحات و پرداخت',signatures:'مهر و امضا',footer:'پاورقی'};let outputOrder=Object.keys(outputOrderLabels),adminOutputTemplates=[];
function renderSectionOrder(){const box=$('#section-order');if(!box)return;box.innerHTML=outputOrder.map((k,i)=>'<div><span>'+(i+1)+'. '+outputOrderLabels[k]+'</span><button type="button" class="btn small" data-order-up="'+k+'">↑</button><button type="button" class="btn small" data-order-down="'+k+'">↓</button></div>').join('');$$('[data-order-up]',box).forEach(b=>b.onclick=()=>moveOutputSection(b.dataset.orderUp,-1));$$('[data-order-down]',box).forEach(b=>b.onclick=()=>moveOutputSection(b.dataset.orderDown,1))}
function moveOutputSection(key,delta){const i=outputOrder.indexOf(key),n=i+delta;if(i<0||n<0||n>=outputOrder.length)return;[outputOrder[i],outputOrder[n]]=[outputOrder[n],outputOrder[i]];renderSectionOrder()}
async function loadOutputTemplates(){try{const j=await api('admin-output-templates');adminOutputTemplates=j.data;renderOutputTemplateList();if(!$('#output-template-form').elements.id.value)fillOutputTemplate(adminOutputTemplates[0]||null)}catch(x){toast(x.message)}}
function renderOutputTemplateList(){const el=$('#output-template-list');if(!el)return;el.innerHTML=adminOutputTemplates.length?adminOutputTemplates.map(t=>'<button type="button" class="designer-template '+(+t.is_default?'default':'')+'" data-output-id="'+t.id+'"><b>'+esc(t.name)+'</b><small>'+t.paper_size.toUpperCase()+' '+(t.orientation==='landscape'?'افقی':'عمودی')+' · '+(+t.is_active?'فعال':'غیرفعال')+(+t.is_default?' · پیش‌فرض':'')+'</small></button>').join(''):'<div class="empty">فرمی ساخته نشده است.</div>';$$('[data-output-id]',el).forEach(b=>b.onclick=()=>fillOutputTemplate(adminOutputTemplates.find(t=>+t.id===+b.dataset.outputId)))}
function fillOutputTemplate(t){const f=$('#output-template-form');f.reset();f.elements.id.value=t?t.id:'';f.elements.name.value=t?t.name:'';f.elements.paper_size.value=t?t.paper_size:'a4';f.elements.orientation.value=t?t.orientation:'landscape';f.elements.style.value=t?t.style:'formal';f.elements.is_active.checked=t?!!+t.is_active:true;f.elements.is_default.checked=t?!!+t.is_default:false;$$('[data-column]',f).forEach(x=>x.checked=!t||((t.config.columns||{})[x.dataset.column]!==false));$$('[data-section]',f).forEach(x=>x.checked=!t||((t.config.sections||{})[x.dataset.section]!==false));outputOrder=t&&Array.isArray(t.config.order)?t.config.order.slice():Object.keys(outputOrderLabels);renderSectionOrder()}
const designerPaperSelect=$('#output-template-form').elements.paper_size;[['a3','A3'],['a6','A6'],['legal','Legal']].forEach(([value,label])=>{if(!Array.from(designerPaperSelect.options).some(o=>o.value===value))designerPaperSelect.add(new Option(label,value))});
const newOutput=$('#new-output-template');if(newOutput)newOutput.onclick=()=>fillOutputTemplate(null);const outputForm=$('#output-template-form');if(outputForm)outputForm.onsubmit=async e=>{e.preventDefault();const f=e.target,body={id:+f.elements.id.value||0,name:f.elements.name.value,paper_size:f.elements.paper_size.value,orientation:f.elements.orientation.value,style:f.elements.style.value,is_active:f.elements.is_active.checked?1:0,is_default:f.elements.is_default.checked?1:0,columns:{},sections:{},order:outputOrder};$$('[data-column]',f).forEach(x=>body.columns[x.dataset.column]=x.checked?1:0);$$('[data-section]',f).forEach(x=>body.sections[x.dataset.section]=x.checked?1:0);try{const j=await api('admin-output-template-save',body);toast(j.message);f.elements.id.value=j.id;await loadOutputTemplates();const bootData=await api('bootstrap');state.templates=bootData.data.templates||[];state.activeOutputTemplate=state.templates[0]||null;renderOutputTemplateChoices()}catch(x){toast(x.message)}};
if(outputForm){const copyButton=document.createElement('button');copyButton.type='button';copyButton.className='btn';copyButton.textContent='ساخت کپی از این فرم';copyButton.onclick=()=>{outputForm.elements.id.value='';outputForm.elements.name.value=(outputForm.elements.name.value||'فرم خروجی')+' - کپی';outputForm.elements.is_default.checked=false;toast('نسخه کپی آماده است؛ برای ثبت روی ذخیره فرم بزنید.')};outputForm.querySelector('.actions').insertBefore(copyButton,$('#delete-output-template'))}
const deleteOutput=$('#delete-output-template');if(deleteOutput)deleteOutput.onclick=async()=>{const id=+$('#output-template-form').elements.id.value;if(!id)return toast('ابتدا یک فرم ذخیره‌شده را انتخاب کنید.');try{const j=await api('admin-output-template-delete',{id:id});toast(j.message);fillOutputTemplate(null);loadOutputTemplates()}catch(x){toast(x.message)}};
async function loadAdminSettings(){const f=$('#admin-settings-form');if(!f)return;try{const j=await api('admin-settings');Object.keys(j.data).forEach(k=>{if(f.elements[k])f.elements[k].value=j.data[k]});$('#api-key-status').textContent=j.data['sms.api_key_configured']?'کلید قبلاً ثبت شده؛ برای حفظ آن این فیلد را خالی بگذارید.':'هنوز کلیدی ثبت نشده است.'}catch(x){toast(x.message)}}
const settingsForm=$('#admin-settings-form');if(settingsForm)settingsForm.onsubmit=async e=>{e.preventDefault();try{const j=await api('admin-settings-save',Object.fromEntries(new FormData(e.target)));toast(j.message);e.target.elements['sms.api_key'].value='';loadAdminSettings()}catch(x){toast(x.message)}};
function toJalali(iso){if(!iso)return'';const date=new Date(iso+'T12:00:00'),parts=new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn',{year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(date),get=t=>(parts.find(p=>p.type===t)||{}).value||'';return get('year')+'/'+get('month')+'/'+get('day')}if(window.jalaliDatepicker)jalaliDatepicker.startWatch({targetValueInput:'attr',targetValueType:'attr',persianDigits:true,autoHide:true,showTodayBtn:true});function attr(v){return esc(v).replace(/"/g,'&quot;')}function esc(v){const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML}document.addEventListener('click',e=>{if(!e.target.closest('.autocomplete'))$$('.results').forEach(x=>x.classList.remove('open'))});let chatPollTimer=null;
function stopChatPolling(){if(chatPollTimer){clearInterval(chatPollTimer);chatPollTimer=null}}
function renderChatBubbles(el,rows,mineRole){el.innerHTML=rows.map(m=>'<div class="chat-bubble '+(m.sender_role===mineRole?'mine':'theirs')+'">'+esc(m.body)+'<time>'+esc(m.created_at)+'</time></div>').join('')||'<div class="empty">هنوز پیامی ثبت نشده است.</div>';el.scrollTop=el.scrollHeight}
async function loadChat(){try{const j=await api('chat-messages');renderChatBubbles($('#chat-messages'),j.data,'user');setChatBadge('#chat-unread-badge',0)}catch(x){toast(x.message)}}
function startUserChat(){loadChat();stopChatPolling();chatPollTimer=setInterval(loadChat,8000)}
const chatForm=$('#chat-form');if(chatForm)chatForm.onsubmit=async e=>{e.preventDefault();const ta=chatForm.elements.body,body=ta.value.trim();if(!body)return;try{await api('chat-send',{body:body});ta.value='';await loadChat()}catch(x){toast(x.message)}};

let activeAdminChatUserId=null;
async function loadAdminChatThreads(){const el=$('#admin-chat-thread-list');if(!el)return;try{const j=await api('admin-chat-threads');el.innerHTML=j.data.map(t=>'<div class="admin-chat-thread'+(t.user_id==activeAdminChatUserId?' active':'')+'" data-thread="'+t.user_id+'"><div class="name">'+esc(t.user_name||t.user_mobile)+(t.unread>0?'<span class="badge">'+t.unread+'</span>':'')+'</div><div class="preview">'+esc(t.last_body||'')+'</div></div>').join('')||'<div class="empty">هنوز گفتگویی ثبت نشده است.</div>';$$('[data-thread]',el).forEach(d=>d.onclick=()=>openAdminChatThread(parseInt(d.dataset.thread,10),d.querySelector('.name').textContent.trim()));const totalUnread=j.data.reduce((s,t)=>s+(+t.unread||0),0);setChatBadge('#admin-chat-unread-badge',totalUnread)}catch(x){toast(x.message)}}
async function openAdminChatThread(userId,label){activeAdminChatUserId=userId;$('#admin-chat-active-title').textContent=label||('کاربر #'+userId);await loadAdminChatMessages();loadAdminChatThreads()}
async function loadAdminChatMessages(){if(!activeAdminChatUserId)return;try{const j=await api('admin-chat-messages&user_id='+activeAdminChatUserId);renderChatBubbles($('#admin-chat-messages'),j.data,'admin')}catch(x){toast(x.message)}}
function startAdminChat(){loadAdminChatThreads();if(activeAdminChatUserId)loadAdminChatMessages();stopChatPolling();chatPollTimer=setInterval(()=>{loadAdminChatThreads();if(activeAdminChatUserId)loadAdminChatMessages()},8000)}
const adminChatForm=$('#admin-chat-form');if(adminChatForm)adminChatForm.onsubmit=async e=>{e.preventDefault();if(!activeAdminChatUserId){toast('ابتدا یک گفتگو یا کاربر را انتخاب کنید.');return}const ta=adminChatForm.elements.body,body=ta.value.trim();if(!body)return;try{await api('admin-chat-send',{user_id:activeAdminChatUserId,body:body});ta.value='';await loadAdminChatMessages();loadAdminChatThreads()}catch(x){toast(x.message)}};
let adminChatSearchTimer=null;const acsInput=$('#admin-chat-search');if(acsInput)acsInput.oninput=()=>{clearTimeout(adminChatSearchTimer);adminChatSearchTimer=setTimeout(async()=>{const q=acsInput.value.trim(),box=$('#admin-chat-search-results');if(!q){box.innerHTML='';return}try{const j=await api('admin-users&q='+encodeURIComponent(q));box.innerHTML=j.data.slice(0,10).map(u=>'<div class="result" data-uid="'+u.id+'" data-uname="'+attr(u.name||u.mobile)+'">'+esc(u.name||u.mobile)+' — '+esc(u.mobile)+'</div>').join('');$$('.result',box).forEach(r=>r.onclick=()=>{openAdminChatThread(parseInt(r.dataset.uid,10),r.dataset.uname);box.innerHTML='';acsInput.value=''})}catch(x){toast(x.message)}},350)};

// Ticket status labels
const ticketStatusLabels={open:'باز',in_progress:'در حال بررسی',waiting_customer:'منتظر پاسخ شما',closed:'بسته'};
const ticketPriorityLabels={low:'کم',normal:'معمولی',high:'بالا',urgent:'فوری'};

function showMyTicketsView(){$('#my-tickets-list').hidden=false;$('#my-ticket-form-panel').hidden=true;$('#my-ticket-detail').hidden=true;loadMyTickets();}

let myTicketsFilter='';

async function loadMyTickets(){
 try{
  const status=myTicketsFilter;
  const j=await api('tickets-list'+(status?'&status='+status:''));
  const el=$('#my-tickets-list');
  if(!j.data.length){
   el.innerHTML='<div class="empty">هنوز تیکتی ثبت نشده است.<br><br><button type="button" class="btn primary" id="new-my-ticket-btn">+ تیکت جدید</button></div>';
   $('#new-my-ticket-btn').onclick=()=>showMyTicketForm();
   return;
  }
  el.innerHTML='<table class="data-table"><thead><tr><th>موضوع</th><th>شرکت</th><th>وضعیت</th><th>اولویت</th><th>آخرین پاسخ</th><th></th></tr></thead><tbody>'+j.data.map(t=>'<tr><td>'+esc(t.subject)+'</td><td>'+esc(t.company_name||'-')+'</td><td><span class="status-badge status-'+t.status+'">'+ticketStatusLabels[t.status]+'</span></td><td>'+ticketPriorityLabels[t.priority]+'</td><td>'+esc(t.last_reply_at||'-')+'</td><td><button class="btn small" onclick="openMyTicketDetail('+t.id+')">مشاهده</button></td></tr>').join('')+'</tbody></table>';
  $$('.status-badge',el).forEach(b=>b.classList.add('status-'+b.dataset.status));
 }catch(x){toast(x.message)}
}

$('#my-tickets-filter').onchange=e=>{myTicketsFilter=e.target.value;loadMyTickets()};

function showMyTicketForm(ticket=null){
 $('#my-tickets-list').hidden=true;
 $('#my-ticket-form-panel').hidden=false;
 $('#my-ticket-detail').hidden=true;
 const f=$('#my-ticket-form');
 f.reset();
 f.elements.id.value=ticket?ticket.id:'';
 $('#my-ticket-form-title').textContent=ticket?'ویرایش تیکت':'ایجاد تیکت جدید';
 f.elements.company_id.innerHTML='<option value="">بدون شرکت</option>'+state.companies.map(c=>'<option value="'+c.id+'">'+esc(c.name)+'</option>').join('');
 if(ticket)f.elements.company_id.value=ticket.company_id||'';
}

$('#back-my-tickets-btn').onclick=showMyTicketsView;
$('#back-my-tickets-btn2').onclick=showMyTicketsView;

$('#my-ticket-form').onsubmit=async e=>{
 e.preventDefault();
 const body=Object.fromEntries(new FormData(e.target));
 if(body.id){
  // Update not implemented for user tickets - they can only create or reply
  toast('برای تغییر تیکت، از دکمه پاسخ استفاده کنید.');
  return;
 }
 try{
  const j=await api('ticket-create',body);
  toast(j.message);
  showMyTicketsView();
 }catch(x){toast(x.message)}
};

async function openMyTicketDetail(id){
 try{
  const j=await api('ticket-get&id='+id);
  const t=j.data.ticket;
  const messages=j.data.messages;
  $('#my-tickets-list').hidden=true;
  $('#my-ticket-form-panel').hidden=true;
  $('#my-ticket-detail').hidden=false;
  $('#my-ticket-detail-title').textContent='تیکت: '+esc(t.subject);
  const msgHtml=messages.map(m=>'<div class="chat-bubble '+(m.sender_role==='user'?'mine':'theirs')+(m.is_internal?' internal':'')+'"><strong>'+(m.sender_role==='user'?'شما':(m.sender_role==='support'?'پشتیبانی':'مدیر'))+'</strong>: '+esc(m.body)+'<time>'+esc(m.created_at)+'</time></div>').join('');
  $('#my-ticket-detail-content').innerHTML='<div class="ticket-header"><div><strong>موضوع:</strong> '+esc(t.subject)+'</div><div><strong>وضعیت:</strong> <span class="status-badge status-'+t.status+'">'+ticketStatusLabels[t.status]+'</span></div><div><strong>اولویت:</strong> '+ticketPriorityLabels[t.priority]+'</div><div><strong>شرکت:</strong> '+esc(t.company_name||'-')+'</div><div><strong>ایجاد شده:</strong> '+esc(t.created_at)+'</div></div><div class="ticket-messages">'+msgHtml+'</div>'+(t.status!=='closed'?'<form id="my-ticket-reply-form" class="chat-form" style="margin-top:16px"><textarea name="body" placeholder="پاسخ خود را بنویسید..." required></textarea><button class="btn primary">ارسال پاسخ</button></form>':'<p class="muted">این تیکت بسته شده است.</p>');
  $('#my-ticket-reply-form')&&($('#my-ticket-reply-form').onsubmit=async e=>{
   e.preventDefault();
   const body=e.target.body.value.trim();
   if(!body)return;
   try{
    await api('ticket-reply',{id:id,body:body});
    toast('پاسخ شما ثبت شد.');
    openMyTicketDetail(id);
   }catch(x){toast(x.message)}
  });
  if(t.status!=='closed'){
   $('#my-ticket-close-btn')&&($('#my-ticket-close-btn').onclick=async()=>{
    if(!confirm('آیا مطمئن هستید که می‌خواهید این تیکت را ببندید؟'))return;
    try{await api('ticket-close',{id:id});toast('تیکت بسته شد.');showMyTicketsView();}catch(x){toast(x.message)}
   });
  }
 }catch(x){toast(x.message)}
}

// Admin Tickets
let adminTicketsFilter='';
let adminTicketsAssigned='';
let activeAdminTicketId=null;

async function loadAdminTickets(){
 try{
  const status=adminTicketsFilter;
  const assigned=adminTicketsAssigned;
  let url='admin-tickets';
  if(status)url+='&status='+status;
  if(assigned)url+='&assigned='+assigned;
  const j=await api(url);
  const el=$('#admin-tickets-list');
  if(!j.data.length){
   el.innerHTML='<div class="empty">تیکتی یافت نشد.</div>';return;
  }
  el.innerHTML='<table class="data-table"><thead><tr><th>موضوع</th><th>کاربر</th><th>شرکت</th><th>وضعیت</th><th>اولویت</th><th>مسئول</th><th>آخرین پاسخ</th><th></th></tr></thead><tbody>'+j.data.map(t=>'<tr><td>'+esc(t.subject)+'</td><td>'+esc(t.user_name)+'<br><small>'+esc(t.user_mobile)+'</small></td><td>'+esc(t.company_name||'-')+'</td><td><span class="status-badge status-'+t.status+'">'+ticketStatusLabels[t.status]+'</span></td><td>'+ticketPriorityLabels[t.priority]+'</td><td>'+esc(t.assigned_name||'-')+'</td><td>'+esc(t.last_reply_at||'-')+'</td><td><button class="btn small" onclick="openAdminTicketDetail('+t.id+')">مشاهده</button></td></tr>').join('')+'</tbody></table>';
 }catch(x){toast(x.message)}
}

$('#admin-tickets-filter')&&($('#admin-tickets-filter').onchange=e=>{adminTicketsFilter=e.target.value;loadAdminTickets();});
$('#admin-tickets-assigned')&&($('#admin-tickets-assigned').onchange=e=>{adminTicketsAssigned=e.target.value;loadAdminTickets();});

async function openAdminTicketDetail(id){
 activeAdminTicketId=id;
 try{
  const j=await api('admin-ticket-get&id='+id);
  const t=j.data.ticket;
  const messages=j.data.messages;
  const staff=j.data.staff;
  $('#view-admin-tickets').hidden=true;
  $('#view-admin-ticket-detail').hidden=false;
  $('#admin-ticket-detail-title').textContent='تیکت #' + id + ': ' + esc(t.subject);
  const msgHtml=messages.map(m=>'<div class="chat-bubble '+(m.sender_role==='user'?'theirs':(m.sender_role==='support'?'mine':'mine'))+(m.is_internal?' internal':'')+'"><strong>'+(m.sender_role==='user'?esc(t.user_name):(m.sender_role==='support'?'پشتیبانی (داخلی)':'مدیر'))+'</strong>: '+esc(m.body)+'<time>'+esc(m.created_at)+(m.is_internal?' <span class="internal-badge">داخلی</span>':'')+'</time></div>').join('');
  $('#admin-ticket-messages').innerHTML=msgHtml;
  // Meta
  $('.ticket-meta').innerHTML='<div><strong>کاربر:</strong> '+esc(t.user_name)+' ('+esc(t.user_mobile)+')</div><div><strong>ایمیل:</strong> '+esc(t.user_email)+'</div><div><strong>شرکت:</strong> '+esc(t.company_name||'-')+'</div><div><strong>وضعیت:</strong> <span class="status-badge status-'+t.status+'">'+ticketStatusLabels[t.status]+'</span></div><div><strong>اولویت:</strong> '+ticketPriorityLabels[t.priority]+'</div><div><strong>مسئول:</strong> '+esc(t.assigned_name||'-')+'</div><div><strong>ایجاد شده:</strong> '+esc(t.created_at)+'</div>';
  // Actions - populate assigned_to select
  const assignedSelect=$('#admin-ticket-update-form').elements.assigned_to;
  assignedSelect.innerHTML='<option value="">بدون مسئول</option>'+staff.map(s=>'<option value="'+s.id+'">'+esc(s.name)+'</option>').join('');
  assignedSelect.value=t.assigned_to||'';
  $('#admin-ticket-update-form').elements.status.value=t.status;
  $('#admin-ticket-update-form').elements.priority.value=t.priority;
  $('#admin-ticket-update-form').elements.id.value=id;
 }catch(x){toast(x.message)}
}

$('#back-admin-tickets-btn').onclick=()=>{$('#view-admin-tickets').hidden=false;$('#view-admin-ticket-detail').hidden=true;loadAdminTickets();};

$('#admin-ticket-reply-form')&&($('#admin-ticket-reply-form').onsubmit=async e=>{
 e.preventDefault();
 if(!activeAdminTicketId)return;
 const body=e.target.body.value.trim();
 const isInternal=e.target.is_internal.checked?1:0;
 if(!body)return;
 try{
  await api('admin-ticket-reply',{id:activeAdminTicketId,body:body,is_internal:isInternal});
  e.target.body.value='';
  toast('پاسخ ثبت شد.');
  openAdminTicketDetail(activeAdminTicketId);
 }catch(x){toast(x.message)}
});

$('#admin-ticket-update-form')&&($('#admin-ticket-update-form').onsubmit=async e=>{
 e.preventDefault();
 const id=+e.target.elements.id.value;
 const body={id:id,status:e.target.elements.status.value,assigned_to:+e.target.elements.assigned_to.value||0,priority:e.target.elements.priority.value};
 try{
  await api('admin-ticket-save',body);
  toast('تیکت به‌روزرسانی شد.');
  openAdminTicketDetail(id);
  loadAdminTickets();
 }catch(x){toast(x.message)}
});

// Admin Support Staff
async function loadSupportStaff(){
 try{
  const j=await api('admin-support-staff');
  const el=$('#support-staff-list');
  if(!j.data.length){
   el.innerHTML='<div class="empty">پرسنل پشتیبانی ثبت نشده است.</div>';return;
  }
  el.innerHTML='<table class="data-table"><thead><tr><th>کاربر</th><th>موبایل</th><th>دپارتمان</th><th>حداکثر تیکت</th><th>وضعیت</th><th></th></tr></thead><tbody>'+j.data.map(s=>'<tr><td>'+esc(s.name)+'</td><td>'+esc(s.mobile)+'</td><td>'+esc(s.department||'-')+'</td><td>'+s.max_tickets+'</td><td>'+(s.is_active?'<span class="status-badge status-open">فعال</span>':'<span class="status-badge status-closed">غیرفعال</span>')+'</td><td><button class="btn small" data-edit="'+s.id+'">ویرایش</button><button class="btn small" data-delete="'+s.id+'">حذف</button></td></tr>').join('')+'</tbody></table>';
  $$('[data-edit]',el).forEach(b=>b.onclick=()=>openSupportStaffForm(j.data.find(s=>s.id==b.dataset.edit)));
  $$('[data-delete]',el).forEach(b=>b.onclick=async()=>{if(!confirm('حذف پرسنل پشتیبانی؟'))return;try{await api('admin-support-staff-delete',{id:b.dataset.delete});toast('حذف شد.');loadSupportStaff();}catch(x){toast(x.message)}});
 }catch(x){toast(x.message)}
}

function openSupportStaffForm(staff=null){
 $('#support-staff-list').closest('.panel').hidden=true;
 $('#support-staff-form-panel').hidden=false;
 const f=$('#support-staff-form');
 f.reset();
 f.elements.id.value=staff?staff.id:'';
 $('#support-staff-form-title').textContent=staff?'ویرایش پرسنل':'افزودن پرسنل';
 // Populate users
 const users=await api('admin-users&q=');
 f.elements.user_id.innerHTML=users.data.map(u=>'<option value="'+u.id+'">'+esc(u.name||u.mobile)+' ('+esc(u.mobile)+')</option>').join('');
 if(staff)f.elements.user_id.value=staff.user_id;
 f.elements.department.value=staff?staff.department:'';
 f.elements.max_tickets.value=staff?staff.max_tickets:20;
 f.elements.is_active.checked=staff?!!staff.is_active:true;
}

$('#new-support-staff').onclick=()=>openSupportStaffForm(null);
$('#back-support-staff-btn').onclick=()=>{$('#support-staff-form-panel').hidden=true;$('#support-staff-list').closest('.panel').hidden=false;loadSupportStaff();};

$('#support-staff-form')&&($('#support-staff-form').onsubmit=async e=>{
 e.preventDefault();
 const body=Object.fromEntries(new FormData(e.target));
 body.is_active=body.is_active?1:0;
 try{
  await api('admin-support-staff-save',body);
  toast('ذخیره شد.');
  $('#back-support-staff-btn').click();
 }catch(x){toast(x.message)}
});

// My Templates
const dimaFonts=[
 'Vazirmatn','DimaYekanWeb','DimaYekanWebBold','DimaYekanRegular','DimaYekanOutline',
 'DimaWeb','Dima2','DimaAbdo','DimaAdan','DimaAra','DimaAraReqular','DimaArsalan',
 'DimaBahman','DimaBarf','DimaBarf2','DimaBlue','DimaCut','DimaDigital','DimaEsfahan',
 'DimaExpo','DimaFantasy','DimaFantasy2','DimaFont','DimaFred','DimaHallFetica',
 'DimaKereshmeh','DimaKhabar','DimaKhabar2','DimaKhaled','DimaKoodak','DimaLatifi',
 'DimaMabella','DimaMahdi','DimaMakeen','DimaMakhtom','DimaMoalemBlack','DimaMolsaq',
 'DimaMothnna','DimaNasim','DimaNaskh','DimaNastaliq','DimaNastalighTahriri','DimaNazBold',
 'DimaNiloofar','DimaNotoRegular','DimaNotoBold','DimaPlatinum','DimaRavanNevis','DimaReyhan',
 'DimaSalam','DimaShekari','DimaShekasteh','DimaShekastehFree2','DimaSogand','Dimathuluth',
 'DimaTraffic','DimaUbuntu','DimaYekanTypography','DimaZar','BalvardiDastnevisFree'
];
const myTemplateSections=['header','body','table','notes','footer'];
const mySectionOrderLabels={header:'سربرگ',seller:'مشخصات فروشنده',buyer:'مشخصات خریدار',items:'جدول اقلام',notes:'توضیحات و پرداخت',signatures:'مهر و امضا',footer:'پاورقی'};
let myOutputOrder=Object.keys(mySectionOrderLabels);

async function showMyTemplatesView(){
 $('#my-template-form').hidden=true;
 $('#my-template-list').closest('.panel').hidden=false;
 await loadMyTemplates();
}

async function loadMyTemplates(){
 try{
  const j=await api('my-templates');
  state.myTemplates=j.data||[];
  renderMyTemplateList();
 }catch(x){toast(x.message)}
}

function renderMyTemplateList(){
 const el=$('#my-template-list');
 if(!state.myTemplates.length){
  el.innerHTML='<div class="empty">هنوز قالبی ایجاد نکرده‌اید.<br><br><button type="button" class="btn primary" onclick="showMyTemplateForm()">+ اولین قالب را بسازید</button></div>';return;
 }
 el.innerHTML=state.myTemplates.map(t=>'<button type="button" class="designer-template '+(+t.is_default?'default':'')+'" data-tid="'+t.id+'"><b>'+esc(t.name)+'</b><small>'+t.paper_size.toUpperCase()+' '+(t.orientation==='landscape'?'افقی':'عمودی')+' · '+(+t.is_active?'فعال':'غیرفعال')+(+t.is_default?' · پیش‌فرض':'')+'</small></button>').join('');
 $$('[data-tid]',el).forEach(b=>b.onclick=()=>openMyTemplateForm(state.myTemplates.find(t=>+t.id===+b.dataset.tid)));
}

function showMyTemplateForm(template=null){
 $('#my-template-list').closest('.panel').hidden=true;
 $('#my-template-form').hidden=false;
 const f=$('#my-template-form');
 f.reset();
 f.elements.id.value=template?template.id:'';
 if(template){
  f.elements.name.value=template.name;
  f.elements.paper_size.value=template.paper_size;
  f.elements.orientation.value=template.orientation;
  f.elements.style.value=template.style;
  f.elements.is_active.checked=!!+template.is_active;
  f.elements.is_default.checked=!!+template.is_default;
  const cfg=template.config||{};
  $$('[data-column]',f).forEach(x=>x.checked=!!(cfg.columns||{})[x.dataset.column]);
  $$('[data-section]',f).forEach(x=>x.checked=!!(cfg.sections||{})[x.dataset.section]);
  myOutputOrder=template.config&&Array.isArray(template.config.order)?template.config.order.slice():Object.keys(mySectionOrderLabels);
 }else{
  $$('[data-column]',f).forEach(x=>x.checked=true);
  $$('[data-section]',f).forEach(x=>x.checked=true);
  myOutputOrder=Object.keys(mySectionOrderLabels).slice();
 }
 renderMySectionOrder();
 renderMyTypography(template?.typography||{});
}

function renderMySectionOrder(){
 const box=$('#my-section-order');
 if(!box)return;
 box.innerHTML=myOutputOrder.map((k,i)=>'<div><span>'+(i+1)+'. '+mySectionOrderLabels[k]+'</span><button type="button" class="btn small" data-order-up="'+k+'">↑</button><button type="button" class="btn small" data-order-down="'+k+'">↓</button></div>').join('');
 $$('[data-order-up]',box).forEach(b=>b.onclick=()=>moveMySection(b.dataset.orderUp,-1));
 $$('[data-order-down]',box).forEach(b=>b.onclick=()=>moveMySection(b.dataset.orderDown,1));
}

function moveMySection(key,delta){
 const i=myOutputOrder.indexOf(key),n=i+delta;
 if(i<0||n<0||n>=myOutputOrder.length)return;
 [myOutputOrder[i],myOutputOrder[n]]=[myOutputOrder[n],myOutputOrder[i]];
 renderMySectionOrder();
}

function renderMyTypography(typography={}){
 const box=$('#my-typography-controls');
 if(!box)return;
 box.innerHTML=myTemplateSections.map(section=>`
  <div class="typography-section" style="border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:12px;background:#f8fafc">
   <h4 style="margin:0 0 12px;font-size:13px;font-weight:700;color:var(--text)">`+section+`
    <button type="button" class="btn small" onclick="applyTypographyToPreview('`+section+`')" style="float:left;font-size:11px;padding:4px 8px">پیش‌نمایش</button>
   </h4>
   <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
    <label>فونت
     <select name="typography_font_`+section+`" class="font-select">
      <option value="">پیش‌فرض سیستم</option>
      `+dimaFonts.map(f=>'<option value="'+f+'" '+(typography[section]?.font_family===f?'selected':'')+'>'+f+'</option>').join('')+`
     </select>
    </label>
    <label>سایز (px)
     <input type="number" name="typography_size_`+section+`" min="6" max="24" step="0.5" value="`+(typography[section]?.font_size||'')+`" placeholder="پیش‌فرض">
    </label>
   </div>
  </div>
 `).join('');
}

function applyTypographyToPreview(section){
 // Apply to preview sheet for live preview
 const sheet=$('#print-sheet');
 if(!sheet)return;
 const f=$('#my-template-form');
 const font=f.elements['typography_font_'+section]?.value;
 const size=f.elements['typography_size_'+section]?.value;
 if(font)sheet.style.setProperty('--font-family-'+section,font);
 if(size)sheet.style.setProperty('--font-size-'+section,size+'px');
 // Trigger preview rebuild
 buildPreview();
}

function openMyTemplateForm(template){
 showMyTemplateForm(template);
}

$('#new-my-template').onclick=()=>showMyTemplateForm(null);
$('#cancel-my-template').onclick=showMyTemplatesView;

$('#my-template-form').onsubmit=async e=>{
 e.preventDefault();
 const f=e.target;
 const body={id:+f.elements.id.value||0,name:f.elements.name.value,paper_size:f.elements.paper_size.value,orientation:f.elements.orientation.value,style:f.elements.style.value,is_active:f.elements.is_active.checked?1:0,is_default:f.elements.is_default.checked?1:0,columns:{},sections:{},order:myOutputOrder};
 $$('[data-column]',f).forEach(x=>body.columns[x.dataset.column]=x.checked?1:0);
 $$('[data-section]',f).forEach(x=>body.sections[x.dataset.section]=x.checked?1:0);
 try{
  const j=await api('my-template-save',body);
  toast(j.message);
  f.elements.id.value=j.id;
  await loadMyTemplates();
  const bootData=await api('bootstrap');
  state.templates=bootData.data.templates||[];
  state.activeOutputTemplate=state.templates[0]||null;
  renderOutputTemplateChoices();
 }catch(x){toast(x.message)}
};

$('#duplicate-my-template').onclick=async()=>{
 const id=+$('#my-template-form').elements.id.value;
 if(!id)return toast('ابتدا یک قالب را انتخاب کنید.');
 try{
  const j=await api('my-template-duplicate',{id:id});
  toast(j.message);
  await loadMyTemplates();
  openMyTemplateForm(state.myTemplates.find(t=>+t.id===+j.id));
 }catch(x){toast(x.message)}
};

$('#delete-my-template').onclick=async()=>{
 const id=+$('#my-template-form').elements.id.value;
 if(!id)return toast('ابتدا یک قالب ذخیره‌شده را انتخاب کنید.');
 if(!confirm('این قالب حذف می‌شود. ادامه؟'))return;
 try{
  await api('my-template-delete',{id:id});
  toast('حذف شد.');
  showMyTemplatesView();
 }catch(x){toast(x.message)}
};

// Update showView to handle new views
const originalShowView=showView;
showView=function(name){
 $$('.view').forEach(x=>x.hidden=x.id!=='view-'+name);
 $$('.sidebar nav button').forEach(x=>x.classList.toggle('active',x.dataset.view===name));
 $('.sidebar').classList.remove('open');
 stopChatPolling();
 if(name==='home')loadDashboard();
 if(name==='company')showCompanyList();
 if(name==='admin')loadAdminOverview();
 if(name==='admin-users')loadAdminUsers();
 if(name==='admin-catalog')loadAdminCatalog();
 if(name==='admin-output')loadOutputTemplates();
 if(name==='admin-settings')loadAdminSettings();
 if(name==='chat')startUserChat();
 if(name==='admin-chat')startAdminChat();
 if(name==='my-tickets')showMyTicketsView();
 if(name==='my-templates')showMyTemplatesView();
 if(name==='admin-tickets'){loadAdminTickets();loadAdminTicketsBadge();}
 if(name==='admin-support-staff')loadSupportStaff();
 originalShowView(name);
};

async function loadAdminTicketsBadge(){
 try{
  const j=await api('admin-tickets&status=open');
  const count=j.data.filter(t=>t.status==='open' || t.status==='in_progress').length;
  setChatBadge('#admin-tickets-badge',count);
 }catch(e){}
}

boot().catch(x=>toast(x.message));
