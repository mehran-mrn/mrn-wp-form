(function (blocks, element, components, blockEditor) {
  'use strict';
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var SelectControl = components.SelectControl;
  var Placeholder = components.Placeholder;
  var options = [{ value: '0', label: 'یک فرم انتخاب کنید…' }].concat((window.mrnfBlock && mrnfBlock.forms) || []);

  blocks.registerBlockType('mrn/form', {
    apiVersion: 3,
    title: 'MRN Form',
    icon: 'feedback',
    category: 'widgets',
    attributes: { formId: { type: 'integer', default: 0 } },
    edit: function (props) {
      var selector = el(SelectControl, {
        label: 'فرم',
        value: String(props.attributes.formId || 0),
        options: options,
        onChange: function (value) { props.setAttributes({ formId: Number(value) }); }
      });
      var selected = options.find(function (item) { return Number(item.value) === props.attributes.formId; });
      return el('div', {},
        el(InspectorControls, {}, el('div', { style: { padding: '16px' } }, selector)),
        el(Placeholder, { icon: 'feedback', label: 'MRN Form' },
          props.attributes.formId ? el('p', {}, 'فرم انتخاب‌شده: ', el('strong', {}, selected ? selected.label : '#' + props.attributes.formId)) : selector
        )
      );
    },
    save: function () { return null; }
  });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor));
