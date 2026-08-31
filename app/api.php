<?php
use App\Auth;use App\DB;use App\Otp;
$action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';$in=$method==='GET'?$_GET:json_input();
try{
 if($action==='otp-send'&&$method==='POST'){[$ok,$message]=Otp::send((string)($in['mobile']??''));json_response(['ok'=>$ok,'message'=>$message],$ok?200:422);}
 if($action==='otp-verify'&&$method==='POST'){[$ok,$message]=Otp::verify((string)($in['mobile']??''),(string)($in['code']??''));json_response(['ok'=>$ok,'message'=>$message,'csrf'=>$ok?csrf_token():null],$ok?200:422);}
 if($action==='password-login'&&$method==='POST'){[$ok,$message]=Auth::passwordLogin((string)($in['mobile']??''),(string)($in['password']??''));json_response(['ok'=>$ok,'message'=>$message,'csrf'=>$ok?csrf_token():null],$ok?200:422);}
 Auth::require();if($method!=='GET')Auth::csrf();$uid=Auth::id();
if($action==='dashboard'){
   $stats=DB::one("SELECT (SELECT COUNT(*) FROM companies WHERE user_id=?) companies,(SELECT COUNT(*) FROM customers WHERE user_id=?) customers,(SELECT COUNT(*) FROM catalog_items WHERE user_id=? AND is_active=1) catalog,(SELECT COUNT(*) FROM quotes WHERE user_id=?) quotes,(SELECT COUNT(*) FROM quotes WHERE user_id=? AND status='draft') drafts,(SELECT COALESCE(SUM(total),0) FROM quotes WHERE user_id=? AND status='issued') issued_total",[$uid,$uid,$uid,$uid,$uid,$uid]);
   try{
    $recent=DB::all("SELECT q.id,q.quote_number,q.status,q.issue_date,q.total,c.name company_name,COALESCE(cu.name,'') customer_name FROM quotes q JOIN companies c ON c.id=q.company_id LEFT JOIN customers cu ON cu.id=q.customer_id AND cu.user_id=q.user_id WHERE q.user_id=? AND q.is_latest=1 ORDER BY q.id DESC LIMIT 8",[$uid]);
   }catch(Throwable){
    $recent=DB::all("SELECT q.id,q.quote_number,q.status,q.issue_date,q.total,c.name company_name,COALESCE(cu.name,'') customer_name FROM quotes q JOIN companies c ON c.id=q.company_id LEFT JOIN customers cu ON cu.id=q.customer_id AND cu.user_id=q.user_id WHERE q.user_id=? ORDER BY q.id DESC LIMIT 8",[$uid]);
   }
   json_response(['ok'=>true,'data'=>['stats'=>$stats,'recent'=>$recent,'user'=>Auth::user()]]);
  }
  if($action==='dashboard-charts'){
   // Revenue last 6 months
   $revenue=DB::all("SELECT DATE_FORMAT(issue_date,'%Y-%m') month,COALESCE(SUM(total),0) total FROM quotes WHERE user_id=? AND status='issued' AND issue_date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(issue_date,'%Y-%m') ORDER BY month ASC",[$uid]);
   // Status distribution
   $statusDist=DB::all("SELECT status,COUNT(*) count FROM quotes WHERE user_id=? GROUP BY status",[$uid]);
   // Monthly trend (last 12 months)
   $trend=DB::all("SELECT DATE_FORMAT(issue_date,'%Y-%m') month,COUNT(*) count,COALESCE(SUM(total),0) total FROM quotes WHERE user_id=? AND issue_date>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(issue_date,'%Y-%m') ORDER BY month ASC",[$uid]);
   json_response(['ok'=>true,'data'=>['revenue'=>$revenue,'status'=>$statusDist,'trend'=>$trend]]);
  }
 if($action==='profile-save'&&$method==='POST'){
  $name=trim((string)($in['name']??''));$email=trim((string)($in['email']??''));if($name!==''&&mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام باید حداقل دو نویسه باشد.'],422);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['ok'=>false,'message'=>'ایمیل معتبر نیست.'],422);
  $current=DB::one('SELECT password_hash FROM users WHERE id=?',[$uid]);$new=(string)($in['new_password']??'');$confirm=(string)($in['password_confirmation']??'');
  if($new!==''){if(strlen($new)<8||!preg_match('/[A-Za-z]/',$new)||!preg_match('/\d/',$new))json_response(['ok'=>false,'message'=>'رمز باید حداقل ۸ نویسه و شامل حرف و عدد باشد.'],422);if(!hash_equals($new,$confirm))json_response(['ok'=>false,'message'=>'تکرار رمز یکسان نیست.'],422);if($current&&$current['password_hash']&&!password_verify((string)($in['current_password']??''),$current['password_hash']))json_response(['ok'=>false,'message'=>'رمز فعلی نادرست است.'],422);$hash=password_hash($new,PASSWORD_DEFAULT);DB::exec('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?',[$name?:null,$email?:null,$hash,$uid]);}
  else DB::exec('UPDATE users SET name=?,email=? WHERE id=?',[$name?:null,$email?:null,$uid]);
  audit($uid,null,'profile_update','user',$uid,null,['name'=>$name,'email'=>$email,'password_changed'=>$new!=='']);json_response(['ok'=>true,'message'=>'پروفایل ذخیره شد.','data'=>Auth::user()]);
 }
 if($action==='chat-messages'){
  $rows=DB::all('SELECT id,sender_role,body,created_at FROM chat_messages WHERE user_id=? ORDER BY id ASC',[$uid]);
  DB::exec("UPDATE chat_messages SET is_read_user=1 WHERE user_id=? AND sender_role='admin' AND is_read_user=0",[$uid]);
  json_response(['ok'=>true,'data'=>$rows]);
 }
if($action==='chat-send'&&$method==='POST'){
   $body=trim((string)($in['body']??''));if($body===''||mb_strlen($body)>4000)json_response(['ok'=>false,'message'=>'متن پیام معتبر نیست.'],422);
   DB::exec("INSERT INTO chat_messages(user_id,sender_id,sender_role,body,is_read_user,is_read_admin)VALUES(?,?,'user',?,1,0)",[$uid,$uid,$body]);
   json_response(['ok'=>true,'message'=>'پیام ارسال شد.']);
  }

  // Ticket endpoints for users
  if($action==='tickets-list'){
   $status=($in['status']??'');
   $where='WHERE t.user_id=?';
   $params=[$uid];
   if($status!=='' && in_array($status,['open','in_progress','waiting_customer','closed'],true)){
    $where.=' AND t.status=?';$params[]=$status;
   }
   $rows=DB::all("SELECT t.*,c.name company_name FROM tickets t LEFT JOIN companies c ON c.id=t.company_id $where ORDER BY t.last_reply_at DESC NULLS LAST, t.id DESC LIMIT 50",$params);
   json_response(['ok'=>true,'data'=>$rows]);
  }
  if($action==='ticket-create'&&$method==='POST'){
   $subject=trim((string)($in['subject']??''));$body=trim((string)($in['body']??''));$companyId=(int)($in['company_id']??0);$priority=($in['priority']??'normal');
   if(mb_strlen($subject)<3)json_response(['ok'=>false,'message'=>'موضوع تیکت باید حداقل ۳ نویسه باشد.'],422);
   if($body===''||mb_strlen($body)>4000)json_response(['ok'=>false,'message'=>'متن تیکت معتبر نیست.'],422);
   if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
   $companyId=$companyId?ownCompany($uid,$companyId):null;
   DB::pdo()->beginTransaction();
   DB::exec("INSERT INTO tickets(user_id,company_id,subject,status,priority,last_reply_at)VALUES(?,?,?,'open',?,NOW())",[$uid,$companyId,$subject,$priority]);
   $ticketId=(int)DB::pdo()->lastInsertId();
   DB::exec("INSERT INTO ticket_messages(ticket_id,sender_id,sender_role,body,is_internal)VALUES(?,?,'user',?,0)",[$ticketId,$uid,$body]);
   DB::pdo()->commit();
   audit($uid,$companyId,'create','ticket',$ticketId,null,['subject'=>$subject,'priority'=>$priority]);
   json_response(['ok'=>true,'message'=>'تیکت با موفقیت ثبت شد.','id'=>$ticketId]);
  }
  if($action==='ticket-get'){
   $id=(int)($in['id']??0);
   $ticket=DB::one('SELECT * FROM tickets WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   $messages=DB::all('SELECT tm.*,u.name sender_name FROM ticket_messages tm JOIN users u ON u.id=tm.sender_id WHERE tm.ticket_id=? ORDER BY tm.id ASC',[$id]);
   json_response(['ok'=>true,'data'=>['ticket'=>$ticket,'messages'=>$messages]]);
  }
  if($action==='ticket-reply'&&$method==='POST'){
   $id=(int)($in['id']??0);$body=trim((string)($in['body']??''));
   $ticket=DB::one('SELECT * FROM tickets WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   if($ticket['status']==='closed')json_response(['ok'=>false,'message'=>'این تیکت بسته شده و قابل پاسخ‌دهی نیست.'],409);
   if($body===''||mb_strlen($body)>4000)json_response(['ok'=>false,'message'=>'متن پاسخ معتبر نیست.'],422);
   DB::exec("INSERT INTO ticket_messages(ticket_id,sender_id,sender_role,body,is_internal)VALUES(?,?,'user',?,0)",[$id,$uid,$body]);
   DB::exec("UPDATE tickets SET last_reply_at=NOW(), status='waiting_customer' WHERE id=?",[$id]);
   audit($uid,$ticket['company_id'],'reply','ticket',$id,null,['body'=>$body]);
   json_response(['ok'=>true,'message'=>'پاسخ شما ثبت شد.']);
  }
  if($action==='ticket-close'&&$method==='POST'){
   $id=(int)($in['id']??0);
   $ticket=DB::one('SELECT * FROM tickets WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   if($ticket['status']==='closed')json_response(['ok'=>false,'message'=>'این تیکت قبلاً بسته شده است.'],409);
   DB::exec("UPDATE tickets SET status='closed', last_reply_at=NOW() WHERE id=?",[$id]);
   audit($uid,$ticket['company_id'],'close','ticket',$id,null,[]);
   json_response(['ok'=>true,'message'=>'تیکت بسته شد.']);
  }

  // Admin ticket endpoints
  if($action==='admin-tickets'){
   $status=($in['status']??'');
   $assigned=isset($in['assigned'])?(int)$in['assigned']:0;
   $where='WHERE 1=1';$params=[];
   if($status!=='' && in_array($status,['open','in_progress','waiting_customer','closed'],true)){
    $where.=' AND t.status=?';$params[]=$status;
   }
   if($assigned===$uid){
    $where.=' AND t.assigned_to=?';$params[]=$uid;
   }elseif($assigned>0){
    $where.=' AND t.assigned_to=?';$params[]=$assigned;
   }
   $rows=DB::all("SELECT t.*,u.name user_name,u.mobile user_mobile,c.name company_name,a.name assigned_name FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN companies c ON c.id=t.company_id LEFT JOIN users a ON a.id=t.assigned_to $where ORDER BY FIELD(t.status,'open','in_progress','waiting_customer','closed'), t.last_reply_at DESC NULLS LAST, t.id DESC LIMIT 100",$params);
   json_response(['ok'=>true,'data'=>$rows]);
  }
  if($action==='admin-ticket-get'){
   $id=(int)($in['id']??0);
   $ticket=DB::one('SELECT t.*,u.name user_name,u.mobile user_mobile,u.email user_email,c.name company_name,a.name assigned_name FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN companies c ON c.id=t.company_id LEFT JOIN users a ON a.id=t.assigned_to WHERE t.id=?',[$id]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   $messages=DB::all('SELECT tm.*,u.name sender_name FROM ticket_messages tm JOIN users u ON u.id=tm.sender_id WHERE tm.ticket_id=? ORDER BY tm.id ASC',[$id]);
   $staff=DB::all('SELECT u.id,u.name FROM users u JOIN support_staff s ON s.user_id=u.id WHERE s.is_active=1');
   json_response(['ok'=>true,'data'=>['ticket'=>$ticket,'messages'=>$messages,'staff'=>$staff]]);
  }
  if($action==='admin-ticket-save'&&$method==='POST'){
   $id=(int)($in['id']??0);$status=($in['status']??'');$assignedTo=(int)($in['assigned_to']??0);$priority=($in['priority']??'');
   $ticket=DB::one('SELECT * FROM tickets WHERE id=?',[$id]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   $updates=[];$params=[];
   if($status!=='' && in_array($status,['open','in_progress','waiting_customer','closed'],true)){
    $updates[]='status=?';$params[]=$status;
   }
   if($assignedTo>0){
    $staff=DB::one('SELECT 1 FROM support_staff WHERE user_id=? AND is_active=1',[$assignedTo]);
    if(!$staff)json_response(['ok'=>false,'message'=>'پرسنل پشتیبانی انتخاب‌شده معتبر نیست.'],422);
    $updates[]='assigned_to=?';$params[]=$assignedTo;
   }elseif($assignedTo===0){
    $updates[]='assigned_to=NULL';
   }
   if($priority!=='' && in_array($priority,['low','normal','high','urgent'],true)){
    $updates[]='priority=?';$params[]=$priority;
   }
   if($updates){
    $params[]=$id;
    DB::exec('UPDATE tickets SET '.implode(',',$updates).' WHERE id=?',$params);
   }
   audit($uid,$ticket['company_id'],'admin_update','ticket',$id,$ticket,['status'=>$status,'assigned_to'=>$assignedTo,'priority'=>$priority]);
   json_response(['ok'=>true,'message'=>'تیکت به‌روزرسانی شد.']);
  }
  if($action==='admin-ticket-reply'&&$method==='POST'){
   $id=(int)($in['id']??0);$body=trim((string)($in['body']??''));$isInternal=!empty($in['is_internal'])?1:0;
   if($body===''||mb_strlen($body)>4000)json_response(['ok'=>false,'message'=>'متن پاسخ معتبر نیست.'],422);
   $ticket=DB::one('SELECT * FROM tickets WHERE id=?',[$id]);
   if(!$ticket)json_response(['ok'=>false,'message'=>'تیکت پیدا نشد.'],404);
   $senderRole=Auth::admin()?'admin':'support';
   DB::exec("INSERT INTO ticket_messages(ticket_id,sender_id,sender_role,body,is_internal)VALUES(?,?,?,?,?)",[$id,$uid,$senderRole,$body,$isInternal]);
   $newStatus=$ticket['status']==='waiting_customer'?'in_progress':$ticket['status'];
   DB::exec("UPDATE tickets SET last_reply_at=NOW(), status=? WHERE id=?",[$newStatus,$id]);
   audit($uid,$ticket['company_id'],'admin_reply','ticket',$id,$ticket,['body'=>$body,'is_internal'=>$isInternal]);
   json_response(['ok'=>true,'message'=>'پاسخ ثبت شد.']);
  }
  if($action==='admin-support-staff'){
   $rows=DB::all('SELECT s.*,u.name,u.mobile,u.email FROM support_staff s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC');
   json_response(['ok'=>true,'data'=>$rows]);
  }
  if($action==='admin-support-staff-save'&&$method==='POST'){
   $id=(int)($in['id']??0);$userId=(int)($in['user_id']??0);$department=trim((string)($in['department']??''));$maxTickets=(int)($in['max_tickets']??20);$active=!empty($in['is_active'])?1:0;
   if(!$userId)json_response(['ok'=>false,'message'=>'کاربر الزامی است.'],422);
   $user=DB::one('SELECT id FROM users WHERE id=?',[$userId]);if(!$user)json_response(['ok'=>false,'message'=>'کاربر پیدا نشد.'],404);
   if($id){
    DB::exec('UPDATE support_staff SET department=?,max_tickets=?,is_active=? WHERE id=?',[$department?:null,$maxTickets,$active,$id]);
   }else{
    if(DB::one('SELECT id FROM support_staff WHERE user_id=?',[$userId]))json_response(['ok'=>false,'message'=>'این کاربر قبلاً به عنوان پرسنل پشتیبانی ثبت شده است.'],422);
    DB::exec('INSERT INTO support_staff(user_id,department,max_tickets,is_active)VALUES(?,?,?,?)',[$userId,$department?:null,$maxTickets,$active]);
   }
   audit($uid,null,'save','support_staff',$id,null,['user_id'=>$userId,'department'=>$department]);
   json_response(['ok'=>true,'message'=>'پرسنل پشتیبانی ذخیره شد.']);
  }
  if($action==='admin-support-staff-delete'&&$method==='POST'){
   $id=(int)($in['id']??0);
   DB::exec('DELETE FROM support_staff WHERE id=?',[$id]);
   json_response(['ok'=>true,'message'=>'پرسنل پشتیبانی حذف شد.']);
  }

  // My Templates endpoints (user templates)
  if($action==='my-templates'){
   $rows=DB::all('SELECT * FROM output_templates WHERE user_id=? AND is_global=0 ORDER BY is_default DESC,id DESC',[$uid]);
   foreach($rows as &$row){
    $row['config']=json_decode((string)$row['config_json'],true)?:[];
    $row['typography']=json_decode((string)$row['typography_json'],true)?:[];
    unset($row['config_json'],$row['typography_json']);
   }
   json_response(['ok'=>true,'data'=>$rows]);
  }
  if($action==='my-template-get'){
   $id=(int)($in['id']??0);
   $row=DB::one('SELECT * FROM output_templates WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$row)json_response(['ok'=>false,'message'=>'템플릿 bulunamadı.'],404);
   $row['config']=json_decode((string)$row['config_json'],true)?:[];
   $row['typography']=json_decode((string)$row['typography_json'],true)?:[];
   unset($row['config_json'],$row['typography_json']);
   json_response(['ok'=>true,'data'=>$row]);
  }
  if($action==='my-template-save'&&$method==='POST'){
   $id=(int)($in['id']??0);
   $name=trim((string)($in['name']??''));
   if(mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام قالب الزامی است.'],422);
   $paper=in_array($in['paper_size']??'',['a3','a4','a5','a6','letter','legal'],true)?$in['paper_size']:'a4';
   $orientation=($in['orientation']??'landscape')==='portrait'?'portrait':'landscape';
   $style=in_array($in['style']??'',['formal','modern','minimal'],true)?$in['style']:'formal';
   $allowedColumns=['code','unit','quantity','unit_price','gross','discount','after_discount','tax','total'];
   $allowedSections=['seller','buyer','notes','payment','signatures','footer'];
   $columns=[];$sections=[];
   foreach($allowedColumns as $key)$columns[$key]=!empty($in['columns'][$key]);
   foreach($allowedSections as $key)$sections[$key]=!empty($in['sections'][$key]);
   $allowedOrder=['header','seller','buyer','items','notes','signatures','footer'];
   $order=array_values(array_intersect(is_array($in['order']??null)?$in['order']:[],$allowedOrder));
   foreach($allowedOrder as $key)if(!in_array($key,$order,true))$order[]=$key;
   $config=json_encode(['columns'=>$columns,'sections'=>$sections,'order'=>$order],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   // Typography
   $typographySections=['header','body','table','notes','footer'];
   $typography=[];
   foreach($typographySections as $section){
    $typography[$section]=[
     'font_family'=>(string)($in["typography_font_{$section}"]??''),
     'font_size'=>(string)($in["typography_size_{$section}"]??''),
    ];
   }
   $typographyJson=json_encode($typography,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $default=!empty($in['is_default'])?1:0;
   $active=$default?1:(!empty($in['is_active'])?1:0);
   DB::pdo()->beginTransaction();
   if($default)DB::exec('UPDATE output_templates SET is_default=0 WHERE user_id=?',[$uid]);
   if($id){
    if(!DB::one('SELECT id FROM output_templates WHERE id=? AND user_id=?',[$id,$uid]))json_response(['ok'=>false,'message'=>'템플릿 bulunamadı.'],404);
    DB::exec('UPDATE output_templates SET name=?,paper_size=?,orientation=?,style=?,config_json=?,typography_json=?,is_default=?,is_active=? WHERE id=? AND user_id=?',[$name,$paper,$orientation,$style,$config,$typographyJson,$default,$active,$id,$uid]);
   }else{
    DB::exec('INSERT INTO output_templates(name,paper_size,orientation,style,config_json,typography_json,is_default,is_active,created_by,user_id,is_global)VALUES(?,?,?,?,?,?,?,?,?,?,0)',[$name,$paper,$orientation,$style,$config,$typographyJson,$default,$active,$uid,$uid]);
    $id=(int)DB::pdo()->lastInsertId();
   }
   DB::pdo()->commit();
   audit($uid,null,'save','my_template',$id,null,['name'=>$name,'columns'=>$columns,'sections'=>$sections,'order'=>$order,'typography'=>$typography]);
   json_response(['ok'=>true,'message'=>'템플릿 ذخیره شد.','id'=>$id]);
  }
  if($action==='my-template-delete'&&$method==='POST'){
   $id=(int)($in['id']??0);
   $row=DB::one('SELECT is_default FROM output_templates WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$row)json_response(['ok'=>false,'message'=>'템플릿 bulunamadı.'],404);
   if((int)$row['is_default']===1)json_response(['ok'=>false,'message':'پیش‌فرض قابل حذف نیست.'],422);
   DB::exec('DELETE FROM output_templates WHERE id=? AND user_id=?',[$id,$uid]);
   json_response(['ok'=>true,'message'=>'템플릿 حذف شد.']);
  }
  if($action==='my-template-duplicate'&&$method==='POST'){
   $id=(int)($in['id']??0);
   $src=DB::one('SELECT * FROM output_templates WHERE id=? AND user_id=?',[$id,$uid]);
   if(!$src)json_response(['ok'=>false,'message'=>'템플릿 bulunamadı.'],404);
   DB::exec('UPDATE output_templates SET is_default=0 WHERE user_id=?',[$uid]);
   DB::exec('INSERT INTO output_templates(name,paper_size,orientation,style,config_json,typography_json,is_default,is_active,created_by,user_id,is_global)VALUES(?,?,?,?,?,?,0,1,?,?,0)',[$src['name'].' (کپی)',$src['paper_size'],$src['orientation'],$src['style'],$src['config_json'],$src['typography_json'],$uid,$uid]);
   $newId=(int)DB::pdo()->lastInsertId();
   json_response(['ok'=>true,'message'=>'کپی ایجاد شد.','id'=>$newId]);
  }

  if(str_starts_with($action,'admin-'))Auth::requireAdmin();
 if($action==='admin-overview'){
  $stats=DB::one("SELECT (SELECT COUNT(*) FROM users) users,(SELECT COUNT(*) FROM users WHERE is_active=1) active_users,(SELECT COUNT(*) FROM companies) companies,(SELECT COUNT(*) FROM catalog_items WHERE is_active=1) catalog,(SELECT COUNT(*) FROM quotes) quotes,(SELECT COALESCE(SUM(total),0) FROM quotes WHERE status='issued') issued_total");
  json_response(['ok'=>true,'data'=>$stats]);
 }
 if($action==='admin-users'){
  $q='%'.trim((string)($in['q']??'')).'%';$users=DB::all('SELECT u.id,u.mobile,u.name,u.email,u.role,u.is_active,u.last_login_at,u.created_at,(SELECT COUNT(*) FROM companies c WHERE c.user_id=u.id) companies_count,(SELECT COUNT(*) FROM quotes q WHERE q.user_id=u.id) quotes_count FROM users u WHERE u.mobile LIKE ? OR u.name LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT 200',[$q,$q,$q]);json_response(['ok'=>true,'data'=>$users]);
 }
 if($action==='admin-user-save'&&$method==='POST'){
  $id=(int)($in['id']??0);$role=($in['role']??'user')==='admin'?'admin':'user';$active=!empty($in['is_active'])?1:0;if($id===$uid&&(!$active||$role!=='admin'))json_response(['ok'=>false,'message'=>'نمی‌توانید دسترسی مدیر فعلی را حذف یا حساب خود را مسدود کنید.'],422);
  $before=DB::one('SELECT id,role,is_active FROM users WHERE id=?',[$id]);if(!$before)json_response(['ok'=>false,'message'=>'کاربر پیدا نشد.'],404);
  if($before['role']==='admin'&&($role!=='admin'||!$active)){ $admins=DB::one("SELECT COUNT(*) c FROM users WHERE role='admin' AND is_active=1");if((int)$admins['c']<=1)json_response(['ok'=>false,'message'=>'آخرین مدیر فعال قابل تنزل یا مسدودکردن نیست.'],422);}
  DB::exec('UPDATE users SET role=?,is_active=? WHERE id=?',[$role,$active,$id]);audit($uid,null,'admin_user_update','user',$id,$before,['role'=>$role,'is_active'=>$active]);json_response(['ok'=>true,'message'=>'وضعیت کاربر به‌روزرسانی شد.']);
 }
 if($action==='admin-chat-threads'){
  $rows=DB::all("SELECT u.id user_id,u.name user_name,u.mobile user_mobile,
   (SELECT body FROM chat_messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) last_body,
   (SELECT created_at FROM chat_messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) last_at,
   (SELECT COUNT(*) FROM chat_messages m WHERE m.user_id=u.id AND m.sender_role='user' AND m.is_read_admin=0) unread
   FROM users u WHERE EXISTS(SELECT 1 FROM chat_messages m WHERE m.user_id=u.id) ORDER BY last_at DESC");
  json_response(['ok'=>true,'data'=>$rows]);
 }
 if($action==='admin-chat-messages'){
  $targetId=(int)($in['user_id']??0);if(!$targetId)json_response(['ok'=>false,'message'=>'کاربر نامعتبر است.'],422);
  $rows=DB::all('SELECT id,sender_role,body,created_at FROM chat_messages WHERE user_id=? ORDER BY id ASC',[$targetId]);
  DB::exec("UPDATE chat_messages SET is_read_admin=1 WHERE user_id=? AND sender_role='user' AND is_read_admin=0",[$targetId]);
  json_response(['ok'=>true,'data'=>$rows]);
 }
 if($action==='admin-chat-send'&&$method==='POST'){
  $targetId=(int)($in['user_id']??0);$body=trim((string)($in['body']??''));if(!$targetId||!DB::one('SELECT id FROM users WHERE id=?',[$targetId]))json_response(['ok'=>false,'message'=>'کاربر نامعتبر است.'],422);if($body===''||mb_strlen($body)>4000)json_response(['ok'=>false,'message'=>'متن پیام معتبر نیست.'],422);
  DB::exec("INSERT INTO chat_messages(user_id,sender_id,sender_role,body,is_read_user,is_read_admin)VALUES(?,?,'admin',?,0,1)",[$targetId,$uid,$body]);
  json_response(['ok'=>true,'message'=>'پیام ارسال شد.']);
 }
 if($action==='admin-catalog'){
  $q='%'.trim((string)($in['q']??'')).'%';$rows=DB::all('SELECT i.id,i.name,i.type,i.sku,i.unit,i.last_price,i.is_active,c.name company_name,u.id user_id,u.mobile user_mobile,u.name user_name FROM catalog_items i JOIN companies c ON c.id=i.company_id JOIN users u ON u.id=i.user_id WHERE i.name LIKE ? OR i.sku LIKE ? OR u.mobile LIKE ? ORDER BY i.id DESC LIMIT 300',[$q,$q,$q]);json_response(['ok'=>true,'data'=>$rows]);
 }
 if($action==='admin-catalog-save'&&$method==='POST'){
  $id=(int)($in['id']??0);$before=DB::one('SELECT * FROM catalog_items WHERE id=?',[$id]);if(!$before)json_response(['ok'=>false,'message'=>'مورد پیدا نشد.'],404);$name=trim((string)($in['name']??''));if(mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام معتبر نیست.'],422);$type=($in['type']??'product')==='service'?'service':'product';$price=max(0,(float)($in['last_price']??0));$active=!empty($in['is_active'])?1:0;
  DB::exec('UPDATE catalog_items SET name=?,type=?,unit=?,last_price=?,is_active=? WHERE id=?',[$name,$type,trim((string)($in['unit']??'عدد')),$price,$active,$id]);audit($uid,(int)$before['company_id'],'admin_catalog_update','catalog_item',$id,$before,['name'=>$name,'type'=>$type,'last_price'=>$price,'is_active'=>$active]);json_response(['ok'=>true,'message'=>'کالا یا خدمت به‌روزرسانی شد.']);
 }
 if($action==='admin-settings'){
  $keys=['app.name','sms.from_number','sms.pattern_code','sms.otp_param','sms.auth_prefix','sms.share_pattern_code','sms.share_number_param','sms.share_password_param','sms.share_link_param','mail.from_address','otp.ttl_seconds','otp.resend_seconds','quote.approval_text'];$out=[];foreach($keys as $key)$out[$key]=\App\Settings::get($key,'');$out['sms.api_key_configured']=\App\Settings::get('sms.api_key',(string)env('IPPANEL_API_KEY',''))!=='';json_response(['ok'=>true,'data'=>$out]);
 }
 if($action==='admin-settings-save'&&$method==='POST'){
  $plain=['app.name','sms.from_number','sms.pattern_code','sms.otp_param','sms.auth_prefix','sms.share_pattern_code','sms.share_number_param','sms.share_password_param','sms.share_link_param','mail.from_address','quote.approval_text'];foreach($plain as $key)if(array_key_exists($key,$in))\App\Settings::set($key,trim((string)$in[$key]),false,$uid);
  $ttl=max(60,min(600,(int)($in['otp.ttl_seconds']??120)));$resend=max(30,min(300,(int)($in['otp.resend_seconds']??60)));\App\Settings::set('otp.ttl_seconds',(string)$ttl,false,$uid);\App\Settings::set('otp.resend_seconds',(string)$resend,false,$uid);
  if(trim((string)($in['sms.api_key']??''))!=='')\App\Settings::set('sms.api_key',trim((string)$in['sms.api_key']),true,$uid);
  audit($uid,null,'admin_settings_update','system',null,null,['keys'=>array_keys($in)]);json_response(['ok'=>true,'message'=>'تنظیمات سامانه ذخیره شد.']);
 }
 if($action==='admin-output-templates'){
  $rows=DB::all('SELECT * FROM output_templates ORDER BY is_default DESC,id DESC');foreach($rows as &$row)$row['config']=json_decode((string)$row['config_json'],true)?:[];unset($row['config_json']);json_response(['ok'=>true,'data'=>$rows]);
 }
 if($action==='admin-output-template-save'&&$method==='POST'){
  $id=(int)($in['id']??0);$name=trim((string)($in['name']??''));if(mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام فرم خروجی الزامی است.'],422);$paper=in_array($in['paper_size']??'', ['a3','a4','a5','a6','letter','legal'],true)?$in['paper_size']:'a4';$orientation=($in['orientation']??'landscape')==='portrait'?'portrait':'landscape';$style=in_array($in['style']??'', ['formal','modern','minimal'],true)?$in['style']:'formal';
  $allowedColumns=['code','unit','quantity','unit_price','gross','discount','after_discount','tax','total'];$allowedSections=['seller','buyer','notes','payment','signatures','footer'];$columns=[];$sections=[];foreach($allowedColumns as $key)$columns[$key]=!empty($in['columns'][$key]);foreach($allowedSections as $key)$sections[$key]=!empty($in['sections'][$key]);$allowedOrder=['header','seller','buyer','items','notes','signatures','footer'];$order=array_values(array_intersect(is_array($in['order']??null)?$in['order']:[],$allowedOrder));foreach($allowedOrder as $key)if(!in_array($key,$order,true))$order[]=$key;$config=json_encode(['columns'=>$columns,'sections'=>$sections,'order'=>$order],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$default=!empty($in['is_default'])?1:0;$active=$default?1:(!empty($in['is_active'])?1:0);
  DB::pdo()->beginTransaction();if($default)DB::exec('UPDATE output_templates SET is_default=0');if($id){if(!DB::one('SELECT id FROM output_templates WHERE id=?',[$id]))json_response(['ok'=>false,'message'=>'فرم پیدا نشد.'],404);DB::exec('UPDATE output_templates SET name=?,paper_size=?,orientation=?,style=?,config_json=?,is_default=?,is_active=? WHERE id=?',[$name,$paper,$orientation,$style,$config,$default,$active,$id]);}else{DB::exec('INSERT INTO output_templates(name,paper_size,orientation,style,config_json,is_default,is_active,created_by)VALUES(?,?,?,?,?,?,?,?)',[$name,$paper,$orientation,$style,$config,$default,$active,$uid]);$id=(int)DB::pdo()->lastInsertId();}DB::pdo()->commit();audit($uid,null,'save','output_template',$id,null,['name'=>$name,'columns'=>$columns,'sections'=>$sections,'order'=>$order]);json_response(['ok'=>true,'message'=>'فرم خروجی ذخیره شد.','id'=>$id]);
 }
 if($action==='admin-output-template-delete'&&$method==='POST'){$id=(int)($in['id']??0);$row=DB::one('SELECT is_default FROM output_templates WHERE id=?',[$id]);if(!$row)json_response(['ok'=>false,'message'=>'فرم پیدا نشد.'],404);if((int)$row['is_default']===1)json_response(['ok'=>false,'message'=>'فرم پیش‌فرض قابل حذف نیست. ابتدا فرم دیگری را پیش‌فرض کنید.'],422);DB::exec('DELETE FROM output_templates WHERE id=?',[$id]);json_response(['ok'=>true,'message'=>'فرم خروجی حذف شد.']);}
 if($action==='bootstrap'){
  $companies=DB::all('SELECT * FROM companies WHERE user_id=? ORDER BY id DESC',[$uid]);
  try{
   $quotes=DB::all("SELECT q.id,q.quote_number,q.status,q.issue_date,q.total,COALESCE(cu.name,'') customer_name,c.name company_name FROM quotes q JOIN companies c ON c.id=q.company_id LEFT JOIN customers cu ON cu.id=q.customer_id AND cu.user_id=q.user_id WHERE q.user_id=? AND (q.is_latest=1 OR q.is_latest IS NULL) ORDER BY q.id DESC LIMIT 100",[$uid]);
   if(empty($quotes)){
    $quotes=DB::all("SELECT q.id,q.quote_number,q.status,q.issue_date,q.total,COALESCE(cu.name,'') customer_name,c.name company_name FROM quotes q JOIN companies c ON c.id=q.company_id LEFT JOIN customers cu ON cu.id=q.customer_id AND cu.user_id=q.user_id WHERE q.user_id=? ORDER BY q.id DESC LIMIT 100",[$uid]);
   }
  }catch(Throwable){
   $quotes=DB::all("SELECT q.id,q.quote_number,q.status,q.issue_date,q.total,COALESCE(cu.name,'') customer_name,c.name company_name FROM quotes q JOIN companies c ON c.id=q.company_id LEFT JOIN customers cu ON cu.id=q.customer_id AND cu.user_id=q.user_id WHERE q.user_id=? ORDER BY q.id DESC LIMIT 100",[$uid]);
  }
  $templates=DB::all('SELECT id,name,paper_size,orientation,style,config_json,is_default FROM output_templates WHERE is_active=1 ORDER BY is_default DESC,id DESC');foreach($templates as &$template)$template['config']=json_decode((string)$template['config_json'],true)?:[];unset($template['config_json']);
  $chatUnread=0;$adminChatUnread=0;
  try{
   $chatUnread=(int)(DB::one("SELECT COUNT(*) c FROM chat_messages WHERE user_id=? AND sender_role='admin' AND is_read_user=0",[$uid])['c']??0);
   if((Auth::user()['role']??'')==='admin')$adminChatUnread=(int)(DB::one("SELECT COUNT(*) c FROM chat_messages WHERE sender_role='user' AND is_read_admin=0")['c']??0);
  }catch(Throwable){}
  json_response(['ok'=>true,'data'=>['companies'=>$companies,'quotes'=>$quotes,'templates'=>$templates,'user'=>Auth::user(),'chat_unread'=>$chatUnread,'admin_chat_unread'=>$adminChatUnread]]);
 }
 if($action==='company-save'&&$method==='POST'){
  $name=trim((string)($in['name']??''));if(mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام شرکت الزامی است.'],422);
  $legalName=trim((string)($in['legal_name']??''));$nationalId=trim((string)($in['national_id']??''));$economicCode=trim((string)($in['economic_code']??''));$registrationNo=trim((string)($in['registration_no']??''));$phone=trim((string)($in['phone']??''));$mobile=trim((string)($in['mobile']??''));$email=trim((string)($in['email']??''));$website=trim((string)($in['website']??''));$address=trim((string)($in['address']??''));$postalCode=trim((string)($in['postal_code']??''));$brandColor=trim((string)($in['brand_color']??'#2563eb'));$bankInfo=trim((string)($in['bank_info']??''));$defaultTerms=trim((string)($in['default_terms']??''));
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['ok'=>false,'message'=>'ایمیل شرکت معتبر نیست.'],422);if(!preg_match('/^#[0-9a-fA-F]{6}$/',$brandColor))$brandColor='#2563eb';
  $tax=max(0,min(100,(float)($in['default_tax']??0)));$id=(int)($in['id']??0);
  DB::pdo()->beginTransaction();
  if($id){$own=DB::one('SELECT id FROM companies WHERE id=? AND user_id=?',[$id,$uid]);if(!$own)json_response(['ok'=>false,'message'=>'دسترسی غیرمجاز.'],403);
   DB::exec('UPDATE companies SET name=?,legal_name=?,national_id=?,economic_code=?,registration_no=?,phone=?,mobile=?,email=?,website=?,address=?,postal_code=?,brand_color=?,bank_info=?,default_terms=?,default_tax=? WHERE id=? AND user_id=?',[$name,$legalName?:null,$nationalId?:null,$economicCode?:null,$registrationNo?:null,$phone?:null,$mobile?:null,$email?:null,$website?:null,$address?:null,$postalCode?:null,$brandColor,$bankInfo?:null,$defaultTerms?:null,$tax,$id,$uid]);
  }else{DB::exec('INSERT INTO companies(user_id,name,legal_name,national_id,economic_code,registration_no,phone,mobile,email,website,address,postal_code,brand_color,bank_info,default_terms,default_tax)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$uid,$name,$legalName?:null,$nationalId?:null,$economicCode?:null,$registrationNo?:null,$phone?:null,$mobile?:null,$email?:null,$website?:null,$address?:null,$postalCode?:null,$brandColor,$bankInfo?:null,$defaultTerms?:null,$tax]);$id=(int)DB::pdo()->lastInsertId();}
  audit($uid,$id,'save','company',$id,null,['name'=>$name,'legal_name'=>$legalName,'national_id'=>$nationalId,'economic_code'=>$economicCode,'registration_no'=>$registrationNo]);DB::pdo()->commit();json_response(['ok'=>true,'message'=>'اطلاعات شرکت ذخیره شد.','id'=>$id]);
 }
 if($action==='quote-share'&&$method==='POST'){
  $quoteId=(int)($in['quote_id']??0);$quote=DB::one('SELECT q.*,c.name company_name FROM quotes q JOIN companies c ON c.id=q.company_id WHERE q.id=? AND q.user_id=?',[$quoteId,$uid]);if(!$quote)json_response(['ok'=>false,'message'=>'ابتدا پیش‌فاکتور را ذخیره کنید.'],404);
  $days=max(1,min(90,(int)($in['expires_days']??14)));$password=(string)random_int(100000,999999);$token=bin2hex(random_bytes(32));
  DB::exec('UPDATE quote_shares SET revoked_at=NOW() WHERE quote_id=? AND user_id=? AND revoked_at IS NULL',[$quoteId,$uid]);DB::exec('INSERT INTO quote_shares(quote_id,user_id,token_hash,password_hash,expires_at)VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))',[$quoteId,$uid,hash('sha256',$token),password_hash($password,PASSWORD_DEFAULT),$days]);
  $link=url('q/'.$token);$sent=[];$errors=[];$email=trim((string)($in['email']??''));$mobile=Otp::normalize((string)($in['mobile']??''));
  if(!empty($in['send_email'])){if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='ایمیل مشتری معتبر نیست.';else{$from=str_replace(["\r","\n"],'',(string)\App\Settings::get('mail.from_address','no-reply@'.($_SERVER['HTTP_HOST']??'localhost')));$subject='پیش‌فاکتور شماره '.$quote['quote_number'];$body="پیش‌فاکتور {$quote['quote_number']} از {$quote['company_name']}\n\nلینک مشاهده: {$link}\nرمز مشاهده: {$password}\nاعتبار لینک: {$days} روز";$headers="From: {$from}\r\nContent-Type: text/plain; charset=UTF-8";if(@mail($email,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,$headers))$sent[]='email';else$errors[]='ارسال ایمیل توسط هاست ناموفق بود.';}}
  if(!empty($in['send_sms'])){if(!$mobile)$errors[]='شماره موبایل مشتری معتبر نیست.';else{[$ok,$msg]=Otp::sendPattern($mobile,(string)\App\Settings::get('sms.share_pattern_code',''),[(string)\App\Settings::get('sms.share_number_param','number')=>(string)$quote['quote_number'],(string)\App\Settings::get('sms.share_password_param','password')=>$password,(string)\App\Settings::get('sms.share_link_param','link')=>$link]);if($ok)$sent[]='sms';else$errors[]=$msg;}}
  audit($uid,(int)$quote['company_id'],'share','quote',$quoteId,null,['expires_days'=>$days,'channels'=>$sent]);json_response(['ok'=>true,'message'=>$errors?implode(' ',$errors):'لینک امن ساخته و ارسال شد.','data'=>['url'=>$link,'password'=>$password,'expires_days'=>$days,'sent'=>$sent,'errors'=>$errors]]);
 }
 if($action==='quote-share-revoke'&&$method==='POST'){$quoteId=(int)($in['quote_id']??0);DB::exec('UPDATE quote_shares SET revoked_at=NOW() WHERE quote_id=? AND user_id=? AND revoked_at IS NULL',[$quoteId,$uid]);json_response(['ok'=>true,'message'=>'لینک‌های فعال این پیش‌فاکتور لغو شدند.']);}
 if($action==='asset-upload'&&$method==='POST'){
  $companyId=(int)($_POST['company_id']??0);$kind=(string)($_POST['kind']??'');$map=['logo'=>'logo_path','stamp'=>'stamp_path','signature'=>'signature_path'];
  if(!isset($map[$kind])||!DB::one('SELECT id FROM companies WHERE id=? AND user_id=?',[$companyId,$uid])||!isset($_FILES['file']))json_response(['ok'=>false,'message'=>'درخواست نامعتبر است.'],422);
  $f=$_FILES['file'];if($f['error']!==UPLOAD_ERR_OK||$f['size']>2097152)json_response(['ok'=>false,'message'=>'فایل باید تصویر و حداکثر ۲ مگابایت باشد.'],422);
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;if(!$ext)json_response(['ok'=>false,'message'=>'فرمت تصویر مجاز نیست.'],422);
  $dir=dirname(__DIR__).'/storage/uploads/companies/'.$companyId;if(!is_dir($dir))mkdir($dir,0750,true);$name=$kind.'-'.bin2hex(random_bytes(8)).'.'.$ext;$dest=$dir.'/'.$name;
  if(!move_uploaded_file($f['tmp_name'],$dest))json_response(['ok'=>false,'message'=>'ذخیره فایل ناموفق بود.'],500);$rel='storage/uploads/companies/'.$companyId.'/'.$name;
  DB::exec('UPDATE companies SET '.$map[$kind].'=? WHERE id=? AND user_id=?',[$rel,$companyId,$uid]);json_response(['ok'=>true,'path'=>$rel]);
 }
 if($action==='asset'&&$method==='GET'){
  $companyId=(int)($in['company_id']??0);$kind=(string)($in['kind']??'');$map=['logo'=>'logo_path','stamp'=>'stamp_path','signature'=>'signature_path'];$row=isset($map[$kind])?DB::one('SELECT '.$map[$kind].' path FROM companies WHERE id=? AND user_id=?',[$companyId,$uid]):null;
  if(!$row||!$row['path']){http_response_code(404);exit;}$file=dirname(__DIR__).'/'.$row['path'];if(!is_file($file)){http_response_code(404);exit;}header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file));header('Cache-Control: private,max-age=3600');readfile($file);exit;
 }
 if($action==='customer-search'){
  $cid=ownCompany($uid,(int)($in['company_id']??0));$q='%'.trim((string)($in['q']??'')).'%';
  json_response(['ok'=>true,'data'=>DB::all('SELECT * FROM customers WHERE user_id=? AND company_id=? AND (name LIKE ? OR mobile LIKE ? OR national_id LIKE ?) ORDER BY updated_at DESC LIMIT 10',[$uid,$cid,$q,$q,$q])]);
 }
 if($action==='customer-save'&&$method==='POST'){
  $cid=ownCompany($uid,(int)($in['company_id']??0));$name=trim((string)($in['name']??''));if(mb_strlen($name)<2)json_response(['ok'=>false,'message'=>'نام مشتری الزامی است.'],422);
  $cols=['type','contact_name','mobile','phone','email','national_id','economic_code','registration_no','address','postal_code','notes'];$v=[];foreach($cols as $c)$v[]=trim((string)($in[$c]??($c==='type'?'business':'')));
  DB::exec('INSERT INTO customers(user_id,company_id,name,type,contact_name,mobile,phone,email,national_id,economic_code,registration_no,address,postal_code,notes)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$uid,$cid,$name,...$v]);json_response(['ok'=>true,'id'=>(int)DB::pdo()->lastInsertId()]);
 }
 if($action==='catalog-search'){
  $cid=ownCompany($uid,(int)($in['company_id']??0));$term=trim((string)($in['q']??''));
  $term=strtr($term,['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه']);$q='%'.$term.'%';
  $normalized="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,'ي','ی'),'ى','ی'),'ك','ک'),'ۀ','ه'),'ة','ه')";
  json_response(['ok'=>true,'data'=>DB::all("SELECT * FROM catalog_items WHERE user_id=? AND company_id=? AND is_active=1 AND($normalized LIKE ? OR sku LIKE ?)ORDER BY last_used_at DESC,updated_at DESC LIMIT 20",[$uid,$cid,$q,$q])]);
 }
 if($action==='quote-get'){$id=(int)($in['id']??0);$q=DB::one('SELECT * FROM quotes WHERE id=? AND user_id=?',[$id,$uid]);if(!$q)json_response(['ok'=>false,'message'=>'سند پیدا نشد.'],404);$q['items']=DB::all('SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order,id',[$id]);json_response(['ok'=>true,'data'=>$q]);}
 if($action==='quote-save'&&$method==='POST'){json_response(saveQuote($uid,$in));}
 if($action==='quote-finalize'&&$method==='POST'){
  $id=(int)($in['id']??0);$q=DB::one('SELECT * FROM quotes WHERE id=? AND user_id=?',[$id,$uid]);if(!$q)json_response(['ok'=>false,'message'=>'سند پیدا نشد.'],404);
  DB::pdo()->beginTransaction();DB::exec("UPDATE quotes SET status='issued',issued_at=NOW(),version=version+1 WHERE id=? AND user_id=? AND status='draft'",[$id,$uid]);
  $items=DB::all('SELECT catalog_item_id,unit_price FROM quote_items WHERE quote_id=? AND catalog_item_id IS NOT NULL',[$id]);foreach($items as $it){DB::exec('UPDATE catalog_items SET last_price=?,last_used_at=NOW()WHERE id=? AND user_id=?',[$it['unit_price'],$it['catalog_item_id'],$uid]);DB::exec('INSERT INTO price_history(catalog_item_id,company_id,price,source_quote_id)VALUES(?,?,?,?)',[$it['catalog_item_id'],$q['company_id'],$it['unit_price'],$id]);}DB::pdo()->commit();
  audit($uid,(int)$q['company_id'],'finalize','quote',$id,$q,null);json_response(['ok'=>true,'message'=>'پیش‌فاکتور صادر و قفل شد.']);
 }
 if($action==='quote-revise'&&$method==='POST'){json_response(reviseQuote($uid,(int)($in['id']??0)));}
 if($action==='quote-history'){$id=(int)($in['id']??0);$cur=DB::one('SELECT id,parent_quote_id FROM quotes WHERE id=? AND user_id=?',[$id,$uid]);if(!$cur)json_response(['ok'=>false,'message'=>'سند پیدا نشد.'],404);$rootId=(int)($cur['parent_quote_id']?:$id);$chain=DB::all("SELECT id,quote_number,revision_no,status,is_latest,issued_at,created_at FROM quotes WHERE (id=? OR parent_quote_id=?) AND user_id=? ORDER BY revision_no ASC",[$rootId,$rootId,$uid]);json_response(['ok'=>true,'data'=>$chain]);}
 json_response(['ok'=>false,'message'=>'مسیر API پیدا نشد.'],404);
}catch(Throwable $e){try{if(DB::pdo()->inTransaction())DB::pdo()->rollBack();}catch(Throwable){}$requestId=bin2hex(random_bytes(6));error_log('[request:'.$requestId.'] '.$e->__toString());json_response(['ok'=>false,'message'=>env('APP_DEBUG')?$e->getMessage():'خطای داخلی رخ داد. کد پیگیری: '.$requestId],500);}

function reviseQuote(int $uid,int $id):array{
 if(!$id)json_response(['ok'=>false,'message'=>'سند نامعتبر است.'],422);
 DB::pdo()->beginTransaction();
 $q=DB::one("SELECT * FROM quotes WHERE id=? AND user_id=? AND status='issued' FOR UPDATE",[$id,$uid]);
 if(!$q){DB::pdo()->rollBack();json_response(['ok'=>false,'message'=>'فقط فاکتور صادرشده و نهایی‌شده قابل ثبت اصلاحیه است.'],409);}
 $rootId=(int)($q['parent_quote_id']?:$id);
 $maxRevision=(int)(DB::one('SELECT MAX(revision_no) m FROM quotes WHERE (id=? OR parent_quote_id=?) AND user_id=?',[$rootId,$rootId,$uid])['m']??1);
 $nextRevision=$maxRevision+1;
 $baseNumber=preg_replace('/-V\d+$/','',(string)$q['quote_number']);
 $newNumber=$baseNumber.'-V'.$nextRevision;
 DB::exec('UPDATE quotes SET is_latest=0 WHERE (id=? OR parent_quote_id=?) AND user_id=?',[$rootId,$rootId,$uid]);
 DB::exec("INSERT INTO quotes(user_id,company_id,parent_quote_id,customer_id,quote_number,status,issue_date,valid_until,currency,company_snapshot,customer_snapshot,subtotal,discount_type,discount_value,discount_amount,tax_amount,total,prepayment,payable,notes,terms,version,revision_no,is_latest)
  SELECT user_id,company_id,?,customer_id,?,'draft',issue_date,valid_until,currency,company_snapshot,customer_snapshot,subtotal,discount_type,discount_value,discount_amount,tax_amount,total,prepayment,payable,notes,terms,1,?,1 FROM quotes WHERE id=? AND user_id=?",
  [$rootId,$newNumber,$nextRevision,$id,$uid]);
 $newId=(int)DB::pdo()->lastInsertId();
 DB::exec('INSERT INTO quote_items(quote_id,catalog_item_id,sort_order,type,name,description,sku,unit,quantity,unit_price,discount_percent,tax_percent,line_subtotal,line_total)
  SELECT ?,catalog_item_id,sort_order,type,name,description,sku,unit,quantity,unit_price,discount_percent,tax_percent,line_subtotal,line_total FROM quote_items WHERE quote_id=?',[$newId,$id]);
 audit($uid,(int)$q['company_id'],'revise','quote',$newId,$q,['revised_from'=>$id,'new_quote_number'=>$newNumber]);
 DB::pdo()->commit();
 return['ok'=>true,'message'=>'اصلاحیه جدید به صورت پیش‌نویس ایجاد شد. سند قبلی به عنوان مدرک، بدون تغییر باقی می‌ماند.','data'=>['id'=>$newId,'quote_number'=>$newNumber,'parent_quote_id'=>$rootId]];
}
function ownCompany(int $uid,int $id):int{if(!$id||!DB::one('SELECT id FROM companies WHERE id=? AND user_id=?',[$id,$uid]))json_response(['ok'=>false,'message'=>'شرکت معتبر نیست.'],403);return$id;}
function audit(int $uid,?int $company,string $action,string $type,?int $id,mixed $before,mixed $after):void{$flags=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE;DB::exec('INSERT INTO audit_logs(user_id,company_id,action,entity_type,entity_id,before_data,after_data,ip,user_agent)VALUES(?,?,?,?,?,?,?,?,?)',[$uid,$company,$action,$type,$id,$before!==null?json_encode($before,$flags):null,$after!==null?json_encode($after,$flags):null,substr($_SERVER['REMOTE_ADDR']??'',0,45),substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);}
function saveQuote(int $uid,array $in):array{
 $cid=ownCompany($uid,(int)($in['company_id']??0));$company=DB::one('SELECT * FROM companies WHERE id=? AND user_id=?',[$cid,$uid]);$customerId=(int)($in['customer_id']??0);$customer=$customerId?DB::one('SELECT * FROM customers WHERE id=? AND company_id=? AND user_id=?',[$customerId,$cid,$uid]):null;
 $issueDate=(string)($in['issue_date']??'');$issueObject=DateTime::createFromFormat('!Y-m-d',$issueDate);if(!$issueObject||$issueObject->format('Y-m-d')!==$issueDate)json_response(['ok'=>false,'message'=>'تاریخ صدور جلالی را از تقویم انتخاب کنید.'],422);$validUntil=trim((string)($in['valid_until']??''));if($validUntil!==''){$validObject=DateTime::createFromFormat('!Y-m-d',$validUntil);if(!$validObject||$validObject->format('Y-m-d')!==$validUntil)json_response(['ok'=>false,'message'=>'تاریخ اعتبار را از تقویم انتخاب کنید.'],422);}
 if(!$customer){$cn=trim((string)($in['customer_name']??''));if(mb_strlen($cn)<2)json_response(['ok'=>false,'message'=>'مشتری را انتخاب یا ایجاد کنید.'],422);DB::exec('INSERT INTO customers(user_id,company_id,name,mobile,phone,national_id,economic_code,registration_no,address,postal_code)VALUES(?,?,?,?,?,?,?,?,?,?)',[$uid,$cid,$cn,(string)($in['customer_mobile']??''),(string)($in['customer_phone']??''),(string)($in['customer_national_id']??''),(string)($in['customer_economic_code']??''),(string)($in['customer_registration_no']??''),(string)($in['customer_address']??''),(string)($in['customer_postal_code']??'')]);$customerId=(int)DB::pdo()->lastInsertId();$customer=DB::one('SELECT * FROM customers WHERE id=?',[$customerId]);}
 $items=is_array($in['items']??null)?$in['items']:[];if(!$items)json_response(['ok'=>false,'message'=>'حداقل یک ردیف معتبر لازم است.'],422);$clean=[];$subtotal=0;$itemDiscount=0;$tax=0;
 foreach($items as $i=>$it){$name=trim((string)($it['name']??''));$qty=max(0,(float)($it['quantity']??0));$price=max(0,(float)($it['unit_price']??0));if(!$name||$qty<=0)continue;$dp=max(0,min(100,(float)($it['discount_percent']??0)));$tp=max(0,min(100,(float)($it['tax_percent']??0)));$base=round($qty*$price,2);$after=round($base*(1-$dp/100),2);$lineTax=round($after*$tp/100,2);$total=$after+$lineTax;$subtotal+=$base;$itemDiscount+=($base-$after);$tax+=$lineTax;$catalogId=(int)($it['catalog_item_id']??0);
  if(!$catalogId){DB::exec('INSERT INTO catalog_items(user_id,company_id,type,name,sku,description,unit,last_price,default_tax)VALUES(?,?,?,?,?,?,?,?,?)',[$uid,$cid,($it['type']??'product')==='service'?'service':'product',$name,(string)($it['sku']??''),(string)($it['description']??''),(string)($it['unit']??'عدد'),$price,$tp]);$catalogId=(int)DB::pdo()->lastInsertId();}
  $clean[]=['catalog_item_id'=>$catalogId,'type'=>($it['type']??'product')==='service'?'service':'product','name'=>$name,'description'=>(string)($it['description']??''),'sku'=>(string)($it['sku']??''),'unit'=>(string)($it['unit']??'عدد'),'quantity'=>$qty,'unit_price'=>$price,'discount_percent'=>$dp,'tax_percent'=>$tp,'line_subtotal'=>$base,'line_total'=>$total];
 }
 if(!$clean)json_response(['ok'=>false,'message'=>'حداقل یک ردیف معتبر لازم است.'],422);$discountType=($in['discount_type']??'amount')==='percent'?'percent':'amount';$dv=max(0,(float)($in['discount_value']??0));$discountBase=max(0,$subtotal-$itemDiscount);$da=$discountType==='percent'?round($discountBase*min($dv,100)/100,2):min($dv,$discountBase);$total=max(0,$subtotal-$itemDiscount-$da+$tax);$pre=max(0,min((float)($in['prepayment']??0),$total));$payable=$total-$pre;$id=(int)($in['id']??0);
 DB::pdo()->beginTransaction();
 if($id){$old=DB::one("SELECT * FROM quotes WHERE id=? AND user_id=? AND status='draft' FOR UPDATE",[$id,$uid]);if(!$old)json_response(['ok'=>false,'message'=>'فقط پیش‌نویس قابل ویرایش است.'],409);DB::exec('UPDATE quotes SET customer_id=?,issue_date=?,valid_until=?,customer_snapshot=?,subtotal=?,discount_type=?,discount_value=?,discount_amount=?,tax_amount=?,total=?,prepayment=?,payable=?,notes=?,terms=?,version=version+1 WHERE id=? AND user_id=?',[$customerId,$issueDate,$validUntil?:null,json_encode($customer,JSON_UNESCAPED_UNICODE),$subtotal,$discountType,$dv,$da,$tax,$total,$pre,$payable,(string)($in['notes']??''),(string)($in['terms']??''),$id,$uid]);DB::exec('DELETE FROM quote_items WHERE quote_id=?',[$id]);
 }else{$num=$company['quote_prefix'].'-'.str_pad((string)$company['next_quote_number'],5,'0',STR_PAD_LEFT);DB::exec('UPDATE companies SET next_quote_number=next_quote_number+1 WHERE id=? AND user_id=?',[$cid,$uid]);DB::exec('INSERT INTO quotes(user_id,company_id,customer_id,quote_number,issue_date,valid_until,company_snapshot,customer_snapshot,subtotal,discount_type,discount_value,discount_amount,tax_amount,total,prepayment,payable,notes,terms)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$uid,$cid,$customerId,$num,$issueDate,$validUntil?:null,json_encode($company,JSON_UNESCAPED_UNICODE),json_encode($customer,JSON_UNESCAPED_UNICODE),$subtotal,$discountType,$dv,$da,$tax,$total,$pre,$payable,(string)($in['notes']??''),(string)($in['terms']??'')]);$id=(int)DB::pdo()->lastInsertId();}
 foreach($clean as $n=>$it)DB::exec('INSERT INTO quote_items(quote_id,catalog_item_id,sort_order,type,name,description,sku,unit,quantity,unit_price,discount_percent,tax_percent,line_subtotal,line_total)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$it['catalog_item_id'],$n,...array_values(array_diff_key($it,['catalog_item_id'=>1]))]);
 DB::pdo()->commit();audit($uid,$cid,'save','quote',$id,null,['total'=>$total]);$saved=DB::one('SELECT quote_number FROM quotes WHERE id=? AND user_id=?',[$id,$uid]);return['ok'=>true,'message'=>'پیش‌نویس ذخیره شد.','id'=>$id,'quote_number'=>$saved['quote_number']??''];
}
