<?php
declare(strict_types=1);

function backup_command(string $source,string $destination): array {
    global $root,$temp;
    $process=proc_open([PHP_BINARY,'upload-ready/maintenance/backup.php','--source='.$source,'--destination='.$destination],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null,['bypass_shell'=>true]);
    check(is_resource($process)); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return ['code'=>proc_close($process),'out'=>$out,'err'=>$err];
}
test('CLI backup snapshots committed WAL data and validates integrity',function () use ($temp): void {
    $source=new SQLite3($temp.'/wal.sqlite'); $source->exec('PRAGMA journal_mode=WAL');
    $source->exec('CREATE TABLE fixture(id INTEGER PRIMARY KEY, value TEXT)');
    $source->exec("INSERT INTO fixture VALUES(1,'committed fixture')");
    $source->exec('BEGIN IMMEDIATE'); $source->exec("INSERT INTO fixture VALUES(2,'not committed')");
    try {
        $result=backup_command($temp.'/wal.sqlite',$temp.'/backup.sqlite'); same(0,$result['code']);
        check(str_contains($result['out'],'integrity_check=ok'));
        $backup=new SQLite3($temp.'/backup.sqlite',SQLITE3_OPEN_READONLY);
        same(1,$backup->querySingle('SELECT COUNT(*) FROM fixture'));
        same('committed fixture',$backup->querySingle('SELECT value FROM fixture'));
        same('ok',$backup->querySingle('PRAGMA integrity_check')); $backup->close();
    } finally { $source->exec('ROLLBACK'); $source->close(); }
});
test('CLI backup refuses overwrite, missing source, public destination and corrupt data',function () use ($temp,$root): void {
    $hash=hash_file('sha256',$temp.'/backup.sqlite');
    same(1,backup_command($temp.'/wal.sqlite',$temp.'/backup.sqlite')['code']); same($hash,hash_file('sha256',$temp.'/backup.sqlite'));
    same(1,backup_command($temp.'/missing.sqlite',$temp.'/new.sqlite')['code']); check(!file_exists($temp.'/new.sqlite'));
    same(1,backup_command($temp.'/wal.sqlite',$root.'/upload-ready/forbidden-fixture.sqlite')['code']); check(!file_exists($root.'/upload-ready/forbidden-fixture.sqlite'));
    $handle=fopen($temp.'/corrupt.sqlite','x'); fwrite($handle,'invalid fixture database'); fclose($handle);
    same(1,backup_command($temp.'/corrupt.sqlite',$temp.'/invalid-backup.sqlite')['code']); check(!file_exists($temp.'/invalid-backup.sqlite'));
});
