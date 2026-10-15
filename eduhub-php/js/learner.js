let SL=[], IDX=0, READ=new Set(), QZ=[], TAKE=null;

function go(v){show(v);({mine:loadMine,browse:loadBrowse,tests:loadTests,grades:loadGrades})[v]?.();}

/* ---------- courses ---------- */
async function loadMine(){
  const L=await api('l_courses');
  $('mineList').innerHTML=L.length?L.map(c=>`<div class="card"><b>${esc(c.title)}</b><br><small class="muted">${c.nr}/${c.ns} slides read</small>
    <div class="row"><button onclick="openCourse(${c.id})">Continue</button><button class="del" onclick="leave(${c.id})">Leave</button></div></div>`).join(''):'<p class="muted">Nothing yet. Use Browse courses to enrol.</p>';
}
async function loadBrowse(){
  const L=await api('l_browse');
  $('browseList').innerHTML=L.map(c=>`<div class="card"><b>${esc(c.title)}</b> <small class="muted">by ${esc(c.tn)}</small><br>${esc(c.description)}<br>
    ${+c.mine?'Enrolled':`<button onclick="enrol(${c.id})">Enrol</button>`}</div>`).join('')||'<p class="muted">No courses published yet.</p>';
}
function enrol(id){run(async()=>{toast((await api('enroll',{id})).ok);loadBrowse();});}
function leave(id){if(confirm('Leave this course?'))run(async()=>{await api('unenroll',{id});loadMine();});}

/* ---------- slides, AI summary, AI check quiz after every 2 slides ---------- */
async function openCourse(id){run(async()=>{
  const r=await api('l_course',{id}); SL=r.slides; READ=new Set(r.read.map(Number));
  IDX=Math.max(0,SL.findIndex(s=>!READ.has(+s.id))); $('lTitle').textContent=r.course.title; show('learn'); renderSlide();});}
function renderSlide(){
  $('aiOut').textContent='';
  $('sumBtn').hidden=$('readBtn').hidden=!SL.length;
  if(!SL.length){$('dots').innerHTML='';$('slideNo').textContent='';$('slideTitle').textContent='';$('slideBody').textContent='No slides yet.';return;}
  const s=SL[IDX];
  $('dots').innerHTML=SL.map((x,i)=>`<button class="${i==IDX?'cur':READ.has(+x.id)?'done':''}" onclick="IDX=${i};renderSlide()">${i+1}</button>`).join('');
  $('slideNo').textContent=`Slide ${IDX+1} of ${SL.length}`; $('slideTitle').textContent=s.title; $('slideBody').textContent=s.content;
}
function summarise(){run(async()=>{
  $('sumBtn').disabled=true;$('aiOut').textContent='Thinking…';
  try{$('aiOut').textContent=(await api('ai_summary',{slide:SL[IDX].id})).text;}
  finally{$('sumBtn').disabled=false;}});}
function markRead(){run(async()=>{
  const s=SL[IDX],r=await api('read',{slide:s.id}); READ.add(+s.id);
  if(IDX<SL.length-1)IDX++; renderSlide();
  if(r.quiz)showQuiz(r.quiz);});}                 // server says: 2 more slides read -> small test
async function showQuiz(sid){
  const head='<h3>&#10024; Quick check-in</h3>';
  openModal(head+'<p>Preparing your questions…</p>');
  try{
    const r=await api('ai_quiz',{slide:sid}); if(!r.questions)throw new Error('Quiz unavailable right now.'); QZ=r.questions;
    openModal(head+QZ.map((q,i)=>`<p><b>${i+1}. ${esc(q.text)}</b></p>`+q.options.map((o,j)=>`<label class="opt"><input type="radio" name="q${i}" value="${j}">${esc(o)}</label>`).join('')).join('')+
      '<div class="row"><button onclick="checkQuiz()">Check answers</button><button class="alt" onclick="closeModal()">Close</button></div><p id="qres"></p>');
  }catch(e){closeModal();toast(e.message,true);}
}
function checkQuiz(){
  let s=0;QZ.forEach((q,i)=>{const v=document.querySelector(`input[name=q${i}]:checked`);if(v&&+v.value===+q.correctIndex)s++;});
  $('qres').textContent=`Score: ${s} / ${QZ.length} (practice only, not graded)`;
}

/* ---------- tests & assignments ---------- */
async function loadTests(){
  const L=await api('l_tests');
  $('testList').innerHTML=L.map(a=>`<div class="card"><b>${esc(a.title)}</b> <small class="muted">(${a.type}) ${esc(a.ct)} · due ${esc(a.due_date||'—')}</small><br>
    ${a.status?`Submitted (${a.status}${a.score!==null?', '+a.score+'/'+a.max_score:''})`:`<button onclick="take(${a.id})">Start</button>`}</div>`).join('')||'<p class="muted">Nothing assigned yet.</p>';
}
function take(id){run(async()=>{
  TAKE=await api('l_take',{id}); $('takeTitle').textContent=TAKE.assessment.title; show('take');
  $('takeForm').innerHTML=TAKE.questions.map(x=>`<div class="card"><b>${esc(x.text)}</b>`+
    (x.kind==='mcq'?x.options.map((o,j)=>`<label class="opt"><input type="radio" name="a${x.id}" value="${j}">${esc(o)}</label>`).join('')
                   :`<textarea name="a${x.id}" rows="4"></textarea>`)+'</div>').join('')+'<button>Submit</button>';});}
$('takeForm').onsubmit=e=>{e.preventDefault();run(async()=>{
  const ans={};TAKE.questions.forEach(x=>{const el=document.querySelector(`[name=a${x.id}]${x.kind==='mcq'?':checked':''}`);if(el)ans[x.id]=el.value;});
  toast((await api('submit',{id:TAKE.assessment.id,ans})).ok);go('grades');});};

/* ---------- grades ---------- */
async function loadGrades(){
  const L=await api('l_grades');
  $('gradeRows').innerHTML=L.map(g=>`<tr><td>${esc(g.title)}</td><td>${g.status}</td><td>${g.score===null?'—':g.score+'/'+g.max_score}</td><td>${esc(g.feedback||'')}</td></tr>`).join('');
}

(async()=>{if(await boot('learner'))go('mine');})();
