<?php
include '_smm_header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* --- 🎨 PREMIUM THEME --- */
:root {
    --primary: #4f46e5;       /* Royal Blue */
    --secondary: #7c3aed;     /* Deep Purple */
    --bg-body: #f8fafc;
    --card-bg: #ffffff;
    --text-dark: #0f172a;
    --text-grey: #64748b;
    --border: #e2e8f0;
    --radius: 16px;
    --shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
}

body {
    background-color: var(--bg-body);
    font-family: 'Outfit', sans-serif;
    color: var(--text-dark);
    background-image: 
        radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.08) 0px, transparent 50%),
        radial-gradient(at 100% 100%, rgba(124, 58, 237, 0.08) 0px, transparent 50%);
    background-attachment: fixed;
}

.tools-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

.page-header { text-align: center; margin-bottom: 50px; }
.page-title { 
    font-size: 2.5rem; font-weight: 800; margin: 0; 
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.page-desc { color: var(--text-grey); margin-top: 10px; font-size: 1.1rem; }

/* --- TABS --- */
.cat-tabs {
    display: flex; justify-content: center; gap: 10px; margin-bottom: 40px; flex-wrap: wrap;
}
.tab-btn {
    background: #fff; border: 1px solid var(--border); padding: 10px 20px;
    border-radius: 50px; font-weight: 600; color: var(--text-grey); cursor: pointer;
    transition: 0.3s; box-shadow: var(--shadow); display: flex; align-items: center; gap: 8px;
}
.tab-btn:hover { transform: translateY(-2px); border-color: var(--primary); }
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); }

/* --- GRID --- */
.tool-section { display: none; animation: fadeIn 0.4s ease-out; }
.tool-section.active { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }

.tool-card {
    background: var(--card-bg); border-radius: var(--radius); padding: 25px;
    box-shadow: var(--shadow); border: 1px solid var(--border);
    transition: all 0.3s; position: relative; overflow: hidden;
}
.tool-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.15); border-color: var(--primary); }

.card-head { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
.card-icon { 
    width: 45px; height: 45px; background: #eff6ff; border-radius: 12px; 
    display: flex; align-items: center; justify-content: center; font-size: 22px; 
}
.tool-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-dark); }
.tool-desc { font-size: 0.85rem; color: var(--text-grey); margin-bottom: 20px; line-height: 1.5; min-height: 40px; }

/* INPUTS */
.tool-input, .tool-textarea, .tool-select {
    width: 100%; padding: 12px; border: 2px solid #f1f5f9; border-radius: 10px;
    font-size: 0.9rem; transition: 0.3s; outline: none; background: #f8fafc;
    color: var(--text-dark); font-family: inherit; margin-bottom: 10px;
}
.tool-input:focus { border-color: var(--primary); background: #fff; }

.btn-tool {
    width: 100%; padding: 12px; background: var(--primary); color: #fff;
    border: none; border-radius: 10px; font-weight: 700; cursor: pointer; 
    transition: 0.3s; font-size: 0.9rem;
}
.btn-tool:hover { filter: brightness(1.1); transform: translateY(-2px); }

/* RESULTS */
.result-box {
    margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 10px;
    display: none; text-align: center; border: 1px dashed #cbd5e1;
}
.fancy-item {
    padding: 10px; background: #fff; margin-bottom: 5px; border-radius: 8px;
    cursor: pointer; border: 1px solid #eee; font-size: 1.1rem;
    display: flex; justify-content: space-between; align-items: center;
}
.fancy-item:hover { border-color: var(--primary); background: #eff6ff; }

@keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

/* Mobile */
@media (max-width: 768px) {
    .tools-grid { grid-template-columns: 1fr; }
    .cat-tabs { gap: 8px; }
}
</style>

<div class="tools-container">
    <div class="page-header">
        <h1 class="page-title">Free Tools Kit</h1>
        <p class="page-desc">Free tools to boost your social media game.</p>
    </div>

    <div class="cat-tabs">
        <button class="tab-btn active" onclick="showCat('social', this)">🔥 Social Media</button>
        <button class="tab-btn" onclick="showCat('text', this)">✍️ Text & Fonts</button>
        <button class="tab-btn" onclick="showCat('dev', this)">💻 Web Tools</button>
        <button class="tab-btn" onclick="showCat('calc', this)">🧮 Calculators</button>
    </div>

    <div id="social" class="tool-section active">
        
        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🖼️</div><h3 class="tool-title">YT Thumbnail</h3></div>
            <p class="tool-desc">Kisi bhi YouTube video ka thumbnail HD mein download karein.</p>
            <input type="text" id="yt-url" class="tool-input" placeholder="Paste YouTube Link...">
            <button class="btn-tool" onclick="getThumbnail()">Get Image</button>
            <div id="yt-result" class="result-box">
                <img id="yt-img" src="" style="width:100%; border-radius:8px; margin-bottom:10px;">
                <a id="yt-link" href="#" download class="btn-tool" style="background:#10b981; display:block; text-decoration:none;">Download HD</a>
            </div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">💬</div><h3 class="tool-title">WhatsApp Link</h3></div>
            <p class="tool-desc">Number save kiye bina direct chat link banayen.</p>
            <input type="number" id="wa-num" class="tool-input" placeholder="923001234567">
            <input type="text" id="wa-msg" class="tool-input" placeholder="Message (Optional)">
            <button class="btn-tool" onclick="genWaLink()">Generate Link</button>
            <div id="wa-result" class="result-box">
                <input type="text" id="wa-final" class="tool-input" readonly>
                <button class="btn-tool" onclick="copyText('wa-final')" style="padding:8px; background:#333;">Copy Link</button>
            </div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">#️⃣</div><h3 class="tool-title">Hashtag Gen</h3></div>
            <p class="tool-desc">Apni post ke liye viral hashtags generate karein.</p>
            <select id="hash-cat" class="tool-select">
                <option value="travel">Travel / Safar</option>
                <option value="food">Food / Khana</option>
                <option value="gym">Gym / Fitness</option>
                <option value="tech">Tech / Coding</option>
                <option value="funny">Funny / Memes</option>
            </select>
            <button class="btn-tool" onclick="genHash()">Get Hashtags</button>
            <div id="hash-result" class="result-box">
                <textarea id="hash-out" class="tool-textarea" style="height:80px"></textarea>
                <button class="btn-tool" onclick="copyText('hash-out')" style="padding:8px; background:#333;">Copy Tags</button>
            </div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">👤</div><h3 class="tool-title">Insta Bio Ideas</h3></div>
            <p class="tool-desc">Apne Instagram/TikTok ke liye stylish bio banayen.</p>
            <select id="bio-type" class="tool-select">
                <option value="cool">Cool / Attitude</option>
                <option value="prof">Business / Pro</option>
                <option value="love">Love / Sad</option>
            </select>
            <button class="btn-tool" onclick="genBio()">Generate Bio</button>
            <div id="bio-result" class="result-box">
                <textarea id="bio-out" class="tool-textarea" style="height:80px"></textarea>
                <button class="btn-tool" onclick="copyText('bio-out')" style="padding:8px; background:#333;">Copy Bio</button>
            </div>
        </div>
    </div>

    <div id="text" class="tool-section">
        
        <div class="tool-card">
            <div class="card-head"><div class="card-icon">✨</div><h3 class="tool-title">Fancy Fonts</h3></div>
            <p class="tool-desc">Normal text ko stylish font mein badlein.</p>
            <input type="text" id="font-in" class="tool-input" placeholder="Type here..." oninput="genFonts()">
            <div id="font-list" style="margin-top:15px; max-height:200px; overflow-y:auto;"></div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🔠</div><h3 class="tool-title">Case Converter</h3></div>
            <p class="tool-desc">Text ko UPPERCASE ya lowercase mein karein.</p>
            <textarea id="case-text" class="tool-textarea" rows="3" placeholder="Type here..."></textarea>
            <div style="display:flex; gap:5px;">
                <button class="btn-tool" style="padding:8px;" onclick="toCase('upper')">UPPER</button>
                <button class="btn-tool" style="padding:8px;" onclick="toCase('lower')">lower</button>
                <button class="btn-tool" style="padding:8px;" onclick="toCase('cap')">Capital</button>
            </div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">📝</div><h3 class="tool-title">Word Counter</h3></div>
            <p class="tool-desc">Check karein kitne words aur characters hain.</p>
            <textarea id="word-text" class="tool-textarea" rows="3" oninput="countWords()" placeholder="Paste text..."></textarea>
            <div style="display:flex; gap:15px; margin-top:10px; font-weight:bold;">
                <span style="color:var(--primary)">Words: <span id="wc">0</span></span>
                <span style="color:var(--secondary)">Chars: <span id="cc">0</span></span>
            </div>
        </div>
    </div>

    <div id="dev" class="tool-section">
        
        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🌍</div><h3 class="tool-title">My IP Info</h3></div>
            <p class="tool-desc">Apna IP Address aur Location check karein.</p>
            <button class="btn-tool" onclick="getIp()">Check My IP</button>
            <div id="ip-res" class="result-box" style="text-align:left"></div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🔐</div><h3 class="tool-title">Strong Password</h3></div>
            <p class="tool-desc">Secure aur strong password generate karein.</p>
            <button class="btn-tool" onclick="genPass()">Generate Pass</button>
            <div id="pass-res" class="result-box"><h3 id="pass-txt" style="margin:0"></h3></div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🔳</div><h3 class="tool-title">QR Generator</h3></div>
            <p class="tool-desc">Kisi bhi link ka QR Code banayen.</p>
            <input type="text" id="qr-text" class="tool-input" placeholder="Enter Link...">
            <button class="btn-tool" onclick="genQr()">Generate QR</button>
            <div id="qr-result" class="result-box"><img id="qr-img" src="" style="width:150px;"></div>
        </div>
    </div>

    <div id="calc" class="tool-section">
        
        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🎂</div><h3 class="tool-title">Age Calculator</h3></div>
            <p class="tool-desc">Apni exact umer (Years, Days) check karein.</p>
            <input type="date" id="dob" class="tool-input">
            <button class="btn-tool" onclick="calcAge()">Calculate Age</button>
            <div id="age-res" class="result-box" style="font-weight:bold;"></div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">🏷️</div><h3 class="tool-title">Discount Calc</h3></div>
            <p class="tool-desc">Sale price calculate karein.</p>
            <input type="number" id="disc-price" class="tool-input" placeholder="Original Price">
            <input type="number" id="disc-off" class="tool-input" placeholder="Discount % (e.g. 20)">
            <button class="btn-tool" onclick="calcDisc()">Calculate</button>
            <div id="disc-res" class="result-box"></div>
        </div>

        <div class="tool-card">
            <div class="card-head"><div class="card-icon">💰</div><h3 class="tool-title">CPM Calculator</h3></div>
            <p class="tool-desc">Ad cost per 1000 views check karein.</p>
            <input type="number" id="cpm-cost" class="tool-input" placeholder="Total Cost">
            <input type="number" id="cpm-views" class="tool-input" placeholder="Total Views">
            <button class="btn-tool" onclick="calcCpm()">Calculate CPM</button>
            <div id="cpm-res" class="result-box"></div>
        </div>
    </div>

</div>

<script>
// --- TABS LOGIC ---
function showCat(id, btn) {
    document.querySelectorAll('.tool-section').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
}

// --- FANCY FONT LOGIC (FIXED) ---
function genFonts() {
    let t = document.getElementById('font-in').value;
    if(!t) return;

    // Direct Character Mapping for reliability
    const normal = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    const bold   = "𝐚𝐛𝐜𝐝𝐞𝐟𝐠𝐡𝐢𝐣𝐤𝐥𝐦𝐧𝐨𝐩𝐪𝐫𝐬𝐭𝐮𝐯𝐰𝐱𝐲𝐳𝐀𝐁𝐂𝐃𝐄𝐅𝐆𝐇𝐈𝐉𝐊𝐋𝐌𝐍𝐎𝐏𝐐𝐑𝐒𝐓𝐔𝐕𝐖𝐗𝐘𝐙𝟎𝟏𝟐𝟑𝟒𝟓𝟔𝟕𝟖𝟗";
    const script = "𝓪𝓫𝓬𝓭𝓮𝓯𝓰𝓱𝓲𝓳𝓴𝓵𝓶𝓷𝓸𝓹𝓺𝓻𝓼𝓽𝓾𝓿𝔀𝔁𝔂𝔃𝓐𝓑𝓒𝓔𝓕𝓖𝓗𝓘𝓙𝓚𝓛𝓜𝓝𝓞𝓟𝓠𝓡𝓢𝓣𝓤𝓥𝓦𝓧𝓨𝓩0123456789";
    const circle = "ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨⓩⒶⒷⒸⒹⒺⒻⒼⒽⒾⒿⓀⓁⓂⓃⓄⓅⓆⓇⓈⓉⓊⓋⓌⓍⓎⓏ0①②③④⑤⑥⑦⑧⑨";
    const square = "𝕒𝕓𝕔𝕕𝕖𝕗𝕘𝕙𝕚𝕛𝕜𝕝𝕞𝕟𝕠𝕡𝕢𝕣𝕤𝕥𝕦𝕧𝕨𝕩𝕪𝕫𝔸𝔹ℂ𝔻𝔼𝔽𝔾ℍ𝕀𝕁𝕂𝕃𝕄ℕ𝕆ℙℚℝ𝕊𝕋𝕌𝕍𝕎𝕏𝕐ℤ0123456789";

    function mapText(text, source, dest) {
        let res = '';
        for(let i=0; i<text.length; i++) {
            let idx = source.indexOf(text[i]);
            res += idx !== -1 ? dest.substr(idx*2, 2) : text[i]; // Handling surrogate pairs for bold/script
            if (dest === circle || dest === square) { // Single char mapping fix
                 // Re-implementing simple array mapping for reliability
            }
        }
        return res;
    }
    
    // Simple Array Mapping for 100% Works
    const toBold = s => s.split('').map(c => {
        let i = normal.indexOf(c); return i > -1 ? [...bold][i] : c; 
    }).join('');
    
    const toScript = s => s.split('').map(c => {
        let i = normal.indexOf(c); return i > -1 ? [...script][i] : c; 
    }).join('');
    
    const toCircle = s => s.split('').map(c => {
        let i = normal.indexOf(c); return i > -1 ? [...circle][i] : c; 
    }).join('');

    let h = `
        <div class="fancy-item" onclick="copyStr('${toBold(t)}')"><b>Bold:</b> ${toBold(t)}</div>
        <div class="fancy-item" onclick="copyStr('${toScript(t)}')"><b>Script:</b> ${toScript(t)}</div>
        <div class="fancy-item" onclick="copyStr('${toCircle(t)}')"><b>Circle:</b> ${toCircle(t)}</div>
    `;
    document.getElementById('font-list').innerHTML = h;
}

// --- HELPERS ---
function copyText(id) { navigator.clipboard.writeText(document.getElementById(id).value || document.getElementById(id).innerText); alert('Copied!'); }
function copyStr(s) { navigator.clipboard.writeText(s); alert('Copied!'); }

// --- OTHER TOOLS ---
function getThumbnail() {
    let url = document.getElementById('yt-url').value;
    let id = url.match(/(?:youtu\.be\/|youtube\.com\/(?:.*v=|.*\/))([^"&?\/\s]{11})/);
    if(id && id[1]) {
        let i = `https://img.youtube.com/vi/${id[1]}/maxresdefault.jpg`;
        document.getElementById('yt-img').src=i; document.getElementById('yt-link').href=i;
        document.getElementById('yt-result').style.display='block';
    } else alert('Invalid Link');
}

function genWaLink() {
    let n = document.getElementById('wa-num').value;
    let m = document.getElementById('wa-msg').value;
    if(!n) return;
    let l = `https://wa.me/${n}?text=${encodeURIComponent(m)}`;
    document.getElementById('wa-final').value = l;
    document.getElementById('wa-result').style.display = 'block';
}

function genHash() {
    let c = document.getElementById('hash-cat').value;
    let t = {
        travel: "#travel #nature #photography #wanderlust #trip #adventure",
        food: "#food #foodie #instafood #yummy #delicious #dinner",
        gym: "#gym #fitness #workout #bodybuilding #motivation #fit",
        tech: "#tech #technology #coding #programming #developer #gadgets",
        funny: "#funny #memes #meme #fun #comedy #lol #jokes"
    };
    document.getElementById('hash-out').value = t[c];
    document.getElementById('hash-result').style.display='block';
}

function genBio() {
    let t = document.getElementById('bio-type').value;
    let b = "";
    if(t=='cool') b = "🌟 Living my best life\n✈️ Traveler | 🍔 Foodie\n👇 Check my links";
    if(t=='prof') b = "💼 Entrepreneur | 🚀 Startup\nHelping you grow\n📩 DM for business";
    if(t=='love') b = "💔 Silent Lover\n🎶 Music Addict\n🚫 No DM";
    document.getElementById('bio-out').value = b;
    document.getElementById('bio-result').style.display='block';
}

function toCase(t) {
    let el = document.getElementById('case-text');
    if(t=='upper') el.value=el.value.toUpperCase();
    if(t=='lower') el.value=el.value.toLowerCase();
    if(t=='cap') el.value=el.value.replace(/\b\w/g, l => l.toUpperCase());
}

function countWords() {
    let t = document.getElementById('word-text').value;
    document.getElementById('cc').innerText = t.length;
    document.getElementById('wc').innerText = t.trim() === '' ? 0 : t.trim().split(/\s+/).length;
}

function getIp() {
    fetch('https://ipapi.co/json/').then(r=>r.json()).then(d=>{
        document.getElementById('ip-res').innerHTML = `IP: <b>${d.ip}</b><br>City: ${d.city}<br>ISP: ${d.org}`;
        document.getElementById('ip-res').style.display='block';
    });
}

function genPass() {
    let c="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#";
    let p=""; for(let i=0;i<12;i++) p+=c.charAt(Math.floor(Math.random()*c.length));
    document.getElementById('pass-txt').innerText=p;
    document.getElementById('pass-res').style.display='block';
}

function genQr() {
    let t = document.getElementById('qr-text').value;
    if(!t) return;
    document.getElementById('qr-img').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(t)}`;
    document.getElementById('qr-result').style.display='block';
}

function calcAge() {
    let d = document.getElementById('dob').value;
    if(!d) return;
    let age = new Date(Date.now() - new Date(d).getTime()).getUTCFullYear() - 1970;
    document.getElementById('age-res').innerText = `You are ${age} years old.`;
    document.getElementById('age-res').style.display='block';
}

function calcDisc() {
    let p = parseFloat(document.getElementById('disc-price').value);
    let d = parseFloat(document.getElementById('disc-off').value);
    if(p && d) {
        let final = p - (p * (d/100));
        document.getElementById('disc-res').innerText = `Final Price: ${final.toFixed(2)}`;
        document.getElementById('disc-res').style.display='block';
    }
}

function calcCpm() {
    let c = parseFloat(document.getElementById('cpm-cost').value);
    let v = parseFloat(document.getElementById('cpm-views').value);
    if(c && v) {
        let cpm = (c / v) * 1000;
        document.getElementById('cpm-res').innerText = `CPM: ${cpm.toFixed(2)}`;
        document.getElementById('cpm-res').style.display='block';
    }
}
</script>

<?php include '_smm_footer.php'; ?>