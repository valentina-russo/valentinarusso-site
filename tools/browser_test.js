// Test script - paste into browser console on carta-hd page after WASM loads
// Wait for initSwe() to complete first

const TEST_DATA = PROFILES_PLACEHOLDER;

async function runTests() {
  if (typeof calculateHDChart !== 'function') {
    console.error('calculateHDChart not found - make sure WASM is loaded');
    return;
  }

  const results = [];
  let pass = 0, fail = 0;

  for (const p of TEST_DATA) {
    const [y,m,d] = p.birth_date.split('-').map(Number);
    const [h,min] = p.birth_time.split(':').map(Number);

    // Get timezone offset
    let tzOffset = 1; // default CET
    try {
      const dt = new Date(y, m-1, d, h, min);
      const tz = p.tz || 'Europe/Rome';
      const utcStr = dt.toLocaleString('en-US', {timeZone: 'UTC'});
      const localStr = dt.toLocaleString('en-US', {timeZone: tz});
      tzOffset = (new Date(localStr) - new Date(utcStr)) / 3600000;
    } catch(e) { tzOffset = 1; }

    try {
      const chart = await calculateHDChart(y, m, d, h, min, tzOffset);

      // Compare type
      const typeMap = {
        'Generator': 'GP', 'Manifesting Generator': 'MGP',
        'Projector': 'PSP', 'Manifestor': 'ME', 'Reflector': 'REF'
      };
      // More specific type codes from authority
      let calcTypeCode = typeMap[chart.type] || chart.type;
      if (chart.type === 'Generator' && chart.authority === 'Solar Plexus') calcTypeCode = 'GE';
      if (chart.type === 'Manifesting Generator' && chart.authority === 'Solar Plexus') calcTypeCode = 'MGE';
      if (chart.type === 'Manifestor' && chart.authority === 'Splenic') calcTypeCode = 'MS';
      if (chart.type === 'Projector' && chart.authority === 'Solar Plexus') calcTypeCode = 'PE';

      // Compare variable (arrows)
      const v = chart.variables || {};
      const calcVar = `D${v.digestion === 'right' ? 'R' : 'L'}${v.environment === 'right' ? 'R' : 'L'}-P${v.perspective === 'right' ? 'R' : 'L'}${v.awareness === 'right' ? 'R' : 'L'}`;

      const typeOk = calcTypeCode === p.type_code;
      const profOk = chart.profile === p.profile;
      const varOk = calcVar === p.variable;

      if (typeOk && profOk && varOk) {
        pass++;
        results.push({name: p.name, status: 'PASS'});
      } else {
        fail++;
        const errors = [];
        if (!typeOk) errors.push(`type: ${calcTypeCode} vs ${p.type_code}`);
        if (!profOk) errors.push(`profile: ${chart.profile} vs ${p.profile}`);
        if (!varOk) errors.push(`variable: ${calcVar} vs ${p.variable}`);
        results.push({name: p.name, status: 'FAIL', errors});
      }
    } catch(e) {
      fail++;
      results.push({name: p.name, status: 'ERROR', error: e.message});
    }
  }

  console.log(`\n=== RESULTS: ${pass} PASS, ${fail} FAIL out of ${TEST_DATA.length} ===\n`);
  for (const r of results) {
    if (r.status !== 'PASS') {
      console.log(`FAIL: ${r.name} - ${r.errors?.join(', ') || r.error}`);
    }
  }

  return {pass, fail, total: TEST_DATA.length, failures: results.filter(r => r.status !== 'PASS')};
}

runTests().then(r => console.log('Test complete:', JSON.stringify(r)));
