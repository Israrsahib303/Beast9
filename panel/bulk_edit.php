<?php
// --- 0. SETUP & AJAX HANDLER (MUST BE AT TOP) ---
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check Admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    if (isset($_POST['ajax_action'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

// --- 1. AJAX PROCESSOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Clean Buffer to prevent HTML leaks
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $percent = floatval($_POST['percent'] ?? 0);
    $action_type = $_POST['action_type']; // inc, dec, set_margin, reset_provider
    $provider_filter = $_POST['provider_id']; // all or specific ID
    $scope = $_POST['scope']; // all, category, service
    $target_id = $_POST['target_id'] ?? '';

    try {
        $db->beginTransaction();

        // Base Query Construction
        $sql = "UPDATE smm_services SET service_rate = ";
        $params = [];

        // --- FORMULA LOGIC (FIXED) ---
        if ($action_type === 'inc') {
            // Increase Current Price by X%
            // Formula: Price * (1 + percent/100)
            // Example: 100 * (1 + 10/100) = 100 * 1.1 = 110
            $factor = 1 + ($percent / 100);
            $sql .= "service_rate * ?";
            $params[] = $factor;
            
        } elseif ($action_type === 'dec') {
            // Decrease Current Price by X%
            // Formula: Price * (1 - percent/100)
            // Example: 100 * (1 - 10/100) = 100 * 0.9 = 90
            $factor = 1 - ($percent / 100);
            $sql .= "service_rate * ?";
            $params[] = $factor;
            
        } elseif ($action_type === 'set_margin') {
            // Set New Margin based on Provider Base Price
            // Formula: BasePrice * (1 + percent/100)
            // Example: 100 * (1.1) = 110
            $sql .= "base_price * ?";
            $params[] = (1 + ($percent / 100));
            
        } elseif ($action_type === 'reset_provider') {
            // Reset to Provider Price (0 Profit)
            $sql .= "base_price"; 
        }

        // --- FILTERS ---
        $sql .= " WHERE 1=1";

        // 1. Provider Filter
        if ($provider_filter !== 'all') {
            $sql .= " AND provider_id = ?";
            $params[] = $provider_filter;
        }

        // 2. Scope Filter
        if ($scope === 'category') {
            $sql .= " AND category = ?";
            $params[] = $target_id;
        } elseif ($scope === 'service') {
            $sql .= " AND id = ?";
            $params[] = $target_id;
        }

        // 3. Safety Check for Base Price actions
        if ($action_type === 'set_margin' || $action_type === 'reset_provider') {
            $sql .= " AND base_price > 0";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        $db->commit();
        echo json_encode(['status' => 'success', 'message' => "Successfully updated $count services!"]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => "DB Error: " . $e->getMessage()]);
    }
    exit; // Stop script here to avoid HTML output
}

// --- 2. VIEW RENDER (HTML Starts Here) ---
include '_header.php'; 
requireAdmin();

// Stats: Average Margin
$avg_margin_query = $db->query("
    SELECT AVG(((service_rate - base_price) / base_price) * 100) as margin 
    FROM smm_services 
    WHERE base_price > 0 AND is_active = 1
");
$current_avg_margin = round($avg_margin_query->fetchColumn() ?: 0, 1);

// Fetch Data
$providers = $db->query("SELECT id, name FROM smm_providers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT DISTINCT category FROM smm_services ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$services_raw = $db->query("
    SELECT s.id, s.name, s.category, s.service_rate, s.base_price, s.provider_id, s.service_id as api_service_id, p.name as provider_name
    FROM smm_services s
    LEFT JOIN smm_providers p ON s.provider_id = p.id
    WHERE s.is_active=1
")->fetchAll(PDO::FETCH_ASSOC);
$services_json = json_encode($services_raw);
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary: #6366f1;
        --secondary: #a855f7;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --dark: #1e293b;
        --light: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
    }

    body {
        font-family: 'Outfit', sans-serif;
        background: #f1f5f9;
        color: var(--dark);
    }

    /* ANIMATIONS */
    @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }

    .main-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 20px;
        animation: slideIn 0.5s ease-out;
    }

    /* STATS CARD */
    .stats-card {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 20px;
        padding: 30px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.2);
        position: relative;
        overflow: hidden;
    }
    .stats-card::after {
        content: ''; position: absolute; top: -50%; right: -10%; width: 200px; height: 200px;
        background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(40px);
    }
    .stat-text h2 { font-size: 2.5rem; margin: 0; font-weight: 800; }
    .stat-text p { margin: 5px 0 0; opacity: 0.9; font-size: 1rem; }
    .stat-icon { font-size: 3rem; opacity: 0.8; animation: float 3s ease-in-out infinite; }

    /* EDITOR GRID */
    .editor-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 25px;
    }
    @media (max-width: 768px) { .editor-grid { grid-template-columns: 1fr; } }

    .card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }

    h3 { font-weight: 700; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px; }

    /* FORM ELEMENTS */
    .form-group { margin-bottom: 20px; position: relative; }
    .form-label { display: block; font-size: 0.9rem; font-weight: 600; color: #64748b; margin-bottom: 8px; }
    
    .custom-select, .custom-input {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        background: #fff;
        transition: 0.3s;
        appearance: none;
    }
    .custom-select:focus, .custom-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }

    /* DROPDOWN ARROW */
    .select-wrapper { position: relative; }
    .select-wrapper::after {
        content: '▼'; position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
        font-size: 0.8rem; color: #94a3b8; pointer-events: none;
    }

    /* ACTION BUTTONS */
    .btn-update {
        width: 100%; padding: 16px; background: var(--dark); color: white;
        border: none; border-radius: 14px; font-size: 1.1rem; font-weight: 700;
        cursor: pointer; transition: 0.3s; margin-top: 10px;
    }
    .btn-update:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }

    /* SEARCH BOX */
    .search-box-wrap { position: relative; display: flex; align-items: center; }
    .search-box-wrap input { padding-left: 40px; }
    .search-icon-input { position: absolute; left: 15px; color: #94a3b8; }

    /* SIMULATOR */
    .sim-box {
        background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px;
        padding: 20px; text-align: center; margin-top: 20px;
    }
    .sim-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.95rem; }
    .sim-val { font-weight: 700; color: var(--dark); }
    .sim-result { font-size: 1.5rem; font-weight: 800; color: var(--success); margin-top: 10px; display: block; }
    
    .margin-badge {
        background: rgba(16, 185, 129, 0.1); color: var(--success);
        padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;
    }
    .margin-badge.zero { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

    /* PROVIDER BADGE */
    .prov-badge {
        display: inline-block; padding: 2px 8px; border-radius: 4px; 
        font-size: 0.75rem; background: #e2e8f0; color: #475569; margin-left: 5px;
    }
</style>

<div class="main-container">

    <div class="stats-card">
        <div class="stat-text">
            <p>Current Average Profit Margin</p>
            <h2 id="liveMargin"><?= $current_avg_margin ?>%</h2>
            <small style="opacity:0.7">Across all active services</small>
        </div>
        <div class="stat-icon">💹</div>
    </div>

    <div class="editor-grid">
        
        <div class="card">
            <h3><i class="fa-solid fa-sliders"></i> Bulk Editor</h3>
            
            <form id="bulkForm">
                <input type="hidden" name="ajax_action" value="1">

                <div class="form-group">
                    <label class="form-label">Step 1: Select API Provider</label>
                    <div class="select-wrapper">
                        <select name="provider_id" id="providerSelect" class="custom-select" onchange="toggleScope()">
                            <option value="all">⚡ All API Providers</option>
                            <?php foreach($providers as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Step 2: Select Scope</label>
                    <div class="select-wrapper">
                        <select name="scope" id="scopeSelect" class="custom-select" onchange="toggleScope()">
                            <option value="all">🌍 All Services (Bulk)</option>
                            <option value="category">kB Specific Category</option>
                            <option value="service">🔧 Specific Service</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="targetGroup" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label class="form-label" id="targetLabel">Select Target</label>
                        
                        <div id="searchContainer" style="display:none; width: 60%; margin-bottom: 5px;">
                            <div class="search-box-wrap">
                                <i class="fa-solid fa-magnifying-glass search-icon-input"></i>
                                <input type="number" id="serviceSearch" class="custom-input" 
                                       placeholder="Enter API Service ID..." 
                                       style="padding: 8px 10px 8px 35px; font-size: 0.9rem;"
                                       oninput="searchServiceById(this.value)">
                            </div>
                        </div>
                    </div>

                    <div class="select-wrapper">
                        <select name="target_id" id="targetSelect" class="custom-select">
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Step 3: Action</label>
                    <div class="select-wrapper">
                        <select name="action_type" id="actionType" class="custom-select" onchange="updateSimulator()">
                            <option value="inc">📈 Increase Price (Percentage)</option>
                            <option value="dec">📉 Decrease Price (Percentage)</option>
                            <option value="set_margin">🎯 Set Fixed Profit Margin (%)</option>
                            <option value="reset_provider" style="color:red; font-weight:bold;">🔄 Reset to Provider Price (0% Profit)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="percentGroup">
                    <label class="form-label">Percentage (%)</label>
                    <input type="number" name="percent" id="percentInput" class="custom-input" placeholder="e.g. 20" value="10" min="0" step="0.1" oninput="updateSimulator()">
                </div>

                <button type="button" onclick="submitUpdate()" class="btn-update">Apply Update</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-calculator"></i> Live Simulator</h3>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">
                See how a service costing <strong>100 Rs</strong> (Provider Price) changes.
            </p>

            <div class="sim-box">
                <div class="sim-row">
                    <span>Provider Cost:</span>
                    <span class="sim-val">100.00 Rs</span>
                </div>
                <div class="sim-row">
                    <span>Action:</span>
                    <span class="sim-val" id="simAction">Increase 10%</span>
                </div>
                <hr style="border-top:1px dashed #cbd5e1; margin:15px 0;">
                <div class="sim-row">
                    <span>New Selling Price:</span>
                    <span class="sim-result" id="simPrice">110.00 Rs</span>
                </div>
                <div style="margin-top:10px;">
                    <span class="margin-badge" id="simMargin">Profit: 10%</span>
                </div>
            </div>

            <div style="margin-top:25px; background:#f0fdf4; padding:15px; border-radius:12px; border:1px solid #bbf7d0;">
                <h4 style="margin:0 0 5px; color:#166534; font-size:0.95rem;">💡 Why Reset?</h4>
                <p style="margin:0; font-size:0.85rem; color:#15803d;">
                    If your rates are lower than the provider (e.g. You: 414, Provider: 428), use <b>"Reset to Provider Price"</b> to fix losses instantly. Then apply a margin.
                </p>
            </div>
        </div>

    </div>
</div>

<script>
// --- DATA FROM PHP ---
const allServices = <?= $services_json ?>;
const categories = <?= json_encode($categories) ?>;

// --- UI LOGIC ---

function toggleScope() {
    const providerId = document.getElementById('providerSelect').value;
    const scope = document.getElementById('scopeSelect').value;
    const targetGroup = document.getElementById('targetGroup');
    const targetSelect = document.getElementById('targetSelect');
    const label = document.getElementById('targetLabel');
    const searchContainer = document.getElementById('searchContainer');

    targetSelect.innerHTML = ''; // Clear previous
    searchContainer.style.display = 'none';

    // Filter Logic based on Provider
    let filteredServices = allServices;
    if (providerId !== 'all') {
        filteredServices = allServices.filter(svc => svc.provider_id == providerId);
    }

    if (scope === 'all') {
        targetGroup.style.display = 'none';
    } else {
        targetGroup.style.display = 'block';
        targetGroup.style.animation = 'slideIn 0.3s ease';
        
        if (scope === 'category') {
            label.innerText = 'Select Category';
            
            // Get unique categories from filtered services
            let uniqueCats = [...new Set(filteredServices.map(item => item.category))];
            
            if(uniqueCats.length === 0) {
                let opt = document.createElement('option');
                opt.innerText = "No categories found for this provider";
                targetSelect.appendChild(opt);
            } else {
                uniqueCats.sort().forEach(cat => {
                    let opt = document.createElement('option');
                    opt.value = cat;
                    opt.innerText = cat;
                    targetSelect.appendChild(opt);
                });
            }

        } else if (scope === 'service') {
            label.innerText = 'Select Service';
            searchContainer.style.display = 'block'; // Show Search Box

            if(filteredServices.length === 0) {
                let opt = document.createElement('option');
                opt.innerText = "No services found";
                targetSelect.appendChild(opt);
            } else {
                filteredServices.forEach(svc => {
                    let opt = document.createElement('option');
                    opt.value = svc.id;
                    // Format: [API-ID] Name - Rate
                    opt.innerText = `[ID: ${svc.api_service_id}] ${svc.name.substring(0, 50)}... (${svc.service_rate})`;
                    // Store API ID in data attribute for search
                    opt.dataset.apiId = svc.api_service_id;
                    targetSelect.appendChild(opt);
                });
            }
        }
    }
}

// --- SEARCH FUNCTION (By API Service ID) ---
function searchServiceById(apiId) {
    const select = document.getElementById('targetSelect');
    const options = select.options;
    
    // Reset if empty
    if(!apiId) return;

    for (let i = 0; i < options.length; i++) {
        // Check if option's data-api-id matches input (loose check for string/int)
        if (options[i].dataset.apiId == apiId) {
            select.selectedIndex = i;
            // Visual feedback
            select.style.borderColor = '#10b981';
            setTimeout(() => select.style.borderColor = '#e2e8f0', 1000);
            break;
        }
    }
}

function updateSimulator() {
    const action = document.getElementById('actionType').value;
    const percentInput = document.getElementById('percentInput');
    const percentGroup = document.getElementById('percentGroup');
    
    let percent = parseFloat(percentInput.value) || 0;
    
    const basePrice = 100;
    let newPrice = 0;
    let profitText = "";
    let actionText = "";
    let marginColor = "var(--success)";
    let marginBg = "rgba(16, 185, 129, 0.1)";

    // Show/Hide Percentage Input based on action
    if (action === 'reset_provider') {
        percentGroup.style.opacity = '0.5';
        percentGroup.style.pointerEvents = 'none';
        percent = 0; // Force 0 for visual logic
    } else {
        percentGroup.style.opacity = '1';
        percentGroup.style.pointerEvents = 'auto';
    }

    if(action === 'inc') {
        newPrice = basePrice * (1 + percent/100);
        actionText = `Increase Selling Price by ${percent}%`;
        let margin = ((newPrice - basePrice) / basePrice) * 100;
        profitText = `Profit Margin: ${margin.toFixed(1)}%`;
    } 
    else if (action === 'dec') {
        // Simulator scenario: Current Selling is 150 (50% profit), reducing 20%
        let currentSelling = 150; 
        newPrice = currentSelling * (1 - percent/100);
        actionText = `Decrease Selling Price by ${percent}%`;
        profitText = `New Price based on hypothetical 150 Rs`;
    } 
    else if (action === 'set_margin') {
        newPrice = basePrice * (1 + percent/100);
        actionText = `Set Margin: ${percent}% over Base`;
        profitText = `Profit Margin: ${percent}%`;
    }
    else if (action === 'reset_provider') {
        newPrice = basePrice;
        actionText = `Reset to Provider Rate`;
        profitText = `Profit Margin: 0% (No Profit)`;
        marginColor = "var(--danger)";
        marginBg = "rgba(239, 68, 68, 0.1)";
    }

    // Update UI
    document.getElementById('simAction').innerText = actionText;
    document.getElementById('simPrice').innerText = newPrice.toFixed(2) + " Rs";
    
    const marginEl = document.getElementById('simMargin');
    marginEl.innerText = profitText;
    marginEl.style.color = marginColor;
    marginEl.style.backgroundColor = marginBg;
}

function submitUpdate() {
    const form = document.getElementById('bulkForm');
    const formData = new FormData(form);
    const action = document.getElementById('actionType').value;
    
    // Confirmation Text Logic
    let warningText = "This will update prices immediately!";
    if (action === 'reset_provider') {
        warningText = "⚠️ WARNING: This will set selling prices equal to provider costs (0 Profit). Are you sure?";
    }

    Swal.fire({
        title: 'Are you sure?',
        text: warningText,
        icon: action === 'reset_provider' ? 'warning' : 'info',
        showCancelButton: true,
        confirmButtonColor: action === 'reset_provider' ? '#ef4444' : '#6366f1',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, Apply Changes!'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Show Loading
            Swal.fire({
                title: 'Processing...',
                html: 'Updating database securely.',
                timerProgressBar: true,
                didOpen: () => { Swal.showLoading(); }
            });

            // Send AJAX
            fetch('bulk_edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('Success!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error!', 'Connection failed. Check console.', 'error');
            });
        }
    })
}

// Init
toggleScope();
updateSimulator();
</script>

<?php include '_footer.php'; ?>