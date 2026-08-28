<?php
/**
 * Script one-time gia' eseguito e disattivato.
 *
 * Questo file resta deployato di proposito, per SOVRASCRIVERE sul server la
 * versione attiva dello script: il deploy FTP non cancella i file, quindi
 * l'unico modo di neutralizzarne uno e' rimpiazzarne il contenuto.
 * Stesso pattern gia' usato per hd-setup.php.
 *
 * Se serve rieseguire una di queste procedure, si scrive uno script nuovo
 * con un token nuovo: mai riattivare questo.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Script non piu disponibile'], JSON_UNESCAPED_UNICODE);
