<?php
include '_header.php'; // Header file ko include karein
?>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .hub-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-top: 2rem;
    }
    .hub-card {
        background: var(--admin-bg-lighter);
        border-radius: 12px;
        padding: 2.5rem;
        text-align: center;
        border: 1px solid var(--admin-border);
        transition: all 0.3s ease;
        animation: fadeInUp 0.5s ease-out forwards;
        text-decoration: none;
        color: var(--admin-text);
    }
    .hub-card:nth-child(2) { animation-delay: 0.2s; }
    .hub-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        border-color: var(--brand-red);
    }
    .hub-card .hub-icon {
        font-size: 3.5rem;
        color: var(--brand-red);
        margin-bottom: 1.5rem;
    }
    .hub-card h2 {
        font-size: 1.8rem;
        margin: 0;
        border-bottom: none;
    }
    .hub-card p {
        font-size: 1rem;
        color: var(--admin-text-muted);
        margin-top: 0.5rem;
    }
    @media (max-width: 768px) {
        .hub-container { grid-template-columns: 1fr; }
    }
</style>

<h1>Admin Control Hub</h1>

<div class="hub-container">
    <a href="sub_dashboard.php" class="hub-card">
        <div class="hub-icon">
            <i class="fas fa-box-open"></i>
        </div>
        <h2>Subscriptions</h2>
        <p>Manage Netflix, Canva, etc.</p>
    </a>
    
    <a href="smm_dashboard.php" class="hub-card">
        <div class="hub-icon">
            <i class="fas fa-share-alt"></i>
        </div>
        <h2>SMM Panel</h2>
        <p>Manage Likes, Followers, etc.</p>
    </a>
</div>

<?php include '_footer.php'; ?>