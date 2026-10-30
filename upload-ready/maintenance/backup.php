<?php
declare(strict_types=1);

// Deliberately independent of bootstrap: no migrations, seed, sessions or web route.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
$source=null; $destination=null; $created=false; $target=null;
try {
    if (!class_exists('SQLite3') || !method_exists('SQLite3','backup')) throw new RuntimeException('PHP sqlite3 with backup API is required.');
    $options=getopt('', ['source:','destination:']);
    $sourcePath=$options['source']??getenv('CARE4ALL_DB_FILE');
    $destinationPath=$options['destination']??null;
    if (!is_string($sourcePath) || !is_string($destinationPath) || !$sourcePath || !$destinationPath) {
        throw new RuntimeException('Usage: php backup.php --source=ABSOLUTE_SQLITE --destination=NEW_PRIVATE_ABSOLUTE_FILE');
    }
    $absolute=fn(string $path):bool=>(bool)preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~',$path);
    if (!$absolute($sourcePath) || !$absolute($destinationPath)) throw new RuntimeException('Use absolute paths.');
    $resolvedSource=realpath($sourcePath);
    $parent=realpath(dirname($destinationPath));
    if (!$resolvedSource || !is_file($resolvedSource) || !$parent || !is_dir($parent)) throw new RuntimeException('Source file and private destination directory must exist.');
    $target=$parent . DIRECTORY_SEPARATOR . basename($destinationPath);
    $normalize=fn(string $path):string=>strtolower(str_replace('\\','/',$path));
    $publicRoot=$normalize((string)realpath(dirname(__DIR__)));
    $normalizedTarget=$normalize($target);
    if ($normalizedTarget===$publicRoot || str_starts_with($normalizedTarget,$publicRoot.'/')) throw new RuntimeException('Destination must be outside the application web directory.');
    if ($normalize($resolvedSource)===$normalizedTarget || file_exists($target) || is_link($target)) throw new RuntimeException('Destination must be new and distinct from source.');
    // Exclusive creation prevents an existing backup from being overwritten.
    $handle=@fopen($target,'x');
    if (!$handle) throw new RuntimeException('Cannot reserve destination file.');
    $created=true; fclose($handle); chmod($target,0600);
    $source=new SQLite3($resolvedSource,SQLITE3_OPEN_READONLY);
    $source->enableExceptions(true); $source->busyTimeout(10000);
    $destination=new SQLite3($target,SQLITE3_OPEN_READWRITE);
    $destination->enableExceptions(true); $destination->busyTimeout(10000);
    if (!$source->backup($destination)) throw new RuntimeException('Backup API failed.');
    $integrity=$destination->query('PRAGMA integrity_check');
    $results=[]; while ($row=$integrity->fetchArray(SQLITE3_NUM)) $results[]=$row[0];
    $integrity->finalize();
    if ($results!==['ok']) throw new RuntimeException('Backup integrity validation failed.');
    $destination->close(); $destination=null;
    $source->close(); $source=null;
    echo 'Backup validated (integrity_check=ok). SHA256=' . hash_file('sha256',$target) . PHP_EOL;
    exit(0);
} catch (Throwable $error) {
    if ($destination instanceof SQLite3) $destination->close();
    if ($source instanceof SQLite3) $source->close();
    if ($created && is_string($target)) {
        foreach (['','-wal','-shm','-journal'] as $suffix) if (is_file($target.$suffix)) @unlink($target.$suffix);
    }
    // SQLite exception text may contain paths or database contents.
    $message=$error instanceof SQLite3Exception ? 'SQLite operation failed; no validated backup produced.' : $error->getMessage();
    fwrite(STDERR,'Backup failed: '.$message.PHP_EOL);
    exit(1);
}
