

const API = "api/";
const $ = (s) => document.querySelector(s);
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
function toggleMenu(){ $("#mainNav")?.classList.toggle("open"); }
async function api(path, options={}) {
  const response = await fetch(API + path, {credentials:"same-origin", ...options});
  let data; try { 
      const text=await response.text(); 
      data = JSON.parse(text.replace(/^\uFEFF/,"").trim());
      
  } catch { throw new Error("تعذر قراءة استجابة الخادم"); }
  if (!response.ok || data.success === false) throw new Error(data.message || data.error || "حدث خطأ، حاول مرة أخرى");
  return data;
}
function message(el, text, success=false){ if(!el)return; el.textContent=text; el.className="message"+(success?" success":""); }
async function logout(){ try{await api("logout.php",{method:"POST"});}catch{} location.href="index.html"; }
async function updateAuth(){
  const links=$("#authLinks"), out=$("#logoutBtn");
  if(!links&&!out)return;
  try{const d=await api("check-login.php"); if(d.logged_in){if(links)links.classList.add("hidden");if(out)out.classList.remove("hidden");}}
  catch{}
}
function jobCard(j){
  return `<article class="job-card"><div class="job-top"><div class="company-icon">${esc((j.company||"ش").slice(0,1))}</div><span class="tag">${esc(j.job_type||"وظيفة")}</span></div><h3>${esc(j.title)}</h3><div class="company">${esc(j.company)}</div><div class="job-meta"><span>📍 ${esc(j.city||"غير محدد")}</span><span>▦ ${esc(j.category||"عام")}</span></div><div class="job-meta"><span>💰 ${esc(j.salary||"حسب الاتفاق")}</span></div><div class="job-actions"><a class="text-btn" href="job.html?id=${encodeURIComponent(j.id)}">التفاصيل والتقديم ←</a><button class="save-btn" onclick="saveJob(${Number(j.id)})">♡ حفظ</button></div></article>`;
}
let currentPage=1;
async function loadJobs(page=1){
    
  currentPage=page; const list=$("#jobsList"); if(!list)return;
  list.innerHTML='<div class="loading">جارٍ تحميل الوظائف...</div>';
  const p=new URLSearchParams({page:String(page)});
  const q=$("#searchText")?.value.trim(), city=$("#searchCity")?.value.trim(), cat=$("#searchCategory")?.value.trim();
  if(q)p.set("search",q);if(city)p.set("city",city);if(cat)p.set("category",cat);
  try{const d=await api("jobs.php?"+p.toString());
     
      const jobs=d.jobs||[];
     
      list.innerHTML=jobs.length?jobs.map(jobCard).join(""):'<div class="empty">لا توجد وظائف مطابقة حالياً.</div>';$("#jobsCount").textContent=`${d.count??jobs.length} وظيفة`;const pg=$("#pagination");if(pg)pg.innerHTML=`${page>1?`<button onclick="loadJobs(${page-1})">السابق</button>`:""}<button class="current">${page}</button>${jobs.length>=20?`<button onclick="loadJobs(${page+1})">التالي</button>`:""}`;}
  catch(e){list.innerHTML=`<div class="empty">${esc(e.message)}<br>تأكد من رفع ملفات API إلى مجلد api.</div>`;}
}
async function saveJob(id){try{await api("saved-jobs.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"save",job_id:id})});alert("تم حفظ الوظيفة");}catch(e){alert(e.message+" — سجّل الدخول أولاً إذا لم تكن مسجلاً.");}}
async function loadJob(){
 const box=$("#jobDetails");if(!box)return;const id=new URLSearchParams(location.search).get("id");if(!id){box.innerHTML='<div class="empty">لم يتم تحديد الوظيفة.</div>';return;}
 try{const d=await api("job-details.php?id="+encodeURIComponent(id));const j=d.job||d;document.title=esc(j.title)+" | فرصتي";box.innerHTML=`<section class="detail-main"><span class="eyebrow">${esc(j.category||"فرصة وظيفية")}</span><h1>${esc(j.title)}</h1><strong>${esc(j.company)}</strong><div class="job-meta"><span>📍 ${esc(j.city||"غير محدد")}</span><span>💼 ${esc(j.job_type||"غير محدد")}</span><span>💰 ${esc(j.salary||"حسب الاتفاق")}</span></div><hr><h3>وصف الوظيفة</h3><p>${esc(j.description)}</p><h3>المتطلبات</h3><p>${esc(j.requirements||"لم يتم تحديد متطلبات إضافية.")}</p><p class="muted">آخر موعد للتقديم: ${esc(j.deadline||"غير محدد")}</p></section><aside class="detail-side"><h3>مهتم بهذه الوظيفة؟</h3><p class="muted">قدّم طلبك الآن وأرفق سيرتك الذاتية.</p><a class="btn" href="apply.html?id=${encodeURIComponent(j.id)}">قدّم الآن</a><button class="save-btn" style="width:100%;margin-top:10px" onclick="saveJob(${Number(j.id)})">♡ حفظ الوظيفة</button></aside>`;}
 catch(e){box.innerHTML=`<div class="empty">${esc(e.message)}</div>`;}
}
async function initApply(){
 const form=$("#applyForm");if(!form)return;const id=new URLSearchParams(location.search).get("id");$("#applyJobId").value=id||"";
 if(id){try{const d=await api("job-details.php?id="+encodeURIComponent(id));const j=d.job||d;$("#applyJobTitle").textContent=j.title+" — "+j.company;}catch{}}
 form.addEventListener("submit",async e=>{e.preventDefault();const m=$("#formMessage");const fd=new FormData(form);try{const r=await api("apply.php",{method:"POST",body:fd});message(m,r.message||"تم إرسال طلبك بنجاح",true);form.reset();$("#applyJobId").value=id||"";}catch(err){message(m,err.message);}});
}
function initLogin(){
 const f=$("#loginForm");if(!f)return;f.addEventListener("submit",async e=>{e.preventDefault();const m=$("#formMessage");const v=Object.fromEntries(new FormData(f));try{await api("login.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(v)});message(m,"تم تسجيل الدخول بنجاح",true);location.href="index.html";}catch(err){message(m,err.message);}});
}
function initRegister(){
 const f=$("#registerForm");if(!f)return;f.addEventListener("submit",async e=>{e.preventDefault();const m=$("#formMessage");const v=Object.fromEntries(new FormData(f));try{const d=await api("register.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(v)});message(m,d.message||"تم إنشاء الحساب بنجاح. يمكنك تسجيل الدخول الآن.",true);f.reset();}catch(err){message(m,err.message);}});
}
async function loadSaved(){
 const box=$("#savedJobs");if(!box)return;try{const d=await api("saved-jobs.php");const jobs=d.jobs||[];box.innerHTML=jobs.length?jobs.map(j=>`<article class="job-card"><h3>${esc(j.title)}</h3><div class="company">${esc(j.company)}</div><div class="job-meta">📍 ${esc(j.city||"")}</div><div class="job-actions"><a class="text-btn" href="job.html?id=${encodeURIComponent(j.id)}">عرض الوظيفة ←</a><button class="save-btn" onclick="removeSaved(${Number(j.id)})">إزالة</button></div></article>`).join(""):'<div class="empty">لا توجد وظائف محفوظة حتى الآن.</div>';}catch(e){box.innerHTML=`<div class="empty">${esc(e.message)} — سجّل الدخول لعرض المحفوظات.</div>`;}
}
async function removeSaved(id){try{await api("saved-jobs.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"remove",job_id:id})});loadSaved();}catch(e){alert(e.message);}}
async function loadProfile(){
 const f=$("#profileForm");if(!f)return;try{const d=await api("profile.php");const u=d.user||d;f.elements.name.value=u.name||"";f.elements.email.value=u.email||"";f.elements.phone.value=u.phone||"";}catch(e){message($("#formMessage"),e.message+" — سجّل الدخول أولاً.");}
 f.addEventListener("submit",async e=>{e.preventDefault();const v=Object.fromEntries(new FormData(f));if(!v.new_password){delete v.new_password;delete v.current_password;}try{const d=await api("profile.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(v)});message($("#formMessage"),d.message||"تم تحديث البيانات",true);}catch(err){message($("#formMessage"),err.message);}});
}
document.addEventListener("DOMContentLoaded",()=>{
 const y=$("#year");if(y)y.textContent=new Date().getFullYear();
 updateAuth();loadJobs();loadJob();initLogin();initRegister();initApply();loadSaved();loadProfile();
 $("#searchForm")?.addEventListener("submit",e=>{e.preventDefault();loadJobs(1);});
});