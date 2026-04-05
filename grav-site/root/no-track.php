<?php
// Pagina segreta — esclude questo browser dal tracking GA4
// Visita una volta, poi sei escluso per sempre
?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>No Track</title>
<style>
  body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
  .box { background: white; padding: 2rem 3rem; border-radius: 12px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
  h2 { color: #1E3A5F; margin-bottom: 0.5rem; }
  p { color: #666; margin: 0.5rem 0; }
  .ok { font-size: 3rem; }
  a { color: #B68397; }
</style>
</head>
<body>
<div class="box">
  <div class="ok" id="icon">⏳</div>
  <h2 id="title">Impostazione in corso...</h2>
  <p id="msg"></p>
  <p style="margin-top:1.5rem"><a href="/">← Torna al sito</a></p>
</div>
<script>
try {
  localStorage.setItem('vr_no_track', '1');
  document.getElementById('icon').textContent = '✅';
  document.getElementById('title').textContent = 'Tracking disattivato';
  document.getElementById('msg').textContent = 'Questo browser non verrà più conteggiato in GA4.';
} catch(e) {
  document.getElementById('icon').textContent = '❌';
  document.getElementById('title').textContent = 'Errore';
  document.getElementById('msg').textContent = 'Impossibile salvare il flag: ' + e.message;
}
</script>
</body>
</html>
