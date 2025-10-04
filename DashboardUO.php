<?php
session_start();
require 'mysql_connect.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: LoginModule.php");
    exit();
}
$owner_id = (int)$_SESSION['owner_id'];

/** Owner name (for header) */
$stmt = $conn->prepare("SELECT first_name, last_name FROM tbadmin WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();
$full_name = $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'Unit Owner';

/** Optional thread from QS */
$thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$counterparty_name = 'Tenant';

/** Owner’s listings (cards + map) */
$listings = [];
$stmt = $conn->prepare("SELECT * FROM tblistings WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$q = $stmt->get_result();
while ($row = $q->fetch_assoc()) $listings[] = $row;
$stmt->close();

/** Threads for this owner: show tenant name + listing title */
$threads = [];
$sql = "
SELECT t.id AS thread_id,
       l.title AS listing_title,
       ta.first_name, ta.last_name, ta.id AS tenant_id
FROM chat_threads t
JOIN tblistings l            ON l.id = t.listing_id
JOIN chat_participants cpo   ON cpo.thread_id = t.id AND cpo.user_id = ? AND cpo.role = 'owner'
JOIN chat_participants cpt   ON cpt.thread_id = t.id AND cpt.role = 'tenant'
JOIN tbadmin ta              ON ta.id = cpt.user_id
ORDER BY t.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) {
    $row['display_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $threads[] = $row;
}
$stmt->close();

/** If a thread is selected, show tenant name as the header */
if ($thread_id > 0) {
    $stmt = $conn->prepare("
        SELECT ta.first_name, ta.last_name
        FROM chat_threads t
        JOIN chat_participants cpo ON cpo.thread_id = t.id AND cpo.user_id = ? AND cpo.role = 'owner'
        JOIN chat_participants cpt ON cpt.thread_id = t.id AND cpt.role = 'tenant'
        JOIN tbadmin ta            ON ta.id = cpt.user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $owner_id, $thread_id);
    $stmt->execute();
    $stmt->bind_result($fn, $ln);
    if ($stmt->fetch()) {
        $counterparty_name = trim(($fn ?? '') . ' ' . ($ln ?? '')) ?: 'Tenant';
    }
    $stmt->close();
}

/** Fetch rental requests for dashboard preview */
$rental_requests = [];
$stmt = $conn->prepare("
    SELECT rr.id, rr.tenant_id, rr.listing_id, rr.status, rr.amount_due, rr.requested_at,
           l.title AS property_title,
           t.first_name AS tenant_first_name, t.last_name AS tenant_last_name
    FROM rental_requests rr
    JOIN tblistings l ON l.id = rr.listing_id
    JOIN tbadmin t ON t.id = rr.tenant_id
    WHERE l.owner_id = ?
    ORDER BY rr.requested_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rental_requests[] = $row;
}
$stmt->close();

$pending_count = count(array_filter($rental_requests, fn($r) => $r['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Owner Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="DashboardUO.css?v=22" />
  <link rel="stylesheet" href="darkmode.css" />
  <style>
    .topFixedBar { position: fixed; top:0; left:0; right:0; z-index:1030; background:#8B4513; color:#fff; }
    .topFixedBar .logo{ height:38px; }
    .bg-soft{ background:#f8fafc; }
    .dashboard-header { background:#fff; border-bottom:1px solid #e5e7eb; position:sticky; top:64px; z-index:100; }
    .badge.bg-orange{ background:#e67e22; }

    /* Floating, collapsible chat widget */
    .hb-chat-widget{
      position: fixed; bottom: 20px; right: 20px;
      width: 380px; max-height: 72vh; background:#fff;
      border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;
      box-shadow:0 10px 25px rgba(0,0,0,.18); z-index: 9999;
      display:flex; flex-direction:column;
    }
    .hb-chat-header-bar{
      background:#8B4513; color:#fff; padding:8px 12px;
      display:flex; align-items:center; justify-content:space-between; cursor:pointer;
    }
    .hb-chat-header-bar .hb-btn-ghost{
      border:none; background:transparent; color:#fff; font-size:18px; cursor:pointer;
    }
    .hb-chat-widget.collapsed{ height:42px; width:260px; }
    .hb-chat-widget.collapsed #hb-chat-body-container{ display:none; }

    /* Chat internals (shared palette) */
    .hb-chat-container{
      --hb-border:#e5e7eb; --hb-bg:#fff; --hb-bg-2:#f9fafb; --hb-text:#111827; --hb-muted:#6b7280;
      --hb-mine:#DCFCE7; --hb-their:#F3F4F6; --hb-accent:#8B4513;
      border-top:1px solid var(--hb-border);
      display:flex; flex-direction:column; max-width:100%; min-height:320px; height:50vh; overflow:hidden;
      background:var(--hb-bg);
    }
    .hb-chat-header{ display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:var(--hb-bg-2); border-bottom:1px solid var(--hb-border); }
    .hb-chat-title{ display:flex; align-items:center; gap:8px; color:var(--hb-text); font-size:14px; }
    .hb-dot{ width:8px; height:8px; border-radius:50%; background:#22c55e; }
    .hb-chat-body{ flex:1; position:relative; overflow:auto; padding:10px; background:linear-gradient(0deg,var(--hb-bg),var(--hb-bg-2) 120%); }
    .hb-history-sentinel{ text-align:center; color:var(--hb-muted); font-size:12px; padding:6px 0; }
    .hb-messages{ display:flex; flex-direction:column; gap:8px; padding-bottom:8px; }
    .hb-msg{ max-width:78%; border-radius:14px; padding:8px 10px; line-height:1.35; word-wrap:break-word; white-space:pre-wrap; }
    .hb-msg.mine{ margin-left:auto; background:var(--hb-mine); color:#0b3a1e; }
    .hb-msg.their{ margin-right:auto; background:var(--hb-their); color:#111827; }
    .hb-meta{ display:block; text-align:right; font-size:11px; color:var(--hb-muted); margin-top:4px; }
    .hb-from{ font-weight:600; font-size:12px; margin-bottom:2px; color:var(--hb-text); opacity:.9; }
    .hb-chat-input{ display:flex; gap:8px; padding:10px; border-top:1px solid var(--hb-border); background:var(--hb-bg-2); }
    .hb-chat-input textarea{
      flex:1; resize:none; border:1px solid var(--hb-border); border-radius:10px; padding:10px 12px; font-size:14px; background:var(--hb-bg); color:var(--hb-text);
      max-height:140px; overflow:auto;
    }
    .hb-btn{ background:var(--hb-accent); color:#fff; border:none; padding:10px 14px; border-radius:10px; font-weight:600; cursor:pointer; min-width:80px; }
    .hb-btn:disabled{ opacity:.6; cursor:not-allowed; }

    /* Map */
    #map{ height:300px; border-radius:12px; }
    @media (max-width: 576px){ .hb-chat-widget{ right:12px; left:12px; width:auto; } }
  
/* Quick Replies */
.hb-quick-replies{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:8px;
  margin:8px 0 6px;
}
.hb-qr-btn{
  border:1px solid #e5e7eb;
  background:#fff;
  padding:8px 10px;
  border-radius:9999px;
  font-size:0.9rem;
  cursor:pointer;
  transition:background .15s ease,border-color .15s ease;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.hb-qr-btn:hover{ background:#f9fafb; border-color:#d1d5db; }
.hb-qr-btn:active{ background:#f3f4f6; }
@media (max-width:640px){
  .hb-quick-replies{ grid-template-columns:repeat(2,minmax(0,1fr)); }
}
</style>
</head>
<body class="bg-soft">

  <nav class="topFixedBar">
    <div class="container-fluid px-3 d-flex align-items-center justify-content-between" style="height:64px;">
      <div class="d-flex align-items-center gap-3">
        <img src="Assets/Logo1.png" alt="HanapBahay Logo" class="logo" />
        <ul class="nav">
          <li class="nav-item"><a class="nav-link text-white" href="DashboardUO">Home</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="rental_requests_uo">Rental Requests</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="#hb-chat-widget">Messages</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="DashboardAddUnit">Add Properties</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="edit_profile">Settings</a></li>
        </ul>
      </div>
      <a href="logout" class="btn btn-outline-light">Logout</a>
    </div>
  </nav>

  <script>
    window.HB_CURRENT_USER_ID = <?php echo (int)$owner_id; ?>;
    window.HB_CURRENT_USER_ROLE = "owner";
    window.HB_THREAD_ID_FROM_QS = <?php echo (int)$thread_id; ?>;
  </script>

  <div id="pageContent" class="flex-grow-1 pt-5 mt-5">
    <header class="dashboard-header px-4 py-3 d-flex justify-content-between align-items-center">
      <h5 class="m-0 text-dark">Welcome back, <?= htmlspecialchars($full_name) ?>!</h5>
      <span class="badge bg-orange text-white">Owner</span>
    </header>

    <main class="p-4">
      <div class="container-fluid px-3">

        <!-- Summary cards -->
        <div class="row mb-4">
          <div class="col-lg-12 mb-3">
            <div class="card shadow-sm h-100">
              <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Rental Requests</h5>
                <?php if ($pending_count > 0): ?>
                  <span class="badge bg-warning text-dark"><?= $pending_count ?> Pending</span>
                <?php endif; ?>
              </div>
              <div class="card-body p-0">
                <?php if (empty($rental_requests)): ?>
                  <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">No rental requests yet</p>
                  </div>
                <?php else: ?>
                  <div class="list-group list-group-flush">
                    <?php foreach ($rental_requests as $req): ?>
                      <?php
                      $tenantName = trim($req['tenant_first_name'] . ' ' . $req['tenant_last_name']);
                      $statusClass = 'warning';
                      if ($req['status'] === 'approved') $statusClass = 'success';
                      if ($req['status'] === 'rejected') $statusClass = 'danger';
                      if ($req['status'] === 'cancelled') $statusClass = 'secondary';
                      $timeAgo = date('M d, g:i A', strtotime($req['requested_at']));
                      ?>
                      <a href="rental_requests_uo.php#request-<?= $req['id'] ?>"
                         class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-start">
                          <div class="flex-grow-1">
                            <h6 class="mb-1"><?= htmlspecialchars($req['property_title']) ?></h6>
                            <p class="mb-1 text-muted small">
                              <i class="bi bi-person"></i> <?= htmlspecialchars($tenantName) ?>
                            </p>
                            <small class="text-muted">
                              <i class="bi bi-clock"></i> <?= $timeAgo ?>
                            </small>
                          </div>
                          <div class="text-end">
                            <span class="badge bg-<?= $statusClass ?> mb-1"><?= ucfirst($req['status']) ?></span>
                            <div class="text-success fw-bold">₱<?= number_format($req['amount_due'], 2) ?></div>
                          </div>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </div>
                  <div class="card-footer text-center">
                    <a href="rental_requests_uo" class="btn btn-sm btn-outline-primary">
                      View All Requests <i class="bi bi-arrow-right"></i>
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Listings -->
        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <div class="card border-success">
              <div class="card-header bg-success text-white">Visible Listings</div>
              <div class="card-body">
                <?php
                $visible = array_filter($listings, fn($l) => (int)$l['is_archived'] === 0);
                if (count($visible) > 0): ?>
                  <?php foreach ($visible as $listing): ?>
                    <div class="border rounded p-2 mb-3">
                      <h6 class="mb-1"><?= htmlspecialchars($listing['title']) ?></h6>
                      <p class="mb-1"><strong>Capacity:</strong> <?= (int)$listing['capacity'] ?> person(s)</p>
                      <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($listing['address']) ?></p>
                      <p class="mb-1"><strong>Price:</strong> ₱<?= number_format($listing['price'], 0) ?></p>
                      <div class="d-flex flex-wrap gap-2">
                        <a href="edit_listing.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <a href="archive_listing.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-outline-warning"
                           onclick="return confirm('Archive this listing? Tenants won\\'t see it.');">Archive</a>
                        <a href="delete_listing.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete this listing permanently?');">Delete</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-muted">No visible listings.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card border-secondary">
              <div class="card-header bg-secondary text-white">Archived Listings</div>
              <div class="card-body">
                <?php
                $archived = array_filter($listings, fn($l) => (int)$l['is_archived'] === 1);
                if (count($archived) > 0): ?>
                  <?php foreach ($archived as $listing): ?>
                    <div class="border rounded p-2 mb-3">
                      <h6 class="mb-1"><?= htmlspecialchars($listing['title']) ?></h6>
                      <p class="mb-1"><strong>Capacity:</strong> <?= (int)$listing['capacity'] ?> person(s)</p>
                      <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($listing['address']) ?></p>
                      <p class="mb-1"><strong>Price:</strong> ₱<?= number_format($listing['price'], 0) ?></p>
                      <div class="d-flex flex-wrap gap-2">
                        <a href="restore_listing.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-outline-success">Restore</a>
                        <a href="delete_listing.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete this listing permanently?');">Delete</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-muted">No archived listings.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Map -->
        <section class="mb-4">
          <h6 class="mb-3">My Property Locations</h6>
          <div id="map" class="border rounded"></div>
        </section>

      </div>
    </main>
  </div>

  <!-- ===== Floating Chat Widget (Owner) ===== -->
  <div id="hb-chat-widget" class="hb-chat-widget">
    <div id="hb-chat-header" class="hb-chat-header-bar">
      <span><i class="bi bi-chat-dots"></i> Messages</span>
      <button id="hb-toggle-btn" class="hb-btn-ghost">_</button>
    </div>
    <div id="hb-chat-body-container" class="hb-chat-body-container">
      <div class="d-flex align-items-center justify-content-between mb-2 px-2 pt-2">
        <select id="hb-thread-select" class="form-select form-select-sm" style="min-width:260px;">
          <option value="0" selected>Select a conversation…</option>
          <?php foreach ($threads as $t): ?>
            <?php
              $name = $t['display_name'] ?: 'Tenant';
              $label = $name . ' — ' . ($t['listing_title'] ?: 'Listing');
              $dataName = htmlspecialchars($name, ENT_QUOTES);
            ?>
            <option value="<?= (int)$t['thread_id'] ?>" data-name="<?= $dataName ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="hb-clear-chat">Clear</button>
      </div>

      <div id="hb-chat" class="hb-chat-container">
        <div class="hb-chat-header">
          <div class="hb-chat-title">
            <span class="hb-dot"></span>
            <strong id="hb-counterparty"><?= htmlspecialchars($counterparty_name) ?></strong>
          </div>
        </div>
        <div id="hb-chat-body" class="hb-chat-body">
          <div id="hb-history-sentinel" class="hb-history-sentinel">
            <?= ($thread_id ? 'Loading…' : 'Select a conversation to view messages') ?>
          </div>
          <div id="hb-messages" class="hb-messages" aria-live="polite"></div>
        

<!-- Quick Replies (Owner Saved Replies) -->
<div id="hb-quick-replies" class="hb-quick-replies" aria-label="Saved replies"></div>

</div>
        <form id="hb-send-form" class="hb-chat-input" autocomplete="off">
          <textarea id="hb-input" rows="1" placeholder="Type a message…" required <?= $thread_id ? '' : 'disabled' ?>></textarea>
          <button id="hb-send" type="submit" class="hb-btn" <?= $thread_id ? '' : 'disabled' ?>>Send</button>
        </form>
      </div>
    </div>
  </div>
  <!-- ======================================== -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://js.pusher.com/8.2/pusher.min.js"></script>

  <script>
    // Collapse / expand floating widget
    document.addEventListener("DOMContentLoaded", () => {
      const widget = document.getElementById("hb-chat-widget");
      const toggleBtn = document.getElementById("hb-toggle-btn");
      document.getElementById("hb-chat-header").addEventListener("click", (e)=>{
        if (e.target.id !== 'hb-toggle-btn') widget.classList.toggle("collapsed");
      });
      toggleBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        widget.classList.toggle("collapsed");
        toggleBtn.textContent = widget.classList.contains("collapsed") ? "▴" : "_";
      });
    });

    // Owner chat logic
    (() => {
      let THREAD_ID     = <?= (int)$thread_id ?>;
      let COUNTERPARTY  = <?= json_encode($counterparty_name) ?>;

      const PUSHER_KEY = "c9a924289093535f51f9";
      const PUSHER_CLUSTER = "ap1";

      const bodyEl = document.getElementById('hb-chat-body');
      const msgsEl = document.getElementById('hb-messages');
      const inputEl = document.getElementById('hb-input');
      const formEl = document.getElementById('hb-send-form');
      const sendBtn = document.getElementById('hb-send');
      const sentinel = document.getElementById('hb-history-sentinel');
      const counterpartyEl = document.getElementById('hb-counterparty');
      const threadSelect = document.getElementById('hb-thread-select');
      const clearBtn = document.getElementById('hb-clear-chat');

      let loading=false, beforeId=null, reachedTop=false, cooldown=false, channel=null, pusher=null, userCleared=false;

      function esc(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
      function fmt(ts){
        try {
          if (!ts) return '';
          // Parse timestamp as local time (server already in Asia/Manila timezone)
          const dateStr = ts.replace(' ','T');
          const date = new Date(dateStr);

          // Check if date is valid
          if (isNaN(date.getTime())) return ts;

          const now = new Date();
          const diffMs = now - date;
          const diffMins = Math.floor(diffMs / 60000);
          const diffHours = Math.floor(diffMs / 3600000);
          const diffDays = Math.floor(diffMs / 86400000);

          // Show relative time for recent messages
          if (diffMins < 1) return 'Just now';
          if (diffMins < 60) return diffMins + 'm ago';
          if (diffHours < 24) return diffHours + 'h ago';
          if (diffDays < 7) return diffDays + 'd ago';

          // For older messages, show formatted date
          const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          };
          return date.toLocaleString('en-US', options);
        } catch { return ts || ''; }
      }
      function setSentinel(t){ sentinel.textContent = t; }
      function setInputEnabled(on){ inputEl.disabled = !on; sendBtn.disabled = !on; }

      function addMsg(m, opts = {prepend:false}){
        const wrap = document.createElement('div');
        const mine = String(m.sender_id || '') === String(window.HB_CURRENT_USER_ID || '');
        wrap.className = `hb-msg ${mine ? 'mine' : 'their'}`;
        wrap.dataset.msgId = m.id || '';
        if (!mine){
          const from = document.createElement('div');
          from.className = 'hb-from';
          from.textContent = COUNTERPARTY;
          wrap.appendChild(from);
        }
        const txt = document.createElement('div');
        txt.innerHTML = esc(m.body || '');
        wrap.appendChild(txt);
        const meta = document.createElement('span');
        meta.className = 'hb-meta';
        meta.textContent = fmt(m.created_at || '');
        wrap.appendChild(meta);

        if (opts.prepend) { msgsEl.prepend(wrap); }
        else { msgsEl.appendChild(wrap); bodyEl.scrollTop = bodyEl.scrollHeight; }
      }

      async function loadMore(){
        if (loading || reachedTop || !THREAD_ID || userCleared) return;
        loading = true; setSentinel('Loading…');

        const qs = new URLSearchParams({ thread_id: THREAD_ID });
        if (beforeId) qs.set('before_id', beforeId);

        try {
          const res = await fetch(`/api/chat/fetch_messages.php?${qs}`, { credentials:'include' });
          const data = await res.json();

          const firstVisible = bodyEl.scrollHeight - bodyEl.scrollTop;
          if (Array.isArray(data.messages) && data.messages.length){
            data.messages.forEach(m => addMsg(m, {prepend:true}));
            beforeId = data.next_before_id || null;
            setSentinel('Scroll up for older messages');
            bodyEl.scrollTop = bodyEl.scrollHeight - firstVisible;
          } else {
            reachedTop = true; setSentinel('No more messages');
          }
        } catch { setSentinel('Failed to load'); }
        finally { loading = false; }
      }

      function resetConversationUI(){
        msgsEl.innerHTML = ''; beforeId = null; reachedTop = false;
        // Check if this thread was previously cleared in this session
        userCleared = sessionStorage.getItem(`chat_cleared_${THREAD_ID}`) === 'true';
        if (userCleared) {
          setSentinel('Chat cleared. Send a message or scroll up to reload.');
        } else {
          setSentinel(THREAD_ID ? 'Loading…' : 'Select a conversation to view messages');
        }
        setInputEnabled(!!THREAD_ID);
      }

      function subscribeRealtime(){
        if (!PUSHER_KEY || !THREAD_ID) return;
        if (!pusher) pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER, forceTLS: true });
        if (channel) { pusher.unsubscribe(channel.name); channel = null; }
        channel = pusher.subscribe(`thread-${THREAD_ID}`);
        channel.bind('new-message', (m) => addMsg(m));
      }

      // Load new messages when page becomes visible (user comes back online)
      async function refreshMessages(){
        if (!THREAD_ID || loading || userCleared) return;

        // Get the most recent message ID currently displayed
        const lastMsgEl = msgsEl.lastElementChild;
        if (!lastMsgEl) {
          // No messages loaded yet, do initial load
          await loadMore();
          return;
        }

        // Fetch any new messages since the last one we have
        try {
          const res = await fetch(`/api/chat/fetch_messages.php?thread_id=${THREAD_ID}`, { credentials:'include' });
          const data = await res.json();

          if (Array.isArray(data.messages) && data.messages.length){
            // Get IDs of messages we already have
            const existingIds = new Set(
              Array.from(msgsEl.children).map(el => el.dataset.msgId).filter(Boolean)
            );

            // Add only new messages that we don't have yet
            data.messages.forEach(m => {
              if (!existingIds.has(String(m.id))) {
                addMsg(m, {prepend:false});
              }
            });
          }
        } catch (err) {
          console.error('Failed to refresh messages:', err);
        }
      }

      bodyEl.addEventListener('scroll', () => {
        if (bodyEl.scrollTop < 40) {
          if (userCleared) {
            // User is manually scrolling up after clearing - allow reload
            userCleared = false;
            sessionStorage.removeItem(`chat_cleared_${THREAD_ID}`);
          }
          loadMore();
        }
      });

      function autogrow(){ inputEl.style.height = 'auto'; inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px'; }
      inputEl && inputEl.addEventListener('input', autogrow);

      formEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!THREAD_ID) return alert('Please select a conversation first.');
        const body = (inputEl.value || '').trim();
        if (!body) return;

        // Reset cleared flag when user sends a message
        if (userCleared) {
          userCleared = false;
          sessionStorage.removeItem(`chat_cleared_${THREAD_ID}`);
        }

        if (cooldown) return; cooldown = true; setInputEnabled(false);
        setTimeout(() => { cooldown = false; setInputEnabled(true); }, 1200);

        const fd = new FormData();
        fd.set('thread_id', THREAD_ID);
        fd.set('body', body);

        try {
          const res = await fetch('/api/chat/post_message.php', { method:'POST', body: fd, credentials:'include' });
          const data = await res.json();
          if (!data.ok) { alert(data.error || 'Send failed'); return; }
          inputEl.value = ''; autogrow();
        } catch { alert('Network error'); }
      });

      threadSelect.addEventListener('change', () => {
        const val = threadSelect.value || '0';
        const name = threadSelect.options[threadSelect.selectedIndex]?.dataset?.name || 'Tenant';
        THREAD_ID = parseInt(val, 10);
        COUNTERPARTY = name;
        counterpartyEl.textContent = COUNTERPARTY;
        resetConversationUI();
        if (THREAD_ID){ subscribeRealtime(); loadMore(); }
      });

      clearBtn.addEventListener('click', () => {
        if (!THREAD_ID) return;
        msgsEl.innerHTML = '';
        beforeId = null;
        reachedTop = false;
        userCleared = true;
        // Persist cleared state in sessionStorage (cleared for this session only)
        sessionStorage.setItem(`chat_cleared_${THREAD_ID}`, 'true');
        setSentinel('Chat cleared. Send a message or scroll up to reload.');
      });

      // Refresh messages when page becomes visible (user comes back online/switches tabs)
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden && THREAD_ID) {
          refreshMessages();
        }
      });

      // Periodic refresh every 10 seconds for active threads
      setInterval(() => {
        if (!document.hidden && THREAD_ID) {
          refreshMessages();
        }
      }, 10000);

      // Boot selection: QS > single thread > disabled
      (function bootThreadSelection(){
        const qsThread = String(window.HB_THREAD_ID_FROM_QS || '');
        const selectEl = threadSelect;

        function selectThreadById(idStr){
          const opt = Array.from(selectEl.options).find(o => o.value === idStr);
          if (!opt) return false;
          opt.selected = true;
          THREAD_ID = parseInt(idStr, 10);
          COUNTERPARTY = opt.dataset.name || 'Tenant';
          counterpartyEl.textContent = COUNTERPARTY;
          setInputEnabled(true);
          subscribeRealtime();
          loadMore();
          return true;
        }

        if (qsThread && selectThreadById(qsThread)) return;

        const realOptions = Array.from(selectEl.options).filter(o => o.value !== "0");
        if (realOptions.length === 1) { selectThreadById(realOptions[0].value); return; }

        counterpartyEl.textContent = COUNTERPARTY;
        setInputEnabled(false);
      })();
    })();

    // Owner map
    function initOwnerMap(){
    const el = document.getElementById('map');
    if (!el) return;

    const map = new google.maps.Map(el, {
      center: { lat: 14.5995, lng: 120.9842 },  // fallback
      zoom: 12,                                  // fallback
      gestureHandling: "greedy",
      scrollwheel: true,
      mapTypeControl: false,
      streetViewControl: true,
      fullscreenControl: true
    });

    // Ensure only one InfoWindow is open
    let activeInfoWindow = null;

    // Close any open bubble when clicking the map background
    map.addListener('click', () => {
      if (activeInfoWindow) { activeInfoWindow.close(); activeInfoWindow = null; }
    });

    const listings = <?= json_encode($listings); ?>;
    const bounds   = new google.maps.LatLngBounds();
    let markerCount = 0;
    let lastPos = null;

    (listings || []).forEach(item => {
      const lat = parseFloat(item.latitude), lng = parseFloat(item.longitude);
      if (Number.isNaN(lat) || Number.isNaN(lng)) return;

      const pos = { lat, lng };
      lastPos = pos; markerCount++;

      const marker = new google.maps.Marker({
        map,
        position: pos,
        title: item.title || '',
        icon: { url: "https://maps.google.com/mapfiles/ms/icons/green-dot.png" }
      });

      const info = new google.maps.InfoWindow({
        content: `
          <div>
            <h6>${(item.title || '').toString()}</h6>
            <p><strong>Address:</strong> ${(item.address || '').toString()}</p>
            <p><strong>Price:</strong> ₱${Number(item.price || 0).toLocaleString()}</p>
            <p><strong>Status:</strong> ${String(item.is_available) === '1' ? 'Available' : 'Occupied'}</p>
          </div>`
      });

      marker.addListener('click', () => {
        if (activeInfoWindow) activeInfoWindow.close();
        info.open(map, marker);
        activeInfoWindow = info;
      });

      bounds.extend(pos);
    });

    if (markerCount === 0) {
      // keep fallback center/zoom
      return;
    } else if (markerCount === 1 && lastPos) {
      map.setCenter(lastPos);
      map.setZoom(16);
    } else {
      map.fitBounds(bounds);
      google.maps.event.addListenerOnce(map, "bounds_changed", () => {
        if (map.getZoom() > 15) map.setZoom(15);
      });
    }
  }

  // expose for Google callback
  window.initOwnerMap = initOwnerMap;
</script>

<!-- Make sure your loader calls the callback -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= 'AIzaSyCrKcnAX9KOdNp_TNHwWwzbLSQodgYqgnU' ?>&callback=initOwnerMap" async defer></script>
<script>
(function(){
  const container = document.getElementById('hb-quick-replies');
  const inputEl   = document.getElementById('hb-input');
  const formEl    = document.getElementById('hb-send-form');
  const sendBtn   = document.getElementById('hb-send');
  if(!container || !inputEl || !formEl) return;

  // Hide if no thread (input disabled)
  const threadActive = !inputEl.hasAttribute('disabled');
  if (!threadActive) { container.style.display = 'none'; return; }

  const PROMPTS = ['Hi! Yes, it’s available. When would you like to view?', 'Viewing hours: Mon–Sat, 10am–6pm. Please share your preferred time.', 'Payment terms: 1 month advance + 1 month deposit. GCash/QR accepted.', 'Inclusions: Wi‑Fi and water included; electricity billed separately.', 'Please share your target move‑in date and number of occupants.'];

  PROMPTS.slice(0,5).forEach(text=>{
    const b=document.createElement('button');
    b.type='button';
    b.className='hb-qr-btn';
    b.textContent=text;
    b.addEventListener('click',()=>handleQuick(text));
    container.appendChild(b);
  });

  function handleQuick(text){
    inputEl.value = inputEl.value.trim()
      ? (inputEl.value.trim() + "
" + text)
      : text;

    if (sendBtn) sendBtn.disabled = true;
    formEl.requestSubmit ? formEl.requestSubmit() : formEl.submit();
  }
})();
</script>

<script src="darkmode.js"></script>
</body>
</html>
