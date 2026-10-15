/**
 * Minimal fake of the ClickUp API v2 endpoints used by the automation (for local end-to-end runs).
 *   GET/POST /list/:id/task (tags[], include_closed, page), PUT /task/:id (markdown_content), POST /task/:id/comment, GET /task/:id/comment, POST /comment/:id/reply, GET /comment/:id/reply
 * Comments are returned newest first, 25 per page, with start/start_id paging like ClickUp.
 */
import http from 'node:http';

export function startFakeClickUp({ token = 'pk_test', botUser = { id: 1, username: 'automation-bot' } } = {}) {
  const comments = [];   // { id, comment_text, user, date, taskId, parentId }
  const tasks = [];      // { id, list, name, markdown_description, assignees, tags, status }
  let seq = 1000;
  const stats = { listCalls: 0, updates: 0 };
  const server = http.createServer(async (req, res) => {
    const send = (code, data) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(data)); };
    if (req.headers.authorization !== token) return send(401, { err: 'Token invalid' });
    let body = '';
    for await (const chunk of req) body += chunk;
    const url = new URL(req.url, 'http://x');
    let m;
    if ((m = url.pathname.match(/^\/list\/([^/]+)\/task$/)) && req.method === 'POST') {
      const b = JSON.parse(body);
      if (!b.name) return send(400, { err: 'Task name invalid' });
      const t = { id: 't' + (++seq), list: m[1], status: { status: 'to do', type: 'open' }, date_created: String(Date.now()), ...b, url: '' };
      t.url = `https://app.clickup.com/t/${t.id}`;
      tasks.push(t); return send(200, t);
    }
    if ((m = url.pathname.match(/^\/list\/([^/]+)\/task$/)) && req.method === 'GET') {
      const tags = url.searchParams.getAll('tags[]');
      const closed = url.searchParams.get('include_closed') === 'true';
      const page = Number(url.searchParams.get('page') || 0);
      const list = tasks.filter(t => t.list === m[1] && (closed || t.status?.type !== 'closed')
        && tags.every(tag => (t.tags || []).map(x => x.name ?? x).includes(tag)));
      stats.listCalls++;
      return send(200, { tasks: list.slice(page * 100, page * 100 + 100), last_page: list.length <= (page + 1) * 100 });
    }
    if ((m = url.pathname.match(/^\/task\/([^/]+)$/)) && req.method === 'PUT') {
      const t = tasks.find(x => x.id === m[1]);
      if (!t) return send(404, { err: 'Task not found' });
      const b = JSON.parse(body);
      if (b.markdown_content !== undefined) { t.markdown_description = b.markdown_content; delete b.markdown_content; }
      stats.updates++;
      Object.assign(t, b); return send(200, t);
    }
    if ((m = url.pathname.match(/^\/task\/([^/]+)\/comment$/))) {
      if (req.method === 'POST') {
        const c = { id: String(++seq), comment_text: JSON.parse(body).comment_text, user: botUser, date: String(Date.now()), taskId: m[1] };
        comments.push(c); return send(200, { id: c.id, hist_id: 'h' + c.id, date: Number(c.date) });
      }
      let list = comments.filter(c => c.taskId === m[1] && !c.parentId).sort((a, b) => Number(b.date) - Number(a.date));
      const start = url.searchParams.get('start');
      if (start) list = list.filter(c => Number(c.date) < Number(start));
      return send(200, { comments: list.slice(0, 25) });
    }
    if ((m = url.pathname.match(/^\/comment\/([^/]+)\/reply$/))) {
      if (req.method === 'POST') {
        const c = { id: String(++seq), comment_text: JSON.parse(body).comment_text, user: botUser, date: String(Date.now()), parentId: m[1] };
        comments.push(c); return send(200, { id: c.id, date: Number(c.date) });
      }
      return send(200, { comments: comments.filter(c => c.parentId === m[1]).sort((a, b) => Number(a.date) - Number(b.date)) });
    }
    send(404, { err: 'not found' });
  });
  return new Promise(resolve => server.listen(0, '127.0.0.1', () => resolve({
    base: `http://127.0.0.1:${server.address().port}`, token, comments, tasks, stats,
    /** simulate a human comment */
    addComment(taskId, text, user, parentId) { const c = { id: String(++seq), comment_text: text, user, date: String(Date.now()), taskId: parentId ? undefined : taskId, parentId }; comments.push(c); return c; },
    close: () => new Promise(r => server.close(r)),
  })));
}
