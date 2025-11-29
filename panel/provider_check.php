<?php
// 1. Error Reporting On (Debug ke liye)
ini_set('display_errors', 0); // Production mein off rakhein
error_reporting(E_ALL);

include '_header.php';
requireAdmin();
require_once __DIR__ . '/../includes/smm_api.class.php';

// Fetch Settings for Rate
$usd_to_pkr = (float)($GLOBALS['settings']['currency_conversion_rate'] ?? 280.00);

// Fetch ALL providers
try {
    $all_providers = $db->query("SELECT * FROM smm_providers")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_providers = [];
    echo "<div class='message error'>Database Error: " . $e->getMessage() . "</div>";
}
?>

<style>
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .bg-success { background: #d1fae5; color: #065f46; }
    .bg-danger { background: #fee2e2; color: #b91c1c; }
    
    /* Toggle Buttons */
    .currency-toggles {
        display: flex; gap: 10px; margin-bottom: 20px;
    }
    .btn-curr {
        padding: 10px 20px; border: 1px solid #ccc; background: #fff; 
        cursor: pointer; border-radius: 6px; font-weight: 600; transition: 0.2s;
    }
    .btn-curr:hover { background: #f0f0f0; }
    .btn-curr.active { background: #007bff; color: #fff; border-color: #007bff; }
</style>

<div class="admin-container">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="margin:0;">🕵️ Provider Balance Checker</h1>
        
        <div class="currency-toggles">
            <button class="btn-curr active" onclick="switchCurrency('USD', this)">Show in USD ($)</button>
            <button class="btn-curr" onclick="switchCurrency('PKR', this)">Show in PKR (Rs)</button>
        </div>
    </div>

    <p style="color:#666; margin-bottom:20px;">
        <strong>Current Rate:</strong> 1 USD = <?php echo $usd_to_pkr; ?> PKR
        <small>(Set in Settings)</small>
    </p>

    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Provider Name</th>
                    <th>Status</th>
                    <th>API Connection</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_providers)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#777;">No providers found.</td></tr>
                <?php else: ?>
                    <?php foreach($all_providers as $p): 
                        $api_status = '<span style="color:#999">Checking...</span>';
                        $bal_usd = 0.00;
                        $bal_pkr = 0.00;
                        $row_style = '';
                        $currency_code = 'USD';

                        try {
                            if (empty($p['api_url']) || empty($p['api_key'])) {
                                throw new Exception("Missing Key/URL");
                            }

                            // API Call
                            $api = new SmmApi($p['api_url'], $p['api_key']);
                            $bal_data = $api->balance();
                            
                            if (isset($bal_data['balance'])) {
                                $api_status = '<span style="color:green">✔ Connected</span>';
                                
                                // Clean balance string (remove symbols if any)
                                $raw_bal = preg_replace('/[^0-9.]/', '', $bal_data['balance']);
                                $bal_usd = (float)$raw_bal;
                                
                                // Convert to PKR
                                $bal_pkr = $bal_usd * $usd_to_pkr;
                                
                                $currency_code = $bal_data['currency'] ?? 'USD';
                            } else {
                                throw new Exception("Invalid Response");
                            }

                        } catch (Exception $e) {
                            $api_status = '<span style="color:red">✘ ' . $e->getMessage() . '</span>';
                            $row_style = 'background: #fff5f5;';
                        }
                    ?>
                    <tr style="<?php echo $row_style; ?>">
                        <td>#<?php echo $p['id']; ?></td>
                        <td><strong><?php echo sanitize($p['name']); ?></strong></td>
                        <td>
                            <?php if($p['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $api_status; ?></td>
                        
                        <td class="balance-cell" 
                            data-usd="<?php echo number_format($bal_usd, 2); ?>" 
                            data-pkr="<?php echo number_format($bal_pkr, 2); ?>">
                            
                            <span class="view-usd" style="font-weight:bold; color:#007bff; font-size:1.1rem;">
                                $<?php echo number_format($bal_usd, 2); ?>
                            </span>
                            
                            <span class="view-pkr" style="font-weight:bold; color:#28a745; font-size:1.1rem; display:none;">
                                Rs. <?php echo number_format($bal_pkr, 2); ?>
                            </span>
                            
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function switchCurrency(curr, btn) {
    // Buttons Active State
    document.querySelectorAll('.btn-curr').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Toggle Visibility
    const usdSpans = document.querySelectorAll('.view-usd');
    const pkrSpans = document.querySelectorAll('.view-pkr');

    if (curr === 'USD') {
        usdSpans.forEach(el => el.style.display = 'inline');
        pkrSpans.forEach(el => el.style.display = 'none');
    } else {
        usdSpans.forEach(el => el.style.display = 'none');
        pkrSpans.forEach(el => el.style.display = 'inline');
    }
}
</script>

<?php include '_footer.php'; ?>