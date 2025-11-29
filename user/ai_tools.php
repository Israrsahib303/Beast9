<?php include '_header.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* --- THEME VARIABLES --- */
    :root {
        --accent: #4f46e5;
        --accent-hover: #4338ca;
        --glass: rgba(255, 255, 255, 0.95);
        --shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
    }

    /* --- PAGE LAYOUT --- */
    .ai-page-header {
        text-align: center; padding: 40px 20px;
        background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
        border-radius: 20px; margin-bottom: 40px;
        border: 1px solid rgba(255,255,255,0.5);
    }
    .ai-page-header h1 {
        font-size: 2.8rem; font-weight: 800; margin-bottom: 10px;
        background: linear-gradient(to right, #4f46e5, #9333ea);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .ai-page-header p { font-size: 1.1rem; color: #64748b; max-width: 600px; margin: 0 auto; }

    /* --- TOOLS GRID --- */
    .tools-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px; padding-bottom: 60px;
    }

    /* --- TOOL CARD (Heavy UI) --- */
    .ai-card {
        background: #fff; border-radius: 24px; padding: 30px;
        border: 1px solid #f1f5f9; position: relative; overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .ai-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.15);
        border-color: #c7d2fe;
    }
    .ai-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
        background: linear-gradient(90deg, #4f46e5, #ec4899);
        opacity: 0; transition: 0.3s;
    }
    .ai-card:hover::before { opacity: 1; }

    .card-icon {
        width: 60px; height: 60px; border-radius: 16px;
        background: #eef2ff; color: var(--accent);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; margin-bottom: 20px; transition: 0.3s;
    }
    .ai-card:hover .card-icon { background: var(--accent); color: #fff; transform: rotate(-5deg) scale(1.1); }

    .card-title { font-size: 1.4rem; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
    .card-desc { font-size: 0.95rem; color: #64748b; line-height: 1.6; }
    
    .try-btn {
        margin-top: 20px; display: inline-block; font-weight: 700;
        color: var(--accent); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;
    }
    .try-btn i { transition: 0.2s; }
    .ai-card:hover .try-btn i { transform: translateX(5px); }

    /* --- MODAL (The Engine) --- */
    .ai-modal-overlay {
        position: fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px);
        z-index: 10000; display: none; align-items: center; justify-content: center;
        padding: 20px; animation: fadeIn 0.3s ease;
    }
    .ai-modal-box {
        background: #fff; width: 100%; max-width: 700px;
        border-radius: 24px; overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        display: flex; flex-direction: column; max-height: 90vh;
        animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .modal-header {
        padding: 25px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-header h2 { margin: 0; font-size: 1.5rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    .close-btn { background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; transition: 0.2s; }
    .close-btn:hover { color: #ef4444; transform: rotate(90deg); }

    .modal-body { padding: 30px; overflow-y: auto; }
    
    .ai-textarea {
        width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 16px;
        font-size: 1rem; font-family: inherit; resize: none; outline: none;
        transition: 0.3s; background: #fff;
    }
    .ai-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

    .generate-btn {
        width: 100%; padding: 16px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #fff; border: none; border-radius: 14px; font-size: 1.1rem; font-weight: 700;
        cursor: pointer; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 10px;
        transition: 0.3s; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
    }
    .generate-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4); }
    .generate-btn:disabled { opacity: 0.7; cursor: not-allowed; }

    /* RESULT AREA */
    .result-box {
        margin-top: 25px; padding: 25px; background: #f8fafc;
        border-radius: 16px; border: 1px solid #e2e8f0;
        display: none; position: relative;
    }
    .result-content { font-size: 1rem; color: #334155; line-height: 1.7; }
    .result-content b { color: #0f172a; }
    
    .copy-btn {
        position: absolute; top: 15px; right: 15px;
        background: #fff; border: 1px solid #cbd5e1; padding: 5px 12px;
        border-radius: 8px; font-size: 0.8rem; cursor: pointer; color: #475569;
        display: flex; align-items: center; gap: 5px; transition: 0.2s;
    }
    .copy-btn:hover { background: #f1f5f9; color: var(--accent); border-color: var(--accent); }

    /* Animations */
    @keyframes spin { 100% { transform: rotate(360deg); } }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { transform: translateY(50px); opacity:0; } to { transform: translateY(0); opacity:1; } }
    
    .loading-spinner {
        width: 24px; height: 24px; border: 3px solid rgba(255,255,255,0.3);
        border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite;
        display: none;
    }
</style>

<div class="main-content-wrapper">

    <div class="ai-page-header">
        <h1>✨ AI Power Tools</h1>
        <p>Supercharge your social media growth with 10+ Advanced AI Tools. <br> Powered by <b>IsrarLiaqat.com</b> Intelligence.</p>
    </div>

    <div class="tools-grid">
        
        <div class="ai-card" onclick="openTool('audit', '🔍 AI Profile Auditor', 'Enter your Profile Link (Insta/TikTok):')">
            <div class="card-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
            <div class="card-title">Profile Auditor</div>
            <div class="card-desc">Get a professional audit of your account and find out why you are not growing.</div>
            <div class="try-btn">Analyze Now <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('hook', '🪝 Viral Hook Gen', 'Enter Video Topic (e.g. Cooking, Tech):')">
            <div class="card-icon"><i class="fa-solid fa-magnet"></i></div>
            <div class="card-title">Viral Hooks</div>
            <div class="card-desc">Generate explosive start lines (hooks) to keep viewers watching till the end.</div>
            <div class="try-btn">Create Hooks <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('plan', '📅 7-Day Growth Plan', 'What is your Goal? (e.g. 10k Followers):')">
            <div class="card-icon"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="card-title">Growth Planner</div>
            <div class="card-desc">Get a complete 7-day content & promotion strategy tailored for you.</div>
            <div class="try-btn">Plan Now <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('caption', '✍️ Smart Caption', 'Describe your post:')">
            <div class="card-icon"><i class="fa-solid fa-pen-nib"></i></div>
            <div class="card-title">Smart Captions</div>
            <div class="card-desc">Auto-generate engaging captions with trending hashtags for maximum reach.</div>
            <div class="try-btn">Write Caption <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('finder', '🤖 Service Finder', 'What do you need? (e.g. Cheap Non-Drop Likes):')">
            <div class="card-icon"><i class="fa-solid fa-robot"></i></div>
            <div class="card-title">Service Finder</div>
            <div class="card-desc">Confused? Let AI recommend the best & cheapest service ID for your needs.</div>
            <div class="try-btn">Find Service <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('reply', '💬 Savage Reply', 'Paste the hate comment:')">
            <div class="card-icon"><i class="fa-solid fa-face-grin-tears"></i></div>
            <div class="card-title">Savage Reply</div>
            <div class="card-desc">Roast haters or give funny replies to comments instantly.</div>
            <div class="try-btn">Generate Reply <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('email', '📧 Cold Email Writer', 'Who are you emailing and why?')">
            <div class="card-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
            <div class="card-title">Email Writer</div>
            <div class="card-desc">Write professional sales emails to pitch your services to clients.</div>
            <div class="try-btn">Write Email <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('bio', '🆔 Bio Generator', 'Tell us about yourself/niche:')">
            <div class="card-icon"><i class="fa-solid fa-id-card"></i></div>
            <div class="card-title">Bio Generator</div>
            <div class="card-desc">Create aesthetic and professional bios for Instagram & TikTok.</div>
            <div class="try-btn">Create Bio <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('idea', '💡 Viral Ideas', 'Your Niche (e.g. Gaming, Beauty):')">
            <div class="card-icon"><i class="fa-solid fa-lightbulb"></i></div>
            <div class="card-title">Video Ideas</div>
            <div class="card-desc">Get 5 unique video concepts that have high viral potential.</div>
            <div class="try-btn">Get Ideas <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="ai-card" onclick="openTool('hashtag', '#️⃣ Hashtag Gen', 'Enter Topic (e.g. Gym):')">
            <div class="card-icon"><i class="fa-solid fa-hashtag"></i></div>
            <div class="card-title">Hashtag Gen</div>
            <div class="card-desc">Generate sets of low, medium, and high volume hashtags.</div>
            <div class="try-btn">Get Tags <i class="fa-solid fa-arrow-right"></i></div>
        </div>

    </div>
</div>

<div id="aiModal" class="ai-modal-overlay" onclick="if(event.target===this) closeModal()">
    <div class="ai-modal-box">
        <div class="modal-header">
            <h2 id="mTitle"><i class="fa-solid fa-wand-magic-sparkles"></i> Tool Name</h2>
            <button class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <textarea id="mInput" class="ai-textarea" rows="4" placeholder="Type here..."></textarea>
            
            <button class="generate-btn" onclick="runAI()" id="genBtn">
                <div class="loading-spinner" id="spinIcon"></div>
                <span id="btnText">Generate Magic ✨</span>
            </button>

            <div class="result-box" id="resultBox">
                <button class="copy-btn" onclick="copyResult()"><i class="fa-regular fa-copy"></i> Copy</button>
                <div class="result-content" id="aiResult"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentToolId = '';

function openTool(id, title, placeholder) {
    currentToolId = id;
    document.getElementById('mTitle').innerHTML = '<i class="fa-solid fa-robot"></i> ' + title;
    document.getElementById('mInput').placeholder = placeholder;
    document.getElementById('mInput').value = '';
    document.getElementById('resultBox').style.display = 'none';
    document.getElementById('aiModal').style.display = 'flex';
    document.getElementById('mInput').focus();
}

function closeModal() {
    document.getElementById('aiModal').style.display = 'none';
}

function runAI() {
    const input = document.getElementById('mInput').value;
    if(!input) return alert("Please enter some details!");

    const btn = document.getElementById('genBtn');
    const btnText = document.getElementById('btnText');
    const spinner = document.getElementById('spinIcon');
    const resultBox = document.getElementById('resultBox');
    const resultDiv = document.getElementById('aiResult');

    // UI Loading State
    btn.disabled = true;
    btnText.innerText = "AI is thinking...";
    spinner.style.display = 'block';
    resultBox.style.display = 'none';

    let formData = new FormData();
    formData.append('tool_id', currentToolId);
    formData.append('user_input', input);

    fetch('../includes/ai_tool_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text()) // Pehle Text lo (JSON nahi)
    .then(rawText => {
        // Reset Button
        btn.disabled = false;
        btnText.innerText = "Regenerate ✨";
        spinner.style.display = 'none';

        try {
            // Koshish karo JSON parse karne ki
            const data = JSON.parse(rawText);
            
            if (data.reply) {
                resultDiv.innerHTML = data.reply;
                resultBox.style.display = 'block';
            } else {
                alert("AI Error: " + JSON.stringify(data));
            }
        } catch (e) {
            // Agar JSON fail hua, to ASLI ERROR dikhao
            console.error("Server Error:", rawText);
            alert("SERVER ERROR (Check Console or see below):\n\n" + rawText.substring(0, 300)); // Pehle 300 chars dikhao
            resultDiv.innerHTML = "<span style='color:red;'><b>Critical Error:</b><br>" + rawText + "</span>";
            resultBox.style.display = 'block';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btnText.innerText = "Try Again";
        spinner.style.display = 'none';
        alert("Network Error: " + err.message);
    });
}

function copyResult() {
    const text = document.getElementById('aiResult').innerText;
    navigator.clipboard.writeText(text);
    alert("Copied to clipboard!");
}
</script>

<?php include '_footer.php'; ?>