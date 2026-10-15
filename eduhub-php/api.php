<?php // JSON API used by the JavaScript front end. Every action: api.php?action=NAME
require 'config.php';
header('Content-Type: application/json');
function out($d,$c=200){http_response_code($c);echo json_encode($d);exit;}
function fail($m,$c=400){out(['error'=>$m],$c);}
$in=json_decode(file_get_contents('php://input'),true)?:[]; $act=$_GET['action']??'';
function I($k,$d=''){global $in;$v=$in[$k]??$d;return is_string($v)?trim($v):$v;}
function PW(){global $in;return (string)($in['pass']??'');}
function role($r){$u=me();if(!$u)fail('Please sign in.',401);if($u['role']!==$r)fail('Not allowed.',403);return $u;}
function own($cid){$u=role('teacher');$c=q('SELECT * FROM courses WHERE id=? AND teacher_id=?',[$cid,$u['id']])->fetch();if(!$c)fail('Not your course.',403);return $c;}
function enrolled($cid){$u=role('learner');$c=q('SELECT c.* FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE c.id=? AND e.user_id=?',[$cid,$u['id']])->fetch();if(!$c)fail('Enrol in this course first.',403);return $c;}
function slideFor($sid){$u=role('learner');$s=q('SELECT s.* FROM slides s JOIN enrollments e ON e.course_id=s.course_id WHERE s.id=? AND e.user_id=?',[$sid,$u['id']])->fetch();if(!$s)fail('No access.',403);return $s;}
function opts($rows){return array_map(function($x){$x['options']=json_decode($x['options']??'[]',true);return $x;},$rows);}

if($act==='me') out(['user'=>me(),'csrf'=>csrf()]);
if($_SERVER['REQUEST_METHOD']!=='POST') fail('POST required.',405);
if(!hash_equals(csrf(),$_SERVER['HTTP_X_CSRF']??'')) fail('Invalid token. Reload the page.',403);

switch($act){
/* ---------- public ---------- */
case 'register':
 $e=strtolower(I('email'));
 if(!I('name')||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen(PW())<6) fail('Fill every field; password needs 6+ characters.');
 if(q('SELECT 1 FROM users WHERE email=?',[$e])->fetch()) fail('That email is already registered.');
 q('INSERT INTO users(name,email,pass_hash,role) VALUES(?,?,?,?)',[I('name'),$e,password_hash(PW(),PASSWORD_DEFAULT),I('role')==='teacher'?'teacher':'learner']);
 out(['ok'=>'Account created. Please sign in.']);
case 'login':
 $u=q('SELECT *,GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),locked_until)) AS wait FROM users WHERE email=?',[strtolower(I('email'))])->fetch();
 if(!$u) fail('Invalid email or password.',401);
 if($u['wait']>0) fail('Account locked. Try again in '.$u['wait'].' seconds, or ask an administrator.',423);
 if(!password_verify(PW(),$u['pass_hash'])){
  $f=$u['failed_attempts']+1;
  if($f>=MAX_ATTEMPTS){q('UPDATE users SET failed_attempts=0,locked_until=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?',[LOCK_SECONDS,$u['id']]);fail('3 wrong attempts. Account locked for '.LOCK_SECONDS.' seconds.',423);}
  q('UPDATE users SET failed_attempts=? WHERE id=?',[$f,$u['id']]); fail('Wrong password. '.(MAX_ATTEMPTS-$f).' attempt(s) left.',401);
 }
 q('UPDATE users SET failed_attempts=0,locked_until=NULL WHERE id=?',[$u['id']]);
 session_regenerate_id(true); $_SESSION['u']=['id'=>(int)$u['id'],'name'=>$u['name'],'role'=>$u['role']]; out(['role'=>$u['role']]);
case 'logout': unset($_SESSION['u']); out(['ok'=>1]);

/* ---------- teacher ---------- */
case 't_courses': $u=role('teacher');
 out(q('SELECT c.*,(SELECT COUNT(*) FROM slides WHERE course_id=c.id) ns,(SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) ne FROM courses c WHERE teacher_id=? ORDER BY id DESC',[$u['id']])->fetchAll());
case 'course_save': $u=role('teacher'); $id=(int)I('id',0); if(!I('title')) fail('Title is required.');
 if($id){own($id);q('UPDATE courses SET title=?,description=? WHERE id=?',[I('title'),I('description'),$id]);}
 else q('INSERT INTO courses(teacher_id,title,description) VALUES(?,?,?)',[$u['id'],I('title'),I('description')]);
 out(['ok'=>'Course saved.']);
case 'course_del': $c=own((int)I('id')); q('DELETE FROM courses WHERE id=?',[$c['id']]); out(['ok'=>'Course deleted.']);
case 't_course': $c=own((int)I('id'));
 $as=array_map(function($a){$a['questions']=opts(q('SELECT * FROM questions WHERE assessment_id=?',[$a['id']])->fetchAll());return $a;},q('SELECT * FROM assessments WHERE course_id=? ORDER BY id DESC',[$c['id']])->fetchAll());
 out(['course'=>$c,'slides'=>q('SELECT * FROM slides WHERE course_id=? ORDER BY position',[$c['id']])->fetchAll(),'assessments'=>$as]);
case 'slide_save': $c=own((int)I('course_id')); $id=(int)I('id',0); if(!I('title')||!I('content')) fail('Title and content are required.');
 if($id) q('UPDATE slides SET title=?,content=? WHERE id=? AND course_id=?',[I('title'),I('content'),$id,$c['id']]);
 else q('INSERT INTO slides(course_id,title,content,position) SELECT ?,?,?,COALESCE(MAX(position),0)+1 FROM slides WHERE course_id=?',[$c['id'],I('title'),I('content'),$c['id']]);
 out(['ok'=>'Slide saved.']);
case 'slide_del': $c=own((int)I('course_id')); q('DELETE FROM slides WHERE id=? AND course_id=?',[(int)I('id'),$c['id']]); out(['ok'=>'Slide deleted.']);
case 'as_save': $c=own((int)I('course_id')); $id=(int)I('id',0); if(!I('title')) fail('Title is required.');
 if($id) q('UPDATE assessments SET title=?,due_date=? WHERE id=? AND course_id=?',[I('title'),I('due')?:null,$id,$c['id']]);
 else q('INSERT INTO assessments(course_id,title,type,due_date) VALUES(?,?,?,?)',[$c['id'],I('title'),in_array(I('type'),['quiz','test','assignment'])?I('type'):'quiz',I('due')?:null]);
 out(['ok'=>'Saved.']);
case 'as_del': $c=own((int)I('course_id')); q('DELETE FROM assessments WHERE id=? AND course_id=?',[(int)I('id'),$c['id']]); out(['ok'=>'Deleted.']);
case 'q_add': $c=own((int)I('course_id'));
 $as=q('SELECT * FROM assessments WHERE id=? AND course_id=?',[(int)I('as'),$c['id']])->fetch(); if(!$as) fail('Bad assessment.'); if(!I('text')) fail('Question text is required.');
 if($as['type']==='assignment') q('INSERT INTO questions(assessment_id,kind,text) VALUES(?,?,?)',[$as['id'],'short',I('text')]);
 else{
  $opt=array_map('trim',array_map('strval',(array)I('opt',[]))); $right=$opt[(int)I('correct')]??''; $o=array_values(array_filter($opt,'strlen'));
  if(count($o)<2||$right==='') fail('Give 2+ options and mark a filled one as correct.');
  q('INSERT INTO questions(assessment_id,kind,text,options,correct_index) VALUES(?,?,?,?,?)',[$as['id'],'mcq',I('text'),json_encode($o),array_search($right,$o)]);
 }
 out(['ok'=>'Question added.']);
case 'q_del': $c=own((int)I('course_id'));
 q('DELETE FROM questions WHERE id=? AND assessment_id IN(SELECT id FROM assessments WHERE course_id=?)',[(int)I('id'),$c['id']]); out(['ok'=>'Question removed.']);
case 't_subs': $u=role('teacher');
 $a=q('SELECT a.* FROM assessments a JOIN courses c ON c.id=a.course_id WHERE a.id=? AND c.teacher_id=?',[(int)I('id'),$u['id']])->fetch(); if(!$a) fail('Not yours.',403);
 $subs=array_map(function($s){$s['answers']=json_decode($s['answers'],true);$s['marks']=json_decode($s['marks']??'{}',true);return $s;},
  q('SELECT s.*,u.name FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.assessment_id=? ORDER BY s.submitted_at DESC',[$a['id']])->fetchAll());
 out(['assessment'=>$a,'questions'=>opts(q('SELECT * FROM questions WHERE assessment_id=?',[$a['id']])->fetchAll()),'subs'=>$subs]);
case 'grade': $u=role('teacher');
 $s=q('SELECT s.* FROM submissions s JOIN assessments a ON a.id=s.assessment_id JOIN courses c ON c.id=a.course_id WHERE s.id=? AND c.teacher_id=?',[(int)I('id'),$u['id']])->fetch(); if(!$s) fail('Not yours.',403);
 $ans=json_decode($s['answers'],true); $in_m=(array)I('m',[]); $marks=[];$tot=0;$max=0;
 foreach(q('SELECT * FROM questions WHERE assessment_id=?',[$s['assessment_id']])->fetchAll() as $x){
  $max+=$x['points'];
  $m=$x['kind']==='mcq'?((int)($ans[$x['id']]??-1)===(int)$x['correct_index']?$x['points']:0):max(0,min($x['points'],(int)($in_m[$x['id']]??0)));
  $marks[$x['id']]=$m;$tot+=$m;
 }
 q("UPDATE submissions SET marks=?,score=?,max_score=?,status='graded',feedback=? WHERE id=?",[json_encode($marks),$tot,$max,I('feedback'),$s['id']]);
 out(['ok'=>"Marked: $tot/$max"]);

/* ---------- learner ---------- */
case 'l_courses': $u=role('learner');
 out(q('SELECT c.*,(SELECT COUNT(*) FROM slides WHERE course_id=c.id) ns,(SELECT COUNT(*) FROM slide_progress sp JOIN slides s ON s.id=sp.slide_id WHERE s.course_id=c.id AND sp.user_id=?) nr FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.user_id=?',[$u['id'],$u['id']])->fetchAll());
case 'l_browse': $u=role('learner');
 out(q('SELECT c.*,u.name tn,(SELECT COUNT(*) FROM enrollments WHERE course_id=c.id AND user_id=?) mine FROM courses c JOIN users u ON u.id=c.teacher_id ORDER BY c.id DESC',[$u['id']])->fetchAll());
case 'enroll': $u=role('learner'); q('INSERT IGNORE INTO enrollments VALUES(?,?)',[(int)I('id'),$u['id']]); out(['ok'=>'Enrolled.']);
case 'unenroll': $u=role('learner'); q('DELETE FROM enrollments WHERE course_id=? AND user_id=?',[(int)I('id'),$u['id']]); out(['ok'=>'You left the course.']);
case 'l_course': $c=enrolled((int)I('id')); $u=me();
 out(['course'=>$c,'slides'=>q('SELECT * FROM slides WHERE course_id=? ORDER BY position',[$c['id']])->fetchAll(),
  'read'=>q('SELECT sp.slide_id FROM slide_progress sp JOIN slides s ON s.id=sp.slide_id WHERE s.course_id=? AND sp.user_id=?',[$c['id'],$u['id']])->fetchAll(PDO::FETCH_COLUMN)]);
case 'read': $s=slideFor((int)I('slide')); $u=me();
 $new=q('INSERT IGNORE INTO slide_progress VALUES(?,?)',[$u['id'],$s['id']])->rowCount();
 $n=q('SELECT COUNT(*) FROM slide_progress sp JOIN slides s ON s.id=sp.slide_id WHERE sp.user_id=? AND s.course_id=?',[$u['id'],$s['course_id']])->fetchColumn();
 out(['n'=>(int)$n,'quiz'=>($new&&$n%2==0)?(int)$s['id']:0]);   // every 2nd newly-read slide -> AI check quiz
case 'ai_summary': $s=slideFor((int)I('slide'));
 if(time()-($_SESSION['ai']??0)<3) fail('Please wait a few seconds.',429); $_SESSION['ai']=time();
 out(['text'=>ai("Summarise this lesson slide for a student in 3-4 short bullet points.\nTitle: $s[title]\n$s[content]")]);
case 'ai_quiz': $s=slideFor((int)I('slide'));
 if(time()-($_SESSION['ai']??0)<3) fail('Please wait a few seconds.',429); $_SESSION['ai']=time();
 $p=q('SELECT * FROM slides WHERE course_id=? AND position<? ORDER BY position DESC LIMIT 1',[$s['course_id'],$s['position']])->fetch();
 $txt=($p?"Title: $p[title]\n$p[content]\n\n":'')."Title: $s[title]\n$s[content]";
 $raw=ai("Write exactly 2 multiple-choice questions on these slides. Return ONLY JSON, no fences: {\"questions\":[{\"text\":\"\",\"options\":[\"\",\"\",\"\",\"\"],\"correctIndex\":0}]}\n\n$txt");
 $j=json_decode(trim(preg_replace('/```(json)?/','',$raw)),true); out($j&&isset($j['questions'])?$j:['error'=>'AI unavailable']);
case 'l_tests': $u=role('learner');
 out(q('SELECT a.id,a.title,a.type,a.due_date,c.title ct,s.status,s.score,s.max_score FROM assessments a JOIN courses c ON c.id=a.course_id JOIN enrollments e ON e.course_id=c.id AND e.user_id=? LEFT JOIN submissions s ON s.assessment_id=a.id AND s.user_id=? ORDER BY a.id DESC',[$u['id'],$u['id']])->fetchAll());
case 'l_take': $u=role('learner');
 $a=q('SELECT a.* FROM assessments a JOIN enrollments e ON e.course_id=a.course_id WHERE a.id=? AND e.user_id=?',[(int)I('id'),$u['id']])->fetch(); if(!$a) fail('No access.',403);
 if(q('SELECT 1 FROM submissions WHERE assessment_id=? AND user_id=?',[$a['id'],$u['id']])->fetch()) fail('You already submitted this.');
 out(['assessment'=>$a,'questions'=>opts(q('SELECT id,kind,text,options,points FROM questions WHERE assessment_id=?',[$a['id']])->fetchAll())]);   // correct answers are never sent
case 'submit': $u=role('learner');
 $a=q('SELECT a.* FROM assessments a JOIN enrollments e ON e.course_id=a.course_id WHERE a.id=? AND e.user_id=?',[(int)I('id'),$u['id']])->fetch(); if(!$a) fail('No access.',403);
 if($a['due_date']&&$a['due_date']<date('Y-m-d')) fail('This one is past its due date.');
 $ans=(array)I('ans',[]);$sc=0;$mx=0;$short=false;
 foreach(q('SELECT * FROM questions WHERE assessment_id=?',[$a['id']])->fetchAll() as $x){
  $mx+=$x['points']; if($x['kind']==='mcq'){if(isset($ans[$x['id']])&&(int)$ans[$x['id']]===(int)$x['correct_index'])$sc+=$x['points'];} else $short=true;
 }
 try{q('INSERT INTO submissions(assessment_id,user_id,answers,score,max_score,status) VALUES(?,?,?,?,?,?)',[$a['id'],$u['id'],json_encode($ans),$short?null:$sc,$mx,$short?'pending':'graded']);}
 catch(PDOException $e){fail('You already submitted this.');}
 out(['ok'=>$short?'Submitted. Your teacher will mark it.':"Submitted. Score $sc/$mx"]);
case 'l_grades': $u=role('learner');
 out(q('SELECT s.status,s.score,s.max_score,s.feedback,s.submitted_at,a.title FROM submissions s JOIN assessments a ON a.id=s.assessment_id WHERE s.user_id=? ORDER BY s.submitted_at DESC',[$u['id']])->fetchAll());

/* ---------- admin ---------- */
case 'a_stats': role('admin');
 out(q("SELECT (SELECT COUNT(*) FROM users WHERE role='learner') learners,(SELECT COUNT(*) FROM users WHERE role='teacher') teachers,(SELECT COUNT(*) FROM courses) courses,(SELECT COUNT(*) FROM submissions WHERE status='pending') pending,(SELECT COUNT(*) FROM users WHERE locked_until>NOW()) locked")->fetch());
case 'a_users': role('admin'); out(q('SELECT id,name,email,role,IF(locked_until>NOW(),1,0) AS locked FROM users ORDER BY name')->fetchAll());
case 'u_role': $a=role('admin'); if((int)I('id')===$a['id']||!in_array(I('role'),['learner','teacher','admin'])) fail('Not allowed.'); q('UPDATE users SET role=? WHERE id=?',[I('role'),(int)I('id')]); out(['ok'=>'Role updated.']);
case 'u_del': $a=role('admin'); if((int)I('id')===$a['id']) fail('You cannot delete yourself.'); q('DELETE FROM users WHERE id=?',[(int)I('id')]); out(['ok'=>'User removed.']);
case 'u_unlock': role('admin'); q('UPDATE users SET failed_attempts=0,locked_until=NULL WHERE id=?',[(int)I('id')]); out(['ok'=>'Account unlocked.']);
case 'u_reset': role('admin'); $tmp=substr(bin2hex(random_bytes(6)),0,10);
 q('UPDATE users SET pass_hash=?,failed_attempts=0,locked_until=NULL WHERE id=?',[password_hash($tmp,PASSWORD_DEFAULT),(int)I('id')]); out(['ok'=>'Temporary password (share it securely): '.$tmp]);
default: fail('Unknown action.',404);
}
