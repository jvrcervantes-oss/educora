<?php
// Helpers de lectura/escritura con bloqueo de fichero (flock) para evitar
// que dos envíos simultáneos corrompan el JSON o se pisen entre sí.
// Nunca se concatenan strings de usuario a mano: siempre json_encode/json_decode reales.

declare(strict_types=1);

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Añade un registro al final del array JSON del fichero, con lock exclusivo. */
function append_record(string $file, array $record): bool {
    $fp = fopen($file, 'c+');
    if ($fp === false) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }

    $size = filesize($file);
    $content = $size > 0 ? fread($fp, $size) : '';
    $data = $content !== '' ? json_decode($content, true) : [];
    if (!is_array($data)) $data = [];

    $data[] = $record;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/** Aplica $mutator(&$record) al registro con ese id. Devuelve el registro actualizado o null. */
function update_record_by_id(string $file, string $id, callable $mutator): ?array {
    $fp = fopen($file, 'c+');
    if ($fp === false) return null;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }

    $size = filesize($file);
    $content = $size > 0 ? fread($fp, $size) : '';
    $data = $content !== '' ? json_decode($content, true) : [];
    if (!is_array($data)) $data = [];

    $found = null;
    foreach ($data as &$rec) {
        if (($rec['id'] ?? null) === $id) {
            $mutator($rec);
            $found = $rec;
            break;
        }
    }
    unset($rec);

    if ($found !== null) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $found;
}

function read_records(string $file): array {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    if ($fp === false) return [];
    flock($fp, LOCK_SH);
    $content = fread($fp, max(filesize($file), 1));
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = $content !== '' ? json_decode($content, true) : [];
    return is_array($data) ? $data : [];
}

function clean_str($v, int $max = 500): string {
    $v = is_string($v) ? $v : '';
    $v = trim($v);
    $v = str_replace(["\r"], '', $v);
    if (function_exists('mb_substr')) $v = mb_substr($v, 0, $max);
    else $v = substr($v, 0, $max);
    return $v;
}
