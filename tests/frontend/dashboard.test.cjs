const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(data) {
    const elements = new Map();
    const events = {};
    const document = {
        querySelector(selector) {
            if (!elements.has(selector)) elements.set(selector, { value: '', textContent: '', innerHTML: '', dataset: {}, classList: { add() {}, remove() {} } });
            return elements.get(selector);
        },
        addEventListener(name, callback) { events[name] = callback; },
    };
    const context = vm.createContext({ document, window: { ADS_DATA: data }, Intl, URLSearchParams, FormData, setTimeout, clearTimeout });
    vm.runInContext(fs.readFileSync('resources/js/app.js', 'utf8'), context);
    return { context, document, elements, events };
}

test('login page safely skips dashboard initialization', () => {
    const { events } = setup(undefined);
    assert.doesNotThrow(() => events.DOMContentLoaded());
});

test('empty workspace shows no sample campaign or revenue', () => {
    const { context, document } = setup({ campaigns: [] });
    vm.runInContext('renderOverview();paintMetrics({},[]);', context);
    assert.match(document.querySelector('#campaignTableBody').innerHTML, /Tidak ada campaign/);
    assert.equal(document.querySelector('#metricLeads').textContent, '0');
    assert.match(document.querySelector('#performanceChart').innerHTML, /Belum ada data/);
});

test('metrics, daily chart and both funnels render actual filtered values', () => {
    const { context, document } = setup({ campaigns: [] });
    vm.runInContext(`paintMetrics({budget:100,revenue:350,impressions:1000,reach:700,clicks:90,chats:20,leads:10,closings:3},[normalizeCampaign({id:1,date:'2026-10-05',name:'<script>',platform:'Instagram',budget:100,revenue:350})]);`, context);
    assert.equal(document.querySelector('#metricLeads').textContent, '10');
    assert.equal(document.querySelector('#metricReach').textContent, '700');
    assert.match(document.querySelector('#chartRoas').textContent, /3,5/);
    assert.match(document.querySelector('#performanceChart').innerHTML, /2026-10-05/);
    assert.match(document.querySelector('#overviewFunnel').innerHTML, /700/);
    assert.match(document.querySelector('#fullFunnel').innerHTML, /700/);
    assert.doesNotMatch(document.querySelector('#campaignTableBody').innerHTML, /<script>/);
});
