(function () {
  'use strict';

  var BASE = document.currentScript && document.currentScript.src
    ? document.currentScript.src.replace(/\/js\/boot\.js.*$/, '')
    : '/animacion-cumpleanos';

  function showError(message) {
    var root = document.getElementById('root');
    if (!root) {
      return;
    }
    root.innerHTML =
      '<div style="color:#fff;font-family:Verdana,sans-serif;padding:2rem;max-width:36rem;line-height:1.5">' +
      '<strong style="display:block;margin-bottom:0.5rem">No se pudo cargar la animación</strong>' +
      message +
      '</div>';
  }

  function transformAndRun(url) {
    return fetch(url, { cache: 'no-cache' }).then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status + ' al cargar ' + url);
      }
      return response.text();
    }).then(function (source) {
      if (!window.Babel || typeof window.Babel.transform !== 'function') {
        throw new Error('Babel no está disponible');
      }
      var compiled = window.Babel.transform(source, {
        presets: ['react'],
        filename: url,
      }).code;
      // eslint-disable-next-line no-eval
      eval(compiled);
    });
  }

  function mount() {
    if (!window.React || !window.ReactDOM || !window.BirthdayAnimation) {
      return false;
    }
    var rootEl = document.getElementById('root');
    if (!rootEl) {
      return false;
    }
    window.ReactDOM.createRoot(rootEl).render(
      window.React.createElement(window.BirthdayAnimation)
    );
    return true;
  }

  function waitMount(attempt) {
    if (mount()) {
      return;
    }
    if (attempt > 200) {
      showError('El componente de animación no se registró a tiempo. Recarga la página.');
      return;
    }
    setTimeout(function () {
      waitMount(attempt + 1);
    }, 50);
  }

  function boot() {
    if (!window.React || !window.ReactDOM) {
      showError('React no se cargó. Comprueba tu conexión o recarga.');
      return;
    }
    if (!window.Babel) {
      showError('Babel no se cargó. Comprueba tu conexión o recarga.');
      return;
    }

    transformAndRun(BASE + '/js/animations-v3.js')
      .then(function () {
        return transformAndRun(BASE + '/js/tweaks-panel.js');
      })
      .then(function () {
        return transformAndRun(BASE + '/js/cumple-anim.js');
      })
      .then(function () {
        waitMount(0);
      })
      .catch(function (error) {
        console.error('[animacion-cumpleanos]', error);
        showError((error && error.message) ? error.message : 'Error desconocido.');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
