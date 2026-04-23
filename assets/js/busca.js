/**
 * busca.js — filtro instantâneo por nome (sem reload).
 * Usado tanto em index.php quanto em favoritos.php.
 */
(function () {
  const input    = document.getElementById('buscaNome');
  const itens    = document.querySelectorAll('.produto-item');
  const contador = document.getElementById('contadorProdutos');
  const vazio    = document.getElementById('vazioBusca');
  if (!input || !itens.length) return;

  function aplicar() {
    const termo = input.value.trim().toLowerCase();
    let visiveis = 0;
    itens.forEach(it => {
      if (it.style.opacity === '0') return; // ignora removidos
      const nome  = it.dataset.nome || '';
      const match = !termo || nome.includes(termo);
      it.style.display = match ? '' : 'none';
      if (match) visiveis++;
    });
    if (contador) contador.textContent = visiveis + ' peça(s)';
    if (vazio)    vazio.style.display = visiveis === 0 ? '' : 'none';

    const url = new URL(window.location.href);
    if (termo) url.searchParams.set('q', termo);
    else       url.searchParams.delete('q');
    history.replaceState(null, '', url);
  }

  let t;
  input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(aplicar, 120); });
})();
