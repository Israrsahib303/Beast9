<?php
// Fix: Undefined variable current_page logic to prevent error logs
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);
?>
    <nav class="smm-bottom-nav">
        <a href="smm_dashboard.php" class="<?php echo ($current_page == 'smm_dashboard.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m18.7 8-5.1 5.2-2.8-2.7L7 15.2"></path></svg>
            <span>Dash</span>
        </a>
        
        <a href="smm_order.php" class="<?php echo ($current_page == 'smm_order.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            <span>Order</span>
        </a>
        
        <a href="mass_order.php" class="<?php echo ($current_page == 'mass_order.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <span>Mass</span>
        </a>

        <a href="smm_history.php" class="<?php echo ($current_page == 'smm_history.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
            <span>History</span>
        </a>
        
        <a href="updates.php" class="<?php echo ($current_page == 'updates.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
            <span>Updates</span>
        </a>
    </nav>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script src="../assets/js/smm_main.js?v=2.9"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Check karein ke graph canvas mojood hai
        const ctx = document.getElementById('smm-spending-chart');
        if (ctx && typeof Chart !== 'undefined' && window.smmGraphLabels && window.smmGraphValues) {
            
            // Graph banayein
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: window.smmGraphLabels, // PHP se 'D, j M' format
                    datasets: [{
                        label: 'PKR Spent',
                        data: window.smmGraphValues, // PHP se [0, 0, 5.85, ...]
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4 // Line ko smooth karein
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                // 'PKR 100' likha aaye
                                callback: function(value, index, values) {
                                    return 'PKR ' + value;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // 'PKR Spent' label ko chupayein
                        }
                    }
                }
            });
        }
    });
    </script>
    
</body>
</html>