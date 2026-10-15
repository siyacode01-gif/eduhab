let ME=null;
async function load(){
  const s=await api('a_stats');
  $('stats').innerHTML=`Learners <b>${s.learners}</b> · Teachers <b>${s.teachers}</b> · Courses <b>${s.courses}</b> · Marking pending <b>${s.pending}</b> · Locked accounts <b>${s.locked}</b>`;
  const U=await api('a_users');
  $('userRows').innerHTML=U.map(u=>{const self=u.id==ME.id;return `<tr><td>${esc(u.name)}</td><td>${esc(u.email)}</td>
    <td>${self?u.role:`<select onchange="setRole(${u.id},this.value)" style="width:auto;margin:0">${['learner','teacher','admin'].map(r=>`<option ${r==u.role?'selected':''}>${r}</option>`).join('')}</select>`}</td>
    <td>${+u.locked?'Locked':'Active'}</td>
    <td class="row">${+u.locked?`<button class="alt" onclick="act('u_unlock',${u.id})">Unlock</button>`:''}
      <button class="alt" onclick="act('u_reset',${u.id})">Reset password</button>
      ${self?'':`<button class="del" onclick="if(confirm('Delete this user?'))act('u_del',${u.id})">Delete</button>`}</td></tr>`;}).join('');
}
function act(a,id){run(async()=>{const r=await api(a,{id});
  if(a==='u_reset')prompt('Temporary password – share it securely:',r.ok.split(': ')[1]); else toast(r.ok); load();});}
function setRole(id,role){run(async()=>{toast((await api('u_role',{id,role})).ok);load();});}
(async()=>{ME=await boot('admin');if(ME)load();})();
