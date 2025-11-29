<?php
include '_header.php';

// --- ADD TUTORIAL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_video'])) {
    $title = sanitize($_POST['title']);
    $link = trim($_POST['video_link']); // YouTube Link
    $desc = sanitize($_POST['description']);
    
    if (!empty($title) && !empty($link)) {
        $stmt = $db->prepare("INSERT INTO tutorials (title, video_link, description) VALUES (?, ?, ?)");
        $stmt->execute([$title, $link, $desc]);
        echo "<script>window.location='tutorials.php?success=added';</script>";
    }
}

// --- DELETE TUTORIAL ---
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM tutorials WHERE id = ?")->execute([$_GET['delete']]);
    echo "<script>window.location='tutorials.php';</script>";
}

// Fetch All
$videos = $db->query("SELECT * FROM tutorials ORDER BY id DESC")->fetchAll();

// Helper to get YouTube ID
function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? '';
}
?>

<div class="container-fluid" style="padding:30px;">
    <h2 class="mb-4 fw-bold">📚 Video Tutorials Manager</h2>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">Add New Video</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Video Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. How to use Netflix" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">YouTube Link</label>
                            <input type="url" name="video_link" class="form-control" placeholder="https://youtu.be/..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Short details..."></textarea>
                        </div>
                        <button type="submit" name="add_video" class="btn btn-dark w-100">Publish Video</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Uploaded Tutorials</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Thumbnail</th>
                                <th>Title</th>
                                <th>Link</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($videos)): ?>
                                <tr><td colspan="4" class="text-center p-4 text-muted">No tutorials added yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($videos as $v): 
                                    $vidID = getYoutubeId($v['video_link']);
                                    $thumb = "https://img.youtube.com/vi/$vidID/mqdefault.jpg";
                                ?>
                                <tr>
                                    <td><img src="<?= $thumb ?>" width="80" class="rounded"></td>
                                    <td>
                                        <div class="fw-bold"><?= $v['title'] ?></div>
                                        <small class="text-muted"><?= substr($v['description'], 0, 50) ?>...</small>
                                    </td>
                                    <td><a href="<?= $v['video_link'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a></td>
                                    <td>
                                        <a href="?delete=<?= $v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '_footer.php'; ?>