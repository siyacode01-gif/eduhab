// Shared helpers used by every page
let CSRF='';
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function api(action,data={}){            // call PHP API, throw Error(message) on failure
  if(!CSRF) await boot();
  const r=await fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':CSRF},body:JSON.stringify(data)});
  const j=await r.json().catch(()=>({error:'Server error'}));
  if(!r.ok) throw new Error(j.error||'Something went wrong');
  return j;
}
async function boot(role){                      // load session + CSRF token; send wrong roles to login
  const r=await(await fetch('api.php?action=me')).json(); CSRF=r.csrf;
  if(role&&(!r.user||r.user.role!==role)){location='index.html';return null;}
  if(r.user&&$('who'))$('who').textContent=r.user.name+' ('+r.user.role+')';
  return r.user;
}
function toast(m,err){const t=$('toast');t.textContent=m;t.className=err?'err':'';t.hidden=false;clearTimeout(toast.t);toast.t=setTimeout(()=>t.hidden=true,4000);}
async function run(fn){try{await fn()}catch(e){toast(e.message,true)}}   // run an action, show errors as toast
function show(v){document.querySelectorAll('main>section').forEach(s=>s.hidden=s.id!=='v-'+v);document.querySelectorAll('nav button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));}
function openModal(html){$('modalBody').innerHTML=html;$('modal').hidden=false;}
function closeModal(){$('modal').hidden=true;}
async function logout(){await api('logout');location='index.html';}
