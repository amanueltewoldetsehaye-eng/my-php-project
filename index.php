<?php 
session_start();

// Database connection
try {
    $conn = new PDO("mysql:host=localhost;dbname=perfectaxum_date", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Redirect if not logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}
// --- AJAX PROFILE PIC UPLOAD HANDLER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['profile_pic']) && isset($_POST['ajax'])) {
    // Only allow owner!
    if (!isset($_SESSION['userid']) || $_POST['userid'] != $_SESSION['userid']) {
        echo json_encode(["success"=>false,"error"=>"Unauthorized."]);
        exit;
    }

    $allowed = ['jpg', 'jpeg', 'png'];
    $maxDim = 800;
    $uploadDir = __DIR__ . "/uploads/user_pro_pics/";
    $webDir = "uploads/user_pro_pics/";
    $userid = $_SESSION['userid'];

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['profile_pic'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $error = "";

    if (!in_array($ext, $allowed)) {
        $error = "Invalid file type.";
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = "File too large.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error.";
    } else {
        $unique = bin2hex(random_bytes(12));
        $newName = $unique . '.' . $ext;
        $target = $uploadDir . $newName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $error = "Failed to move uploaded file.";
        } else {
            // Get original dimensions and type
            list($origW, $origH, $type) = getimagesize($target);

            switch ($type) {
                case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($target); break;
                case IMAGETYPE_PNG:  $img = imagecreatefrompng($target); break;
                default: $img = false;
            }

            if ($img) {
                if ($origW > $maxDim || $origH > $maxDim) {
                    if ($origW/$maxDim > $origH/$maxDim) {
                        $newW = $maxDim;
                        $newH = intval($origH * ($maxDim/$origW));
                    } else {
                        $newH = $maxDim;
                        $newW = intval($origW * ($maxDim/$origH));
                    }
                } else {
                    $newW = $origW;
                    $newH = $origH;
                }

                $resized = imagecreatetruecolor($newW, $newH);
                if ($type == IMAGETYPE_PNG) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                }
                imagecopyresampled($resized, $img, 0,0,0,0, $newW, $newH, $origW, $origH);
                switch ($type) {
                    case IMAGETYPE_JPEG: imagejpeg($resized, $target, 90); break;
                    case IMAGETYPE_PNG:  imagepng($resized, $target, 7); break;
                }
                imagedestroy($resized);
                imagedestroy($img);
            }

            // Save/Update DB
            $stmt = $conn->prepare("INSERT INTO profile_pic (userid, profile_pic) VALUES (?, ?) ON DUPLICATE KEY UPDATE profile_pic = ?");
            $stmt->execute([$userid, $newName, $newName]);
            // Return new image web path
            echo json_encode([
                "success" => true,
                "avatar" => $webDir . $newName . "?v=" . time()
            ]);
            exit;
        }
    }
    echo json_encode(["success" => false, "error" => $error]);
    exit;
}

// Fetch logged-in user info
$stmt = $conn->prepare("SELECT u.userid, u.username, u.gender FROM users u WHERE u.userid = ?");
$stmt->execute([$_SESSION['userid']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get profile pic from profile_pic table
$stmtPic = $conn->prepare("SELECT profile_pic FROM profile_pic WHERE userid = ?");
$stmtPic->execute([$_SESSION['userid']]);
$pic = $stmtPic->fetch(PDO::FETCH_ASSOC);

$uploadsDir = "uploads/user_pro_pics/";
$defaultMale = "uploads/static/male.jpeg";
$defaultFemale = "uploads/static/female.jpeg";

if (!empty($pic['profile_pic']) && file_exists(__DIR__ . "/uploads/user_pro_pics/" . $pic['profile_pic'])) {
    $avatar = $uploadsDir . $pic['profile_pic'] . "?v=" . time();
} else {
    $avatar = ($user['gender'] === 'male') ? $defaultMale : $defaultFemale;
}

?>

<!DOCTYPE html> 
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Axum Date</title>
  <link href="https://cdn.jsdelivr.net/npm/@iconscout/unicons/css/line.css" rel="stylesheet">
  <style>
* { margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }

body { background:var(--color-light); display:flex; flex-direction:column; align-items:center; }

:root{
--color-white: hsl(252, 30%, 100%);
--color-light: hsl(252, 30%, 95%);
--color-gray: hsl(252, 15%, 65%);
--color-primary: hsl(252, 75%, 60%);
--color-secondary: hsl(252, 100%, 90%);
--color-success: hsl(120, 95%, 65%);
--color-danger: hsl(0, 95%, 65%);
--color-dark: hsl(252, 30%, 17%);
--color-black: hsl(252, 30%, 10%);  
}

.navbar { display:flex; align-items:center; justify-content:space-between; width:100%; background:#fff; padding:10px 20px; box-shadow:0 2px 5px rgba(0,0,0,0.1); position:sticky; top:0; z-index:1000; }
.logo { font-size:20px; font-weight:bold; color:var(--color-dark); margin-left:20px; }

.search-bar { flex:1; display:flex; justify-content:center; position:relative; }
.search-bar input { width:50%; padding:7px 30px; border-radius:25px; border:1px solid #ccc; }

.search-input-wrapper { position:relative; width:50%; margin:0 auto; }
.search-input-wrapper input { width:100%; padding:7px 40px 7px 15px; border-radius:25px; border:1px solid #ccc; outline:none; }
.search-input-wrapper .search-icon-btn { position:absolute; top:50%; right:10px; transform:translateY(-50%); border:none; background:none; cursor:pointer; font-size:16px; color:#888; padding:0; }

#searchResults { position:absolute; top:100%; left:0; width:100%; background:#fff; border:1px solid #ddd; border-radius:0 0 25px 25px; margin-top:2px; box-shadow:0 4px 12px rgba(0,0,0,0.15); display:none; max-height:300px; overflow-y:auto; z-index:2000; }
#searchResults .result-item { display:flex; align-items:center; padding:10px; cursor:pointer; transition:background 0.2s ease; }
#searchResults .result-item:hover { background:#f0f2f5; }
#searchResults .result-item img { width:40px; height:40px; border-radius:50%; margin-right:10px; object-fit:cover; }
#searchResults .result-item span { font-size:14px; font-weight:500; }
#searchResults div:hover { background:#f0f2f5; }

.nav-profile { display:flex; align-items:center; margin-right:20px; gap:10px; }
.nav-profile img { width:50px; height:50px; border-radius:50%; object-fit:cover; }

.container { display:flex; margin-top:20px; width:80%; justify-content:space-between; }
.left-sidebar { width:20%; }
.main-content { width:50%; display:flex; flex-direction:column; gap:20px; }
.right-sidebar { width:25%; display:flex; flex-direction:column; gap:20px; }

.info-card { background:#fff; padding:20px; border-radius:10px; margin-bottom:20px; text-align:center; }
.profile-info { display:flex; align-items:center; justify-content:center; margin-bottom:15px; }
.profile-info img { width:50px; height:50px; border-radius:50%; object-fit:cover; margin-right:10px; }
.profile-info .username { font-weight:bold; font-size:16px; }
.follow-btn { padding:8px 20px; background:var(--color-primary); color:#fff; border:none; border-radius:20px; cursor:pointer; font-size:14px; }
.follow-btn:hover { background:var(--color-dark); }

.sidebar-card { background:#fff; padding:15px; border-radius:10px; max-height:300px; overflow-y:auto; }
.sidebar-item { display:flex; align-items:center; padding:10px; cursor:pointer; border-radius:5px; margin:5px 0; transition:all 0.3s ease; }
.sidebar-item:hover { background:#e0e7ff; transform:translateX(5px); }
.sidebar-item.active { background:#cbd5e1; font-weight:bold; color:#1d4ed8; }
.sidebar-item img { width:20px; height:20px; margin-right:10px; }

.right-card { background:#fff; padding:15px; border-radius:10px; }
.right-card h3 { margin-bottom:10px; }
.message-search, .following-search { margin-bottom:10px; display:flex; justify-content:center; }
.message-search input, .following-search input { width:90%; padding:5px 10px; border-radius:20px; border:1px solid #ccc; }
.messages-list, .following-list { max-height:200px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; }
.message-item, .following-item { display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer; }
.message-item img, .following-item img { width:35px; height:35px; border-radius:50%; object-fit:cover; }
.message-text { display:flex; flex-direction:column; }
.message-text .username { font-weight:bold; }
.message-text .timestamp { font-size:12px; color:#888; }

.notification-popup { position:fixed; top:80px; left:20%; width:250px; max-height:400px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.2); overflow-y:auto; display:none; z-index:1500; padding:10px; }
.notification-popup h4 { margin-bottom:10px; font-size:16px; border-bottom:1px solid #ddd; padding-bottom:5px; }
.notifications-list .notification-item { display:flex; align-items:center; gap:10px; padding:8px; border-bottom:1px solid #eee; font-size:14px; cursor:pointer; transition:background 0.2s; }
.notifications-list .notification-item:hover { background:#f0f2f5; }
.notifications-list .notification-item img { width:35px; height:35px; border-radius:50%; object-fit:cover; }
.notification-text { display:flex; flex-direction:column; }
.notification-text .username { font-weight:bold; }
.notification-text .msg { font-size:13px; color:#555; }
.notification-text .timestamp { font-size:11px; color:#888; }

.post-card { background:#fff; padding:15px; border-radius:10px; position:relative; }
.post-header { display:flex; align-items:center; gap:10px; }
.post-header img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
.options-btn { position:absolute; top:15px; right:15px; cursor:pointer; font-size:20px; color:#555; }
.post-image { width:100%; border-radius:10px; margin-top:10px; }
.icon-btn { margin-top:10px; cursor:pointer; width:18px; height:18px; vertical-align:middle; }
.post-card .icon-btn { font-size:24px; width:auto; height:auto; }
.icon-container { margin-top:10px; display:flex; gap:35px; }
.post-footer { display:flex; justify-content:space-between; margin-top:5px; font-size:14px; color:#555; }

.chat-container { position:fixed; bottom:10px; right:10px; display:flex; flex-direction:row; gap:10px; z-index:2000; }
@media (max-height:500px) { .chat-container { bottom:60px; } }

.chat-box { width:320px; height:350px; background:#fff; border-radius:10px 10px 0 0; box-shadow:0 2px 10px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden; font-size:14px; }
.chat-box.minimized { height:40px; width:200px; border-radius:10px; flex-direction:row; align-items:center; overflow:hidden; bottom:10px!important; background:transparent; box-shadow:0 2px 10px rgba(0,0,0,0.2); }
.chat-box.minimized .chat-header { flex:1; background:#007bff; border-radius:10px 10px 0 0; }
.chat-box.minimized .chat-messages, .chat-box.minimized .chat-input { display:none; }

.chat-header { background:#007bff; color:#fff; padding:8px; display:flex; align-items:center; justify-content:space-between; cursor:move; }
.chat-header .user-info { display:flex; align-items:center; gap:5px; }
.chat-header img { width:30px; height:30px; border-radius:50%; object-fit:cover; }
.chat-header .controls button { background:none; border:none; color:#fff; font-size:16px; margin-left:5px; cursor:pointer; }

.chat-messages { padding:5px; flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:5px; background:#f0f2f5; min-height:0; max-height:none; }
.message { padding:5px 10px; border-radius:15px; max-width:80%; word-wrap:break-word; }
.message.left { background:#e0e7ff; align-self:flex-start; }
.message.right { background:#007bff; color:#fff; align-self:flex-end; }

.chat-input { display:flex; align-items:center; border-top:1px solid #ccc; padding:6px 8px; gap:5px; position:relative; flex-shrink:0; }
.chat-input input { flex:1; border:none; padding:6px 12px; border-radius:3px; outline:none; min-height:28px; }
.chat-input button { border:none; background:none; color:#007bff; padding:6px 12px; border-radius:5px; cursor:pointer; font-size:16px; }
.chat-input input { flex:1; border:none; padding:8px; border-radius:5px; outline:none; }

#overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); backdrop-filter:blur(5px); display:none; justify-content:center; align-items:center; z-index:3000; }
#settingsPopup { background:#fff; width:800px; max-width:95%; height:800px; max-height:90%; padding:30px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.4); position:relative; text-align:center; overflow-y:auto; }
#settingsPopup .close-btn { position:absolute; top:10px; right:15px; font-size:20px; font-weight:bold; color:#555; cursor:pointer; }
#settingsPopup .close-btn:hover { color:red; }



#settingsForm { display:flex; flex-direction:column; align-items:center; gap:15px; }
.input-group { display:flex; flex-direction:column; width:70%; max-width:400px; text-align:left; }
.input-group label { margin-bottom:5px; font-weight:bold; font-size:14px; }
.input-group input { padding:10px; border-radius:5px; border:1px solid #ccc; font-size:14px; width:100%; }

.nav-profile { display:flex; align-items:center; margin-right:20px; gap:10px; }
.create-btn { padding:6px 15px; background:var(--color-primary); color:#fff; border:none; border-radius:20px; cursor:pointer; font-size:14px; transition:background 0.3s; }
.create-btn:hover { background:var(--color-dark); }

.icon-btn.comment:hover { color:#1da1f2; }
.comment-popup { display:none; margin-top:10px; width:100%; }
.comment-popup input { width:100%; padding:8px 12px; border-radius:20px; border:1px solid #ccc; outline:none; }

#themeOverlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(6px); display:none; justify-content:center; align-items:center; z-index:4000; }
#themeDialog { background:#fff; width:500px; max-width:95%; padding:25px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.4); text-align:center; position:relative; }
#themeDialog .close-btn { position:absolute; top:10px; right:15px; font-size:24px; font-weight:bold; color:#555; cursor:pointer; transition:color 0.3s, text-shadow 0.3s; }
#themeDialog .close-btn:hover { color:red; text-shadow:0 0 10px red; }

/* Likes Popup */
.likes-popup { position:absolute; left:0; bottom:-10px; background:#fff; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.2); width:250px; max-height:300px; overflow-y:auto; display:none; transform:translateY(10px); opacity:0; transition:all 0.3s ease; z-index:2000; }
.likes-popup.active { display:block; opacity:1; transform:translateY(0); }
.likes-popup .like-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid #eee; cursor:pointer; transition:background 0.2s; }
.likes-popup .like-item:hover { background:#f5f5f5; }
.likes-popup .like-item img { width:35px; height:35px; border-radius:50%; object-fit:cover; }
.likes-popup .like-item span { font-size:14px; font-weight:500; }

.comments-section { display:none; margin-top:10px; max-height:250px; overflow-y:auto; border-top:1px solid #eee; padding-top:10px; }
.comment-item { display:flex; gap:10px; margin-bottom:12px; }
.comment-item img { width:35px; height:35px; border-radius:50%; object-fit:cover; }
.comment-details { flex:1; }
.comment-details .username { font-weight:bold; font-size:14px; }
.comment-details .date { font-size:12px; color:#888; }
.comment-details .text { font-size:14px; margin:5px 0; }
.comment-actions { display:flex; gap:15px; font-size:14px; color:#555; }
.comment-actions i { cursor:pointer; }
.comment-actions i:hover { color:#1877f2; }

.options-dropdown { position:absolute; top:40px; right:15px; background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.2); display:none; z-index:100; min-width:100px; }
.options-dropdown button { width:100%; padding:8px 12px; border:none; background:none; text-align:left; cursor:pointer; border-radius:8px; transition:background 0.2s; }
.options-dropdown button:hover { background:#f0f2f5; color:red; }

.share-popup { position:absolute; top:50px; left:10px; width:200px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.2); padding:10px; display:none; z-index:1500; }
.share-popup h4 { margin-bottom:10px; font-size:16px; border-bottom:1px solid #ddd; padding-bottom:5px; }
.share-popup .share-item { display:flex; align-items:center; gap:10px; padding:5px; cursor:pointer; transition:background 0.2s; }
.share-popup .share-item:hover { background:#f0f2f5; }
.share-popup .share-item img { width:35px; height:35px; border-radius:50%; object-fit:cover; }
/* Glow animation for Messages card */
@keyframes pulseGlow {
  0% { box-shadow: 0 0 5px hsl(252, 75%, 45%); }
  50% { box-shadow: 0 0 20px hsl(252, 75%, 45%); }
  100% { box-shadow: 0 0 5px hsl(252, 75%, 45%); }
}

.right-card.glow {
  animation: pulseGlow 1.5s infinite;
}

.profile-upload-label {
      cursor: pointer;
      display: inline-block;
      position: relative;
    }
    .profile-upload-label input[type="file"] {
      display: none;
    }
    .profile-upload-label:hover img {
      outline: 3px solid #1d4ed8;
    }
    .profile-upload-label::after {
      content: "✚";
      position: absolute;
      bottom: 7px;
      right: 7px;
      color: #1d4ed8;
      background: #fff;
      border-radius: 50%;
      width: 21px;
      height: 21px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      border: 1px solid #1d4ed8;
      opacity: 0.9;
      pointer-events: none;
    }
    /* Ensure avatars are always perfectly circular */
    #navbarAvatar, #sidebarAvatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      aspect-ratio: 1 / 1;
      background: #eee;
      display: block;
    } 
.profile-info {
  position: relative;
}
.profile-options-btn {
  cursor: pointer;
  font-size: 22px;
  color: #555;
  user-select: none;
  background: none;
  border: none;
  padding: 5px 10px;
  position: absolute;
  top: 0; right: 0;
  z-index: 12;
}
.profile-options-dropdown {
  display: none;
  position: absolute;
  top: 35px;
  right: 0;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  z-index: 500;
  min-width: 100px;
}
.profile-options-dropdown button {
  width: 100%;
  padding: 10px 15px;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.2s, color 0.2s, text-shadow 0.2s;
  font-size: 15px;
  color: #e11d48;
  font-weight: bold;
}
.profile-options-dropdown button:hover {
  background: #ffe4e6;
  color: #fff;
  text-shadow: 0 0 6px #e11d48, 0 0 18px #e11d48;
  animation: glowDelete 0.8s alternate infinite;
}
@keyframes glowDelete {
  from { box-shadow: 0 0 0 #e11d48; }
  to { box-shadow: 0 0 16px #e11d48; }
}
</style>
  <!-- Emoji Picker Library -->
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>

</head>
<body>

 <!-- Navbar -->
  <div class="navbar">
    <div class="logo">Axum Date</div>
    <div class="search-bar">
      <div class="search-input-wrapper">
        <input type="text" id="searchInput" placeholder="Search for people">
        <button class="search-icon-btn">🔍</button>
        <div id="searchResults"></div>
      </div>
    </div>
    <div class="nav-profile">
      <!-- Only owner can upload! -->
      <form id="profilePicForm" enctype="multipart/form-data" style="display:inline;">
        <label class="profile-upload-label">
          <img src="<?php echo htmlspecialchars($avatar); ?>" id="navbarAvatar" alt="Profile">
          <input type="file" name="profile_pic" id="profilePicInput" accept=".jpg,.jpeg,.png">
          <input type="hidden" name="userid" value="<?php echo $_SESSION['userid']; ?>">
        </label>
      </form>
    </div>
  </div>


  <!-- Container -->
  <div class="container">
    <!-- Notification Pop-up -->
    <div id="notificationPopup" class="notification-popup">
      <h4>Notifications</h4>
      <div class="notifications-list">
        <!-- Notifications will be dynamically added here -->
      </div>
    </div>

    <!-- Left Sidebar -->
    <div class="left-sidebar">

      <!-- Info Card -->
      <div class="info-card">
  <div class="profile-info" style="position:relative;">
    <img src="<?php echo htmlspecialchars($avatar); ?>" id="sidebarAvatar" alt="Profile">
    <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
    <!-- Three dot menu -->
    <span class="profile-options-btn" style="margin-left:auto; cursor:pointer; font-size:22px; color:#555; position:absolute; top:0; right:0;">&#8230;</span>
    <div class="profile-options-dropdown">
      <button class="profile-delete-btn">Delete</button>
    </div>
  </div>
  <button class="follow-btn">Follow (32)</button>
</div>
      <!-- Sidebar Options -->
<div class="sidebar-card">
  <div class="sidebar-item active">
    <img src="https://cdn-icons-png.flaticon.com/512/1946/1946436.png">
    <span>Home</span>
  </div>
  <div class="sidebar-item">
    <a href="photos.php" style="text-decoration:none; color:inherit; display:flex; align-items:center;">
      <img src="https://cdn-icons-png.flaticon.com/512/685/685655.png">
      <span>Photos</span>
    </a>
  </div>
  <div class="sidebar-item">
    <img src="https://cdn-icons-png.flaticon.com/512/1827/1827349.png">
    <span>Notifications</span>
  </div>
  <div class="sidebar-item">
    <img src="https://cdn-icons-png.flaticon.com/512/2462/2462719.png">
    <span>Messages</span>
  </div>
  <div class="sidebar-item">
    <a href="memories.php" style="text-decoration:none; color:inherit; display:flex; align-items:center;">
      <img src="https://cdn-icons-png.flaticon.com/512/747/747310.png">
      <span>Memories</span>
    </a>
  </div>
  <div class="sidebar-item">
    <img src="https://cdn-icons-png.flaticon.com/512/2099/2099058.png">
    <span>Settings</span>
  </div>


  <!-- theme changer option to be functional after update (under construction )
<div class="sidebar-item">
  <img src="https://cdn-icons-png.flaticon.com/512/4727/4727255.png" alt="Theme Icon">
  <span>Theme</span>
</div>


  under construction code above this comment-->



  <div class="sidebar-item">
    <img src="https://cdn-icons-png.flaticon.com/512/1828/1828479.png">
    <span>Logout</span>
  </div>
</div>
    </div>

    <!-- Middle Section -->
<div class="main-content">
  <!-- Individual Post Cards -->
  <div class="post-card">
    <div class="post-header">
      <img src="https://via.placeholder.com/40" alt="User">
      <span>Username</span>
      <span class="options-btn">...</span>
      <div class="options-dropdown">
  <button class="delete-btn">Delete</button>
</div>

    </div>
    <div class="post-body">
      <img src="body-4.jpg" class="post-image" alt="Post">
    </div>
    <div class="icon-container">
      <i class="uil uil-heart icon-btn"></i>
      <i class="uil uil-comment icon-btn"></i>
      <i class="uil uil-share-alt icon-btn" id="sharebtn"></i>
    </div>
    <!-- Share Popup -->
<div class="share-popup">
  <h4>Share with</h4>
  <div class="share-list">
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Alice">
      <span>Alice</span>
    </div>
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Bob">
      <span>Bob</span>
    </div>
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Charlie">
      <span>Charlie</span>
    </div>
  </div>
</div>

    <div class="comment-popup">
      <input type="text" placeholder="Comment here...">
    </div>

    <div class="post-footer">
      <span>You and 458 people liked this post</span>
      <span>See all comments</span>
    </div>
    <div class="comments-section">
  <div class="comment-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <div class="comment-details">
      <div class="username">Alice</div>
      <div class="date">Sep 28, 2025</div>
      <div class="text">This is such a nice post!</div>
      <div class="comment-actions">
        <i class="uil uil-heart"></i>
        <i class="uil uil-comment"></i>
      </div>
    </div>
  </div>

  <div class="comment-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <div class="comment-details">
      <div class="username">Bob</div>
      <div class="date">Sep 27, 2025</div>
      <div class="text">Love this 😍</div>
      <div class="comment-actions">
        <i class="uil uil-heart"></i>
        <i class="uil uil-comment"></i>
      </div>
    </div>
  </div>
</div>

    <div class="likes-popup">
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Alice</span>
  </div>
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Bob</span>
  </div>
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Charlie</span>
  </div>
</div>

  </div>

  <div class="post-card">
    <div class="post-header">
      <img src="https://via.placeholder.com/40" alt="User2">
      <span>User2</span>
      <span class="options-btn">...</span>
      <div class="options-dropdown">
  <button class="delete-btn">Delete</button>
</div>

    </div>
    <div class="post-body">
      <img src="https://via.placeholder.com/400" class="post-image" alt="Post">
    </div>
    <div class="icon-container">
      <i class="uil uil-heart icon-btn"></i>
      <i class="uil uil-comment icon-btn"></i>
      <i class="uil uil-share-alt icon-btn" id="sharebtn"></i>
    </div>
    <!-- Share Popup -->
<div class="share-popup">
  <h4>Share with</h4>
  <div class="share-list">
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Alice">
      <span>Alice</span>
    </div>
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Bob">
      <span>Bob</span>
    </div>
    <div class="share-item">
      <img src="https://via.placeholder.com/35" alt="Charlie">
      <span>Charlie</span>
    </div>
  </div>
</div>

    <div class="comment-popup">
      <input type="text" placeholder="Comment here...">
    </div>

    <div class="post-footer">
      <span>You and 458 people liked this post</span>
      <span>See all comments</span>
    </div>
    <div class="comments-section">
  <div class="comment-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <div class="comment-details">
      <div class="username">Alice</div>
      <div class="date">Sep 28, 2025</div>
      <div class="text">This is such a nice post!</div>
      <div class="comment-actions">
        <i class="uil uil-heart"></i>
        <i class="uil uil-comment"></i>
      </div>
    </div>
  </div>

  <div class="comment-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <div class="comment-details">
      <div class="username">Bob</div>
      <div class="date">Sep 27, 2025</div>
      <div class="text">Love this 😍</div>
      <div class="comment-actions">
        <i class="uil uil-heart"></i>
        <i class="uil uil-comment"></i>
      </div>
    </div>
  </div>
</div>

      <div class="likes-popup">
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Alice</span>
  </div>
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Bob</span>
  </div>
  <div class="like-item">
    <img src="https://via.placeholder.com/35" alt="User">
    <span>Charlie</span>
  </div>
</div>

  </div>

</div>

    <!-- Right Sidebar -->
    <div class="right-sidebar">

      <!-- Messages Card -->
      <div class="right-card" id="messagesCard">
        <h3>Messages</h3>
      <div class="message-search">
        <input type="text" style="outline: none;" id="messageSearchInput" placeholder="Search for message">
      </div>
      <div class="messages-list" id="messagesList">
        <div class="message-item" data-name="Alice" data-img="https://via.placeholder.com/35">
  <img src="https://via.placeholder.com/35" alt="Alice">
  <div class="message-text">
    <span class="username">Alice</span>
    <span class="timestamp">Hi there! 👋</span>
  </div>
</div>

<div class="message-item" data-name="Bob" data-img="https://via.placeholder.com/35">
  <img src="https://via.placeholder.com/35" alt="Bob">
  <div class="message-text">
    <span class="username">Bob</span>
    <span class="timestamp">What's up?</span>
  </div>
</div>

<div class="message-item" data-name="Charlie" data-img="https://via.placeholder.com/35">
  <img src="https://via.placeholder.com/35" alt="Charlie">
  <div class="message-text">
    <span class="username">Charlie</span>
    <span class="timestamp">Let's catch up later!</span>
  </div>
</div>
  
      </div>
    </div>

      <!-- Following Card -->
      <!-- Following Card -->
<div class="right-card">
  <h3>Following</h3>
  <div class="following-search">
    <input type="text" style="outline: none;" id="followingSearchInput" placeholder="Search for people you follow">
  </div>
  <div class="following-list" id="followingList">
    <div class="following-item">
      <img src="https://via.placeholder.com/35" alt="User1">
      <span>Alice</span>
    </div>
    <div class="following-item">
      <img src="https://via.placeholder.com/35" alt="User2">
      <span>Charlie</span>
    </div>
    <div class="following-item">
      <img src="https://via.placeholder.com/35" alt="User3">
      <span>Abera</span>
    </div>
  </div>
</div>

    </div>

  </div>

  <!-- Chat Boxes Container -->
  <div class="chat-container" id="chatContainer"></div>
  <!-- Overlay + Popup -->
<!-- Overlay + Popup -->
<div id="overlay">
  <div id="settingsPopup">
    <span class="close-btn">&times;</span>
    <h2>Edit your profile</h2>
    <form id="settingsForm" method="post">
  <div class="input-group">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" placeholder="Enter your username" required>
  </div>

  <div class="input-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" placeholder="Enter your email" required>
  </div>

  <div class="input-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" placeholder="Enter your password" required>
  </div>

  <div class="input-group">
    <label for="confirm_password">Confirm Password</label>
    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
  </div>

  <button type="submit" class="upload-btn">Save</button>
</form>
  </div>
</div>
<!-- Theme Overlay + Dialog -->
<div id="themeOverlay">
  <div id="themeDialog">
    <span class="close-btn">&times;</span>
    <h2>Choose Theme</h2>
    <p>Select a theme option (light, dark, or custom):</p>
    <button>Light Theme</button>
    <button>Dark Theme</button>
    <button>Custom Theme</button>
  </div>
</div>

<script>

  // === DYNAMIC USER SEARCH ===
const searchInput = document.getElementById("searchInput");
const searchResults = document.getElementById("searchResults");
let searchTimeout = null;

searchInput.addEventListener("input", () => {
  clearTimeout(searchTimeout);
  const query = searchInput.value.trim();
  if (!query) {
    searchResults.innerHTML = "";
    searchResults.style.display = "none";
    return;
  }
  searchTimeout = setTimeout(() => {
    fetch("search-users.php?q=" + encodeURIComponent(query))
      .then(resp => resp.json())
      .then(users => {
        searchResults.innerHTML = "";
        if (users.length === 0) {
          searchResults.innerHTML = `<div class="result-item"><span>No users found</span></div>`;
        } else {
          users.forEach(user => {
            const item = document.createElement("div");
            item.className = "result-item";
            const img = document.createElement("img");
            img.src = user.avatar;
            img.alt = user.username;
            const span = document.createElement("span");
            span.textContent = user.username;
            item.appendChild(img);
            item.appendChild(span);
            // Optional: clicking a result goes to profile (if you implement user-profile.php)
            // item.addEventListener('click', () => { window.location.href = 'user-profile.php?userid=' + user.userid; });
            searchResults.appendChild(item);
          });
        }
        searchResults.style.display = "block";
      });
  }, 250);
});

// Hide results if clicked outside
document.addEventListener("click", (e) => {
  if (!e.target.closest(".search-bar")) {
    searchResults.style.display = "none";
  }
});

const messagesList = document.querySelectorAll('.message-item');
const container = document.getElementById('chatContainer');

// Keep track of minimized chat boxes in order

/*let minimizedSlots = [];

*/
function createChatBox(name, img) {
  // Prevent duplicate chat boxes
  if (document.getElementById('chat-' + name)) return;

  const chatBox = document.createElement('div');
  chatBox.classList.add('chat-box');
  chatBox.id = 'chat-' + name;

  chatBox.innerHTML = `
    <div class="chat-header">
      <div class="user-info">
        <img src="${img}" alt="${name}">
        <div>
          <div>${name}</div>
          <div style="font-size:10px;">Last seen at 10:00 AM</div>
        </div>
      </div>
      <div class="controls">
        <button class="close">×</button>
      </div>

    </div>
    <div class="chat-messages">
      <div class="message left">Hello! How are you?</div>
      <div class="message right">I'm good, thanks!</div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Write a message here">
      <button class="emoji-btn">😀</button>
      <button class="send-btn">Send</button>
      <emoji-picker style="display:none; position:absolute; bottom:45px; right:50px; z-index:3000;"></emoji-picker>
    </div>
  `;

  container.appendChild(chatBox);

  const minimizeBtn = chatBox.querySelector('.minimize');
  const closeBtn = chatBox.querySelector('.close');
/*
  // Handle minimize/restore
  minimizeBtn.addEventListener('click', () => {
    chatBox.classList.toggle('minimized');

    if (chatBox.classList.contains('minimized')) {
      // Assign slot if not assigned yet
      if (!chatBox.dataset.dockedRight) {
        minimizedSlots.push(chatBox.id);
        const index = minimizedSlots.indexOf(chatBox.id);
        chatBox.dataset.dockedRight = 10 + index * 210; // 210 = width + gap
      }
      chatBox.style.bottom = '10px';
      chatBox.style.right = chatBox.dataset.dockedRight + 'px';
    } else {
      // Restore to original position
      chatBox.style.bottom = '';
      chatBox.style.right = '';
    }
  });
*/
  // Handle close button
  closeBtn.addEventListener('click', () => {
    // Remove from slots
    if (chatBox.dataset.dockedRight) {
      const index = minimizedSlots.indexOf(chatBox.id);
      if (index > -1) minimizedSlots.splice(index, 1);

      // Recalculate positions of remaining minimized boxes
      minimizedSlots.forEach((id, i) => {
        const box = document.getElementById(id);
        if (box && box.classList.contains('minimized')) {
          box.dataset.dockedRight = 10 + i * 210;
          box.style.right = box.dataset.dockedRight + 'px';
        }
      });
    }
    container.removeChild(chatBox);
  });
}

// Open chat box when a message item is clicked
messagesList.forEach(item => {
  item.addEventListener('click', () => {
    const name = item.getAttribute('data-name');
    const img = item.getAttribute('data-img');
    createChatBox(name, img);
  });
});

// Emoji picker functionality
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('emoji-btn')) {
    const chatInput = e.target.closest('.chat-input');
    const picker = chatInput.querySelector('emoji-picker');
    picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
  }
});

document.addEventListener('emoji-click', function(e) {
  const picker = e.target.closest('emoji-picker');
  if (!picker) return;
  const input = picker.closest('.chat-input').querySelector('input');
  input.value += e.detail.unicode;
});

// --- Static Users for Testing (temporary demo data) ---
// --- Static Users for Testing (temporary demo data) ---

// Listen to typing
// Hide results if clicked outside
document.addEventListener("click", (e) => {
  if (!e.target.closest(".search-bar")) {
    searchResults.style.display = "none";
  }
});
// Grab notification item in sidebar
const notificationBtn = document.querySelector('.sidebar-item:nth-child(3)');
const notificationPopup = document.getElementById('notificationPopup');

// Example notifications (you can later fetch from DB)
// Example notifications (dummy data)
const notifications = [
  { username: "Alice", avatar: "https://via.placeholder.com/35", msg: "sent you a message", time: "2m ago" },
  { username: "Bob", avatar: "https://via.placeholder.com/35", msg: "started following you", time: "1h ago" },
  { username: "Charlie", avatar: "https://via.placeholder.com/35", msg: "liked your post", time: "3h ago" },
  { username: "Daniela", avatar: "https://via.placeholder.com/35", msg: "sent you a message", time: "5h ago" },
  { username: "John", avatar: "https://via.placeholder.com/35", msg: "commented on your post", time: "Just now" } // new dummy notification
];


// Populate notifications dynamically
const notificationsList = notificationPopup.querySelector('.notifications-list');
notifications.forEach(n => {
  const item = document.createElement('div');
  item.className = 'notification-item';
  item.innerHTML = `
    <img src="${n.avatar}" alt="${n.username}">
    <div class="notification-text">
      <span class="username">${n.username}</span>
      <span class="msg">${n.msg}</span>
      <span class="timestamp">${n.time}</span>
    </div>
  `;
  notificationsList.appendChild(item);
});

// Toggle popup on click
notificationBtn.addEventListener('click', () => {
  notificationPopup.style.display = notificationPopup.style.display === 'block' ? 'none' : 'block';
});

// Hide popup if clicked outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.sidebar-item:nth-child(3)') && !e.target.closest('#notificationPopup')) {
    notificationPopup.style.display = 'none';
  }
});
// Grab the Messages sidebar item and right-card for Messages
const messagesSidebarItem = Array.from(document.querySelectorAll('.sidebar-item'))
  .find(item => item.querySelector('span')?.textContent === 'Messages');

const messagesCard = document.querySelector('.right-sidebar .right-card');

// Grab settings item from sidebar
const settingsBtn = Array.from(document.querySelectorAll('.sidebar-item'))
  .find(item => item.querySelector('span')?.textContent === 'Settings');

const overlay = document.getElementById('overlay');
const closeBtn = document.querySelector('#settingsPopup .close-btn');

// Open popup on settings click
settingsBtn.addEventListener('click', () => {
  overlay.style.display = 'flex'; // flex centers it
});

// Close popup on X click
closeBtn.addEventListener('click', () => {
  overlay.style.display = 'none';
});

// Close if clicking outside the popup box
overlay.addEventListener('click', (e) => {
  if (e.target === overlay) {
    overlay.style.display = 'none';
  }
});
// Theme Dialog
const themeItem = document.querySelector('.sidebar-item span:nth-child(2)'); 
const themeOverlay = document.getElementById('themeOverlay');
const themeClose = document.querySelector('#themeDialog .close-btn');

// Find Theme sidebar-item correctly
document.querySelectorAll('.sidebar-item').forEach(item => {
  if (item.innerText.trim() === "Theme") {
    item.addEventListener('click', () => {
      themeOverlay.style.display = 'flex';
    });
  }
});

// Close button
themeClose.addEventListener('click', () => {
  themeOverlay.style.display = 'none';
});

// Close if clicked outside dialog
themeOverlay.addEventListener('click', (e) => {
  if (e.target === themeOverlay) {
    themeOverlay.style.display = 'none';
  }
});

// Select all heart icons in the post feed
const heartIcons = document.querySelectorAll('.uil-heart');

heartIcons.forEach(icon => {
  icon.addEventListener('click', () => {
    // Toggle a "liked" class
    icon.classList.toggle('liked');

    // Optional: change the icon color to red if liked, revert if not
    if (icon.classList.contains('liked')) {
      icon.style.color = 'red';
    } else {
      icon.style.color = ''; // revert to default
    }
  });
});

// Select all comment icons
const commentIcons = document.querySelectorAll('.icon-btn.comment');

commentIcons.forEach(icon => {
  icon.addEventListener('click', () => {
    const postCard = icon.closest('.post-card');
    const commentPopup = postCard.querySelector('.comment-popup');

    // Toggle display of comment input
    if (commentPopup.style.display === 'block') {
      commentPopup.style.display = 'none';
    } else {
      commentPopup.style.display = 'block';
      commentPopup.querySelector('input').focus(); // focus input automatically
    }
  });
});


  // Toggle comment input when clicking comment icon
  document.querySelectorAll('.post-card').forEach(card => {
    const commentIcon = card.querySelector('.uil-comment');
    const commentPopup = card.querySelector('.comment-popup');

    commentIcon.addEventListener('click', (e) => {
      e.stopPropagation(); // prevent triggering document click
      // Toggle visibility
      commentPopup.style.display =
        commentPopup.style.display === 'block' ? 'none' : 'block';
    });

    // Clicking anywhere outside closes it
    document.addEventListener('click', (e) => {
      if (!card.contains(e.target)) {
        commentPopup.style.display = 'none';
      }
    });
  });
// Handle likes popup toggle
document.querySelectorAll('.post-footer span:first-child').forEach(span => {
  span.style.cursor = "pointer"; // make it clickable
  span.addEventListener('click', (e) => {
    const postCard = span.closest('.post-card');
    const popup = postCard.querySelector('.likes-popup');

    // Close other open popups
    document.querySelectorAll('.likes-popup.active').forEach(p => {
      if (p !== popup) p.classList.remove('active');
    });

    // Toggle this popup
    popup.classList.toggle('active');

    // Stop click from bubbling up (so outside click doesn't close immediately)
    e.stopPropagation();
  });
});

// Close popup when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.likes-popup') && !e.target.closest('.post-footer span:first-child')) {
    document.querySelectorAll('.likes-popup.active').forEach(p => p.classList.remove('active'));
  }
});
// Toggle comments popup
document.querySelectorAll('.post-card').forEach(post => {
  const seeComments = post.querySelector('.post-footer span:last-child');
  const commentsSection = post.querySelector('.comments-section');

  if (seeComments && commentsSection) {
    seeComments.style.cursor = 'pointer';

    // Toggle comments when "See all comments" is clicked
    seeComments.addEventListener('click', (e) => {
      e.stopPropagation(); // prevent closing immediately
      const isVisible = commentsSection.style.display === 'block';

      // Hide all other comment sections
      document.querySelectorAll('.comments-section').forEach(cs => {
        cs.style.display = 'none';
      });

      // Show current one if it was hidden
      commentsSection.style.display = isVisible ? 'none' : 'block';
    });

    // Hide comments when clicking outside
    document.addEventListener('click', (e) => {
      if (!commentsSection.contains(e.target) && !seeComments.contains(e.target)) {
        commentsSection.style.display = 'none';
      }
    });
  }
});

// Handle options dropdown
document.querySelectorAll('.post-card').forEach(card => {
  const optionsBtn = card.querySelector('.options-btn');
  const dropdown = card.querySelector('.options-dropdown');
  const deleteBtn = card.querySelector('.delete-btn');

  // Toggle dropdown on options click
  optionsBtn.addEventListener('click', (e) => {
    e.stopPropagation(); // prevent event bubbling
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  });

  // Hide dropdown if clicked outside
  document.addEventListener('click', () => {
    dropdown.style.display = 'none';
  });

  // Delete button action
  deleteBtn.addEventListener('click', () => {
    card.remove(); // remove the post card from DOM
    // Optional: send AJAX request to delete from DB
    console.log('Post deleted!');
  });
});
// Toggle Share Popup
document.querySelectorAll('.uil-share-alt').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation(); // prevent closing when clicking inside
    const postCard = btn.closest('.post-card');
    const popup = postCard.querySelector('.share-popup');
    
    // Close other share popups
    document.querySelectorAll('.share-popup').forEach(p => {
      if (p !== popup) p.style.display = 'none';
    });

    // Toggle current popup
    popup.style.display = popup.style.display === 'block' ? 'none' : 'block';
  });
});

// Hide popup if clicked outside
document.addEventListener('click', (e) => {
  document.querySelectorAll('.share-popup').forEach(popup => {
    if (!popup.contains(e.target)) {
      popup.style.display = 'none';
    }
  });
});
document.querySelectorAll('.post-card').forEach(card => {
  const heartIcon = card.querySelector('.uil-heart.icon-btn');
  const commentIcon = card.querySelector('.uil-comment.icon-btn');
  const shareIcon = card.querySelector('.uil-share-alt.icon-btn');
  const optionsBtn = card.querySelector('.options-btn');
  const likesText = card.querySelector('.post-footer span:first-child');
  const commentsText = card.querySelector('.post-footer span:last-child');

  const commentPopup = card.querySelector('.comment-popup');
  const commentsSection = card.querySelector('.comments-section');
  const sharePopup = card.querySelector('.share-popup');
  const optionsDropdown = card.querySelector('.options-dropdown');
  const likesPopup = card.querySelector('.likes-popup');

  function closeAllPopups() {
    commentPopup.style.display = 'none';
    commentsSection.style.display = 'none';
    sharePopup.style.display = 'none';
    optionsDropdown.style.display = 'none';
    likesPopup.style.display = 'none';
  }

  // Heart Icon (like)
  heartIcon.addEventListener('click', () => {
    closeAllPopups();
    // Add like logic here if needed
  });

  // Comment Icon
  commentIcon.addEventListener('click', () => {
    closeAllPopups();
    commentPopup.style.display = 'block';
  });

  // Share Icon
  shareIcon.addEventListener('click', () => {
    closeAllPopups();
    sharePopup.style.display = 'block';
  });

  // Options Button
  optionsBtn.addEventListener('click', () => {
    closeAllPopups();
    optionsDropdown.style.display = optionsDropdown.style.display === 'block' ? 'none' : 'block';
  });

  // Likes Text
  likesText.addEventListener('click', () => {
    closeAllPopups();
    likesPopup.style.display = 'block';
  });

  // See All Comments Text
  commentsText.addEventListener('click', () => {
    closeAllPopups();
    commentsSection.style.display = 'block';
  });

  // Optional: click outside to close popups
  document.addEventListener('click', e => {
    if (!card.contains(e.target)) closeAllPopups();
  });
});
// === Messages Search Function ===
const messageSearchInput = document.getElementById("messageSearchInput");
const messagesListContainer = document.getElementById("messagesList");
const messages = messagesListContainer.querySelectorAll(".message-item");

messageSearchInput.addEventListener("input", function () {
  const query = this.value.toLowerCase();
  messages.forEach(item => {
    const username = item.querySelector(".username").textContent.toLowerCase();
    const text = item.querySelector(".timestamp").textContent.toLowerCase();
    if (username.includes(query) || text.includes(query)) {
      item.style.display = "flex";
    } else {
      item.style.display = "none";
    }
  });
});

// === Glow Effect when Messages in Sidebar is Clicked ===
document.querySelectorAll(".sidebar-item").forEach(item => {
  if (item.textContent.trim() === "Messages") {
    item.addEventListener("click", () => {
      const messagesCard = document.getElementById("messagesCard");
      messagesCard.classList.add("glow");
      setTimeout(() => messagesCard.classList.remove("glow"), 5000);
    });
  }
});
// FOLLOWING SEARCH FUNCTIONALITY
const followingSearchInput = document.getElementById("followingSearchInput");
const followingList = document.getElementById("followingList");
const followingItems = followingList.querySelectorAll(".following-item");

followingSearchInput.addEventListener("keyup", function () {
  const filter = this.value.toLowerCase();

  followingItems.forEach(item => {
    const name = item.querySelector("span").textContent.toLowerCase();
    if (name.includes(filter)) {
      item.style.display = "flex";  // show matching user
    } else {
      item.style.display = "none";  // hide non-matching user
    }
  });
});
  // profile picture
document.getElementById('profilePicInput').addEventListener('change', function() {
  if (!this.files || !this.files[0]) return;
  const formData = new FormData();
  formData.append('profile_pic', this.files[0]);
  formData.append('ajax', 1);
  formData.append('userid', "<?php echo $_SESSION['userid']; ?>");

  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(resp => resp.json())
  .then(data => {
    if (data.success) {
      document.getElementById('navbarAvatar').src = data.avatar;
      document.getElementById('sidebarAvatar').src = data.avatar;
    } else {
      alert(data.error || "Upload failed.");
    }
    this.value = ""; // reset input
  });
});

// Three dot menu toggle for profile card
const pOptionsBtn = document.querySelector('.profile-options-btn');
const pOptionsDropdown = document.querySelector('.profile-options-dropdown');
if (pOptionsBtn && pOptionsDropdown) {
  pOptionsBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    pOptionsDropdown.style.display = (pOptionsDropdown.style.display === 'block') ? 'none' : 'block';
  });
  document.addEventListener('click', function(e) {
    if (!pOptionsDropdown.contains(e.target) && e.target !== pOptionsBtn) {
      pOptionsDropdown.style.display = 'none';
    }
  });
}
const profileDeleteBtn = document.querySelector('.profile-delete-btn');
if (profileDeleteBtn) {
  profileDeleteBtn.addEventListener('click', function() {
    if (confirm('Are you sure you want to delete your profile?')) {
      // Replace with actual delete logic
      alert('Profile deletion is not implemented yet.');
      pOptionsDropdown.style.display = 'none';
    }
  });
}

</script>
</body>
</html>
