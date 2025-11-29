<?php
include '_smm_header.php'; // Using SMM Header for consistent theme

$user_id = $_SESSION['user_id'];
$error = ''; $success = '';

// --- 1. CREATE NEW TICKET ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_ticket'])) {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    if ($subject && $message) {
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO tickets (user_id, subject, status, created_at) VALUES (?, ?, 'pending', NOW())")->execute([$user_id, $subject]);
            $ticket_id = $db->lastInsertId();
            $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)")->execute([$ticket_id, $message]);
            $db->commit();
            $success = "Ticket #$ticket_id created successfully!";
            // Redirect to avoid resubmission
            echo "<script>window.location.href='tickets.php?id=$ticket_id';</script>";
        } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
    }
}

// --- 2. REPLY TO TICKET ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_ticket'])) {
    $tid = (int)$_POST['ticket_id'];
    $msg = sanitize($_POST['message']);
    if ($msg) {
        $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)")->execute([$tid, $msg]);
        $db->prepare("UPDATE tickets SET status='pending', updated_at=NOW() WHERE id=? AND user_id=?")->execute([$tid, $user_id]);
        // Refresh
        echo "<script>window.location.href='tickets.php?id=$tid';</script>";
    }
}

// Fetch All Tickets
$tickets = $db->prepare("SELECT * FROM tickets WHERE user_id=? ORDER BY updated_at DESC");
$tickets->execute([$user_id]);
$all_tickets = $tickets->fetchAll();

// Fetch Active Ticket Data
$active_ticket = null;
$messages = [];
if (isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $t_stmt = $db->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
    $t_stmt->execute([$tid, $user_id]);
    $active_ticket = $t_stmt->fetch();
    
    if ($active_ticket) {
        $msgs_stmt = $db->prepare("SELECT * FROM ticket_messages WHERE ticket_id=? ORDER BY created_at ASC");
        $msgs_stmt->execute([$tid]);
        $messages = $msgs_stmt->fetchAll();
    }
}
?>

<style>
/* --- THEME VARIABLES --- */
:root {
    --primary: #4F46E5;
    --secondary: #8B5CF6;
    --bg-body: #F8FAFC;
    --card-bg: #FFFFFF;
    --text-main: #1E293B;
    --text-sub: #64748B;
    --border: #E2E8F0;
    --radius: 16px;
    --shadow-card: 0 10px 30px -5px rgba(0,0,0,0.05);
}

body { background: var(--bg-body); font-family: 'Inter', sans-serif; color: var(--text-main); height: 100vh; overflow: hidden; }

/* --- LAYOUT --- */
.support-container {
    max-width: 1200px; margin: 20px auto; display: grid; grid-template-columns: 350px 1fr;
    gap: 25px; height: calc(100vh - 140px); /* Adjust for header */
}

/* --- LEFT SIDE: TICKET LIST --- */
.ticket-sidebar {
    background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border);
    display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-card);
}

.sidebar-header {
    padding: 20px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center; background: #fff;
}
.sidebar-header h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); }

.btn-new {
    background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; border: none;
    padding: 8px 15px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 5px; transition: 0.2s;
}
.btn-new:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }

.ticket-list {
    flex: 1; overflow-y: auto; padding: 10px; background: #f8fafc;
}

.ticket-item {
    display: block; text-decoration: none; background: #fff; padding: 15px;
    border-radius: 12px; margin-bottom: 10px; border: 1px solid transparent;
    transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); position: relative;
}
.ticket-item:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
.ticket-item.active { border-color: var(--primary); background: #eff6ff; }
.ticket-item.active::before {
    content:''; position: absolute; left: 0; top: 10px; bottom: 10px; width: 4px;
    background: var(--primary); border-radius: 0 4px 4px 0;
}

.t-top { display: flex; justify-content: space-between; margin-bottom: 5px; }
.t-id { font-size: 0.75rem; font-weight: 700; color: var(--text-sub); }
.t-date { font-size: 0.7rem; color: #94a3b8; }

.t-subject { font-size: 0.95rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.t-status {
    display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 3px 8px;
    border-radius: 4px; text-transform: uppercase;
}
.st-pending { background: #fff7ed; color: #c2410c; }
.st-answered { background: #dcfce7; color: #15803d; }
.st-closed { background: #f1f5f9; color: #64748b; }

/* --- RIGHT SIDE: CHAT AREA --- */
.chat-panel {
    background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border);
    display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-card);
    position: relative;
}

/* Empty State */
.chat-empty {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: var(--text-sub); text-align: center;
}
.chat-empty svg { font-size: 4rem; color: #e2e8f0; margin-bottom: 15px; }

/* Chat Header */
.chat-header {
    padding: 15px 25px; border-bottom: 1px solid var(--border); background: #fff;
    display: flex; justify-content: space-between; align-items: center;
}
.ch-info h2 { margin: 0; font-size: 1.1rem; font-weight: 700; }
.ch-info span { font-size: 0.8rem; color: var(--text-sub); }

/* Messages Area */
.messages-box {
    flex: 1; padding: 25px; overflow-y: auto; background: #f8fafc;
    display: flex; flex-direction: column; gap: 15px;
}

.msg { max-width: 75%; padding: 12px 18px; font-size: 0.95rem; line-height: 1.5; position: relative; }
.msg-user {
    align-self: flex-end; background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff; border-radius: 18px 18px 0 18px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
}
.msg-admin {
    align-self: flex-start; background: #fff; color: var(--text-main);
    border-radius: 18px 18px 18px 0; border: 1px solid var(--border);
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}
.msg-time {
    display: block; font-size: 0.65rem; margin-top: 5px; opacity: 0.7; text-align: right;
}

/* Input Area */
.chat-input-area {
    padding: 20px; background: #fff; border-top: 1px solid var(--border);
}
.input-group { position: relative; display: flex; gap: 10px; }
.chat-input {
    width: 100%; padding: 15px 20px; border: 1px solid var(--border); border-radius: 50px;
    background: #f8fafc; font-size: 0.95rem; transition: 0.3s; outline: none;
}
.chat-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
.btn-send {
    width: 50px; height: 50px; border-radius: 50%; border: none;
    background: var(--primary); color: #fff; cursor: pointer;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    transition: 0.2s;
}
.btn-send:hover { transform: scale(1.1); background: var(--secondary); }

/* --- MODAL --- */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(5px); z-index: 9999;
    justify-content: center; align-items: center; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #fff; width: 100%; max-width: 500px; border-radius: 20px; padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: popIn 0.3s ease-out;
}
.modal-title { margin: 0 0 20px 0; font-size: 1.5rem; font-weight: 800; color: var(--text-main); }
.form-group { margin-bottom: 15px; }
.form-label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; }
.form-control {
    width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 12px;
    font-size: 1rem; transition: 0.3s;
}
.form-control:focus { border-color: var(--primary); outline: none; }

.btn-submit {
    width: 100%; padding: 14px; background: var(--primary); color: #fff; font-weight: 700;
    border: none; border-radius: 12px; cursor: pointer; margin-top: 10px;
}

@keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* --- RESPONSIVE --- */
@media (max-width: 768px) {
    .support-container { grid-template-columns: 1fr; height: auto; display: block; }
    .ticket-sidebar { height: 400px; margin-bottom: 20px; }
    .chat-panel { height: 600px; }
    
    /* Mobile: Hide list if chat is open (Optional, keeping both stacked for simplicity) */
}
</style>

<div class="app-header" style="max-width:1200px; margin:20px auto; padding:20px; background:#fff; border-radius:16px; display:flex; justify-content:space-between; align-items:center; border:1px solid #e2e8f0;">
    <div>
        <p style="margin:0; font-size:0.8rem; color:#64748b; font-weight:700;">WALLET BALANCE</p>
        <h2 style="margin:0; font-size:1.8rem; color:#4f46e5; font-weight:800;"><?= formatCurrency($user_balance) ?></h2>
    </div>
    <a href="add-funds.php" style="width:40px; height:40px; background:#4f46e5; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:24px;">+</a>
</div>

<div class="support-container">
    
    <div class="ticket-sidebar">
        <div class="sidebar-header">
            <h3>My Tickets</h3>
            <button class="btn-new" onclick="openModal()">+ New</button>
        </div>
        <div class="ticket-list">
            <?php if(empty($all_tickets)): ?>
                <div style="text-align:center; padding:30px; color:#94a3b8;">No tickets found.</div>
            <?php else: ?>
                <?php foreach($all_tickets as $t): ?>
                <a href="?id=<?= $t['id'] ?>" class="ticket-item <?= ($active_ticket['id']??0)==$t['id']?'active':'' ?>">
                    <div class="t-top">
                        <span class="t-id">#<?= $t['id'] ?></span>
                        <span class="t-status st-<?= $t['status'] ?>"><?= $t['status'] ?></span>
                    </div>
                    <span class="t-subject"><?= sanitize($t['subject']) ?></span>
                    <span class="t-date"><?= date('M d, h:i A', strtotime($t['updated_at'])) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-panel">
        <?php if ($active_ticket): ?>
            <div class="chat-header">
                <div class="ch-info">
                    <h2><?= sanitize($active_ticket['subject']) ?></h2>
                    <span>Ticket #<?= $active_ticket['id'] ?> &bull; <b style="text-transform:uppercase"><?= $active_ticket['status'] ?></b></span>
                </div>
            </div>

            <div class="messages-box" id="chatBox">
                <?php foreach($messages as $m): ?>
                <div class="msg msg-<?= $m['sender'] ?>">
                    <?= nl2br(sanitize($m['message'])) ?>
                    <span class="msg-time"><?= date('h:i A', strtotime($m['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if($active_ticket['status'] != 'closed'): ?>
            <form method="POST" class="chat-input-area">
                <input type="hidden" name="reply_ticket" value="1">
                <input type="hidden" name="ticket_id" value="<?= $active_ticket['id'] ?>">
                <div class="input-group">
                    <input type="text" name="message" class="chat-input" placeholder="Type your reply here..." required autocomplete="off">
                    <button class="btn-send">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div style="padding:20px; text-align:center; background:#f1f5f9; color:#64748b; font-weight:600;">
                🚫 This ticket has been closed.
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="chat-empty">
                <svg width="80" height="80" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                <h3>Select a ticket to view</h3>
                <p>Or create a new one to get support.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="ticketModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 class="modal-title">Open New Ticket</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="new_ticket" value="1">
            <div class="form-group">
                <label class="form-label">Subject</label>
                <select name="subject" class="form-control">
                    <option value="Order Issue">Order Issue</option>
                    <option value="Payment Issue">Payment Issue</option>
                    <option value="Refill Request">Refill Request</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" style="height:120px; resize:vertical;" placeholder="Describe your issue here..." required></textarea>
            </div>
            <button class="btn-submit">Submit Ticket</button>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('ticketModal').classList.add('active'); }
function closeModal() { document.getElementById('ticketModal').classList.remove('active'); }

// Auto-Scroll to bottom
const chatBox = document.getElementById('chatBox');
if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>

<?php include '_smm_footer.php'; ?>