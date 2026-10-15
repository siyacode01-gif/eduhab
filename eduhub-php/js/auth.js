function tab(t){
  $('loginForm').hidden=t!=='login'; $('regForm').hidden=t!=='register';
  $('tabLogin').className=t==='login'?'':'alt'; $('tabReg').className=t==='register'?'':'alt'; $('msg').hidden=true;
}
function note(m){$('msg').textContent=m;$('msg').hidden=false;}

$('loginForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  try{const r=await api('login',{email:$('lEmail').value,pass:$('lPass').value});
      location=r.role+'.html';}                 // teacher.html | learner.html | admin.html
  catch(err){note(err.message);}
});};
$('regForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  try{const r=await api('register',{name:$('rName').value,email:$('rEmail').value,pass:$('rPass').value,role:$('rRole').value});
      tab('login');note(r.ok);}
  catch(err){note(err.message);}
});};

(async()=>{const u=await boot(); if(u)location=u.role+'.html';})();   // already signed in? go to dashboard
