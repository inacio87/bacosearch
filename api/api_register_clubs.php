<?php
/**
 * /api/api_register_clubs.php - Cria/Atualiza cadastro de Clubes (clubs)
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['status'=>'error','message'=>'Method not allowed']); exit; }

function db(): PDO { return getDBConnection(); }
function table_columns(PDO $pdo,string $t): array { static $c=[]; if(isset($c[$t])) return $c[$t]; $st=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"); $st->execute([$t]); return $c[$t]=array_flip($st->fetchAll(PDO::FETCH_COLUMN)?:[]); }
function filter_for_table(PDO $pdo,string $t,array $src): array { $cols=table_columns($pdo,$t); $o=[]; foreach($src as $k=>$v){ if(isset($cols[$k])) $o[$k]=$v; } return $o; }
function create_slug(string $s): string { return strtolower(trim(preg_replace('~[^a-z0-9]+~i','-', iconv('UTF-8','ASCII//TRANSLIT',$s)),'-')); }

try {
  $pdo=db(); $pdo->beginTransaction();
  $account_id = filter_input(INPUT_POST,'account_id',FILTER_VALIDATE_INT);
  if(!$account_id) throw new Exception('Conta inválida');
  $acc = db_fetch_one("SELECT id,status FROM accounts WHERE id=?",[$account_id]);
  if(!$acc || $acc['status']!=='active') throw new Exception('Conta não encontrada ou inativa');

  $club_name = trim($_POST['club_name'] ?? ''); if($club_name==='') throw new Exception('Nome do clube é obrigatório');
  $ad_city = trim($_POST['ad_city'] ?? '');
  $slug = create_slug($club_name . ($ad_city? ('-'.$ad_city):''));

  $visitor_id = $_SESSION['visitor_db_id'] ?? null; if(!$visitor_id) throw new Exception('Sessão inválida (visitor)');
  $upload_base = dirname(__DIR__) . '/uploads/clubs/' . $visitor_id . '/'; if(!is_dir($upload_base)) @mkdir($upload_base,0755,true);
  $main_photo_url=null; $gallery=[];
  if (isset($_FILES['main_photo']) && $_FILES['main_photo']['error']===UPLOAD_ERR_OK) {
    $ext=strtolower(pathinfo($_FILES['main_photo']['name'],PATHINFO_EXTENSION)); $fn='main_'.time().'.'.$ext; $dest=$upload_base.$fn;
    if(!move_uploaded_file($_FILES['main_photo']['tmp_name'],$dest)) throw new Exception('Falha ao guardar imagem principal');
    $main_photo_url='/uploads/clubs/'.$visitor_id.'/'.$fn;
  }
  if (isset($_FILES['gallery_photos']) && is_array($_FILES['gallery_photos']['name'])) {
    foreach($_FILES['gallery_photos']['name'] as $i=>$name){ if($_FILES['gallery_photos']['error'][$i]===UPLOAD_ERR_OK){ $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); $fn='g_'.time().'_'.uniqid().'.'.$ext; $dest=$upload_base.$fn; if(move_uploaded_file($_FILES['gallery_photos']['tmp_name'][$i],$dest)) $gallery[]='/uploads/clubs/'.$visitor_id.'/'.$fn; }}
  }
  $gallery_json = $gallery? json_encode($gallery): null;

  $existing = db_fetch_one("SELECT id FROM clubs WHERE account_id=? LIMIT 1",[$account_id]);
  $payload = [
    'account_id'    => $account_id,
    'club_name'     => $club_name,
    'slug'          => $slug,
    'description'   => $_POST['description'] ?? null,
    'keywords'      => $_POST['keywords'] ?? null,
    'phone_code'    => $_POST['phone_code'] ?? null,
    'phone_number'  => $_POST['phone_number'] ?? null,
    'email'         => $_POST['email'] ?? null,
    'website_url'   => $_POST['website_url'] ?? null,
    'ad_country'    => $_POST['ad_country'] ?? null,
    'ad_state'      => $_POST['ad_state'] ?? null,
    'ad_city'       => $ad_city ?: null,
    'ad_street'     => $_POST['ad_street'] ?? null,
    'ad_postal_code'=> $_POST['ad_postal_code'] ?? null,
    'ad_latitude'   => filter_input(INPUT_POST,'ad_latitude',FILTER_VALIDATE_FLOAT) ?: null,
    'ad_longitude'  => filter_input(INPUT_POST,'ad_longitude',FILTER_VALIDATE_FLOAT) ?: null,
    'music_styles'  => $_POST['music_styles'] ?? null,
    'age_restriction'=> $_POST['age_restriction'] ?? null,
    'entry_fee'     => filter_input(INPUT_POST,'entry_fee',FILTER_VALIDATE_FLOAT) ?: null,
    'main_photo_url'=> $main_photo_url,
    'gallery_photos'=> $gallery_json,
    'status'        => 'pending',
    'is_active'     => 0,
    'updated_at'    => date('Y-m-d H:i:s'),
  ];
  $payload = filter_for_table($pdo,'clubs',$payload);

  if($existing){ $payload['id']=(int)$existing['id']; $set=[]; foreach($payload as $k=>$v){ if($k==='id') continue; $set[]="`$k`=:$k"; } if($set){ $sql='UPDATE clubs SET '.implode(',', $set).' WHERE id=:id'; $st=$pdo->prepare($sql); $st->execute($payload);} $id=(int)$existing['id']; }
  else { $payload['created_at']=date('Y-m-d H:i:s'); $cols='`'.implode('`,`',array_keys($payload)).'`'; $ph=':'.implode(',:',array_keys($payload)); $st=$pdo->prepare("INSERT INTO clubs ($cols) VALUES ($ph)"); $st->execute($payload); $id=(int)$pdo->lastInsertId(); }

  $pdo->commit(); echo json_encode(['status'=>'success','message'=>'Clube guardado com sucesso.','data'=>['id'=>$id,'slug'=>$slug]]);
} catch (Throwable $e) {
  if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
  log_system_error('API_REGISTER_CLUBS_ERROR: '.$e->getMessage(),'ERROR','api_register_clubs');
  http_response_code(500); echo json_encode(['status'=>'error','message'=>'Falha ao guardar clube.']);
}
