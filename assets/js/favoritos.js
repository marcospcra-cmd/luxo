/**
 * favoritos.js
 * Liga os botões de coração (.fav-btn) ao endpoint favorito_toggle.php.
 * Atualiza visual e contador no header sem recarregar a página.
 * Na página /favoritos.php, ao desfavoritar, remove o card da grade.
 */
(function () {
  const isFavoritosPage = /favoritos\.php(\?|$)/.test(window.location.pathname + window.location.search);

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.fav-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    if (window.PRECISA_LOGIN) {
      window.location.href = 'cliente_login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
      return;
    }

    const id = btn.dataset.id;
    if (!id) return;

    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('produto_id', id);
      fd.append('csrf', window.CSRF_CLIENTE || '');
      const r = await fetch('favorito_toggle.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await r.json();

      if (!data.ok) {
        if (data.precisa_login) {
          window.location.href = 'cliente_login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }
        alert(data.msg || 'Erro ao favoritar.');
        return;
      }

      btn.classList.toggle('is-on', !!data.favorito);
      btn.title = data.favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
      btn.setAttribute('aria-label', btn.title);

      // Atualiza contador no header
      const badge = document.getElementById('favCountBadge');
      if (badge) {
        if (data.total > 0) { badge.textContent = data.total; badge.style.display = ''; }
        else                { badge.style.display = 'none'; }
      }

      // Na própria página de favoritos, removemos o card desfavoritado
      if (isFavoritosPage && !data.favorito) {
        const card = btn.closest('.produto-item');
        if (card) {
          card.style.transition = 'opacity .2s';
          card.style.opacity = '0';
          setTimeout(() => {
            card.remove();
            const restantes = document.querySelectorAll('.produto-item').length;
            const cont = document.getElementById('contadorProdutos');
            if (cont) cont.textContent = restantes + ' peça(s)';
            if (restantes === 0) {
              const grade = document.getElementById('gradeProdutos');
              if (grade) grade.outerHTML = '<div class="text-center py-5 text-muted">Nenhum favorito restante. <a href="index.php" style="color:var(--accent);">Explorar coleção →</a></div>';
            }
          }, 200);
        }
      }
    } catch (err) {
      alert('Falha de rede ao favoritar.');
    } finally {
      btn.disabled = false;
    }
  });
})();
