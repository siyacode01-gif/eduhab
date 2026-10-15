<?php
session_start();
const DB_HOST='localhost', DB_NAME='eduhub', DB_USER='root', DB_PASS='';
const MAX_ATTEMPTS=3, LOCK_SECONDS=60;
const ANTHROPIC_KEY='';   // paste your Anthropic API key to enable AI features

$db=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

function q($sql,$p=[]){global $db;$s=$db->prepare($sql);$s->execute($p);return $s;}  // prepared statements
function me(){return $_SESSION['u']??null;}
function csrf(){return $_SESSION['t']??($_SESSION['t']=bin2hex(random_bytes(16)));}
function ai($prompt){
  if(!ANTHROPIC_KEY) return 'AI is not configured: add ANTHROPIC_KEY in config.php.';
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-sonnet-4-6','max_tokens'=>1000,'messages'=>[['role'=>'user','content'=>$prompt]]])]);
  $r=json_decode(curl_exec($ch),true);
  return $r['content'][0]['text']??'AI is unavailable right now.';
}
