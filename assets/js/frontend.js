(function () {
  'use strict';

  function values(form, key) {
    var elements = form.querySelectorAll('[name="mrnf[' + CSS.escape(key) + ']"], [name="mrnf[' + CSS.escape(key) + '][]"]');
    if (!elements.length) {
      return '';
    }
    if (elements[0].type === 'checkbox' || elements[0].type === 'radio') {
      return Array.prototype.filter.call(elements, function (element) { return element.checked; })
        .map(function (element) { return element.value; }).join(',');
    }
    return elements[0].value || '';
  }

  function conditionMatches(condition, actual) {
    if (!condition || !condition.enabled || !condition.field) {
      return true;
    }
    var target = String(condition.value || '');
    actual = String(actual || '');
    if (condition.operator === 'not_equals') { return actual !== target; }
    if (condition.operator === 'contains') { return actual.indexOf(target) !== -1; }
    if (condition.operator === 'not_empty') { return actual.trim() !== ''; }
    if (condition.operator === 'empty') { return actual.trim() === ''; }
    return actual === target;
  }

  function updateConditions(form) {
    form.querySelectorAll('[data-condition]').forEach(function (field) {
      var condition;
      try { condition = JSON.parse(field.dataset.condition); } catch (error) { condition = {}; }
      var visible = conditionMatches(condition, values(form, condition.field));
      field.hidden = !visible;
      field.querySelectorAll('input, select, textarea').forEach(function (control) {
        if (control.dataset.mrnfOriginallyRequired == null) {
          control.dataset.mrnfOriginallyRequired = control.required ? '1' : '0';
        }
        control.required = visible && control.dataset.mrnfOriginallyRequired === '1';
        control.disabled = !visible;
      });
    });
  }

  function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (field) { field.classList.remove('is-invalid'); });
    form.querySelectorAll('.mrnf-field__error').forEach(function (error) { error.textContent = ''; });
  }

  function showErrors(form, errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (key) {
      var field = form.querySelector('[data-mrnf-field="' + CSS.escape(key) + '"]');
      if (!field) { return; }
      field.classList.add('is-invalid');
      var message = field.querySelector('.mrnf-field__error');
      if (message) { message.textContent = errors[key]; }
      var control = field.querySelector('input, select, textarea');
      if (control) {
        control.setAttribute('aria-invalid', 'true');
        control.setAttribute('aria-describedby', message ? message.id : '');
      }
      first = first || control;
    });
    if (first) { first.focus(); }
  }

  function showNotice(form, message, type) {
    var notice = form.querySelector('.mrnf-form__notice');
    notice.textContent = message || '';
    notice.className = 'mrnf-form__notice is-' + type;
    notice.hidden = false;
    notice.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
  }

  function refreshNonce(form) {
    var url = mrnfFrontend.restUrl + form.dataset.mrnfForm + '/nonce?_=' + Date.now();
    return window.fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data.nonce) {
          throw new Error(data.message || mrnfFrontend.network);
        }
        var input = form.querySelector('[name="_mrnf_nonce"]');
        if (!input) {
          throw new Error(mrnfFrontend.network);
        }
        input.value = data.nonce;
      });
    });
  }

  function responseData(response) {
    return response.json().then(function (data) {
      if (!response.ok) {
        var error = new Error(data.message || mrnfFrontend.network);
        error.fields = data.data && data.data.fields ? data.data.fields : {};
        throw error;
      }
      return data;
    });
  }

  document.querySelectorAll('[data-mrnf-form]').forEach(function (form) {
    updateConditions(form);
    form.addEventListener('input', function () { updateConditions(form); });
    form.addEventListener('change', function () { updateConditions(form); });

    form.addEventListener('submit', function (event) {
      if (typeof window.fetch !== 'function') {
        return;
      }
      event.preventDefault();
      clearErrors(form);
      updateConditions(form);

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var button = form.querySelector('.mrnf-submit');
      var label = button.querySelector('span');
      var original = label.textContent;
      button.disabled = true;
      label.textContent = mrnfFrontend.processing;

      refreshNonce(form).then(function () {
        if (form.dataset.ajax !== '1') {
          window.HTMLFormElement.prototype.submit.call(form);
          return null;
        }

        return window.fetch(mrnfFrontend.restUrl + form.dataset.mrnfForm + '/submit', {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json' }
        }).then(responseData);
      }).then(function (data) {
        if (!data) {
          return;
        }
        showNotice(form, data.message, 'success');
        form.reset();
        updateConditions(form);
        if (data.redirect) {
          window.setTimeout(function () { window.location.assign(data.redirect); }, 500);
        }
        form.dispatchEvent(new CustomEvent('mrnf:success', { detail: data, bubbles: true }));
      }).catch(function (error) {
        showNotice(form, error.message || mrnfFrontend.network, 'error');
        showErrors(form, error.fields);
        form.dispatchEvent(new CustomEvent('mrnf:error', { detail: error, bubbles: true }));
      }).finally(function () {
        button.disabled = false;
        label.textContent = original;
      });
    });
  });
}());
