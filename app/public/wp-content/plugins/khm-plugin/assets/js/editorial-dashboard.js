// editorial-dashboard.js (vanilla)
async function loadRecentSessions(){
  try {
    const data = await wp.apiFetch({
      path: 'editorial/v1/sessions?limit=6',
      headers: { 'X-WP-Nonce': editorialData.nonce }
    });
    const container = document.getElementById('plnr-recent-sessions');
    container.innerHTML = data.length
      ? data.map(s=>`<div><a href="${s.link}">${s.title}</a> · ${s.status||'Draft'}</div>`).join('')
      : '<div>No planning sessions yet — start your first above now!</div>';
  } catch (error) {
    console.error('Failed to load sessions:', error);
    const container = document.getElementById('plnr-recent-sessions');
    const msg = error && error.message ? error.message : 'Unknown error';
    container.innerHTML = '<div>Error loading sessions: ' + msg + '</div>';
  }
}

async function quickCreate(){
  const title = prompt('Planner title?');
  if(!title) return;
  try {
    const payload = await wp.apiFetch({
      path: 'editorial/v1/sessions',
      method: 'POST',
      data: { title },
      headers: { 'X-WP-Nonce': editorialData.nonce }
    });
    if(payload.id) {
      window.location.href = payload.link; // open planner
    } else {
      alert('Create failed');
    }
  } catch (error) {
    console.error('Failed to create session:', error);
    const msg = error && error.message ? error.message : JSON.stringify(error);
    alert('Failed to start session: ' + msg);
  }
}

document.addEventListener('DOMContentLoaded', function(){
  const quick = document.getElementById('plnr-quick-create');
  if(quick){
    quick.innerHTML = '<button id="plnr-create-btn">Create Planner Session</button>';
    document.getElementById('plnr-create-btn').addEventListener('click', quickCreate);
  }
  if(document.getElementById('plnr-recent-sessions')) loadRecentSessions();
});
