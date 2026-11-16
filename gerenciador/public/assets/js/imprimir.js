// Versão para impressão: abre a janela de impressão automaticamente.
document.getElementById('imprimir')?.addEventListener('click', () => window.print());
window.addEventListener('load', () => setTimeout(() => window.print(), 300));
