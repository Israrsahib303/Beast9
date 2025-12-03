<?php
include '_header.php';
requireAdmin();

$error = ''; 
$success = '';

// --- DELETE LOGIC ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM promo_codes WHERE id=?");
    $stmt->execute([$id]);
    $success = "Promo Code Deleted Successfully!";
}

// --- CREATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = strtoupper(sanitize($_POST['code']));
    $bonus = (float)$_POST['bonus'];
    $min = (float)$_POST['min'];
    $max_uses = (int)$_POST['max_uses'];
    
    if(empty($code)) {
        $error = "Code name is required.";
    } else {
        try {
            // Check if code exists first to avoid exception handling for logic flow
            $check = $db->prepare("SELECT id FROM promo_codes WHERE code = ?");
            $check->execute([$code]);
            
            if($check->rowCount() > 0){
                $error = "This Promo Code already exists.";
            } else {
                $stmt = $db->prepare("INSERT INTO promo_codes (code, deposit_bonus, min_deposit, max_uses, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$code, $bonus, $min, $max_uses]);
                $success = "Promo Code <strong>$code</strong> Created! Users get $bonus% Extra.";
            }
        } catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$codes = $db->query("SELECT * FROM promo_codes ORDER BY id DESC")->fetchAll();
?>

<style>
    :root {
        --primary: #6366f1; /* Indigo */
        --primary-hover: #4f46e5;
        --secondary: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --bg-surface: #ffffff;
        --bg-body: #f8fafc;
        --border: #e2e8f0;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .page-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .page-subtitle {
        color: var(--secondary);
        font-size: 0.95rem;
        margin-top: 5px;
    }

    /* Premium Card */
    .premium-card {
        background: var(--bg-surface);
        border-radius: 16px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
        overflow: hidden;
        margin-bottom: 30px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(to right, #ffffff, #f8fafc);
    }
    .card-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #334155;
        font-weight: 600;
    }
    
    .card-body {
        padding: 25px;
    }

    /* Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }

    .form-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.2s;
        background: #f8fafc;
        color: #334155;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    
    .input-hint {
        font-size: 0.75rem;
        color: var(--secondary);
        margin-top: 5px;
        display: block;
    }

    /* Buttons */
    .btn-create {
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 6px rgba(99, 102, 241, 0.25);
    }
    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 10px rgba(99, 102, 241, 0.3);
    }

    /* Alerts */
    .alert {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    /* Modern Table */
    .table-container {
        overflow-x: auto;
    }
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }
    .modern-table thead th {
        background: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        padding: 15px 20px;
        text-align: left;
        letter-spacing: 0.05em;
    }
    .modern-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        color: #334155;
        font-size: 0.9rem;
    }
    .modern-table tbody tr:last-child td { border-bottom: none; }
    .modern-table tbody tr:hover { background: #f8fafc; }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .badge-code { background: #eef2ff; color: #4f46e5; border: 1px solid #e0e7ff; letter-spacing: 0.5px; }
    .badge-bonus { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
    .badge-limit { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

    .btn-action-del {
        padding: 6px 12px;
        background: #fff;
        border: 1px solid #fee2e2;
        color: var(--danger);
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-action-del:hover {
        background: #fee2e2;
        color: #dc2626;
    }
</style>

<div class="page-container">
    
    <div class="page-header">
        <div>
            <h1 class="page-title">🎟️ Promo Manager</h1>
            <p class="page-subtitle">Create and manage discount codes for user deposits.</p>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-error">
            <span>⚠️</span> <?= $error ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <span>✅</span> <?= $success ?>
        </div>
    <?php endif; ?>

    <div class="premium-card">
        <div class="card-header">
            <h3>✨ Create New Coupon</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Promo Code Name</label>
                        <div class="input-wrapper">
                            <input type="text" name="code" class="form-control" placeholder="e.g. SUMMER2024" required>
                        </div>
                        <span class="input-hint">Unique identifier for the promotion.</span>
                    </div>

                    <div class="form-group">
                        <label>Bonus Percentage (%)</label>
                        <div class="input-wrapper">
                            <input type="number" name="bonus" class="form-control" placeholder="e.g. 10" required step="0.01">
                        </div>
                        <span class="input-hint">User gets this % extra (e.g., Deposit 1000 + 10% = 1100).</span>
                    </div>

                    <div class="form-group">
                        <label>Minimum Deposit</label>
                        <div class="input-wrapper">
                            <input type="number" name="min" class="form-control" placeholder="e.g. 500" required>
                        </div>
                        <span class="input-hint">Minimum amount required to apply code.</span>
                    </div>

                    <div class="form-group">
                        <label>Total Usage Limit</label>
                        <div class="input-wrapper">
                            <input type="number" name="max_uses" class="form-control" value="0" required>
                        </div>
                        <span class="input-hint">Set to 0 for unlimited uses.</span>
                    </div>
                </div>

                <div style="margin-top: 25px; text-align: right;">
                    <button class="btn-create">
                        <span>🚀</span> Create Promo Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="premium-card">
        <div class="card-header">
            <h3>📜 Active Coupons</h3>
        </div>
        <div class="table-container">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Code Name</th>
                        <th>Bonus Reward</th>
                        <th>Condition (Min)</th>
                        <th>Usage Stats</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($codes)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">
                                No promo codes found. Create one above!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($codes as $c): ?>
                        <tr>
                            <td>
                                <span class="badge badge-code"><?= sanitize($c['code']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-bonus">+<?= $c['deposit_bonus'] ?>% Bonus</span>
                            </td>
                            <td>
                                <span style="font-weight:600; color:#334155;">
                                    <?= formatCurrency($c['min_deposit']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-limit">
                                    Used: <?= $c['current_uses'] ?> / <?= $c['max_uses'] == 0 ? '∞' : $c['max_uses'] ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="promo_codes.php?delete=<?= $c['id'] ?>" class="btn-action-del" onclick="return confirm('Are you sure you want to delete the code <?= sanitize($c['code']) ?>? This cannot be undone.')">
                                    <span>🗑️</span> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '_footer.php'; ?>