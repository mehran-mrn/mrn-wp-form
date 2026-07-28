(function () {
  'use strict';

  var builder = document.querySelector('[data-mrnf-builder]');
  var dirty = false;

  document.querySelectorAll('[data-mrnf-confirm]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (!window.confirm('این عملیات قابل بازگشت نیست. ادامه می‌دهید؟')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-mrnf-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      navigator.clipboard.writeText(button.dataset.mrnfCopy).then(function () {
        var original = button.innerHTML;
        button.textContent = mrnfAdmin.i18n.copySuccess;
        window.setTimeout(function () { button.innerHTML = original; }, 1200);
      });
    });
  });

  if (!builder) {
    return;
  }

  var fieldsInput = builder.querySelector('[data-mrnf-fields-json]');
  var settingsInput = builder.querySelector('[data-mrnf-settings-json]');
  var notificationsInput = builder.querySelector('[data-mrnf-notifications-json]');
  var canvas = builder.querySelector('[data-mrnf-canvas]');
  var inspector = builder.querySelector('[data-mrnf-inspector]');
  var form = builder.querySelector('[data-mrnf-builder-form]');
  var fields = safeParse(fieldsInput.value, []);
  var settings = safeParse(settingsInput.value, {});
  var notifications = safeParse(notificationsInput.value, []);
  var selectedId = fields.length ? fields[0].id : '';
  var draggedId = '';
  var typeDefinitions = mrnfAdmin.fieldTypes || {};

  function safeParse(value, fallback) {
    try {
      var parsed = JSON.parse(value);
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 10);
  }

  function slugify(value) {
    var result = String(value || '').trim().toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/[^\w\u0600-\u06ff-]/g, '')
      .replace(/_+/g, '_');
    return result || 'field_' + Math.random().toString(36).slice(2, 6);
  }

  function createField(type) {
    var definition = typeDefinitions[type] || typeDefinitions.text;
    return {
      id: uid('fld'),
      type: type,
      key: slugify(type + '_' + (fields.length + 1)),
      label: definition.label,
      description: '',
      placeholder: '',
      default: '',
      required: false,
      width: '100',
      choices: definition.choices ? [mrnfAdmin.i18n.choice + ' ۱', mrnfAdmin.i18n.choice + ' ۲', mrnfAdmin.i18n.choice + ' ۳'] : [],
      validation: { min: '', max: '', minLength: '', maxLength: '', pattern: '', extensions: 'jpg,jpeg,png,pdf,doc,docx', maxFileMB: 5 },
      condition: { enabled: false, field: '', operator: 'equals', value: '' },
      content: ''
    };
  }

  function sync() {
    fieldsInput.value = JSON.stringify(fields);
    settingsInput.value = JSON.stringify(settings);
    notificationsInput.value = JSON.stringify(notifications);
    dirty = true;
  }

  function selectedField() {
    return fields.find(function (field) { return field.id === selectedId; });
  }

  function fieldControl(field) {
    if (field.type === 'heading') {
      return '<div class="mrnf-canvas-control">' + escapeHtml(field.content || 'عنوان یک بخش تازه') + '</div>';
    }
    if (field.type === 'html') {
      return '<div class="mrnf-canvas-control">' + escapeHtml(field.content || 'متن توضیحی یا HTML') + '</div>';
    }
    if (field.type === 'textarea') {
      return '<div class="mrnf-canvas-control">' + escapeHtml(field.placeholder || 'متن بلند…') + '</div>';
    }
    if (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox') {
      return '<div class="mrnf-canvas-control">' + escapeHtml((field.choices || []).join('  ·  ') || 'بدون گزینه') + '</div>';
    }
    if (field.type === 'file') {
      return '<div class="mrnf-canvas-control">انتخاب فایل…</div>';
    }
    if (field.type === 'consent') {
      return '<div class="mrnf-canvas-control">□ ' + escapeHtml(field.label) + '</div>';
    }
    if (field.type === 'hidden') {
      return '<div class="mrnf-canvas-control">پنهان · ' + escapeHtml(field.default || 'بدون مقدار') + '</div>';
    }
    return '<div class="mrnf-canvas-control">' + escapeHtml(field.placeholder || field.default || 'ورودی ' + typeDefinitions[field.type].label) + '</div>';
  }

  function renderCanvas() {
    if (!fields.length) {
      canvas.innerHTML = '<div class="mrnf-canvas-empty"><span class="dashicons dashicons-plus-alt2"></span><b>فرم شما از اینجا شروع می‌شود</b><p>' + escapeHtml(mrnfAdmin.i18n.emptyCanvas) + '</p></div>';
    } else {
      canvas.innerHTML = fields.map(function (field) {
        var definition = typeDefinitions[field.type] || typeDefinitions.text;
        return '<article class="mrnf-canvas-field mrnf-canvas-field--' + escapeHtml(field.type) + (field.id === selectedId ? ' is-selected' : '') + '" style="--field-width:' + escapeHtml(field.width) + '%" draggable="true" data-field-id="' + escapeHtml(field.id) + '">' +
          '<div class="mrnf-canvas-field__actions"><button type="button" data-field-duplicate title="تکثیر"><span class="dashicons dashicons-admin-page"></span></button><button type="button" data-field-delete title="حذف"><span class="dashicons dashicons-trash"></span></button></div>' +
          '<div class="mrnf-canvas-field__top"><span class="dashicons dashicons-move"></span><b>' + escapeHtml(field.label) + (field.required ? ' <em>*</em>' : '') + '</b><small>' + escapeHtml(definition.label) + ' · ' + escapeHtml(field.width) + '%</small></div>' +
          fieldControl(field) +
          '</article>';
      }).join('');
    }
    var count = builder.querySelector('[data-mrnf-field-count]');
    if (count) {
      count.textContent = fields.length + ' فیلد';
    }
    bindCanvasItems();
  }

  function bindCanvasItems() {
    canvas.querySelectorAll('[data-field-id]').forEach(function (item) {
      item.addEventListener('click', function (event) {
        selectedId = item.dataset.fieldId;
        if (event.target.closest('[data-field-delete]')) {
          if (window.confirm(mrnfAdmin.i18n.deleteField)) {
            fields = fields.filter(function (field) { return field.id !== selectedId; });
            selectedId = fields.length ? fields[Math.max(0, fields.length - 1)].id : '';
            sync();
            render();
          }
          return;
        }
        if (event.target.closest('[data-field-duplicate]')) {
          var sourceIndex = fields.findIndex(function (field) { return field.id === selectedId; });
          var duplicate = JSON.parse(JSON.stringify(fields[sourceIndex]));
          duplicate.id = uid('fld');
          duplicate.key = slugify(duplicate.key + '_copy');
          fields.splice(sourceIndex + 1, 0, duplicate);
          selectedId = duplicate.id;
          sync();
          render();
          return;
        }
        render();
      });
      item.addEventListener('dragstart', function () {
        draggedId = item.dataset.fieldId;
        item.classList.add('is-dragging');
      });
      item.addEventListener('dragend', function () {
        draggedId = '';
        item.classList.remove('is-dragging');
      });
      item.addEventListener('dragover', function (event) {
        event.preventDefault();
        if (!draggedId || draggedId === item.dataset.fieldId) {
          return;
        }
        var from = fields.findIndex(function (field) { return field.id === draggedId; });
        var to = fields.findIndex(function (field) { return field.id === item.dataset.fieldId; });
        var moved = fields.splice(from, 1)[0];
        fields.splice(to, 0, moved);
        sync();
        renderCanvas();
      });
    });
  }

  function input(label, prop, value, type, options, help) {
    type = type || 'text';
    var control = '';
    if (type === 'select') {
      control = '<select data-field-prop="' + prop + '">' + options.map(function (option) {
        return '<option value="' + escapeHtml(option.value) + '"' + (String(option.value) === String(value) ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
      }).join('') + '</select>';
    } else if (type === 'textarea') {
      control = '<textarea data-field-prop="' + prop + '">' + escapeHtml(value) + '</textarea>';
    } else {
      control = '<input type="' + type + '" value="' + escapeHtml(value) + '" data-field-prop="' + prop + '">';
    }
    return '<label class="mrnf-input"><span>' + escapeHtml(label) + '</span>' + control + (help ? '<small>' + escapeHtml(help) + '</small>' : '') + '</label>';
  }

  function toggle(label, prop, checked) {
    return '<label class="mrnf-builder-toggle"><input type="checkbox" data-field-prop="' + prop + '"' + (checked ? ' checked' : '') + '><i></i><span>' + escapeHtml(label) + '</span></label>';
  }

  function renderInspector() {
    var field = selectedField();
    if (!field) {
      inspector.innerHTML = '<div class="mrnf-inspector-empty"><span class="dashicons dashicons-admin-generic"></span><p>' + escapeHtml(mrnfAdmin.i18n.selectField) + '</p></div>';
      return;
    }
    var html = '<div class="mrnf-inspector">';
    html += '<section class="mrnf-inspector-section"><b>محتوا</b>';
    html += input('برچسب', 'label', field.label);
    html += input('کلید یکتا', 'key', field.key, 'text', null, 'برای merge tag: {field:' + field.key + '}');
    if (field.type === 'html' || field.type === 'heading') {
      html += input('محتوا', 'content', field.content, 'textarea');
    } else {
      if (field.type !== 'consent' && field.type !== 'hidden' && field.type !== 'file') {
        html += input('Placeholder', 'placeholder', field.placeholder);
      }
      if (field.type !== 'file') {
        html += input('مقدار پیش‌فرض', 'default', Array.isArray(field.default) ? field.default.join(',') : field.default);
      }
      html += input('توضیح راهنما', 'description', field.description);
    }
    html += '</section>';
    html += '<section class="mrnf-inspector-section"><b>چیدمان</b>' + input('عرض فیلد', 'width', field.width, 'select', [
      { value: '25', label: '۲۵٪' }, { value: '33', label: '۳۳٪' }, { value: '50', label: '۵۰٪' },
      { value: '66', label: '۶۶٪' }, { value: '75', label: '۷۵٪' }, { value: '100', label: '۱۰۰٪' }
    ]) + '</section>';
    if (typeDefinitions[field.type] && typeDefinitions[field.type].input) {
      html += '<section class="mrnf-inspector-section"><b>اعتبارسنجی</b>' + toggle('تکمیل این فیلد الزامی است', 'required', field.required);
      if (field.type === 'text' || field.type === 'textarea' || field.type === 'tel') {
        html += input('حداقل نویسه', 'validation.minLength', field.validation.minLength, 'number') + input('حداکثر نویسه', 'validation.maxLength', field.validation.maxLength, 'number') + input('الگوی RegEx', 'validation.pattern', field.validation.pattern);
      }
      if (field.type === 'number') {
        html += input('کمینه', 'validation.min', field.validation.min, 'number') + input('بیشینه', 'validation.max', field.validation.max, 'number');
      }
      if (field.type === 'file') {
        html += input('پسوندهای مجاز', 'validation.extensions', field.validation.extensions) + input('حداکثر حجم (MB)', 'validation.maxFileMB', field.validation.maxFileMB, 'number');
      }
      html += '</section>';
    }
    if (typeDefinitions[field.type] && typeDefinitions[field.type].choices) {
      html += '<section class="mrnf-inspector-section"><b>گزینه‌ها</b><div class="mrnf-choice-rows">' + (field.choices || []).map(function (choice, index) {
        return '<div class="mrnf-choice-row"><input value="' + escapeHtml(choice) + '" data-choice-index="' + index + '"><button type="button" data-delete-choice="' + index + '">×</button></div>';
      }).join('') + '</div><button type="button" class="mrnf-add-choice" data-add-choice>+ افزودن گزینه</button></section>';
    }
    if (field.type !== 'hidden') {
      var sourceOptions = [{ value: '', label: 'انتخاب فیلد…' }].concat(fields.filter(function (candidate) {
        return candidate.id !== field.id && typeDefinitions[candidate.type] && typeDefinitions[candidate.type].input;
      }).map(function (candidate) { return { value: candidate.key, label: candidate.label }; }));
      html += '<section class="mrnf-inspector-section"><b>منطق شرطی</b>' + toggle('نمایش شرطی این فیلد', 'condition.enabled', field.condition.enabled);
      if (field.condition.enabled) {
        html += input('فیلد مبنا', 'condition.field', field.condition.field, 'select', sourceOptions);
        html += input('شرط', 'condition.operator', field.condition.operator, 'select', [
          { value: 'equals', label: 'برابر باشد با' }, { value: 'not_equals', label: 'برابر نباشد با' },
          { value: 'contains', label: 'شامل باشد' }, { value: 'not_empty', label: 'خالی نباشد' }, { value: 'empty', label: 'خالی باشد' }
        ]);
        if (field.condition.operator !== 'empty' && field.condition.operator !== 'not_empty') {
          html += input('مقدار مقایسه', 'condition.value', field.condition.value);
        }
      }
      html += '</section>';
    }
    html += '</div>';
    inspector.innerHTML = html;
    bindInspector();
  }

  function setNested(object, path, value) {
    var parts = path.split('.');
    var target = object;
    for (var i = 0; i < parts.length - 1; i++) {
      target[parts[i]] = target[parts[i]] || {};
      target = target[parts[i]];
    }
    target[parts[parts.length - 1]] = value;
  }

  function bindInspector() {
    var field = selectedField();
    inspector.querySelectorAll('[data-field-prop]').forEach(function (control) {
      var handler = function () {
        var value = control.type === 'checkbox' ? control.checked : control.value;
        if (control.dataset.fieldProp === 'key') {
          value = slugify(value);
          control.value = value;
        }
        setNested(field, control.dataset.fieldProp, value);
        sync();
        if (control.dataset.fieldProp === 'condition.enabled') {
          renderInspector();
        }
        renderCanvas();
      };
      control.addEventListener(control.type === 'text' || control.tagName === 'TEXTAREA' ? 'input' : 'change', handler);
    });
    inspector.querySelectorAll('[data-choice-index]').forEach(function (control) {
      control.addEventListener('input', function () {
        field.choices[Number(control.dataset.choiceIndex)] = control.value;
        sync();
        renderCanvas();
      });
    });
    inspector.querySelectorAll('[data-delete-choice]').forEach(function (button) {
      button.addEventListener('click', function () {
        field.choices.splice(Number(button.dataset.deleteChoice), 1);
        sync();
        render();
      });
    });
    var addChoice = inspector.querySelector('[data-add-choice]');
    if (addChoice) {
      addChoice.addEventListener('click', function () {
        field.choices.push(mrnfAdmin.i18n.choice + ' ' + (field.choices.length + 1));
        sync();
        render();
      });
    }
  }

  function render() {
    renderCanvas();
    renderInspector();
  }

  builder.querySelectorAll('[data-mrnf-add-field]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = createField(button.dataset.mrnfAddField);
      fields.push(field);
      selectedId = field.id;
      sync();
      render();
    });
    button.addEventListener('dragstart', function (event) {
      event.dataTransfer.setData('mrnf/type', button.dataset.mrnfAddField);
    });
  });

  canvas.addEventListener('dragover', function (event) {
    event.preventDefault();
    canvas.classList.add('is-dragover');
  });
  canvas.addEventListener('dragleave', function () {
    canvas.classList.remove('is-dragover');
  });
  canvas.addEventListener('drop', function (event) {
    event.preventDefault();
    canvas.classList.remove('is-dragover');
    var type = event.dataTransfer.getData('mrnf/type');
    if (type) {
      var field = createField(type);
      fields.push(field);
      selectedId = field.id;
      sync();
      render();
    }
  });

  builder.querySelectorAll('[data-mrnf-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      builder.querySelectorAll('[data-mrnf-tab]').forEach(function (tab) { tab.classList.remove('is-active'); });
      builder.querySelectorAll('[data-mrnf-tab-panel]').forEach(function (panel) { panel.classList.remove('is-active'); });
      button.classList.add('is-active');
      builder.querySelector('[data-mrnf-tab-panel="' + button.dataset.mrnfTab + '"]').classList.add('is-active');
    });
  });

  builder.querySelectorAll('[data-mrnf-setting]').forEach(function (control) {
    control.addEventListener(control.type === 'text' || control.tagName === 'TEXTAREA' ? 'input' : 'change', function () {
      settings[control.dataset.mrnfSetting] = control.type === 'checkbox' ? control.checked : control.value;
      sync();
    });
  });

  builder.querySelectorAll('[data-notification-index]').forEach(function (card) {
    card.querySelectorAll('[data-notification-key]').forEach(function (control) {
      control.addEventListener(control.type === 'text' || control.tagName === 'TEXTAREA' ? 'input' : 'change', function () {
        var index = Number(card.dataset.notificationIndex);
        setNested(notifications[index], control.dataset.notificationKey, control.type === 'checkbox' ? control.checked : control.value);
        sync();
      });
    });
  });

  var previewButton = builder.querySelector('[data-mrnf-preview]');
  var previewModal = builder.querySelector('[data-mrnf-preview-modal]');
  if (previewButton && previewModal) {
    previewButton.addEventListener('click', function () {
      var title = form.querySelector('[name="title"]').value;
      var description = form.querySelector('[name="description"]').value;
      var controls = fields.map(function (field) {
        if (field.type === 'heading') { return '<h3>' + escapeHtml(field.label) + '</h3>'; }
        if (field.type === 'html') { return '<p>' + escapeHtml(field.content) + '</p>'; }
        if (field.type === 'hidden') { return ''; }
        var control = field.type === 'textarea' ? '<textarea placeholder="' + escapeHtml(field.placeholder) + '"></textarea>' :
          '<input type="' + (['email', 'tel', 'number', 'date', 'file'].indexOf(field.type) >= 0 ? field.type : 'text') + '" placeholder="' + escapeHtml(field.placeholder) + '">';
        return '<label style="flex:1 1 calc(' + escapeHtml(field.width) + '% - 16px);max-width:calc(' + escapeHtml(field.width) + '% - 8px)"><b>' + escapeHtml(field.label) + (field.required ? ' *' : '') + '</b>' + control + '<small>' + escapeHtml(field.description) + '</small></label>';
      }).join('');
      var doc = '<!doctype html><html dir="' + (settings.direction === 'ltr' ? 'ltr' : 'rtl') + '"><head><meta charset="utf-8"><style>body{background:#f4f1ea;padding:50px;font-family:Tahoma;color:' + escapeHtml(settings.textColor) + '}.form{max-width:820px;margin:auto;background:' + escapeHtml(settings.backgroundColor) + ';border-radius:' + Number(settings.borderRadius) + 'px;padding:32px;box-shadow:0 16px 50px #173b3d14}.form h2{color:' + escapeHtml(settings.primaryColor) + ';margin-top:0}.grid{display:flex;flex-wrap:wrap;gap:' + Number(settings.layoutGap) + 'px}label{display:grid;gap:7px;font-size:13px}input,textarea{border:1px solid #d8d9d4;border-radius:' + Number(settings.borderRadius) + 'px;padding:12px;font:inherit}small{color:#77827f}button{margin-top:20px;background:' + escapeHtml(settings.primaryColor) + ';color:#fff;border:0;border-radius:' + Number(settings.borderRadius) + 'px;padding:12px 25px;font:inherit;font-weight:bold}</style></head><body><div class="form"><h2>' + escapeHtml(title) + '</h2><p>' + escapeHtml(description) + '</p><div class="grid">' + controls + '</div><button>' + escapeHtml(settings.submitLabel) + '</button></div></body></html>';
      previewModal.querySelector('iframe').srcdoc = doc;
      previewModal.hidden = false;
    });
    previewModal.querySelector('[data-mrnf-close-preview]').addEventListener('click', function () { previewModal.hidden = true; });
  }

  form.addEventListener('submit', function () {
    fieldsInput.value = JSON.stringify(fields);
    settingsInput.value = JSON.stringify(settings);
    notificationsInput.value = JSON.stringify(notifications);
    dirty = false;
  });
  form.addEventListener('input', function () { dirty = true; });
  window.addEventListener('beforeunload', function (event) {
    if (dirty) {
      event.preventDefault();
      event.returnValue = mrnfAdmin.i18n.unsaved;
    }
  });

  render();
}());
