<?php
/**
 * /api/api_register_providers.php - Registro/Update de Providers (compatível com schema modular)
 * - providers: cria/atualiza registro base (só grava colunas que existirem)
 * - providers_body: 1:1 características físicas
 * - providers_contact: 1:1 contactos
 * - providers_logistics: 1:1 logística (plural)
 * - providers_service_offerings: N:N serviços por service_key
 *
 * Observações:
 * - Campos como display_name/slug/ad_title/description/media/prices só serão gravados em `providers` SE a coluna existir.
 * - Uploads continuam a salvar no filesystem; paths são gravados em `providers` se houver colunas (main_photo_url/galleries/videos).
 * - Status de serviços mapeado para: not_available | included | negotiable | extra
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$response = ['status' => 'error', 'message' => 'Ocorreu um erro desconhecido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status'=>'error','message'=>'Método de requisição não permitido.']);
  exit;
}

/* ---------- helpers ---------- */
function db(): PDO { return getDBConnection(); }

/** Retorna lista de colunas existentes em uma tabela (cache simples em runtime). */
function table_columns(PDO $pdo, string $table): array {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];
  $stmt = $pdo->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
  );
  $stmt->execute([$table]);
  $cols = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $cache[$table] = array_flip($cols); // flip para O(1) em isset
  return $cache[$table];
}

/** Filtra array $data mantendo apenas chaves que existem como colunas na $table. (Suporta map post->coluna) */
function filter_for_table(PDO $pdo, string $table, array $data, array $map = []): array {
  $cols = table_columns($pdo, $table);
  $out = [];
  if ($map) {
    foreach ($map as $from => $to) {
      if (array_key_exists($from, $data) && isset($cols[$to])) {
        $out[$to] = $data[$from];
      }
    }
  } else {
    foreach ($data as $k => $v) {
      if (isset($cols[$k])) $out[$k] = $v;
    }
  }
  return $out;
}

/** UPSERT 1:1 por PK provider_id (se já existe faz UPDATE, senão INSERT). */
function upsert_one_to_one(PDO $pdo, string $table, array $row, string $pk = 'provider_id'): void {
  if (!isset($row[$pk])) throw new InvalidArgumentException("Falta $pk em $table");
  // Existe?
  $exists = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$pk` = ? LIMIT 1");
  $exists->execute([$row[$pk]]);
  if ($exists->fetchColumn()) {
    $set = [];
    $params = [];
    foreach ($row as $k => $v) {
      if ($k === $pk) continue;
      $set[] = "`$k` = :$k";
      $params[$k] = $v;
    }
    if ($set) {
      $params[$pk] = $row[$pk];
      $sql = "UPDATE `$table` SET ".implode(',',$set)." WHERE `$pk` = :$pk";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
    }
  } else {
    $cols = '`'.implode('`,`', array_keys($row)).'`';
    $ph   = ':'.implode(',:', array_keys($row));
    $sql  = "INSERT INTO `$table` ($cols) VALUES ($ph)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($row);
  }
}

/** Normaliza status recebido do form para ENUM da tabela. */
function norm_service_status(?string $s): string {
  $s = strtolower((string)$s);
  switch ($s) {
    case 'yes':
    case 'included':
      return 'included';
    case 'maybe':
    case 'negotiable':
      return 'negotiable';
    case 'extra':
    case 'extra_fee':
      return 'extra';
    default:
      return 'not_available';
  }
}

try {
  $pdo = db();
  $pdo->beginTransaction();

  /* -------- 1) validar entrada básica -------- */
  $account_id = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
  if (!$account_id) throw new Exception('ID da conta inválido.');

  /* -------- 2) slug amigável (não falha se não houver coluna) -------- */
  $display_name  = $_POST['artistic_name'] ?? '';
  $category_id   = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
  $nationality_id= filter_input(INPUT_POST, 'nationality_id', FILTER_VALIDATE_INT) ?: null;
  $ad_city       = trim($_POST['ad_city'] ?? '');

  $category_name = '';
  if ($category_id) {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category_name = (string)($stmt->fetchColumn() ?: '');
  }
  $nationality_name = '';
  if ($nationality_id) {
    $stmt = $pdo->prepare("SELECT nationality_female FROM countries WHERE id = ?");
    $stmt->execute([$nationality_id]);
    $nationality_name = (string)($stmt->fetchColumn() ?: '');
  }
  $slug_parts = array_filter([$category_name, $nationality_name, 'em', $ad_city, $display_name]);
  $slug = create_slug(implode(' ', $slug_parts));

  /* -------- 3) idade (a partir de accounts.birth_date) -------- */
  $stmt = $pdo->prepare("SELECT birth_date FROM accounts WHERE id = ?");
  $stmt->execute([$account_id]);
  $birth = $stmt->fetchColumn();
  if (!$birth) throw new Exception('Data de nascimento não encontrada na conta.');
  $birth_date = new DateTime($birth);
  $age = (new DateTime())->diff($birth_date)->y;
  if ($age < 18) throw new Exception('Idade menor que 18 anos não permitida.');

  /* -------- 4) provider base: localizar (por account_id) -------- */
  $stmt = $pdo->prepare("SELECT id FROM providers WHERE account_id = ? LIMIT 1");
  $stmt->execute([$account_id]);
  $provider_id = $stmt->fetchColumn() ?: null;

  /* -------- 5) uploads -------- */
  $visitor_id = $_SESSION['visitor_db_id'] ?? null;
  if (!$visitor_id) throw new Exception('Visitor ID ausente na sessão.');

  $upload_base_dir_root = dirname(__DIR__) . '/uploads/providers/';
  $base_dir   = $upload_base_dir_root . $visitor_id . '/';
  $photos_dir = $base_dir . 'photos/';
  $videos_dir = $base_dir . 'videos/';
  foreach ([$photos_dir,$videos_dir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
      throw new Exception('Falha ao criar diretório: '.$dir);
    }
    if (!is_writable($dir)) {
      @chmod($dir, 0755);
      if (!is_writable($dir)) throw new Exception('Diretório não gravável: '.$dir);
    }
  }

  $allowed_image_types = ['image/jpeg','image/png','image/webp'];
  $allowed_video_types = ['video/mp4','video/avi'];
  $max_image_size = 10 * 1024 * 1024;
  $max_video_size = 50 * 1024 * 1024;

  // Listas atuais (se quiseres, dá pra carregar do DB se existir colunas)
  $existing_photos = [];
  $existing_videos = [];

  // remoção de fotos (baseado no que o cliente enviou)
  $removed_gallery_photos_client = $_POST['removed_gallery_photos'] ?? [];
  if (is_array($removed_gallery_photos_client)) {
    foreach ($removed_gallery_photos_client as $rel) {
      $full = dirname(__DIR__) . $rel;
      if (is_string($rel) && strpos($rel, '/uploads/providers/') === 0 && file_exists($full)) {
        @unlink($full);
      }
      $existing_photos = array_values(array_filter($existing_photos, fn($p) => $p !== $rel));
    }
  }

  // remoção de vídeos
  $removed_videos_client = $_POST['removed_videos'] ?? [];
  if (is_array($removed_videos_client)) {
    foreach ($removed_videos_client as $rel) {
      $full = dirname(__DIR__) . $rel;
      if (is_string($rel) && strpos($rel, '/uploads/providers/') === 0 && file_exists($full)) {
        @unlink($full);
      }
      $existing_videos = array_values(array_filter($existing_videos, fn($v) => $v !== $rel));
    }
  }

  // main photo
  $main_photo_url = null;
  if (isset($_FILES['main_photo']) && $_FILES['main_photo']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['main_photo'];
    if (in_array($f['type'], $allowed_image_types) && $f['size'] <= $max_image_size) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $fn  = 'main_' . time() . '.' . $ext;
      $dest= $photos_dir . $fn;
      if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Falha ao mover a imagem principal.');
      $main_photo_url = '/uploads/providers/' . $visitor_id . '/photos/' . $fn;
    } else {
      throw new Exception('Imagem principal inválida ou muito grande.');
    }
  }

  // novas fotos da galeria
  $new_photos = [];
  if (isset($_FILES['gallery_photos']) && is_array($_FILES['gallery_photos']['name'])) {
    foreach ($_FILES['gallery_photos']['name'] as $k => $name) {
      if ($_FILES['gallery_photos']['error'][$k] === UPLOAD_ERR_OK) {
        $file = [
          'name' => $_FILES['gallery_photos']['name'][$k],
          'type' => $_FILES['gallery_photos']['type'][$k],
          'tmp'  => $_FILES['gallery_photos']['tmp_name'][$k],
          'size' => $_FILES['gallery_photos']['size'][$k],
        ];
        if (in_array($file['type'],$allowed_image_types) && $file['size'] <= $max_image_size) {
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          $fn  = 'gallery_'.time().'_'.uniqid().'.'.$ext;
          $dest= $photos_dir.$fn;
          if (move_uploaded_file($file['tmp'], $dest)) {
            $new_photos[$file['name']] = '/uploads/providers/'.$visitor_id.'/photos/'.$fn;
          }
        }
      }
    }
  }

  // ordem final da galeria
  $final_gallery = [];
  $order_client = isset($_POST['gallery_order']) ? json_decode($_POST['gallery_order'], true) : [];
  if (!is_array($order_client)) $order_client = [];
  $known = array_unique(array_merge($existing_photos, array_values($new_photos)));
  foreach ($order_client as $item) {
    $found = null;
    if (is_string($item) && strpos($item, '/uploads/providers/') === 0 && in_array($item, $known)) {
      $found = $item;
    } elseif (is_string($item) && isset($new_photos[$item])) {
      $found = $new_photos[$item];
    } else {
      if (is_string($item)) {
        foreach ($known as $k) if (basename($k) === $item) { $found = $k; break; }
      }
    }
    if ($found && !in_array($found, $final_gallery)) $final_gallery[] = $found;
  }
  foreach ($known as $p) if (!in_array($p,$final_gallery)) $final_gallery[] = $p;
  $gallery_photos_json = $final_gallery ? json_encode($final_gallery) : null;

  // novos vídeos
  $new_videos = [];
  if (isset($_FILES['videos']) && is_array($_FILES['videos']['name'])) {
    foreach ($_FILES['videos']['name'] as $k => $name) {
      if ($_FILES['videos']['error'][$k] === UPLOAD_ERR_OK) {
        $file = [
          'name' => $_FILES['videos']['name'][$k],
          'type' => $_FILES['videos']['type'][$k],
          'tmp'  => $_FILES['videos']['tmp_name'][$k],
          'size' => $_FILES['videos']['size'][$k],
        ];
        if (in_array($file['type'],$allowed_video_types) && $file['size'] <= $max_video_size) {
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          $fn  = 'video_'.time().'_'.uniqid().'.'.$ext;
          $dest= $videos_dir.$fn;
          if (move_uploaded_file($file['tmp'], $dest)) {
            $new_videos[$file['name']] = '/uploads/providers/'.$visitor_id.'/videos/'.$fn;
          }
        }
      }
    }
  }
  $videos_order_client = isset($_POST['videos_order']) ? json_decode($_POST['videos_order'], true) : [];
  if (!is_array($videos_order_client)) $videos_order_client = [];
  $known_videos = array_unique(array_merge($existing_videos, array_values($new_videos)));
  $final_videos = [];
  foreach ($videos_order_client as $item) {
    $found = null;
    if (is_string($item) && strpos($item, '/uploads/providers/') === 0 && in_array($item, $known_videos)) {
      $found = $item;
    } elseif (is_string($item) && isset($new_videos[$item])) {
      $found = $new_videos[$item];
    } else {
      if (is_string($item)) {
        foreach ($known_videos as $k) if (basename($k) === $item) { $found = $k; break; }
      }
    }
    if ($found && !in_array($found, $final_videos)) $final_videos[] = $found;
  }
  foreach ($known_videos as $v) if (!in_array($v,$final_videos)) $final_videos[] = $v;
  $videos_json = $final_videos ? json_encode($final_videos) : null;

  /* -------- 6) montar payloads por tabela -------- */

  // Providers (somente o que existir como coluna)
  // ⚠️ IMPORTANTE: SEMPRE inicia com status='pending'
  // Política do site: TODOS os providers (Free e Premium) precisam ser aprovados manualmente pelo admin
  // NUNCA mudar para 'active' automaticamente, nem mesmo após pagamento confirmado
  $provider_payload = [
    'account_id'       => $account_id,
    'status'           => 'pending',  // ⚠️ OBRIGATÓRIO: sempre pending até admin aprovar
    'display_name'     => $display_name ?: null,
    'slug'             => $slug ?: null,
    'category_id'      => $category_id,
    'ad_title'         => $_POST['ad_title'] ?? null,
    'description'      => $_POST['description'] ?? null,
    'gender'           => $_POST['gender'] ?? null,
    'age'              => $age,
    'provider_type'    => $_POST['provider_type'] ?? null,
    'nationality_id'   => $nationality_id,
    'main_photo_url'   => $main_photo_url,      // só grava se coluna existir
    'gallery_photos'   => $gallery_photos_json, // idem
    'videos'           => $videos_json,         // idem
    'onlyfans_url'     => $_POST['onlyfans_url'] ?? null,
    'instagram_username'=> $_POST['instagram_username'] ?? null,
    'twitter_username' => $_POST['twitter_username'] ?? null,
    'currency'         => $_POST['currency'] ?? null,
    'base_hourly_rate' => filter_input(INPUT_POST,'base_hourly_rate',FILTER_VALIDATE_FLOAT) ?: null,
    'price_15_min'     => filter_input(INPUT_POST,'price_15_min',FILTER_VALIDATE_FLOAT) ?: null,
    'price_30_min'     => filter_input(INPUT_POST,'price_30_min',FILTER_VALIDATE_FLOAT) ?: null,
    'price_2_hr'       => filter_input(INPUT_POST,'price_2_hr',FILTER_VALIDATE_FLOAT) ?: null,
    'price_overnight'  => filter_input(INPUT_POST,'price_overnight',FILTER_VALIDATE_FLOAT) ?: null,
    'advertised_phone_code'   => $_POST['advertised_phone_code'] ?? null,
    'advertised_phone_number' => $_POST['advertised_phone_number'] ?? null,
    'show_on_ad_sms'       => isset($_POST['show_on_ad_sms']) ? 1 : 0,
    'show_on_ad_call'      => isset($_POST['show_on_ad_call']) ? 1 : 0,
    'show_on_ad_whatsapp'  => isset($_POST['show_on_ad_whatsapp']) ? 1 : 0,
    'show_on_ad_telegram'  => isset($_POST['show_on_ad_telegram']) ? 1 : 0,
    'updated_at'       => date('Y-m-d H:i:s'),
  ];
  // Filtra só colunas que realmente existem em `providers`
  $provider_payload = filter_for_table($pdo, 'providers', $provider_payload);

  if ($provider_id) {
    // UPDATE providers
    if ($provider_payload) {
      $provider_payload['id'] = $provider_id;
      $set = [];
      foreach ($provider_payload as $k => $v) if ($k !== 'id') $set[] = "`$k`=:$k";
      if ($set) {
        $sql = "UPDATE providers SET ".implode(',', $set)." WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($provider_payload);
      }
    }
  } else {
    // INSERT providers (garante pelo menos account_id/status)
    // ⚠️ POLÍTICA: Novo provider SEMPRE começa com status='pending' e is_active=0
    // Somente o admin pode aprovar e mudar para 'active'
    $base = ['account_id'=>$account_id, 'status'=>'pending', 'created_at'=>date('Y-m-d H:i:s')];
    $base = filter_for_table($pdo, 'providers', $base) + $provider_payload;
    $cols = '`'.implode('`,`', array_keys($base)).'`';
    $ph   = ':'.implode(',:', array_keys($base));
    $stmt = $pdo->prepare("INSERT INTO providers ($cols) VALUES ($ph)");
    $stmt->execute($base);
    $provider_id = (int)$pdo->lastInsertId();
  }

  /* providers_body */
  $body_src = [
    'height_cm'  => filter_input(INPUT_POST, 'height', FILTER_VALIDATE_INT) ?: null,
    'weight_kg'  => filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT) ?: null,
    'hair_color' => $_POST['hair_color'] ?? null,
    'eye_color'  => $_POST['eye_color'] ?? null,
    'body_type'  => $_POST['body_type'] ?? null,
    'bust_cm'    => $_POST['bust_size'] ?? null,
    'tattoos'    => isset($_POST['has_tattoos']) ? 1 : 0,
    'piercings'  => isset($_POST['has_piercings']) ? 1 : 0,
    'foot_size'  => $_POST['foot_size'] ?? null, // só entra se a coluna existir
    'notes'      => null,
  ];
  $body = filter_for_table($pdo, 'providers_body', $body_src);
  if ($body) {
    $body['provider_id'] = $provider_id;
    upsert_one_to_one($pdo, 'providers_body', $body);
  }

  /* providers_contact */
  $contact_map = [
    'advertised_phone_code'   => 'phone_code',
    'advertised_phone_number' => 'phone_number',
    'instagram_username'      => 'instagram',
    'twitter_username'        => 'twitter',
    'show_on_ad_whatsapp'     => 'accepts_whatsapp',
    'show_on_ad_sms'          => 'accepts_sms',
    'show_on_ad_call'         => 'accepts_calls',
  ];
  $contact_src = [
    'advertised_phone_code'   => $_POST['advertised_phone_code'] ?? null,
    'advertised_phone_number' => $_POST['advertised_phone_number'] ?? null,
    'instagram_username'      => $_POST['instagram_username'] ?? null,
    'twitter_username'        => $_POST['twitter_username'] ?? null,
    'show_on_ad_whatsapp'     => isset($_POST['show_on_ad_whatsapp']) ? 1 : 0,
    'show_on_ad_sms'          => isset($_POST['show_on_ad_sms']) ? 1 : 0,
    'show_on_ad_call'         => isset($_POST['show_on_ad_call']) ? 1 : 0,
  ];
  $contact = filter_for_table($pdo, 'providers_contact', $contact_src, $contact_map);
  if ($contact) {
    $contact['provider_id'] = $provider_id;
    upsert_one_to_one($pdo, 'providers_contact', $contact);
  }

  /* providers_logistics (plural) */
  $log_src = [
    'ad_country'            => $_POST['ad_country'] ?? null,
    'ad_city'               => $_POST['ad_city'] ?? null,
    'ad_latitude'           => filter_input(INPUT_POST, 'ad_latitude', FILTER_VALIDATE_FLOAT) ?: null,
    'ad_longitude'          => filter_input(INPUT_POST, 'ad_longitude', FILTER_VALIDATE_FLOAT) ?: null,
    'in_call'               => isset($_POST['in_call']) ? 1 : 0,
    'out_call'              => isset($_POST['out_call']) ? 1 : 0,
    'serves_nearby_cities'  => isset($_POST['serves_nearby_cities']) ? (int)$_POST['serves_nearby_cities'] : 0,
    'nearby_cities_radius'  => filter_input(INPUT_POST, 'nearby_cities_radius', FILTER_VALIDATE_INT) ?: null,
    'service_locations'     => isset($_POST['service_locations']) ? json_encode($_POST['service_locations']) : null,
    'ad_city_autocomplete'  => $_POST['ad_city_autocomplete'] ?? null,
  ];
  $logistics = filter_for_table($pdo, 'providers_logistics', $log_src);
  if ($logistics) {
    $logistics['provider_id'] = $provider_id;
    upsert_one_to_one($pdo, 'providers_logistics', $logistics);
  }

  /* providers_service_offerings */
  $pdo->prepare("DELETE FROM providers_service_offerings WHERE provider_id = ?")->execute([$provider_id]);
  if (!empty($_POST['services']) && is_array($_POST['services'])) {
    $rows = [];
    foreach ($_POST['services'] as $service_key => $details) {
      $status = norm_service_status($details['status'] ?? null);
      if ($status === 'not_available') continue;
      $price  = null;
      if ($status === 'extra' && isset($details['price'])) {
        $price = filter_var($details['price'], FILTER_VALIDATE_FLOAT);
      }
      $rows[] = [$provider_id, $service_key, $status, $price, $details['notes'] ?? null];
    }
    if ($rows) {
      $sql = "INSERT INTO providers_service_offerings
              (provider_id, service_key, status, price, notes)
              VALUES ".implode(',', array_fill(0, count($rows), "(?,?,?,?,?)"));
      $vals = [];
      foreach ($rows as $r) { $vals = array_merge($vals, $r); }
      $stmt = $pdo->prepare($sql);
      $stmt->execute($vals);
    }
  }

  $pdo->commit();

  echo json_encode([
    'status'  => 'success',
    'message' => 'Perfil de prestador guardado com sucesso!',
    'data'    => ['provider_id' => $provider_id, 'account_id' => $account_id, 'new_slug' => $slug]
  ]);
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  log_system_error(
    'API_REGISTER_PROVIDERS_ERROR: '.$e->getMessage().
    ' | File: '.$e->getFile().' | Line: '.$e->getLine(),
    'CRITICAL','api_register_providers_fatal'
  );
  http_response_code(500);
  $msg = 'Ocorreu um erro ao guardar o seu perfil.';
  if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') $msg .= ' (Debug: '.$e->getMessage().')';
  echo json_encode(['status'=>'error','message'=>$msg]);
}
