/**
 * reCAPTCHA para o Joomla 6: checkbox v2, invisivel v2 e v3.
 * Define o callback global (onload) usado pela api.js do Google. Nao depende de jQuery.
 */
((window, document) => {
  const opcoes = () => window.Joomla?.getOptions?.('plg_captcha_recaptcha') || {};

  // Reenvia o formulario depois do token (preserva a validacao do Joomla e o botao clicado).
  const reenviar = (form, botao) => {
    form.dataset.recaptchaOk = '1';
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(botao || undefined);
    } else {
      form.submit();
    }
  };

  const ligarInvisivel = (el, widgetId) => {
    const form = el.closest('form');
    if (!form || form.dataset.recaptchaBound) return;
    form.dataset.recaptchaBound = '1';
    form.addEventListener('submit', (ev) => {
      if (form.dataset.recaptchaOk === '1') {
        form.dataset.recaptchaOk = '';
        return; // segunda passagem (ja com token): segue o envio normal
      }
      ev.preventDefault();
      ev.stopImmediatePropagation();
      form.__recaptchaSubmitter = ev.submitter || null;
      // reset so se ja houver token de uma tentativa anterior (o token expira em ~2 min e e de uso unico)
      const tokenAnterior = window.grecaptcha.getResponse(widgetId);
      if (tokenAnterior) window.grecaptcha.reset(widgetId);
      window.grecaptcha.execute(widgetId);
    }, true);
  };

  const iniciarV2 = () => {
    document.querySelectorAll('.g-recaptcha:not([data-recaptcha-widget-id])').forEach((el) => {
      const invisivel = el.dataset.size === 'invisible';
      const cfg = {
        sitekey: el.dataset.sitekey,
        theme: el.dataset.theme,
        size: el.dataset.size,
        tabindex: el.dataset.tabindex,
        badge: el.dataset.badge,
      };
      if (invisivel) {
        const form = el.closest('form');
        cfg.callback = () => reenviar(form, form ? form.__recaptchaSubmitter : null);
      }
      const id = window.grecaptcha.render(el, cfg);
      el.dataset.recaptchaWidgetId = String(id);
      if (invisivel) ligarInvisivel(el, id);
    });
  };

  const iniciarV3 = () => {
    const { sitekey, action } = opcoes();
    document.querySelectorAll('input.g-recaptcha-v3').forEach((campo) => {
      const form = campo.closest('form');
      if (!form || form.dataset.recaptchaBound) return;
      form.dataset.recaptchaBound = '1';
      form.addEventListener('submit', (ev) => {
        if (form.dataset.recaptchaOk === '1') {
          form.dataset.recaptchaOk = '';
          return;
        }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        const botao = ev.submitter || null;
        window.grecaptcha.ready(() => {
          window.grecaptcha.execute(sitekey, { action: action || 'joomla_form' }).then((token) => {
            campo.value = token;
            reenviar(form, botao);
          });
        });
      }, true);
    });
  };

  window.turismoRecaptchaInit = () => {
    if (!window.grecaptcha) return;
    if (opcoes().mode === 'v3') {
      iniciarV3();
    } else {
      iniciarV2();
    }
  };
})(window, document);
