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

test('profile identity updates sidebar and avatar without reloading', () => {
    const { context, document } = setup({ user: { id: 1, name: 'Before', email: 'old@example.com' }, users: [] });
    const name = { textContent: '' }, email = { textContent: '' };
    const avatar = { children: [], textContent: '', replaceChildren() { this.children = []; }, append(image) { this.children.push(image); } };
    document.createElement = () => ({});
    document.querySelectorAll = selector => ({ '[data-profile-name]': [name], '[data-profile-email]': [email], '[data-profile-avatar]': [avatar] }[selector] || []);
    vm.runInContext("updateProfileIdentity({id:1,name:'Rina Putri',email:'rina@example.com',avatar_url:'/profile/avatar?v=1'});", context);
    assert.equal(name.textContent, 'Rina Putri');
    assert.equal(email.textContent, 'rina@example.com');
    assert.equal(avatar.children[0].src, '/profile/avatar?v=1');
    vm.runInContext("updateProfileIdentity({id:1,name:'Rina Putri',email:'rina@example.com',avatar_url:null});", context);
    assert.equal(avatar.children.length, 0);
    assert.equal(avatar.textContent, 'RP');
});

test('lead campaign choices follow the selected branch', () => {
    const { context, document } = setup({ campaigns: [{id:1,name:'Bandung campaign',branch:'Bandung'}, {id:2,name:'JakPus campaign',branch:'JakPus'}] });
    const select={value:'',innerHTML:''};
    document.querySelector('#leadForm').elements={branch:{value:'Bandung'},campaign_id:select};
    vm.runInContext('renderLeadCampaignOptions();',context);
    assert.match(select.innerHTML,/Bandung campaign/);
    assert.doesNotMatch(select.innerHTML,/JakPus campaign/);
    document.querySelector('#leadForm').elements.branch.value='JakPus';
    vm.runInContext('renderLeadCampaignOptions();',context);
    assert.match(select.innerHTML,/JakPus campaign/);
    assert.doesNotMatch(select.innerHTML,/Bandung campaign/);
});
