<?php
declare(strict_types=1);

function profiles_migrate(PDO $pdo): void
{
    foreach (['carers' => ['birth_date'=>'TEXT NULL','hourly_rate_weekday'=>'REAL NULL','hourly_rate_special'=>'REAL NULL'],
        'patients' => ['municipality'=>'TEXT NULL','client_special_rate'=>'REAL NULL'],
        'services' => ['hourly_rate_special'=>'REAL NULL','client_rate_snapshot'=>'REAL NULL','client_special_snapshot'=>'REAL NULL','series_key'=>'TEXT NULL']] as $table=>$columns) {
        foreach ($columns as $column=>$definition) ensure_column($pdo,$table,$column,$definition);
    }
    // Separate table avoids loading image bytes in existing SELECT * profile lists.
    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_photos (kind TEXT NOT NULL CHECK(kind IN ('patient','carer')), entity_id INTEGER NOT NULL, mime TEXT NOT NULL, content BLOB NOT NULL, PRIMARY KEY(kind,entity_id));
        CREATE INDEX IF NOT EXISTS idx_services_series ON services(series_key);
        CREATE TRIGGER IF NOT EXISTS remove_patient_photo AFTER DELETE ON patients BEGIN DELETE FROM profile_photos WHERE kind='patient' AND entity_id=OLD.id; END;
        CREATE TRIGGER IF NOT EXISTS remove_carer_photo AFTER DELETE ON carers BEGIN DELETE FROM profile_photos WHERE kind='carer' AND entity_id=OLD.id; END;");
    $version=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?'); $version->execute(['v3-client-planning']);
    $applied=(bool)$version->fetchColumn(); $version->closeCursor();
    if(!$applied)$pdo->prepare('INSERT OR IGNORE INTO schema_migrations(version,applied_at) VALUES(?,?)')->execute(['v3-client-planning',now()]);
}

function profile_photo_upload(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Não foi possível receber a fotografia.');
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 2*1024*1024) throw new InvalidArgumentException('A fotografia deve ter até 2 MB.');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true) || $info[0] > 6000 || $info[1] > 6000) throw new InvalidArgumentException('Escolha uma fotografia JPEG, PNG ou WebP até 6000 × 6000 píxeis.');
    return ['mime'=>$info['mime'],'content'=>file_get_contents($file['tmp_name'])];
}

function profile_photo_save(PDO $pdo,string $kind,int $id,?array $photo): void
{
    if (!$photo) return;
    $stmt=$pdo->prepare('INSERT INTO profile_photos(kind,entity_id,mime,content) VALUES(?,?,?,?) ON CONFLICT(kind,entity_id) DO UPDATE SET mime=excluded.mime,content=excluded.content');
    $stmt->bindValue(1,$kind); $stmt->bindValue(2,$id,PDO::PARAM_INT); $stmt->bindValue(3,$photo['mime']); $stmt->bindValue(4,$photo['content'],PDO::PARAM_LOB); $stmt->execute();
}

function profile_photo_endpoint(): never
{
    $user=require_auth(); $kind=$_GET['kind']??''; $id=(int)($_GET['id']??0);
    if (!in_array($kind,['patient','carer'],true) || $id<=0) { http_response_code(404); exit; }
    if ($user['role']==='CARER' && ($kind!=='carer' || $id!==(int)$user['carer_id'])) { http_response_code(403); exit; }
    $stmt=db()->prepare('SELECT mime,content FROM profile_photos WHERE kind=? AND entity_id=?'); $stmt->execute([$kind,$id]); $photo=$stmt->fetch();
    if (!$photo) { http_response_code(404); exit; }
    header('Content-Type: '.$photo['mime']); header('Content-Disposition: inline'); header('X-Content-Type-Options: nosniff');
    echo $photo['content']; exit;
}

function profile_photo_field(string $kind,?int $id=null): void
{
    if (!$id) echo '<img class="profile-photo" data-profile-photo="'.$kind.'" alt="Fotografia atual do perfil" width="72" height="72" hidden>';
    if ($id) {
        $stmt=db()->prepare('SELECT 1 FROM profile_photos WHERE kind=? AND entity_id=?'); $stmt->execute([$kind,$id]);
        if ($stmt->fetchColumn()) echo '<img class="profile-photo" src="index.php?page=photo&amp;kind='.e($kind).'&amp;id='.$id.'" alt="Fotografia do perfil" width="72" height="72">';
    }
    echo '<label class="field"><span class="label">Fotografia (opcional)</span><input class="input" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><span class="subtitle">JPEG, PNG ou WebP, até 2 MB.</span></label>';
}

function has_profile_photo(string $kind,int $id): bool
{
    static $photos=null;
    if ($photos===null) { $photos=[]; foreach (db()->query('SELECT kind,entity_id FROM profile_photos') as $row) $photos[$row['kind'].':'.$row['entity_id']]=true; }
    return isset($photos[$kind.':'.$id]);
}

function profile_photo_thumb(string $kind,int $id): void
{
    if (has_profile_photo($kind,$id)) echo '<img class="profile-photo" src="index.php?page=photo&amp;kind='.$kind.'&amp;id='.$id.'" alt="Fotografia do perfil" width="40" height="40" loading="lazy"> ';
}

function profile_birthdays(PDO $pdo,?string $today=null): array
{
    $today??=date('Y-m-d');
    $stmt=$pdo->prepare("SELECT id,name,'Cuidadora' AS kind FROM carers WHERE substr(birth_date,6,5)=? UNION ALL SELECT id,name,'Cliente' AS kind FROM patients WHERE substr(birth_date,6,5)=? ORDER BY name");
    $stmt->execute([substr($today,5),substr($today,5)]); return $stmt->fetchAll();
}

function profile_extra_values(string $action,array $data): array
{
    $values=[];
    if (in_array($action,['save_carer','save_profile'],true)) {
        $birth=$data['birth_date']??'';
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);
        if (!$date || $date->format('Y-m-d')!==$birth || $birth>date('Y-m-d') || $birth<'1900-01-01') throw new InvalidArgumentException('Introduza a data de nascimento da cuidadora.');
        $values['birth_date']=$birth; $values['age']=$date->diff(new DateTimeImmutable('today'))->y;
    }
    $fields=$action==='save_carer'?['hourly_rate_weekday','hourly_rate_special']:($action==='save_patient'?['client_special_rate']:[]);
    foreach ($fields as $field) {
        $value=$data[$field]??'';
        if ($value!=='' && (!is_numeric($value) || !is_finite((float)$value) || (float)$value<0 || (float)$value>10000)) throw new InvalidArgumentException('Introduza uma tarifa horária válida.');
        $values[$field]=$value===''?null:(float)$value;
    }
    if ($action==='save_patient') {
        $municipality=$data['municipality']??'';
        if ($municipality!=='' && !in_array($municipality,scheduling_municipalities(),true)) throw new InvalidArgumentException('Selecione um concelho válido.');
        $values['municipality']=$municipality;
    }
    return $values;
}

function profile_extra_save(PDO $pdo,string $table,int $id,array $values): void
{
    if (!$values) return;
    // Table and columns only originate in profile_extra_values / internal callers.
    $sql='UPDATE '.$table.' SET '.implode(', ',array_map(fn($column)=>$column.'=?',array_keys($values))).' WHERE id=?';
    $pdo->prepare($sql)->execute([...array_values($values),$id]);
}
