/* Verify opt-in behavior and prevent contact data leaking into GA events. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/tracking.js'), 'utf8');
function run(settings, withGtag = true) {
  const handlers = {};
  const events = [];
  let writes = 0;
  const hidden = { value: '' };
  const window = {
    mrnfTracking: settings,
    location: { href: 'https://example.test/contact/?utm_source=google&secret=email@example.test', search: '?utm_source=google&secret=email@example.test', hostname: 'example.test' },
    sessionStorage: { getItem: () => null, setItem: () => { writes++; } }
  };
  if (withGtag) window.gtag = (...args) => events.push(args);
  vm.runInNewContext(source, {
    window, URL, URLSearchParams, Date,
    document: { referrer: '', querySelectorAll: () => [hidden], addEventListener: (name, handler) => { handlers[name] = handler; } }
  });
  return { window, handlers, events, hidden, writes };
}
let state = run(undefined);
assert.equal(state.writes, 0);
assert.deepEqual(Object.keys(state.handlers), []);
state = run({ collectAttribution: true, analyticsEvents: false });
assert.equal(state.writes, 1);
assert.equal(JSON.parse(state.hidden.value).landing_page, 'https://example.test/contact/');
state.handlers['mrnf:success']({ target: { dataset: { mrnfForm: '1' } } });
assert.equal(state.events.length, 0);
state = run({ collectAttribution: false, analyticsEvents: true });
assert.equal(state.writes, 0);
state.handlers.click({ target: { closest: () => ({ getAttribute: () => 'tel:+981234567890', textContent: 'Private contact' }) } });
assert.equal(state.events[0][1], 'phone_click');
assert.equal(state.events[0][2].page_location, 'https://example.test/contact/');
assert.equal(JSON.stringify(state.events).includes('+981234567890'), false);
assert.equal(JSON.stringify(state.events).includes('Private contact'), false);
state = run({ collectAttribution: false, analyticsEvents: true }, false);
state.handlers['mrnf:success']({ target: { dataset: {} } });
assert.equal(state.window.gtag, undefined);
assert.equal(state.window.dataLayer, undefined);
console.log('Tracking consent and data-minimization tests passed.');
