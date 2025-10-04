<?php
session_start();
require 'mysql_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'tenant') {
    header("Location: LoginModule.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$first_name = ''; $last_name = '';

if ($user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM tbadmin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $last_name);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name']  = $last_name;
}
$thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$ownerName = null;
$counterparty_name = 'Owner';

/** Listings for map/search (stored lat/lng only) */
$listings = [];
$res = $conn->query("
  SELECT id, title, description, address, latitude, longitude, price, capacity,
         is_available, amenities, owner_id, property_photos,
         bedroom, unit_sqm, kitchen, kitchen_type, gender_specific, pets
  FROM tblistings
  WHERE is_archived = 0
    AND is_verified = 1
    AND (verification_status = 'approved' OR verification_status IS NULL)
  ORDER BY id DESC
");
while ($row = $res->fetch_assoc()) {
  // Decode property_photos JSON if present
  if (!empty($row['property_photos'])) {
    $row['property_photos_array'] = json_decode($row['property_photos'], true) ?: [];
  } else {
    $row['property_photos_array'] = [];
  }
  $listings[] = $row;
}

/** If thread selected, show owner display name + listing title in header */
if ($thread_id > 0 && $user_id) {
    $stmt = $conn->prepare("
        SELECT o.first_name, o.last_name, l.title
        FROM chat_threads t
        JOIN chat_participants pt ON pt.thread_id = t.id AND pt.user_id = ? AND pt.role = 'tenant'
        JOIN tblistings l         ON l.id = t.listing_id
        JOIN chat_participants po ON po.thread_id = t.id AND po.role = 'owner'
        JOIN tbadmin o            ON o.id = po.user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $user_id, $thread_id);

    // define variables first to avoid "Undefined variable" notices
    $ofn = $oln = $ltitle = null;

    $stmt->execute();
    $stmt->bind_result($ofn, $oln, $ltitle);

    if ($stmt->fetch()) {
        $ownerName = trim(($ofn ?? '').' '.($oln ?? ''));
        $counterparty_name = ($ownerName ?: 'Owner') . ((string)$ltitle !== '' ? " - $ltitle" : '');
    } else {
        // keep defaults if no row (invalid thread_id or not a participant)
        $ownerName = null;
        // $counterparty_name stays as "Owner"
    }
    $stmt->close();
}


/** Thread selector: show "OwnerName - Listing Title" */
$threads = [];
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT t.id   AS thread_id,
               l.title,
               o.first_name, o.last_name
        FROM chat_threads t
        JOIN chat_participants pt ON pt.thread_id = t.id AND pt.user_id = ? AND pt.role = 'tenant'
        JOIN chat_participants po ON po.thread_id = t.id AND po.role = 'owner'
        JOIN tbadmin o            ON o.id = po.user_id
        JOIN tblistings l         ON l.id = t.listing_id
        ORDER BY t.id DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['owner_name'] = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: 'Owner';
        $threads[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tenant Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="DashboardT.css?v=23" />
  <link rel="stylesheet" href="darkmode.css" />

  <style>
    /* Ensure price panel is always visible */
    #priceComparisonPanel {
      max-height: calc(100vh - 120px);
      overflow-y: auto;
    }

    /* Center the map within its container */
    #map {
      margin: 0 auto;
      max-width: 100%;
    }

    /* Improve search bar positioning */
    .mb-4 {
      position: relative;
      z-index: 1;
    }

    /* Responsive adjustments */
    @media (max-width: 991px) {
      #priceComparisonPanel {
        position: relative !important;
        top: 0 !important;
        margin-top: 1rem;
      }
    }

    /* Smooth scrolling for price panel */
    #priceComparisonPanel .card-body {
      overflow-y: auto;
      max-height: calc(100vh - 200px);
    }

    /* Ensure consistent button and select heights */
    #radiusSelect,
    #sortSelect,
    #amenitiesDropdown,
    #searchBtn {
      height: 38px !important;
      line-height: 1.5;
      padding-top: 0.375rem;
      padding-bottom: 0.375rem;
    }

    /* Fix amenities count color */
    #amenitiesCount {
      color: rgba(255, 255, 255, 0.7);
      font-size: 0.875rem;
    }
  </style>

</head>
<body class="dashboard-bg">
  <nav class="topFixedBar d-flex justify-content-between align-items-center px-4">
    <img src="Assets/Logo1.png" alt="HanapBahay Logo" class="logo" />
    <ul class="nav gap-4">
      <li class="nav-item"><a class="nav-link text-white fw-bold" href="DashboardT">Home</a></li>
      <li class="nav-item"><a class="nav-link text-white fw-bold" href="rental_request">Application Status</a></li>
      <li class="nav-item"><a class="nav-link text-white fw-bold" href="edit_profile_tenant">Settings</a></li>
    </ul>
    <a href="logout" class="btn btn-outline-light">Logout</a>
  </nav>

  <script>
    window.HB_CURRENT_USER_ID   = <?= (int)$user_id ?>;
    window.HB_CURRENT_USER_ROLE = "tenant";
    window.HB_THREAD_ID_FROM_QS = <?= (int)$thread_id ?>;
  </script>

  <main class="container-fluid py-5 mt-4">
    <div class="row g-4 justify-content-center">
      <!-- Main Content Area -->
      <div class="col-lg-8 col-xl-7">
    <!-- Search / amenities -->
    <!-- Search / amenities (UNIFIED) -->
    <div class="mb-4">
      <input id="searchBar" type="text" class="form-control mb-2" placeholder="Search a place, workplace, school, or keywords (e.g., 'Makati', 'JRU', 'pool')" />
    
      <div class="d-flex gap-2 mb-2">
        <select id="radiusSelect" class="form-select" style="max-width: 170px;">
          <option value="2">Within 2 km</option>
          <option value="5" selected>Within 5 km</option>
          <option value="10">Within 10 km</option>
          <option value="15">Within 15 km</option>
          <option value="20">Within 20 km</option>
        </select>
        <select id="sortSelect" class="form-select" style="max-width: 220px;">
          <option value="distance" selected>Sort by Distance</option>
          <option value="price_low">Sort by Price (Low)</option>
          <option value="price_high">Sort by Price (High)</option>
        </select>

        <!-- Amenities Dropdown -->
        <div class="dropdown" style="flex: 1;">
          <button class="btn btn-brown dropdown-toggle w-100" id="amenitiesDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Amenities <span id="amenitiesCount">(0 selected)</span>
          </button>
        <div class="dropdown-menu p-3 amenities-menu" aria-labelledby="amenitiesDropdown" style="max-height: 260px; overflow:auto; width:100%;">
          <div class="row g-2">
            <div class="col-12 col-sm-6">
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="wifi" id="amenityWifi"><label class="form-check-label" for="amenityWifi">Wi-Fi</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="parking" id="amenityParking"><label class="form-check-label" for="amenityParking">Parking</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="air conditioning" id="amenityAircon"><label class="form-check-label" for="amenityAircon">Air Conditioning</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="kitchen" id="amenityKitchen"><label class="form-check-label" for="amenityKitchen">Kitchen</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="laundry" id="amenityLaundry"><label class="form-check-label" for="amenityLaundry">Laundry</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="furnished" id="amenityFurnished"><label class="form-check-label" for="amenityFurnished">Furnished</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="bathroom" id="amenityBathroom"><label class="form-check-label" for="amenityBathroom">Bathroom</label></div>
            </div>
            <div class="col-12 col-sm-6">
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="elevator" id="amenityElevator"><label class="form-check-label" for="amenityElevator">Elevator</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="security" id="amenitySecurity"><label class="form-check-label" for="amenitySecurity">Security/CCTV</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="balcony" id="amenityBalcony"><label class="form-check-label" for="amenityBalcony">Balcony</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="gym" id="amenityGym"><label class="form-check-label" for="amenityGym">Gym</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="pool" id="amenityPool"><label class="form-check-label" for="amenityPool">Pool</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="pet friendly" id="amenityPets"><label class="form-check-label" for="amenityPets">Pet Friendly</label></div>
              <div class="form-check"><input class="form-check-input amenity-item" type="checkbox" value="sink" id="amenitySink"><label class="form-check-label" for="amenitySink">Sink</label></div>
            </div>
          </div>
          <div class="d-flex justify-content-end pt-2">
            <button type="button" class="btn btn-sm btn-light me-2" id="amenitiesClear">Clear</button>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="dropdown">Done</button>
          </div>
        </div>
        </div>

        <button class="btn btn-primary" id="searchBtn" style="min-width: 150px;">Find Properties</button>
      </div>
    </div>

    <!-- Map -->
    <div id="map" class="mb-5"></div>
    
    <!-- Unified Search Results Panel (only shown for geocoded place search) -->
    <div id="resultsPanel" style="display:none" class="mt-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Nearest Available Properties</h5></div>
        <div class="card-body" id="resultsContent"></div>
      </div>
    </div>
      </div>

      <!-- Price Comparison Panel (Right Side) -->
      <div class="col-lg-4 col-xl-3">
        <div id="priceComparisonPanel" style="position: sticky; top: 100px;">
          <div class="card shadow-sm">
            <div class="card-header" style="background: #8B4513; color: white;">
              <h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Price Analysis</h6>
            </div>
            <div class="card-body">
              <div id="priceAnalysisContent">
                <div class="text-center text-muted py-4">
                  <i class="bi bi-info-circle fs-3"></i>
                  <p class="mt-2 small">Click on a property to see price analysis</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- ============ FLOATING CHAT WIDGET ============ -->
  <div id="hb-chat-widget" class="hb-chat-widget">
    <div id="hb-chat-header" class="hb-chat-header-bar">
      <span><i class="bi bi-chat-dots"></i> Messages</span>
      <button id="hb-toggle-btn" class="hb-btn-ghost">_</button>
    </div>
    <div id="hb-chat-body-container" class="hb-chat-body-container">
      <div class="d-flex align-items-center justify-content-between mb-2 px-2 pt-2">
        <select id="hb-thread-select" class="form-select form-select-sm" style="min-width:240px;">
          <option value="0" selected>Select a conversation…</option>
          <?php foreach ($threads as $t): ?>
            <?php
              $label = ($t['owner_name'] ?: 'Owner') . ' — ' . ($t['title'] ?: 'Listing');
              $dataName = htmlspecialchars($t['owner_name'] ?: 'Owner', ENT_QUOTES);
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
        

<!-- Quick Replies (Tenant) -->
<div id="hb-quick-replies" class="hb-quick-replies" aria-label="Suggested questions"></div>

</div>
        <form id="hb-send-form" class="hb-chat-input" autocomplete="off">
          <textarea id="hb-input" rows="1" placeholder="Type a message…" required <?= $thread_id ? '' : 'disabled' ?>></textarea>
          <button id="hb-send" type="submit" class="hb-btn" <?= $thread_id ? '' : 'disabled' ?>>Send</button>
        </form>
      </div>
    </div>
  </div>
  <!-- ============================================== -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://js.pusher.com/8.2/pusher.min.js"></script>

  <script>
    // ---------- Collapse/expand chat widget (restored) ----------
    document.addEventListener("DOMContentLoaded", () => {
      const widget = document.getElementById("hb-chat-widget");
      const toggleBtn = document.getElementById("hb-toggle-btn");
      const header = document.getElementById("hb-chat-header");

      if (header) {
        header.addEventListener("click", (e)=>{
          if (e.target && e.target.id === 'hb-toggle-btn') return; // button handles itself
          widget.classList.toggle("collapsed");
          if (toggleBtn) toggleBtn.textContent = widget.classList.contains("collapsed") ? "▴" : "_";
        });
      }
      if (toggleBtn) {
        toggleBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          widget.classList.toggle("collapsed");
          toggleBtn.textContent = widget.classList.contains("collapsed") ? "▴" : "_";
        });
      }
    });

    // ---------- Chat logic ----------
    (() => {
      let THREAD_ID    = <?= (int)$thread_id ?>;
      let COUNTERPARTY = <?= json_encode($counterparty_name) ?>;

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
          const from = document.createElement('div'); from.className = 'hb-from'; from.textContent = COUNTERPARTY; wrap.appendChild(from);
        }
        const txt = document.createElement('div'); txt.innerHTML = esc(m.body || ''); wrap.appendChild(txt);
        const meta = document.createElement('span'); meta.className = 'hb-meta'; meta.textContent = fmt(m.created_at || ''); wrap.appendChild(meta);

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
          } else { reachedTop = true; setSentinel('No more messages'); }
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
        if (!THREAD_ID) return;
        if (!pusher) pusher = new Pusher("c9a924289093535f51f9", { cluster: "ap1", forceTLS: true });
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
        const name = threadSelect.options[threadSelect.selectedIndex]?.dataset?.name || 'Owner';
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

      // Boot
      (function boot(){
        const selectEl = threadSelect;
        const qsThread = String(window.HB_THREAD_ID_FROM_QS || '');
        function selectThreadById(idStr){
          const opt = Array.from(selectEl.options).find(o => o.value === idStr);
          if (!opt) return false;
          opt.selected = true;
          THREAD_ID = parseInt(idStr, 10);
          COUNTERPARTY = opt.dataset.name || 'Owner';
          counterpartyEl.textContent = COUNTERPARTY;
          setInputEnabled(true); subscribeRealtime(); loadMore();
          return true;
        }
        if (qsThread && selectThreadById(qsThread)) return;
        const realOpts = Array.from(selectEl.options).filter(o => o.value !== "0");
        if (realOpts.length === 1) { selectThreadById(realOpts[0].value); return; }
        counterpartyEl.textContent = COUNTERPARTY; setInputEnabled(false);
      })();
    })();

    // ---------- Quick Replies Logic ----------
    (() => {
      const quickRepliesEl = document.getElementById('hb-quick-replies');
      let quickReplies = [];

      // Load quick replies from server
      async function loadQuickReplies() {
        try {
          const res = await fetch('/api/chat/get_quick_replies.php', { credentials: 'include' });
          const data = await res.json();
          if (data.ok && Array.isArray(data.quick_replies)) {
            quickReplies = data.quick_replies;
            renderQuickReplies();
          }
        } catch (e) {
          console.warn('Failed to load quick replies:', e);
        }
      }

      // Render quick reply buttons
      function renderQuickReplies() {
        if (!quickRepliesEl) return;

        quickRepliesEl.innerHTML = '';

        // Only show quick replies when a thread is selected and user is tenant
        const threadId = parseInt(document.getElementById('hb-thread-select')?.value || '0', 10);
        if (!threadId || window.HB_CURRENT_USER_ROLE !== 'tenant') return;

        quickReplies.forEach(reply => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'hb-qr-btn';
          btn.textContent = reply.message;
          btn.title = reply.message;
          btn.addEventListener('click', () => {
            const inputEl = document.getElementById('hb-input');
            const formEl = document.getElementById('hb-send-form');
            if (inputEl && formEl) {
              inputEl.value = reply.message;
              inputEl.focus();
              // Auto-send the quick reply
              formEl.dispatchEvent(new Event('submit'));
            }
          });
          quickRepliesEl.appendChild(btn);
        });
      }

      // Update quick replies visibility when thread changes
      const threadSelect = document.getElementById('hb-thread-select');
      if (threadSelect) {
        threadSelect.addEventListener('change', renderQuickReplies);
      }

      // Load quick replies on page load
      loadQuickReplies();
    })();
  </script>

  <script>
    // ---------- Map + Unified Search (place OR text) ----------
    const listings = <?= json_encode($listings); ?>;

    let map, geocoder, directionsService, directionsRenderer;
    let activeRouteListingId = null;
    let lastOriginLatLng = null;
    let lastOriginAddress = '';
    const commuteDetails = new Map();
    const crowDistanceFallback = new Map();

    let markers = [];
    let activeInfoWindow = null;
    let workplaceMarker = null;
    let LAST_NEARBY = [];

    function formatDistanceMeters(meters) {
      if (!Number.isFinite(meters)) return '';
      if (meters >= 1000) return `${(meters / 1000).toFixed(1)} km`;
      return `${Math.round(meters)} m`;
    }

    function escapeHtml(value) {
      return (value || '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      })[ch] || ch);
    }

    function buildInfoContent(item) {
      const key = String(item.id || '');
      const commute = commuteDetails.get(key);
      const fallback = crowDistanceFallback.get(key);
      const safeTitle = escapeHtml(item.title || '');
      const safeAddress = escapeHtml(item.address || '');
      const priceLabel = '&#8369;' + Number(item.price || 0).toLocaleString();
      const statusLabel = String(item.is_available) === '1' ? 'Available' : 'Occupied';
      let commuteHtml = '';

      if (commute && (commute.distanceText || commute.durationText)) {
        const parts = [];
        if (commute.distanceText) parts.push(escapeHtml(commute.distanceText));
        if (commute.durationText) parts.push(escapeHtml(commute.durationText));
        if (parts.length) {
          commuteHtml = `<p class="hb-commute"><strong>Commute (driving):</strong> ${parts.join(' \u2022 ')}</p>`;
        }
      } else if (fallback) {
        commuteHtml = `<p class="hb-commute"><strong>Approx distance:</strong> ${escapeHtml(fallback)}</p>`;
      }

      return `
            <div class="hb-map-popup">
              <h6>${safeTitle}</h6>
              <p><strong>Address:</strong> ${safeAddress}</p>
              <p><strong>Price:</strong> ${priceLabel}</p>
              <p><strong>Status:</strong> ${statusLabel}</p>
              ${commuteHtml}
              <div class="d-flex gap-2 mt-2">
                <a href="property_details?id=${item.id}&ret=DashboardT" class="btn btn-sm btn-primary">More Details</a>
                <a href="start_chat?listing_id=${item.id}" class="btn btn-sm btn-outline-secondary">Message Owner</a>
              </div>
            </div>`;
    }

    // Price Comparison Panel Functions
    async function loadPriceComparison(item) {
      const panel = document.getElementById('priceComparisonPanel');
      const content = document.getElementById('priceAnalysisContent');

      if (!panel || !content) return;

      // Show panel with loading state
      panel.style.display = 'block';
      content.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 small text-muted">Analyzing price...</p>
        </div>`;

      try {
        // Prepare ML input data
        const addressParts = (item.address || '').split(',');
        const location = addressParts[addressParts.length - 1]?.trim() || 'Unknown';

        const mlInput = {
          Capacity: parseInt(item.capacity) || 1,
          Bedroom: parseInt(item.bedroom) || 1,
          unit_sqm: parseFloat(item.unit_sqm) || 20,
          cap_per_bedroom: Math.round((parseInt(item.capacity) || 1) / Math.max(parseInt(item.bedroom) || 1, 1) * 100) / 100,
          Type: derivePropertyType(item.title || ''),
          Kitchen: item.kitchen || 'Yes',
          'Kitchen type': item.kitchen_type || 'Private',
          'Gender specific': item.gender_specific || 'Mixed',
          Pets: item.pets || 'Allowed',
          Location: location
        };

        // Auto-detect correct API path for localhost vs production
        const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const apiPath = isLocalhost ? '/public_html/api/ml_suggest_price.php' : '/api/ml_suggest_price.php';

        const response = await fetch(apiPath, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ inputs: [mlInput] })
        });

        const data = await response.json();

        if (data.prediction) {
          const actualPrice = parseFloat(item.price) || 0;
          const mlPrice = data.prediction;
          const diffPercent = ((actualPrice - mlPrice) / mlPrice) * 100;

          let status, statusClass, statusIcon, message;
          if (diffPercent <= -10) {
            status = 'great';
            statusClass = 'success';
            statusIcon = 'bi-check-circle-fill';
            message = 'Great Deal!';
          } else if (diffPercent <= 10) {
            status = 'fair';
            statusClass = 'info';
            statusIcon = 'bi-info-circle-fill';
            message = 'Fair Price';
          } else {
            status = 'high';
            statusClass = 'warning';
            statusIcon = 'bi-exclamation-triangle-fill';
            message = 'Above Market';
          }

          const photosArray = item.property_photos_array || [];
          const mainPhoto = photosArray.length > 0 ? photosArray[0] : 'https://via.placeholder.com/300x150?text=No+Image';

          content.innerHTML = `
            <div class="mb-3">
              <img src="${escapeHtml(mainPhoto)}" alt="${escapeHtml(item.title || '')}"
                   style="width:100%; height:120px; object-fit:cover; border-radius:8px;">
            </div>

            <h6 class="mb-2 text-truncate" title="${escapeHtml(item.title || '')}">${escapeHtml(item.title || 'Property')}</h6>

            <div class="alert alert-${statusClass} py-2 px-3 mb-3">
              <div class="d-flex align-items-center gap-2">
                <i class="bi ${statusIcon}"></i>
                <strong>${message}</strong>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted small">Actual Price:</span>
                <strong class="text-primary">₱${actualPrice.toLocaleString()}</strong>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted small">ML Predicted:</span>
                <strong>₱${mlPrice.toLocaleString()}</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted small">Difference:</span>
                <span class="${diffPercent > 0 ? 'text-danger' : 'text-success'} fw-bold">
                  ${diffPercent > 0 ? '+' : ''}${diffPercent.toFixed(1)}%
                </span>
              </div>
            </div>

            <div class="small text-muted mb-3">
              <div><i class="bi bi-people"></i> ${item.capacity || 1} capacity</div>
              <div><i class="bi bi-door-closed"></i> ${item.bedroom || 1} bedroom</div>
              <div><i class="bi bi-rulers"></i> ${parseFloat(item.unit_sqm || 20).toFixed(1)} sqm</div>
            </div>

            <div class="d-grid gap-2">
              <a href="property_details?id=${item.id}&ret=DashboardT" class="btn btn-sm btn-primary">
                <i class="bi bi-eye"></i> View Details
              </a>
              <a href="start_chat?listing_id=${item.id}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chat-dots"></i> Message Owner
              </a>
            </div>

            <div class="mt-3 p-2 bg-light rounded small text-muted">
              <i class="bi bi-lightbulb"></i> AI prediction based on property features and market data
            </div>`;
        } else {
          throw new Error('No prediction data');
        }
      } catch (error) {
        console.error('Price analysis error:', error);
        content.innerHTML = `
          <div class="alert alert-warning py-2 px-3">
            <i class="bi bi-exclamation-triangle"></i>
            <small>Unable to load price analysis</small>
          </div>
          <div class="d-grid gap-2 mt-3">
            <a href="property_details?id=${item.id}&ret=DashboardT" class="btn btn-sm btn-primary">View Details</a>
          </div>`;
      }
    }

    function derivePropertyType(title) {
      const lower = (title || '').toLowerCase();
      if (lower.includes('studio')) return 'Studio';
      if (lower.includes('apartment')) return 'Apartment';
      if (lower.includes('condo')) return 'Condominium';
      if (lower.includes('house') || lower.includes('boarding')) return 'Boarding House';
      return 'Apartment';
    }

    function resetAllCommuteDisplays() {
      markers.forEach(entry => entry.info.setContent(buildInfoContent(entry.item)));
    }

    function clearRoute() {
      if (activeRouteListingId !== null) {
        commuteDetails.delete(String(activeRouteListingId));
      }
      activeRouteListingId = null;
      if (directionsRenderer) {
        try {
          directionsRenderer.setDirections({ routes: [] });
        } catch (err) {
          directionsRenderer.setDirections(null);
        }
      }
      resetAllCommuteDisplays();
    }

    function clearCommuteState() {
      commuteDetails.clear();
      crowDistanceFallback.clear();
      lastOriginLatLng = null;
      lastOriginAddress = '';
      LAST_NEARBY = [];
      clearRoute();
      resetAllCommuteDisplays();
    }

    function drawRouteTo(entry) {
      if (!directionsService || !directionsRenderer || !lastOriginLatLng) return;
      if (!entry || !entry.marker) return;
      const request = {
        origin: lastOriginLatLng,
        destination: entry.marker.getPosition(),
        travelMode: google.maps.TravelMode.DRIVING
      };
      directionsService.route(request, (result, status) => {
        if (status === 'OK' && result && result.routes && result.routes[0] && result.routes[0].legs && result.routes[0].legs[0]) {
          const leg = result.routes[0].legs[0];
          const key = String(entry.item.id || '');
          commuteDetails.set(key, {
            distanceText: leg.distance?.text || null,
            durationText: leg.duration?.text || null
          });
          directionsRenderer.setDirections(result);
          activeRouteListingId = entry.item.id;
          entry.info.setContent(buildInfoContent(entry.item));
          if (activeInfoWindow === entry.info) {
            activeInfoWindow.close();
            entry.info.open(map, entry.marker);
          }
          applyAmenityFiltersInWorkplaceMode();
        } else {
          console.warn('Directions request failed:', status);
        }
      });
    }

    function initMap() {
      map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 14.5995, lng: 120.9842 },
        zoom: 12,
        gestureHandling: 'greedy',
        scrollwheel: true,
        mapTypeControl: false,
        streetViewControl: true,
        fullscreenControl: true,
      });

      geocoder = new google.maps.Geocoder();
      directionsService = new google.maps.DirectionsService();
      directionsRenderer = new google.maps.DirectionsRenderer({
        map,
        suppressMarkers: true,
        polylineOptions: {
          strokeColor: '#8B4513',
          strokeOpacity: 0.75,
          strokeWeight: 4
        }
      });

      map.addListener('click', () => {
        if (activeInfoWindow) {
          activeInfoWindow.close();
          activeInfoWindow = null;
        }
      });

      const bounds = new google.maps.LatLngBounds();

      (listings || []).forEach(item => {
        const lat = parseFloat(item.latitude);
        const lng = parseFloat(item.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const pos = { lat, lng };
        const marker = new google.maps.Marker({
          map,
          position: pos,
          title: item.title || '',
          icon: {
            url: (String(item.is_available) === '1')
              ? 'https://maps.google.com/mapfiles/ms/icons/green-dot.png'
              : 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
          }
        });

        const info = new google.maps.InfoWindow({
          content: buildInfoContent(item)
        });

        const entry = { marker, item, info };

        marker.addListener('click', () => {
          if (activeInfoWindow) activeInfoWindow.close();
          info.open(map, marker);
          activeInfoWindow = info;

          // Auto-center map on selected property with smooth animation
          map.panTo(marker.getPosition());

          // Adjust zoom if needed for better view
          if (map.getZoom() < 14) {
            map.setZoom(14);
          }

          if (workplaceMarker && lastOriginLatLng) {
            drawRouteTo(entry);
          }
          // Load price comparison
          loadPriceComparison(item);
        });
        markers.push(entry);
        bounds.extend(pos);
      });

      if (!bounds.isEmpty()) map.fitBounds(bounds);

      initUnifiedSearch();
      wireFilters();
    }

    function getSelectedAmenities() {
      return Array.from(document.querySelectorAll('.amenity-item'))
        .filter(c => c.checked)
        .map(c => (c.value || '').toLowerCase());
    }

    function textMatches(item, q) {
      if (!q) return true;
      q = q.toLowerCase();
      return (
        (item.title || '').toLowerCase().includes(q) ||
        (item.address || '').toLowerCase().includes(q) ||
        String(item.price || '').toLowerCase().includes(q)
      );
    }

    function amenitiesMatch(item, selectedAmenities) {
      if (!selectedAmenities || selectedAmenities.length === 0) return true;
      const amenStr = (item.amenities || '').toLowerCase();
      return selectedAmenities.every(a => amenStr.includes(a));
    }

    function applyGeneralFilter() {
      const q = (document.getElementById('searchBar')?.value || '').toLowerCase();
      const selectedAmenities = getSelectedAmenities();
      const bounds = new google.maps.LatLngBounds();
      const resultsPanel = document.getElementById('resultsPanel');
      if (resultsPanel) resultsPanel.style.display = 'none';
      if (!workplaceMarker) clearRoute();

      markers.forEach(({ marker, item, info }) => {
        const visible = textMatches(item, q) && amenitiesMatch(item, selectedAmenities);
        marker.setVisible(visible);
        if (!visible && activeInfoWindow === info) {
          info.close();
          activeInfoWindow = null;
        }
        if (visible) bounds.extend(marker.getPosition());
      });

      if (!bounds.isEmpty()) map.fitBounds(bounds);
    }

    function displayWorkplaceResults(properties, workplaceAddress) {
      const panel = document.getElementById('resultsPanel');
      const content = document.getElementById('resultsContent');
      if (!panel || !content) return;

      if (!properties.length) {
        content.innerHTML = '<p class="text-muted mb-0">No available properties found within the selected radius.</p>';
      } else {
        const safeOrigin = workplaceAddress ? escapeHtml(workplaceAddress) : '';
        const note = workplaceAddress
          ? `<p class="text-muted small mb-3">Routes use <strong>${safeOrigin}</strong> as the origin.</p>`
          : '';

        const cards = properties.map(p => {
          const key = String(p.id || '');
          const commute = commuteDetails.get(key);
          const fallback = crowDistanceFallback.get(key) || (Number.isFinite(p.distance) ? formatDistanceMeters(p.distance) : null);
          const distanceLabel = commute && commute.distanceText ? escapeHtml(commute.distanceText) : (fallback ? escapeHtml(fallback) : 'Distance unavailable');
          const durationLabel = commute && commute.durationText ? escapeHtml(commute.durationText) : null;
          const metaHtml = durationLabel
          ? `<p class="commute-meta mb-2">Driving estimate: ${distanceLabel} &bull; ${durationLabel}</p>`
          : '';
          const safeTitle = escapeHtml(p.title || 'Untitled Property');
          const safeAddress = escapeHtml(p.address || '');
          const priceLabel = '&#8369;' + Number(p.price || 0).toLocaleString() + '/month';
          const isActive = String(activeRouteListingId || '') === String(p.id || '');
          const cardClasses = `results-item mb-3${isActive ? ' route-active' : ''}`;

          // Property photos
          let photosHtml = '';
          if (p.property_photos_array && p.property_photos_array.length > 0) {
            const firstPhoto = escapeHtml(p.property_photos_array[0]);
            photosHtml = `<div class="mb-2"><img src="${firstPhoto}" alt="${safeTitle}" style="width:100%; height:160px; object-fit:cover; border-radius:8px;"></div>`;
          }

          return `
            <div class="${cardClasses}" data-listing-id="${p.id}">
              ${photosHtml}
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h6 class="mb-1">${safeTitle}</h6>
                <span class="distance-badge">${distanceLabel}</span>
              </div>
              <p class="text-muted mb-1">${safeAddress}</p>
              ${metaHtml}
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">${priceLabel}</span>
                <div class="btn-group btn-group-sm">
                  <a href="property_details?id=${p.id}&ret=DashboardT" class="btn btn-outline-primary">View Details</a>
                  <a href="start_chat?listing_id=${p.id}" class="btn btn-primary">Message Owner</a>
                </div>
              </div>
            </div>`;
        }).join('');

        content.innerHTML = note + cards;

        content.querySelectorAll('[data-listing-id]').forEach(card => {
          card.addEventListener('click', event => {
            if (event.target.closest('a')) return;
            const listingId = card.dataset.listingId;
            if (!listingId) return;
            const entry = markers.find(m => String(m.item.id) === String(listingId));
            if (!entry) return;

            // Trigger marker click which handles centering
            google.maps.event.trigger(entry.marker, 'click');

            if (workplaceMarker && lastOriginLatLng) {
              drawRouteTo(entry);
            }
          });
        });
      }

      panel.style.display = 'block';
    }

    function geocodeAddress(address) {
      return new Promise((resolve, reject) => {
        geocoder.geocode({ address: address + ', Philippines' }, (results, status) => {
          if (status === 'OK' && results && results[0]) resolve(results[0]);
          else reject(status);
        });
      });
    }

    function initUnifiedSearch() {
      const input = document.getElementById('searchBar');
      const searchBtn = document.getElementById('searchBtn');

      if (google.maps.places && google.maps.places.Autocomplete) {
        const ac = new google.maps.places.Autocomplete(input, {
          fields: ['formatted_address', 'geometry'],
          componentRestrictions: { country: 'ph' }
        });
        ac.addListener('place_changed', () => {
          const place = ac.getPlace();
          if (place && place.geometry && place.geometry.location) {
            runWorkplaceSearch(place.formatted_address || input.value, place.geometry.location);
          } else {
            doUnifiedSearch();
          }
        });
      }

      input.addEventListener('input', () => {
        if (!input.value.trim()) {
          if (workplaceMarker) {
            workplaceMarker.setMap(null);
            workplaceMarker = null;
          }
          clearCommuteState();
          applyGeneralFilter();
        }
      });

      searchBtn?.addEventListener('click', doUnifiedSearch);
    }

    async function doUnifiedSearch() {
      const q = (document.getElementById('searchBar')?.value || '').trim();
      if (!q) {
        if (workplaceMarker) {
          workplaceMarker.setMap(null);
          workplaceMarker = null;
        }
        clearCommuteState();
        applyGeneralFilter();
        return;
      }

      try {
        const result = await geocodeAddress(q);
        const loc = result.geometry.location;
        runWorkplaceSearch(result.formatted_address || q, loc);
      } catch (err) {
        console.warn('Geocode failed:', err);
        if (workplaceMarker) {
          workplaceMarker.setMap(null);
          workplaceMarker = null;
        }
        clearCommuteState();
        applyGeneralFilter();
      }
    }

    function runWorkplaceSearch(address, locLatLng) {
      const radiusKm = parseFloat(document.getElementById('radiusSelect')?.value || '5');
      const sortBy = document.getElementById('sortSelect')?.value || 'distance';
      const selectedAmenities = getSelectedAmenities();

      if (workplaceMarker) workplaceMarker.setMap(null);
      const wpLL = new google.maps.LatLng(locLatLng.lat(), locLatLng.lng());
      workplaceMarker = new google.maps.Marker({
        map,
        position: wpLL,
        title: 'Workplace/School',
        icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' }
      });

      commuteDetails.clear();
      crowDistanceFallback.clear();
      clearRoute();

      const maxM = radiusKm * 1000;
      const nearby = [];
      markers.forEach(({ marker, item }) => {
        if (String(item.is_available) !== '1') return;
        const idKey = String(item.id);
        const distM = google.maps.geometry.spherical.computeDistanceBetween(wpLL, marker.getPosition());
        if (distM <= maxM) {
          const approxText = formatDistanceMeters(distM);
          crowDistanceFallback.set(idKey, approxText);
          commuteDetails.delete(idKey);
          nearby.push({ ...item, distance: distM, marker });
        }
      });

      if (sortBy === 'distance') {
        nearby.sort((a, b) => a.distance - b.distance);
      } else if (sortBy === 'price_low') {
        nearby.sort((a, b) => (parseFloat(a.price) || 0) - (parseFloat(b.price) || 0));
      } else if (sortBy === 'price_high') {
        nearby.sort((a, b) => (parseFloat(b.price) || 0) - (parseFloat(a.price) || 0));
      }

      LAST_NEARBY = nearby;

      const filtered = nearby.filter(p => amenitiesMatch(p, selectedAmenities));
      const idSet = new Set(filtered.map(p => String(p.id)));
      markers.forEach(({ marker, item }) => {
        marker.setVisible(idSet.has(String(item.id)));
      });

      map.setCenter(wpLL);
      map.setZoom(15);

      lastOriginLatLng = wpLL;
      lastOriginAddress = address || '';

      resetAllCommuteDisplays();
      displayWorkplaceResults(filtered, address);
    }

    function applyAmenityFiltersInWorkplaceMode() {
      if (!LAST_NEARBY.length) return;
      const selectedAmenities = getSelectedAmenities();
      const filtered = LAST_NEARBY.filter(p => amenitiesMatch(p, selectedAmenities));
      const idSet = new Set(filtered.map(p => String(p.id)));
      markers.forEach(({ marker, item }) => {
        marker.setVisible(idSet.has(String(item.id)));
      });
      if (activeRouteListingId && !idSet.has(String(activeRouteListingId))) {
        clearRoute();
      }
      displayWorkplaceResults(filtered, lastOriginAddress);
    }

    function wireFilters() {
      const clearBtn = document.getElementById('amenitiesClear');
      const checks = document.querySelectorAll('.amenity-item');
      const countEl = document.getElementById('amenitiesCount');

      function updateCountAndFilter() {
        const n = Array.from(checks).filter(c => c.checked).length;
        if (countEl) countEl.textContent = `(${n} selected)`;

        if (workplaceMarker) {
          applyAmenityFiltersInWorkplaceMode();
        } else {
          applyGeneralFilter();
        }
      }

      checks.forEach(c => c.addEventListener('change', updateCountAndFilter));
      clearBtn && clearBtn.addEventListener('click', () => {
        checks.forEach(c => c.checked = false);
        updateCountAndFilter();
      });
      updateCountAndFilter();
    }

    window.initMap = initMap;
  </script>
  <!-- Google Maps JS API with Places + callback -->
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode('AIzaSyCrKcnAX9KOdNp_TNHwWwzbLSQodgYqgnU') ?>&libraries=places,geometry&callback=initMap" async defer></script>
<script>
(function(){
  const container = document.getElementById('hb-quick-replies');
  const inputEl   = document.getElementById('hb-input');
  const formEl    = document.getElementById('hb-send-form');
  const sendBtn   = document.getElementById('hb-send');
  if (!container || !inputEl || !formEl) return;

  // Prompts for TENANT (use owner set in DashboardUO.php)
  const PROMPTS = [
    "What services do you offer?",
    "Is this property still available?",
    "Can I schedule a viewing?",
    "What are the payment terms?",
    "Are utilities and amenities included?"
  ];

  // 1) Render once (no early return)
  if (!container.dataset.rendered) {
    PROMPTS.forEach((text)=>{
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'hb-qr-btn';
      b.textContent = text;
      b.addEventListener('click', () => handleQuick(text));
      container.appendChild(b);
    });
    container.dataset.rendered = '1';
  }

  // 2) Show/hide chips based on whether a thread is active (input enabled)
  function syncVisibility(){
    // Hide when the textarea is disabled (no active thread), show when enabled
    container.style.display = inputEl.hasAttribute('disabled') ? 'none' : '';
  }
  syncVisibility();

  // Watch for the 'disabled' attribute to change when you click "Message Owner"
  const mo = new MutationObserver(syncVisibility);
  mo.observe(inputEl, { attributes: true, attributeFilter: ['disabled'] });

  // 3) Click -> fill; only auto-send when thread is active
  function handleQuick(text){
    inputEl.value = inputEl.value.trim()
      ? (inputEl.value.trim() + "\n" + text)
      : text;

    // Only submit if input is enabled (thread active)
    if (!inputEl.hasAttribute('disabled')) {
      if (sendBtn) sendBtn.disabled = true;
      formEl.requestSubmit ? formEl.requestSubmit() : formEl.submit();
    } else {
      // Optional: focus so the user sees it filled while waiting for thread
      inputEl.focus();
    }
  }
})();
</script>

<script src="darkmode.js"></script>
</body>
</html>