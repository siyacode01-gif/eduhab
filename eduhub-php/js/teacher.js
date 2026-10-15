let CID=0, COURSES=[], SLIDES=[];

/* ---------- courses ---------- */
async function showCourses(){show('courses');await loadCourses();}
async function loadCourses(){
  COURSES=await api('t_courses');
  $('courseList').innerHTML=COURSES.length?COURSES.map(c=>`
    <div class="card"><b>${esc(c.title)}</b><br>${esc(c.description)}<br><small class="muted">${c.ns} slides · ${c.ne} learners</small>
      <div class="row"><button onclick="openCourse(${c.id})">Open</button>
      <button class="alt" onclick="editCourse(${c.id})">Edit</button>
      <button class="del" onclick="delCourse(${c.id})">Delete</button></div></div>`).join(''):'<p class="muted">No courses yet.</p>';
}
function resetCourse(){$('cId').value='';$('cTitle').value='';$('cDesc').value='';$('cfTitle').textContent='New course';}
function editCourse(id){const c=COURSES.find(x=>x.id==id);$('cId').value=c.id;$('cTitle').value=c.title;$('cDesc').value=c.description||'';$('cfTitle').textContent='Edit course';scrollTo(0,0);}
function delCourse(id){if(confirm('Delete this course and everything in it?'))run(async()=>{await api('course_del',{id});toast('Course deleted');loadCourses();});}
$('courseForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  const r=await api('course_save',{id:$('cId').value,title:$('cTitle').value,description:$('cDesc').value});
  toast(r.ok);resetCourse();loadCourses();});};

/* ---------- one course ---------- */
async function openCourse(id){CID=id;show('course');await loadCourse();}
async function loadCourse(){
  const r=await api('t_course',{id:CID}); SLIDES=r.slides; $('courseName').textContent=r.course.title;
  $('slideList').innerHTML=SLIDES.map(s=>`
    <div class="card"><b>${s.position}. ${esc(s.title)}</b><p>${esc(s.content.slice(0,160))}${s.content.length>160?'…':''}</p>
      <div class="row"><button class="alt" onclick="editSlide(${s.id})">Edit</button><button class="del" onclick="delSlide(${s.id})">Delete</button></div></div>`).join('');
  $('asList').innerHTML=r.assessments.map(a=>`
    <div class="card"><b>${esc(a.title)}</b> <small class="muted">(${a.type}) due ${esc(a.due_date||'—')}</small>
      ${a.questions.map(x=>`<div class="opt">${esc(x.text)}${x.options.length?`<br><small class="muted">${x.options.map((o,i)=>(i==x.correct_index?'✔ ':'')+esc(o)).join(' | ')}</small>`:''}
        <div><button class="del" onclick="delQ(${x.id})">Remove</button></div></div>`).join('')}
      <div class="opt"><label>New question<input id="qt${a.id}"></label>
        ${a.type==='assignment'?'':[0,1,2,3].map(i=>`<div class="row"><input type="radio" name="cor${a.id}" value="${i}" ${i?'':'checked'}><input id="op${a.id}_${i}" placeholder="Option ${i+1}" style="flex:1"></div>`).join('')}
        <button onclick="addQ(${a.id},'${a.type}')">Add question</button></div>
      <div class="row"><button class="alt" onclick="openSubs(${a.id})">Submissions &amp; marking</button><button class="del" onclick="delAs(${a.id})">Delete</button></div></div>`).join('');
}
/* slides */
function resetSlide(){$('sId').value='';$('sTitle').value='';$('sContent').value='';$('sBtn').textContent='Add slide';}
function editSlide(id){const s=SLIDES.find(x=>x.id==id);$('sId').value=s.id;$('sTitle').value=s.title;$('sContent').value=s.content;$('sBtn').textContent='Update slide';scrollTo(0,0);}
function delSlide(id){if(confirm('Delete this slide?'))run(async()=>{await api('slide_del',{id,course_id:CID});loadCourse();});}
$('slideForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  const r=await api('slide_save',{id:$('sId').value,course_id:CID,title:$('sTitle').value,content:$('sContent').value});
  toast(r.ok);resetSlide();loadCourse();});};
/* assessments + questions */
$('asForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  const r=await api('as_save',{course_id:CID,title:$('aTitle').value,type:$('aType').value,due:$('aDue').value});
  toast(r.ok);$('asForm').reset();loadCourse();});};
function delAs(id){if(confirm('Delete this and all its submissions?'))run(async()=>{await api('as_del',{id,course_id:CID});loadCourse();});}
function addQ(aid,type){run(async()=>{
  const d={course_id:CID,as:aid,text:$('qt'+aid).value};
  if(type!=='assignment'){d.opt=[0,1,2,3].map(i=>$(`op${aid}_${i}`).value);d.correct=document.querySelector(`input[name=cor${aid}]:checked`).value;}
  const r=await api('q_add',d);toast(r.ok);loadCourse();});}
function delQ(id){run(async()=>{await api('q_del',{id,course_id:CID});loadCourse();});}

/* ---------- marking ---------- */
async function openSubs(aid){
  show('subs');const r=await api('t_subs',{id:aid});
  $('subsTitle').textContent=r.assessment.title; $('backToCourse').onclick=()=>openCourse(CID);
  $('subsList').innerHTML=r.subs.length?r.subs.map(s=>`
    <div class="card"><b>${esc(s.name)}</b> · ${s.status} · ${s.score===null?'—':s.score+'/'+s.max_score}
      ${r.questions.map(x=>{const a=s.answers[x.id];return `<div class="opt"><b>${esc(x.text)}</b><br>`+
        (x.kind==='mcq'?`${esc(x.options[a]??'(none)')} ${a!==undefined&&+a===+x.correct_index?'✔':'✘'}`
         :`<span style="white-space:pre-wrap">${esc(a||'(no answer)')}</span><br>Mark /${x.points}
           <input type="number" min="0" max="${x.points}" id="m${s.id}_${x.id}" value="${s.marks[x.id]??''}">`)+`</div>`;}).join('')}
      <label>Feedback<textarea id="fb${s.id}">${esc(s.feedback||'')}</textarea></label>
      <button onclick='saveGrade(${s.id},${aid},${JSON.stringify(r.questions.filter(x=>x.kind==="short").map(x=>x.id))})'>Save marks</button></div>`).join(''):'<p class="muted">No submissions yet.</p>';
}
function saveGrade(sid,aid,shortIds){run(async()=>{
  const m={};shortIds.forEach(q=>m[q]=$(`m${sid}_${q}`).value||0);
  const r=await api('grade',{id:sid,m,feedback:$('fb'+sid).value});toast(r.ok);openSubs(aid);});}

(async()=>{if(await boot('teacher'))showCourses();})();
