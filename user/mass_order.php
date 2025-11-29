<?php
include '_smm_header.php';

// --- 1. PHP PROCESSOR ---
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['mass_order_content']); 
    $lines = explode("\n", $content);
    $count = 0; $fail = 0; $total_charge = 0;

    $db->beginTransaction();
    try {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Format: ID|Link|Qty
            $parts = explode('|', $line);
            if (count($parts) != 3) { $fail++; continue; }
            
            $sid = (int)trim($parts[0]);
            $link = trim($parts[1]);
            $qty = (int)trim($parts[2]);
            
            $s = $db->query("SELECT * FROM smm_services WHERE id=$sid AND is_active=1")->fetch();
            if (!$s || $qty < $s['min'] || $qty > $s['max']) { $fail++; continue; }
            
            $price = ($qty/1000) * $s['service_rate'];
            
            // Currency Conversion for Deducting (Database is usually base currency, but if user sees converted, we need to handle)
            // Assuming Database stores Base/PKR. If user has USD, we convert back or just deduct raw amount if base is consistent.
            // Let's stick to Standard Logic: Deduct Rate * Qty
            
            $u = $db->query("SELECT balance FROM users WHERE id=".$_SESSION['user_id'])->fetch();
            if ($u['balance'] < $price) { $fail++; continue; }
            
            $db->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$price, $_SESSION['user_id']]);
            $db->prepare("INSERT INTO smm_orders (user_id, service_id, service_name, link, quantity, charge, status, api_provider_id, created_at) VALUES (?,?,?,?,?,?, 'pending', ?, NOW())")
               ->execute([$_SESSION['user_id'], $sid, $s['name'], $link, $qty, $price, $s['provider_id']]);
            
            $total_charge += $price;
            $count++;
        }
        $db->commit();
        
        if ($count > 0) {
            $success = "✅ Successfully placed $count orders! (Total: ".formatCurrency($total_charge).")";
            if ($fail > 0) $error = "⚠️ $fail orders failed (Invalid limits/balance).";
        } else {
            $error = "❌ Failed. Check inputs or balance.";
        }

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. DATA FETCHING FOR JS ---
// Fetch Categories
$cats = $db->query("SELECT * FROM smm_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Services
$svcs = $db->query("SELECT id, name, category, service_rate, min, max FROM smm_services WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group Services by Category Name for JS
$services_by_cat = [];
foreach ($svcs as $s) {
    $catName = trim($s['category']);
    $services_by_cat[$catName][] = $s;
}

// Currency
$curr_code = $_COOKIE['site_currency'] ?? 'PKR';
$curr_rate = 1; 
if ($curr_code != 'PKR') $curr_rate = getCurrencyRate($curr_code);
?>

<script>
    window.servicesByCat = <?= json_encode($services_by_cat) ?>;
    window.currency = { code: "<?=$curr_code?>", rate: <?=$curr_rate?>, sym: "<?=$curr_symbol ?? 'Rs'?>" };
</script>

<style>
/* --- THEME CSS --- */
:root {
    --primary: #2563eb;
    --bg-body: #f3f4f6;
    --card-bg: #ffffff;
    --border: #e5e7eb;
    --radius: 12px;
}

body { background: var(--bg-body); color: #1f2937; font-family: 'Inter', sans-serif; }

.mass-container { max-width: 1000px; margin: 20px auto; padding: 20px; }

.page-header { text-align: center; margin-bottom: 30px; }
.page-title { font-size: 2rem; font-weight: 800; color: #111; margin: 0; }
.page-desc { color: #6b7280; }

/* ORDER ROW CARD */
.order-row {
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
    padding: 20px; margin-bottom: 15px; position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02); animation: slideUp 0.3s ease-out;
    display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
}

.form-col { flex: 1; min-width: 200px; }
.form-col.small { flex: 0.5; min-width: 120px; }

.form-label { display: block; font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 5px; }
.form-select, .form-input {
    width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;
    font-size: 0.9rem; background: #f9fafb; transition: 0.2s;
}
.form-select:focus, .form-input:focus { border-color: var(--primary); background: #fff; outline: none; }

.service-info {
    font-size: 0.75rem; color: #2563eb; background: #eff6ff; padding: 5px 10px;
    border-radius: 4px; margin-top: 5px; display: none; /* Hidden initially */
}
.service-info span { margin-right: 10px; font-weight: 600; }

.btn-remove {
    background: #fee2e2; color: #ef4444; border: none; width: 30px; height: 30px;
    border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: 0.2s; margin-bottom: 5px;
}
.btn-remove:hover { background: #dc2626; color: #fff; }

.btn-add-row {
    width: 100%; padding: 15px; background: #fff; border: 2px dashed #cbd5e1;
    color: var(--primary); border-radius: var(--radius); font-weight: 700; cursor: pointer;
    transition: 0.2s; margin-bottom: 30px;
}
.btn-add-row:hover { background: #f0f9ff; border-color: var(--primary); }

.sticky-footer {
    position: sticky; bottom: 20px; background: #fff; padding: 15px 25px;
    border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    display: flex; justify-content: space-between; align-items: center;
    border: 1px solid var(--border); z-index: 100;
}
.total-text { font-size: 0.9rem; color: #6b7280; font-weight: 600; }
.total-amount { font-size: 1.4rem; color: var(--primary); font-weight: 800; }

.btn-submit {
    background: var(--primary); color: #fff; border: none; padding: 10px 30px;
    border-radius: 30px; font-weight: 700; cursor: pointer; font-size: 1rem;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); transition: 0.2s;
}
.btn-submit:hover { transform: translateY(-2px); }

@keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
</style>

<div class="mass-container">
    <div class="page-header">
        <h1 class="page-title">📦 Mass Order</h1>
        <p class="page-desc">Add multiple orders at once.</p>
    </div>

    <?php if($error): ?><div style="background:#fee2e2;color:#b91c1c;padding:15px;border-radius:8px;margin-bottom:20px;"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div style="background:#d1fae5;color:#065f46;padding:15px;border-radius:8px;margin-bottom:20px;"><?= $success ?></div><?php endif; ?>

    <form method="POST" id="massForm" style="display:none;">
        <textarea name="mass_order_content" id="massInput"></textarea>
    </form>

    <div id="rows-container">
        </div>

    <button onclick="addRow()" class="btn-add-row">+ Add Another Order</button>

    <div class="sticky-footer">
        <div>
            <span class="total-text">Total Estimate</span><br>
            <span class="total-amount" id="grandTotal">0.00</span>
        </div>
        <button onclick="submitOrders()" class="btn-submit">Submit Orders</button>
    </div>
</div>

<template id="rowTemplate">
    <div class="order-row">
        <div class="form-col">
            <label class="form-label">Category</label>
            <select class="form-select cat-select" onchange="loadServices(this)">
                <option value="">-- Select --</option>
                <?php foreach($cats as $c): ?>
                    <option value="<?= sanitize($c['name']) ?>"><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-col">
            <label class="form-label">Service</label>
            <select class="form-select svc-select" onchange="updateInfo(this)" disabled>
                <option value="">-- Select Category First --</option>
            </select>
            <div class="service-info">
                <span class="s-rate">Rate: 0</span>
                <span class="s-limit">Min: 0</span>
            </div>
        </div>

        <div class="form-col">
            <label class="form-label">Link</label>
            <input type="text" class="form-input link-input" placeholder="https://...">
        </div>

        <div class="form-col small">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-input qty-input" placeholder="1000" oninput="calcTotal()">
        </div>

        <button class="btn-remove" onclick="removeRow(this)">✕</button>
    </div>
</template>

<script>
// --- LOGIC ---
const cur = window.currency;

function addRow() {
    const tpl = document.getElementById('rowTemplate');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('rows-container').appendChild(clone);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.order-row');
    if (rows.length > 1) {
        btn.closest('.order-row').remove();
        calcTotal();
    } else {
        // Reset first row instead of removing
        const row = btn.closest('.order-row');
        row.querySelector('.cat-select').value = "";
        row.querySelector('.svc-select').innerHTML = '<option value="">-- Select Category First --</option>';
        row.querySelector('.svc-select').disabled = true;
        row.querySelector('.link-input').value = "";
        row.querySelector('.qty-input').value = "";
        row.querySelector('.service-info').style.display = 'none';
        calcTotal();
    }
}

// 1. Load Services based on Category
function loadServices(catSelect) {
    const row = catSelect.closest('.order-row');
    const svcSelect = row.querySelector('.svc-select');
    const catName = catSelect.value;
    const services = window.servicesByCat[catName] || [];

    svcSelect.innerHTML = '<option value="">-- Select Service --</option>';
    
    if (services.length > 0) {
        svcSelect.disabled = false;
        services.forEach(s => {
            // Rate Conversion for display
            let rate = parseFloat(s.service_rate);
            if(cur.code !== 'PKR') rate *= cur.rate;
            
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.dataset.rate = s.service_rate; // Store Base Rate
            opt.dataset.min = s.min;
            opt.dataset.max = s.max;
            opt.innerText = `${s.id} - ${s.name}`;
            svcSelect.appendChild(opt);
        });
    } else {
        svcSelect.disabled = true;
        svcSelect.innerHTML = '<option value="">No services found</option>';
    }
    updateInfo(svcSelect); // Reset info
}

// 2. Update Info Bar & Total
function updateInfo(svcSelect) {
    const row = svcSelect.closest('.order-row');
    const infoBox = row.querySelector('.service-info');
    const opt = svcSelect.options[svcSelect.selectedIndex];

    if (svcSelect.value && opt) {
        let rate = parseFloat(opt.dataset.rate);
        if(cur.code !== 'PKR') rate *= cur.rate;
        
        row.querySelector('.s-rate').innerText = `Rate: ${cur.sym} ${rate.toFixed(3)}`;
        row.querySelector('.s-limit').innerText = `Min: ${opt.dataset.min} | Max: ${opt.dataset.max}`;
        infoBox.style.display = 'block';
    } else {
        infoBox.style.display = 'none';
    }
    calcTotal();
}

// 3. Calculate Total
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.order-row').forEach(row => {
        const svcSelect = row.querySelector('.svc-select');
        const qtyInput = row.querySelector('.qty-input');
        const opt = svcSelect.options[svcSelect.selectedIndex];
        
        if (svcSelect.value && qtyInput.value && opt) {
            const rate = parseFloat(opt.dataset.rate); // Base PKR rate
            const qty = parseInt(qtyInput.value);
            total += (qty / 1000) * rate;
        }
    });

    // Display Conversion
    if(cur.code !== 'PKR') total *= cur.rate;
    
    document.getElementById('grandTotal').innerText = `${cur.sym} ${total.toFixed(2)}`;
}

// 4. Submit
function submitOrders() {
    let data = "";
    let hasData = false;
    
    document.querySelectorAll('.order-row').forEach(row => {
        const sid = row.querySelector('.svc-select').value;
        const link = row.querySelector('.link-input').value.trim();
        const qty = row.querySelector('.qty-input').value;
        
        if (sid && link && qty) {
            data += `${sid}|${link}|${qty}\n`;
            hasData = true;
        }
    });

    if (!hasData) {
        alert("Please fill at least one order correctly.");
        return;
    }

    if(confirm("Are you sure you want to place these orders?")) {
        document.getElementById('massInput').value = data;
        document.getElementById('massForm').submit();
    }
}

// Init
document.addEventListener('DOMContentLoaded', () => addRow());
</script>

<?php include '_footer.php'; ?>