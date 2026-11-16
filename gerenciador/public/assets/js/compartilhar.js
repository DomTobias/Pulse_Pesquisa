// QR Code gerado localmente (biblioteca qrcode-generator, MIT).
(function () {
  const alvo = document.getElementById('qr');
  if (!alvo || typeof qrcode !== 'function') return;
  const qr = qrcode(0, 'M');
  qr.addData(alvo.dataset.texto || '');
  qr.make();
  const n = qr.getModuleCount();
  const escala = 6, margem = 4;
  const tam = (n + margem * 2) * escala;
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = tam;
  canvas.style.width = canvas.style.height = '220px';
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, tam, tam);
  ctx.fillStyle = '#032B4C';
  for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
    if (qr.isDark(r, c)) ctx.fillRect((c + margem) * escala, (r + margem) * escala, escala, escala);
  }
  alvo.appendChild(canvas);
  const baixar = document.getElementById('baixar-qr');
  if (baixar) baixar.href = canvas.toDataURL('image/png');
})();
